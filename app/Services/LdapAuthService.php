<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class LdapAuthService
{
    /**
     * @return array{user: User, description: string|null}
     */
    public function authenticate(string $username, string $password): array
    {
        if (! class_exists(\Symfony\Component\Ldap\Ldap::class)) {
            throw new RuntimeException('Pacote symfony/ldap não instalado. Rode composer install com PHP 8.3+.');
        }

        $encryption = (string) config('ldap.encryption', 'none');

        $ldap = \Symfony\Component\Ldap\Ldap::create('ext_ldap', [
            'host' => (string) config('ldap.host'),
            'port' => (int) config('ldap.port', 389),
            'encryption' => $encryption !== '' ? $encryption : 'none',
        ]);

        $serviceDn = (string) config('ldap.service_dn');
        $servicePassword = (string) config('ldap.service_password');
        if ($serviceDn === '' || $servicePassword === '') {
            throw new RuntimeException('LDAP_SERVICE_DN e LDAP_SERVICE_PASSWORD precisam estar configurados.');
        }

        $ldap->bind($serviceDn, $servicePassword);

        $filter = sprintf((string) config('ldap.user_filter'), $this->escapeFilter($username));
        $results = $ldap->query((string) config('ldap.base_dn'), $filter)->execute();
        $entry = $results[0] ?? null;

        if (! $entry) {
            throw new RuntimeException('Usuário ou senha inválidos.');
        }

        $dn = $entry->getDn();
        $name = $entry->getAttribute('cn')[0] ?? $username;
        $mail = $entry->getAttribute('mail')[0] ?? Str::lower($username).'@ldap.local';
        $description = $entry->getAttribute('description')[0] ?? null;
        $required = (string) config('ldap.required_description_contains', 'DTI');

        if ($required !== '' && stripos((string) $description, $required) === false) {
            throw new RuntimeException('Acesso restrito a usuários com description contendo '.$required.'.');
        }

        $ldap->bind($dn, $password);

        $query = User::query()->where('email', $mail);
        if (Schema::hasColumn('users', 'ldap_username')) {
            $query->orWhere('ldap_username', $username);
        }

        $attributes = [
            'name' => (string) $name,
            'email' => (string) $mail,
            'email_verified_at' => now(),
            'password' => Hash::make(Str::random(40)),
            'is_it_admin' => true,
        ];

        if (Schema::hasColumn('users', 'ldap_username')) {
            $attributes['ldap_username'] = $username;
        }

        if (Schema::hasColumn('users', 'ldap_description')) {
            $attributes['ldap_description'] = $description;
        }

        $user = $query->first() ?? new User();
        $user->fill($attributes)->save();

        return ['user' => $user, 'description' => $description];
    }

    private function escapeFilter(string $value): string
    {
        return str_replace(
            ['\\', '*', '(', ')', "\x00"],
            ['\5c', '\2a', '\28', '\29', '\00'],
            $value,
        );
    }
}

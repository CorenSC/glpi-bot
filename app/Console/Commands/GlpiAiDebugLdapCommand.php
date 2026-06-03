<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\LdapAuthService;
use Illuminate\Console\Command;

class GlpiAiDebugLdapCommand extends Command
{
    protected $signature = 'glpi-ai:debug-ldap {login?}';

    protected $description = 'Testa a autenticacao LDAP do painel GLPI AI sem expor a senha.';

    public function handle(LdapAuthService $ldap): int
    {
        $login = (string) ($this->argument('login') ?: $this->ask('Login de rede'));
        $password = (string) $this->secret('Senha');

        $this->line('LDAP_ENABLED: '.((bool) config('ldap.enabled') ? 'true' : 'false'));
        $this->line('LDAP_HOST: '.(string) config('ldap.host'));
        $this->line('LDAP_BASE_DN: '.(string) config('ldap.base_dn'));
        $this->line('Filtro: '.sprintf((string) config('ldap.user_filter'), $login));

        try {
            $result = $ldap->authenticate($login, $password);
        } catch (\Throwable $throwable) {
            $this->error('Falha LDAP: '.$throwable->getMessage());

            return self::FAILURE;
        }

        $this->info('LDAP autenticou e o usuario local foi salvo.');
        $this->table(['Campo', 'Valor'], [
            ['ID local', (string) $result['user']->id],
            ['Nome', (string) $result['user']->name],
            ['E-mail', (string) $result['user']->email],
            ['Description', (string) ($result['description'] ?? '-')],
            ['is_it_admin', (bool) ($result['user']->is_it_admin ?? false) ? 'true' : 'false'],
        ]);

        return self::SUCCESS;
    }
}

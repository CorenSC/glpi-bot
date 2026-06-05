<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class GlpiAiCreateAdminCommand extends Command
{
    protected $signature = 'glpi-ai:create-admin {email} {--name=Administrador} {--password=}';

    protected $description = 'Cria ou atualiza um usuário administrador para o painel GLPI AI.';

    public function handle(): int
    {
        $password = (string) ($this->option('password') ?: $this->secret('Senha do administrador'));

        if ($password === '') {
            $this->error('Senha obrigatoria.');

            return self::FAILURE;
        }

        $user = User::query()->updateOrCreate(
            ['email' => (string) $this->argument('email')],
            [
                'name' => (string) $this->option('name'),
                'password' => Hash::make($password),
                'is_admin' => true,
                'is_it_admin' => true,
            ],
        );

        $this->info("Usuário administrador pronto: {$user->email}");

        return self::SUCCESS;
    }
}

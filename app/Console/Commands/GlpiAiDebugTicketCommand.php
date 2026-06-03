<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Repositories\Glpi\GlpiTicketApiRepository;
use Illuminate\Console\Command;
use Throwable;

class GlpiAiDebugTicketCommand extends Command
{
    protected $signature = 'glpi-ai:debug-ticket {glpi_ticket_id}';

    protected $description = 'Mostra os dados normalizados retornados pela API do GLPI para um chamado.';

    public function handle(GlpiTicketApiRepository $tickets): int
    {
        try {
            $ticket = $tickets->findTicketById((int) $this->argument('glpi_ticket_id'));
        } catch (Throwable $throwable) {
            $this->error('Nao foi possivel ler o chamado pela API do GLPI.');
            $this->line('Erro: '.$throwable->getMessage());
            $this->warn('Se o HTTP for 403, o usuario/token da API nao tem permissao para abrir esse chamado diretamente.');

            return self::FAILURE;
        }

        if (! $ticket) {
            $this->error('Chamado nao encontrado pela API.');

            return self::FAILURE;
        }

        $this->table(['Campo', 'Valor'], [
            ['glpi_ticket_id', $ticket['glpi_ticket_id'] ?? null],
            ['title', $ticket['title'] ?? null],
            ['status', $ticket['status'] ?? null],
            ['category_id', $ticket['category_id'] ?? null],
            ['category_name', $ticket['category_name'] ?? null],
            ['assigned_technician_id', $ticket['assigned_technician_id'] ?? null],
            ['assigned_technician_name', $ticket['assigned_technician_name'] ?? null],
            ['assigned_group_id', $ticket['assigned_group_id'] ?? null],
            ['assigned_group_name', $ticket['assigned_group_name'] ?? null],
            ['opened_at', $ticket['opened_at'] ?? null],
            ['ticket_users_raw', json_encode($ticket['ticket_users'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            ['ticket_groups_raw', json_encode($ticket['ticket_groups'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ]);

        return self::SUCCESS;
    }
}

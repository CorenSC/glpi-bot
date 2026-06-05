<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GlpiAi\GlpiAiAnalysisOrchestrator;
use Illuminate\Console\Command;

class GlpiAiDryRunTicketCommand extends Command
{
    protected $signature = 'glpi-ai:dry-run-ticket {glpi_ticket_id}';

    protected $description = 'Analisa um chamado específico do GLPI em modo dry-run forçado.';

    public function handle(GlpiAiAnalysisOrchestrator $orchestrator): int
    {
        $suggestion = $orchestrator->analyzeTicketId((int) $this->argument('glpi_ticket_id'), true);

        $this->table(['Campo', 'Valor'], [
            ['Sugestão', $suggestion->id],
            ['Chamado GLPI', $suggestion->glpi_ticket_id],
            ['Ação', $suggestion->recommended_action],
            ['Técnico', $suggestion->recommended_technician_id ?: '-'],
            ['Grupo', $suggestion->recommended_group_id ?: '-'],
            ['Confiança', $suggestion->confidence.'%'],
            ['Risco', $suggestion->risk_level],
            ['Motivo', $suggestion->reason],
        ]);

        return self::SUCCESS;
    }
}

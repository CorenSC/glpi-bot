<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\GlpiAi\GlpiAiAnalysisOrchestrator;
use Illuminate\Console\Command;

class GlpiAiDryRunTicketCommand extends Command
{
    protected $signature = 'glpi-ai:dry-run-ticket {glpi_ticket_id}';

    protected $description = 'Analisa um chamado especifico do GLPI em modo dry-run forcado.';

    public function handle(GlpiAiAnalysisOrchestrator $orchestrator): int
    {
        $suggestion = $orchestrator->analyzeTicketId((int) $this->argument('glpi_ticket_id'), true);

        $this->table(['Campo', 'Valor'], [
            ['Sugestao', $suggestion->id],
            ['Chamado GLPI', $suggestion->glpi_ticket_id],
            ['Acao', $suggestion->recommended_action],
            ['Tecnico', $suggestion->recommended_technician_id ?: '-'],
            ['Grupo', $suggestion->recommended_group_id ?: '-'],
            ['Confianca', $suggestion->confidence.'%'],
            ['Risco', $suggestion->risk_level],
            ['Motivo', $suggestion->reason],
        ]);

        return self::SUCCESS;
    }
}

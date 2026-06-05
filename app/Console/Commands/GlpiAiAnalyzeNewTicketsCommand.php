<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AnalyzeNewGlpiTicketJob;
use App\Models\GlpiAiAnalysisRun;
use App\Models\GlpiAiAssignmentSuggestion;
use App\Models\GlpiAiOperationalRun;
use App\Repositories\Glpi\GlpiTicketApiRepository;
use App\Services\GlpiAi\OperationalRunService;
use Illuminate\Console\Command;

class GlpiAiAnalyzeNewTicketsCommand extends Command
{
    protected $signature = 'glpi-ai:analyze-new-tickets {--limit=50}';

    protected $description = 'Analisa chamados novos do GLPI e cria sugestões de atribuição.';

    public function handle(GlpiTicketApiRepository $tickets, OperationalRunService $runs): int
    {
        return $runs->run('glpi-ai:analyze-new-tickets', fn (GlpiAiOperationalRun $run): int => $this->executeCommand($tickets, $run), [
            'limit' => (int) $this->option('limit'),
        ]);
    }

    private function executeCommand(GlpiTicketApiRepository $tickets, GlpiAiOperationalRun $run): int
    {
        $inspection = $tickets->findNewTicketsInspection((int) $this->option('limit'));
        $items = $inspection['items'];
        $ticketIds = $items->pluck('glpi_ticket_id')->map(fn ($id) => (int) $id)->filter()->values();
        $alreadySuggestedIds = GlpiAiAssignmentSuggestion::query()
            ->whereIn('glpi_ticket_id', $ticketIds)
            ->pluck('glpi_ticket_id')
            ->map(fn ($id) => (int) $id);
        $alreadyAnalyzedIds = GlpiAiAnalysisRun::query()
            ->whereIn('glpi_ticket_id', $ticketIds)
            ->pluck('glpi_ticket_id')
            ->map(fn ($id) => (int) $id);
        $ignoredIds = $alreadySuggestedIds->merge($alreadyAnalyzedIds)->unique()->values()->all();
        $items = $items
            ->reject(fn (array $ticket): bool => in_array((int) ($ticket['glpi_ticket_id'] ?? 0), $ignoredIds, true))
            ->values();

        $this->line("Chamados recentes verificados na API do GLPI: {$inspection['scanned']}.");
        $this->line("Chamados com status configurado para análise: {$inspection['status_matched']}.");
        $this->line("Chamados ignorados por já terem atribuição que bloqueia análise: {$inspection['assigned_filtered']}.");
        $this->line('Chamados ignorados por já terem análise/sugestão: '.count($ignoredIds).'.');
        $this->info("Analises enviadas para processamento: {$items->count()}.");
        $items->each(fn (array $ticket) => AnalyzeNewGlpiTicketJob::dispatch($ticket, false));
        $run->update([
            'summary' => "Chamados verificados: {$inspection['scanned']}; enviados para análise: {$items->count()}.",
            'metadata' => array_merge($run->metadata ?? [], [
                'scanned' => $inspection['scanned'],
                'status_matched' => $inspection['status_matched'],
                'assigned_filtered' => $inspection['assigned_filtered'],
                'ignored_existing' => count($ignoredIds),
                'dispatched' => $items->count(),
                'ticket_ids' => $items->pluck('glpi_ticket_id')->values()->all(),
            ]),
        ]);

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\AnalyzeNewGlpiTicketJob;
use App\Models\GlpiAiAnalysisRun;
use App\Models\GlpiAiAssignmentSuggestion;
use App\Repositories\Glpi\GlpiTicketApiRepository;
use Illuminate\Console\Command;

class GlpiAiAnalyzeNewTicketsCommand extends Command
{
    protected $signature = 'glpi-ai:analyze-new-tickets {--limit=50}';

    protected $description = 'Analisa chamados novos do GLPI e cria sugestoes de atribuicao.';

    public function handle(GlpiTicketApiRepository $tickets): int
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
        $this->line("Chamados com status configurado para analise: {$inspection['status_matched']}.");
        $this->line("Chamados ignorados por ja terem atribuicao que bloqueia analise: {$inspection['assigned_filtered']}.");
        $this->line('Chamados ignorados por ja terem analise/sugestao: '.count($ignoredIds).'.');
        $this->info("Analises enviadas para processamento: {$items->count()}.");
        $items->each(fn (array $ticket) => AnalyzeNewGlpiTicketJob::dispatch($ticket, false));

        return self::SUCCESS;
    }
}

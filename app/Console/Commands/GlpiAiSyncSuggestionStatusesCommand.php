<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SuggestionStatus;
use App\Models\GlpiAiAssignmentSuggestion;
use App\Models\GlpiAiHumanFeedback;
use App\Models\GlpiAiOperationalRun;
use App\Repositories\Glpi\GlpiTicketApiRepository;
use App\Services\GlpiAi\OperationalRunService;
use Illuminate\Console\Command;

class GlpiAiSyncSuggestionStatusesCommand extends Command
{
    protected $signature = 'glpi-ai:sync-suggestion-statuses {--limit=100}';

    protected $description = 'Atualiza sugestoes locais quando o chamado correspondente for solucionado/fechado no GLPI.';

    public function handle(GlpiTicketApiRepository $tickets, OperationalRunService $runs): int
    {
        return $runs->run('glpi-ai:sync-suggestion-statuses', fn (GlpiAiOperationalRun $run): int => $this->executeCommand($tickets, $run), [
            'limit' => (int) $this->option('limit'),
        ]);
    }

    private function executeCommand(GlpiTicketApiRepository $tickets, GlpiAiOperationalRun $run): int
    {
        $statuses = (array) config('glpi-ai.historical_ticket_statuses', [5, 6]);
        $suggestions = GlpiAiAssignmentSuggestion::query()
            ->whereNotIn('status', [SuggestionStatus::GlpiClosed->value, SuggestionStatus::Rejected->value, SuggestionStatus::Failed->value])
            ->latest()
            ->limit((int) $this->option('limit'))
            ->get();

        $closed = 0;

        foreach ($suggestions as $suggestion) {
            $ticket = $tickets->findTicketById((int) $suggestion->glpi_ticket_id);
            if (! $ticket || ! in_array((int) ($ticket['status'] ?? 0), $statuses, true)) {
                continue;
            }

            $previous = $suggestion->status;
            $suggestion->update([
                'status' => SuggestionStatus::GlpiClosed->value,
                'action_taken' => 'glpi_ticket_closed',
                'action_taken_at' => now(),
            ]);

            GlpiAiHumanFeedback::query()->create([
                'assignment_suggestion_id' => $suggestion->id,
                'analysis_run_id' => $suggestion->analysis_run_id,
                'action' => 'glpi_ticket_closed',
                'previous_status' => $previous,
                'new_status' => SuggestionStatus::GlpiClosed->value,
                'observation' => 'Chamado marcado como solucionado/fechado no GLPI; sugestao finalizada automaticamente no painel.',
                'metadata' => ['glpi_status' => $ticket['status'] ?? null],
            ]);

            $closed++;
        }

        $this->info("Sugestoes finalizadas por status do GLPI: {$closed}.");
        $run->update([
            'summary' => "Sugestoes finalizadas por status do GLPI: {$closed}.",
            'metadata' => array_merge($run->metadata ?? [], [
                'checked' => $suggestions->count(),
                'closed' => $closed,
            ]),
        ]);

        return self::SUCCESS;
    }
}

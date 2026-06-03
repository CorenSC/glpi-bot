<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GlpiAiAssignmentSuggestion;
use App\Services\GlpiAi\GlpiAiAnalysisOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RecalculateSuggestionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public int $backoff = 30;

    public function __construct(public int $suggestionId)
    {
        $this->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
    }

    public function handle(GlpiAiAnalysisOrchestrator $orchestrator): void
    {
        $suggestion = GlpiAiAssignmentSuggestion::query()->findOrFail($this->suggestionId);
        $orchestrator->analyzeTicketId($suggestion->glpi_ticket_id, (bool) config('glpi-ai.dry_run', true), $suggestion);
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GlpiAiAssignmentSuggestion;
use App\Services\GlpiAi\GlpiAiAnalysisOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RevalidateSuggestionAiJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int|array $backoff = [60, 180, 300];

    public function __construct(public int $suggestionId)
    {
        $this->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
    }

    public function handle(GlpiAiAnalysisOrchestrator $orchestrator): void
    {
        $suggestion = GlpiAiAssignmentSuggestion::query()->findOrFail($this->suggestionId);
        $orchestrator->revalidateAi($suggestion);
    }
}

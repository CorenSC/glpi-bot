<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GlpiAiAssignmentSuggestion;
use App\Services\GlpiAi\GlpiAiAnalysisOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

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

        try {
            $orchestrator->revalidateAi($suggestion);
        } catch (Throwable) {
            $suggestion->refresh();

            if ((int) $suggestion->ai_validation_attempts < 3 && $suggestion->ai_validation_next_retry_at) {
                self::dispatch($suggestion->id)
                    ->delay($suggestion->ai_validation_next_retry_at)
                    ->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
            }
        }
    }
}

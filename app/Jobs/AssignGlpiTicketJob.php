<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\RecommendedAction;
use App\Enums\SuggestionStatus;
use App\Integrations\Glpi\GlpiApiClient;
use App\Models\GlpiAiAssignmentSuggestion;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class AssignGlpiTicketJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $backoff = 20;

    public function __construct(public GlpiAiAssignmentSuggestion $suggestion)
    {
        $this->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
    }

    public function handle(GlpiApiClient $client): void
    {
        $suggestion = $this->suggestion->refresh();
        try {
            $result = match ($suggestion->recommended_action) {
                RecommendedAction::AssignToTechnician->value => $client->assignTechnician($suggestion->glpi_ticket_id, (int) $suggestion->recommended_technician_id),
                RecommendedAction::AssignToGroup->value => $client->assignGroup($suggestion->glpi_ticket_id, (int) $suggestion->recommended_group_id),
                default => ['skipped' => true, 'reason' => 'Manual triage decision.'],
            };

            $suggestion->update([
                'glpi_payload' => $result['payload'] ?? null,
                'glpi_api_response' => $result,
                'status' => ($result['skipped'] ?? false) ? $suggestion->status : SuggestionStatus::AutoAssigned->value,
                'action_taken' => ($result['skipped'] ?? false) ? 'dry_run_skipped' : 'auto_assigned',
                'action_taken_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            $suggestion->update([
                'status' => SuggestionStatus::Failed->value,
                'action_taken' => 'glpi_api_failed',
                'action_taken_at' => now(),
                'error_message' => $throwable->getMessage(),
                'glpi_api_response' => ['error' => $throwable->getMessage()],
            ]);

            throw $throwable;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\GlpiAiTicketHistory;
use App\Services\GlpiAi\GlpiTicketNormalizer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGlpiTicketHistoryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 15;

    public function __construct(public array $ticket)
    {
        $this->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
    }

    public function handle(GlpiTicketNormalizer $normalizer): void
    {
        $canonical = $normalizer->canonicalize($this->ticket);
        $hash = $normalizer->hash($canonical);
        $clean = $normalizer->clean($this->ticket['original_content'] ?? '');

        $existing = GlpiAiTicketHistory::query()
            ->where('glpi_ticket_id', $this->ticket['glpi_ticket_id'])
            ->first();
        $embeddingHash = $existing && $existing->content_hash === $hash
            ? $existing->embedding_hash
            : null;

        GlpiAiTicketHistory::query()->updateOrCreate(
            ['glpi_ticket_id' => $this->ticket['glpi_ticket_id']],
            [
                'title' => $this->ticket['title'] ?? null,
                'original_content' => $this->ticket['original_content'] ?? null,
                'clean_content' => $clean,
                'canonical_text' => $canonical,
                'category_id' => $this->ticket['category_id'] ?? null,
                'category_name' => $this->ticket['category_name'] ?? null,
                'category_path' => $this->ticket['category_path'] ?? null,
                'assigned_group_id' => $this->ticket['assigned_group_id'] ?? null,
                'assigned_group_name' => $this->ticket['assigned_group_name'] ?? null,
                'assigned_technician_id' => $this->ticket['assigned_technician_id'] ?? null,
                'assigned_technician_name' => $this->ticket['assigned_technician_name'] ?? null,
                'solver_technician_id' => $this->ticket['solver_technician_id'] ?? null,
                'solver_technician_name' => $this->ticket['solver_technician_name'] ?? null,
                'status' => $this->ticket['status'] ?? null,
                'opened_at' => $this->ticket['opened_at'] ?? null,
                'updated_at_glpi' => $this->ticket['updated_at_glpi'] ?? null,
                'solved_at' => $this->ticket['solved_at'] ?? null,
                'closed_at' => $this->ticket['closed_at'] ?? null,
                'solution_text' => $this->ticket['solution_text'] ?? null,
                'followup_summary' => $this->ticket['followup_summary'] ?? null,
                'content_hash' => $hash,
                'embedding_hash' => $embeddingHash,
                'metadata' => ['source' => 'glpi-api', 'api_payload' => $this->ticket['api_payload'] ?? null],
            ],
        );
    }
}

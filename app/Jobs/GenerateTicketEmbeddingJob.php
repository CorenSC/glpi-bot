<?php

declare(strict_types=1);

namespace App\Jobs;

use App\AI\Embeddings\EmbeddingProviderInterface;
use App\Models\GlpiAiTicketHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateTicketEmbeddingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public int $backoff = 20;

    public function __construct(public int $ticketHistoryId)
    {
        $this->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
    }

    public function handle(EmbeddingProviderInterface $provider): void
    {
        $history = GlpiAiTicketHistory::query()->findOrFail($this->ticketHistoryId);
        if ($history->embedding_hash === $history->content_hash && $history->embedding_generated_at) {
            return;
        }

        $history->update([
            'embedding' => $provider->embed($history->canonical_text),
            'embedding_provider' => $provider->providerName(),
            'embedding_model' => $provider->modelName(),
            'embedding_hash' => $history->content_hash,
            'embedding_generated_at' => now(),
        ]);
    }
}

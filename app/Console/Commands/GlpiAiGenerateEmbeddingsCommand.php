<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateTicketEmbeddingJob;
use App\Models\GlpiAiTicketHistory;
use Illuminate\Console\Command;

class GlpiAiGenerateEmbeddingsCommand extends Command
{
    protected $signature = 'glpi-ai:generate-embeddings
        {--limit=100 : Quantidade maxima de embeddings nesta execucao}
        {--all : Envia todos os embeddings pendentes nesta execucao}';

    protected $description = 'Gera embeddings pendentes para chamados historicos importados do GLPI.';

    public function handle(): int
    {
        $query = GlpiAiTicketHistory::query()
            ->select(['id'])
            ->where(fn ($query) => $query->whereNull('embedding_hash')->orWhereColumn('embedding_hash', '!=', 'content_hash'))
            ->orderBy('id');

        if (! (bool) $this->option('all')) {
            $query->limit((int) $this->option('limit'));
        }

        $histories = $query->get();

        $this->info("Embeddings enviados para processamento: {$histories->count()}.");
        $histories->each(fn (GlpiAiTicketHistory $history) => GenerateTicketEmbeddingJob::dispatch($history->id));

        return self::SUCCESS;
    }
}

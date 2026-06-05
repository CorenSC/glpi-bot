<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\GenerateTicketEmbeddingJob;
use App\Models\GlpiAiOperationalRun;
use App\Models\GlpiAiTicketHistory;
use App\Services\GlpiAi\OperationalRunService;
use Illuminate\Console\Command;

class GlpiAiGenerateEmbeddingsCommand extends Command
{
    protected $signature = 'glpi-ai:generate-embeddings
        {--limit=100 : Quantidade máxima de embeddings nesta execução}
        {--all : Envia todos os embeddings pendentes nesta execução}';

    protected $description = 'Gera embeddings pendentes para chamados históricos importados do GLPI.';

    public function handle(OperationalRunService $runs): int
    {
        return $runs->run($this->signatureName(), fn (GlpiAiOperationalRun $run): int => $this->executeCommand($run), [
            'limit' => (int) $this->option('limit'),
            'all' => (bool) $this->option('all'),
        ]);
    }

    private function executeCommand(GlpiAiOperationalRun $run): int
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
        $run->update([
            'summary' => "Embeddings enviados para processamento: {$histories->count()}.",
            'metadata' => array_merge($run->metadata ?? [], ['dispatched' => $histories->count()]),
        ]);

        return self::SUCCESS;
    }

    private function signatureName(): string
    {
        return 'glpi-ai:generate-embeddings';
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RecalculateSuggestionJob;
use Illuminate\Console\Command;

class GlpiAiRecalculateSuggestionCommand extends Command
{
    protected $signature = 'glpi-ai:recalculate-suggestion {suggestion_id}';

    protected $description = 'Envia o recálculo de uma sugestão existente para processamento.';

    public function handle(): int
    {
        RecalculateSuggestionJob::dispatch((int) $this->argument('suggestion_id'));
        $this->info('Recalculo enviado para processamento.');

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GlpiAi\GlpiAiAnalysisOrchestrator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeNewGlpiTicketJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    public int $backoff = 30;

    public function __construct(public array $ticket, public bool $forceDryRun = false)
    {
        $this->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
    }

    public function handle(GlpiAiAnalysisOrchestrator $orchestrator): void
    {
        $orchestrator->analyze($this->ticket, $this->forceDryRun);
    }
}

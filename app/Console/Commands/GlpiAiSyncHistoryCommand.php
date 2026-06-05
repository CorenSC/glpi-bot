<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncGlpiTicketHistoryJob;
use App\Models\GlpiAiOperationalRun;
use App\Models\GlpiAiSetting;
use App\Models\GlpiAiTicketHistory;
use App\Repositories\Glpi\GlpiTicketApiRepository;
use App\Services\GlpiAi\OperationalRunService;
use Illuminate\Console\Command;

class GlpiAiSyncHistoryCommand extends Command
{
    protected $signature = 'glpi-ai:sync-history
        {--limit=2500 : Quantidade máxima de chamados a verificar nesta execução}
        {--batch=200 : Tamanho da pagina da API do GLPI}
        {--start= : Offset inicial manual na lista do GLPI}
        {--fresh : Recomeça a varredura do offset zero}
        {--refresh-existing : Reimporta chamados já existentes na base interna}';

    protected $description = 'Importa chamados solucionados/fechados do GLPI para a base interna da IA.';

    public function handle(GlpiTicketApiRepository $tickets, OperationalRunService $runs): int
    {
        return $runs->run('glpi-ai:sync-history', fn (GlpiAiOperationalRun $run): int => $this->executeCommand($tickets, $run), [
            'limit' => (int) $this->option('limit'),
            'batch' => (int) $this->option('batch'),
            'start' => $this->option('start'),
            'fresh' => (bool) $this->option('fresh'),
            'refresh_existing' => (bool) $this->option('refresh-existing'),
        ]);
    }

    private function executeCommand(GlpiTicketApiRepository $tickets, GlpiAiOperationalRun $run): int
    {
        $limit = (int) $this->option('limit');
        $batch = max(1, (int) $this->option('batch'));
        $start = $this->startOffset();
        $skipExisting = ! (bool) $this->option('refresh-existing');
        $dispatched = 0;
        $scanned = 0;
        $skippedExisting = 0;

        $this->line("Iniciando varredura no offset {$start}. ".($skipExisting ? 'Chamados já importados serão pulados.' : 'Chamados existentes serão reimportados.'));

        for ($offset = $start; $offset < $start + $limit; $offset += $batch) {
            $pageSize = min($batch, ($start + $limit) - $offset);
            $page = $tickets->findHistoricalTicketsForImportPage($offset, $pageSize, $skipExisting);
            $items = $page['items'];
            $scanned += $pageSize;
            $dispatched += $items->count();
            $skippedExisting += (int) ($page['skipped_existing'] ?? 0);

            $totalLabel = $page['total'] === null ? '?' : (string) $page['total'];
            $historicalCount = (int) ($page['historical_count'] ?? $items->count());
            $this->line("Faixa GLPI verificada {$offset}-".($offset + $pageSize - 1)." de {$totalLabel}; {$historicalCount} solucionados/fechados; {$items->count()} para importar; {$page['skipped_existing']} já existiam.");
            $items->each(fn (array $ticket) => SyncGlpiTicketHistoryJob::dispatch($ticket));

            $this->saveNextOffset($offset + $pageSize);

            if ($page['total'] !== null && ($offset + $pageSize) >= $page['total']) {
                $this->saveNextOffset(0);
                break;
            }
        }

        $this->info("Importação enviada: {$dispatched} chamados históricos; {$skippedExisting} já estavam importados; {$scanned} registros GLPI verificados.");
        $run->update([
            'summary' => "Importação enviada: {$dispatched} históricos; {$skippedExisting} já importados; {$scanned} registros verificados.",
            'metadata' => array_merge($run->metadata ?? [], [
                'dispatched' => $dispatched,
                'skipped_existing' => $skippedExisting,
                'scanned' => $scanned,
                'next_offset' => $this->startOffset(),
            ]),
        ]);

        return self::SUCCESS;
    }

    private function startOffset(): int
    {
        if ($this->option('fresh')) {
            $this->saveNextOffset(0);

            return 0;
        }

        if ($this->option('start') !== null) {
            return max(0, (int) $this->option('start'));
        }

        $setting = GlpiAiSetting::query()->where('key', 'sync_history_next_offset')->first();
        $value = $setting?->value;

        if (is_array($value) && isset($value['offset'])) {
            return max(0, (int) $value['offset']);
        }

        return max(0, GlpiAiTicketHistory::query()->count());
    }

    private function saveNextOffset(int $offset): void
    {
        GlpiAiSetting::query()->updateOrCreate(
            ['key' => 'sync_history_next_offset'],
            [
                'value' => ['offset' => $offset],
                'type' => 'integer',
                'description' => 'Próximo offset da varredura incremental de histórico via API GLPI.',
                'is_sensitive' => false,
            ],
        );
    }
}

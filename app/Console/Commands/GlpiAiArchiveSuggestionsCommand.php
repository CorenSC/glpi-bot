<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SuggestionStatus;
use App\Models\GlpiAiAssignmentSuggestion;
use App\Models\GlpiAiOperationalRun;
use App\Services\GlpiAi\OperationalRunService;
use Illuminate\Console\Command;

class GlpiAiArchiveSuggestionsCommand extends Command
{
    protected $signature = 'glpi-ai:archive-suggestions {--days=30 : Dias para manter sugestões finalizadas fora do arquivo}';

    protected $description = 'Arquiva sugestões finalizadas antigas para manter a fila operacional limpa.';

    public function handle(OperationalRunService $runs): int
    {
        return $runs->run('glpi-ai:archive-suggestions', fn (GlpiAiOperationalRun $run): int => $this->executeCommand($run), [
            'days' => (int) $this->option('days'),
        ]);
    }

    private function executeCommand(GlpiAiOperationalRun $run): int
    {
        $days = max(1, (int) $this->option('days'));
        $statuses = [
            SuggestionStatus::Accepted->value,
            SuggestionStatus::AutoAssigned->value,
            SuggestionStatus::Rejected->value,
            SuggestionStatus::Ignored->value,
            SuggestionStatus::GlpiClosed->value,
        ];

        $archived = GlpiAiAssignmentSuggestion::query()
            ->whereNull('archived_at')
            ->whereIn('status', $statuses)
            ->where('updated_at', '<=', now()->subDays($days))
            ->update(['archived_at' => now()]);

        $this->info("Sugestões arquivadas: {$archived}.");
        $run->update([
            'summary' => "Sugestões arquivadas: {$archived}.",
            'metadata' => array_merge($run->metadata ?? [], ['archived' => $archived]),
        ]);

        return self::SUCCESS;
    }
}

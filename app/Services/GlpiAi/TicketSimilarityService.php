<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Models\GlpiAiTicketHistory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class TicketSimilarityService
{
    /**
     * @param list<float> $embedding
     * @return Collection<int, GlpiAiTicketHistory>
     */
    public function findSimilar(array $embedding, ?int $categoryId = null): Collection
    {
        $limit = (int) config('glpi-ai.max_similar_tickets', 10);
        $candidateLimit = max($limit * 50, 250);

        return GlpiAiTicketHistory::query()
            ->whereNotNull('embedding')
            ->latest('solved_at')
            ->limit($candidateLimit)
            ->get()
            ->map(function (GlpiAiTicketHistory $ticket) use ($embedding): GlpiAiTicketHistory {
                $ticket->setAttribute('similarity_score', $this->cosineSimilarity($embedding, (array) $ticket->embedding));

                return $ticket;
            })
            ->when($categoryId, fn (Collection $items) => $items->sortByDesc(fn (GlpiAiTicketHistory $ticket): float => (float) $ticket->similarity_score + ((int) $ticket->category_id === $categoryId ? 0.05 : 0)))
            ->sortByDesc('similarity_score')
            ->take($limit)
            ->values();
    }

    public function persistSimilarTickets(int $analysisRunId, Collection $similarTickets, ?int $categoryId = null): void
    {
        foreach ($similarTickets as $ticket) {
            $similarity = (float) ($ticket->similarity_score ?? 0);
            $categoryScore = $categoryId && $ticket->category_id === $categoryId ? 1.0 : 0.0;
            $recencyScore = $ticket->solved_at ? max(0, 1 - $ticket->solved_at->diffInDays(now()) / 365) : 0.2;

            DB::table('glpi_ai_similar_tickets')->insert([
                'analysis_run_id' => $analysisRunId,
                'glpi_ticket_history_id' => $ticket->id,
                'glpi_ticket_id' => $ticket->glpi_ticket_id,
                'similarity_score' => $similarity,
                'category_score' => $categoryScore,
                'recency_score' => $recencyScore,
                'final_similarity_score' => min(1, ($similarity * 0.75) + ($categoryScore * 0.15) + ($recencyScore * 0.10)),
                'title' => $ticket->title,
                'category_id' => $ticket->category_id,
                'category_path' => $ticket->category_path,
                'assigned_technician_id' => $ticket->assigned_technician_id,
                'assigned_technician_name' => $ticket->assigned_technician_name,
                'solver_technician_id' => $ticket->solver_technician_id,
                'solver_technician_name' => $ticket->solver_technician_name,
                'assigned_group_id' => $ticket->assigned_group_id,
                'assigned_group_name' => $ticket->assigned_group_name,
                'solved_at' => $ticket->solved_at,
                'metadata' => json_encode(['source' => 'mariadb-json-cosine']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param list<float> $left
     * @param list<float> $right
     */
    private function cosineSimilarity(array $left, array $right): float
    {
        $count = min(count($left), count($right));
        if ($count === 0) {
            return 0.0;
        }

        $dot = 0.0;
        $leftNorm = 0.0;
        $rightNorm = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $a = (float) $left[$index];
            $b = (float) $right[$index];
            $dot += $a * $b;
            $leftNorm += $a * $a;
            $rightNorm += $b * $b;
        }

        if ($leftNorm <= 0.0 || $rightNorm <= 0.0) {
            return 0.0;
        }

        return max(0.0, min(1.0, $dot / (sqrt($leftNorm) * sqrt($rightNorm))));
    }
}

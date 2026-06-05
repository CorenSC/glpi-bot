<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Models\GlpiAiHumanFeedback;
use Illuminate\Support\Collection;

final class HumanFeedbackLearningService
{
    /**
     * @param array<int, int|string> $candidateIds
     * @return array<int, array{score: float, positive: int, negative: int, total: int}>
     */
    public function signalsForTicket(array $ticket, array $candidateIds): array
    {
        $candidateIds = array_values(array_unique(array_map('intval', $candidateIds)));
        if ($candidateIds === []) {
            return [];
        }

        $category = $this->normalizeCategory((string) ($ticket['title_category'] ?? $ticket['category_name'] ?? $ticket['category_path'] ?? ''));
        $titleCategory = $this->normalizeCategory((string) ($ticket['title_category'] ?? ''));

        $query = GlpiAiHumanFeedback::query()
            ->whereIn('selected_technician_id', $candidateIds)
            ->whereHas('suggestion', function ($query) use ($category, $titleCategory): void {
                if ($category === '' && $titleCategory === '') {
                    return;
                }

                $query->where(function ($query) use ($category, $titleCategory): void {
                    if ($category !== '') {
                        $query->whereRaw('LOWER(category_name) = ?', [$category]);
                    }

                    if ($titleCategory !== '') {
                        $query->orWhere('title', 'like', '['.$titleCategory.']%');
                    }
                });
            });

        /** @var Collection<int, GlpiAiHumanFeedback> $feedbacks */
        $feedbacks = $query->latest()->limit(300)->get();

        $signals = [];
        foreach ($feedbacks as $feedback) {
            $id = (int) $feedback->selected_technician_id;
            if ($id <= 0) {
                continue;
            }

            $signals[$id] ??= ['score' => 0.5, 'positive' => 0, 'negative' => 0, 'total' => 0];
            $storedWeight = (float) ($feedback->learning_weight ?? 0);
            $weight = $storedWeight !== 0.0
                ? ($storedWeight * 0.10)
                : $this->weightForAction((string) $feedback->action);

            if ($weight > 0) {
                $signals[$id]['positive']++;
            } elseif ($weight < 0) {
                $signals[$id]['negative']++;
            }

            $signals[$id]['total']++;
            $signals[$id]['score'] += $weight;
        }

        foreach ($signals as $id => $signal) {
            $signals[$id]['score'] = max(0.0, min(1.0, $signal['score']));
        }

        return $signals;
    }

    private function weightForAction(string $action): float
    {
        return match ($action) {
            'approve', 'assign_recommended_technician', 'assign_other_technician' => 0.08,
            'reject', 'mark_incorrect' => -0.10,
            'send_to_manual_triage' => -0.04,
            default => 0.0,
        };
    }

    private function normalizeCategory(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}

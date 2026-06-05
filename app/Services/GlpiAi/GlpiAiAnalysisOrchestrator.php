<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\AI\Embeddings\EmbeddingProviderInterface;
use App\Enums\RecommendedAction;
use App\Enums\SuggestionStatus;
use App\Jobs\AssignGlpiTicketJob;
use App\Jobs\RevalidateSuggestionAiJob;
use App\Models\GlpiAiAnalysisRun;
use App\Models\GlpiAiAssignmentSuggestion;
use App\Models\GlpiAiTechnicianScore;
use App\Repositories\Glpi\GlpiTicketApiRepository;
use Illuminate\Support\Facades\DB;
use Throwable;

final class GlpiAiAnalysisOrchestrator
{
    public function __construct(
        private readonly GlpiTicketApiRepository $tickets,
        private readonly GlpiTicketNormalizer $normalizer,
        private readonly SensitiveWordDetector $sensitiveWords,
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly TicketSimilarityService $similarity,
        private readonly TechnicianRankingService $ranking,
        private readonly AiAnalysisService $ai,
        private readonly AssignmentDecisionService $decisions,
    ) {
    }

    public function analyzeTicketId(int $glpiTicketId, bool $forceDryRun = false, ?GlpiAiAssignmentSuggestion $existingSuggestion = null): GlpiAiAssignmentSuggestion
    {
        $ticket = $this->tickets->findTicketById($glpiTicketId);
        if (! $ticket) {
            throw new \RuntimeException("GLPI ticket {$glpiTicketId} not found.");
        }

        return $this->analyze($ticket, $forceDryRun, $existingSuggestion);
    }

    public function analyze(array $ticket, bool $forceDryRun = false, ?GlpiAiAssignmentSuggestion $existingSuggestion = null): GlpiAiAssignmentSuggestion
    {
        if (! $existingSuggestion) {
            $existingSuggestion = GlpiAiAssignmentSuggestion::query()
                ->where('glpi_ticket_id', (int) $ticket['glpi_ticket_id'])
                ->latest()
                ->first();

            if ($existingSuggestion && ! $forceDryRun) {
                return $existingSuggestion;
            }
        }

        $started = microtime(true);
        $canonical = $this->normalizer->canonicalize($ticket);
        $hash = $this->normalizer->hash($canonical);
        $sensitive = $this->sensitiveWords->detect($canonical);

        $run = GlpiAiAnalysisRun::query()->create([
            'glpi_ticket_id' => $ticket['glpi_ticket_id'],
            'started_at' => now(),
            'status' => 'running',
            'algorithm_version' => config('glpi-ai.algorithm_version'),
            'model_used' => config('glpi-ai.openrouter_model'),
            'embedding_provider_used' => $this->embeddings->providerName(),
            'embedding_model_used' => $this->embeddings->modelName(),
            'dry_run' => $forceDryRun || (bool) config('glpi-ai.dry_run', true),
            'auto_assign_enabled' => (bool) config('glpi-ai.auto_assign', false),
            'normalized_text' => $this->normalizer->clean($ticket['content'] ?? $ticket['original_content'] ?? ''),
            'canonical_text' => $canonical,
            'text_hash' => $hash,
            'risk_level' => $sensitive === [] ? 'low' : 'high',
            'sensitive_words_found' => $sensitive,
        ]);

        try {
            $embedding = $this->embeddings->embed($canonical);
            $similar = $this->similarity->findSimilar($embedding, isset($ticket['category_id']) ? (int) $ticket['category_id'] : null);
            $this->similarity->persistSimilarTickets($run->id, $similar, isset($ticket['category_id']) ? (int) $ticket['category_id'] : null);

            $ranking = $this->ranking->rank($similar, $ticket, $sensitive);
            foreach ($ranking['technicians'] as $score) {
                GlpiAiTechnicianScore::query()->create(array_merge($score, ['analysis_run_id' => $run->id]));
            }

            $aiResult = null;
            try {
                $aiResult = $this->ai->validateRecommendation(array_merge($ticket, ['canonical_text' => $canonical]), $ranking, $sensitive);
            } catch (Throwable $throwable) {
                $aiResult = ['payload' => null, 'raw' => null, 'parsed' => null, 'error' => $throwable->getMessage()];
            }

            $final = $this->decisions->finalDecision($ranking, $aiResult['parsed'] ?? null, $sensitive);
            $suggestion = DB::transaction(function () use ($run, $ticket, $ranking, $aiResult, $final, $started, $existingSuggestion): GlpiAiAssignmentSuggestion {
                $run->update([
                    'finished_at' => now(),
                    'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                    'status' => 'completed',
                    'deterministic_decision' => $ranking,
                    'ai_decision' => $aiResult['parsed'] ?? ['error' => $aiResult['error'] ?? null],
                    'final_decision' => $final,
                    'recommended_action' => $final['recommended_action'],
                    'recommended_technician_id' => $final['recommended_technician_id'],
                    'recommended_group_id' => $final['recommended_group_id'],
                    'confidence' => $final['confidence'],
                    'risk_level' => $final['risk_level'],
                ]);

                $payload = [
                    'analysis_run_id' => $run->id,
                    'glpi_ticket_id' => $ticket['glpi_ticket_id'],
                    'title' => $ticket['title'] ?? null,
                    'category_id' => $ticket['category_id'] ?? null,
                    'category_name' => $ticket['category_name'] ?? null,
                    'recommended_action' => $final['recommended_action'],
                    'recommended_technician_id' => $final['recommended_technician_id'],
                    'recommended_technician_name' => collect($ranking['technicians'])->firstWhere('technician_id', $final['recommended_technician_id'])['technician_name'] ?? null,
                    'recommended_group_id' => $final['recommended_group_id'],
                    'recommended_group_name' => collect($ranking['groups'])->firstWhere('group_id', $final['recommended_group_id'])['group_name'] ?? null,
                    'confidence' => $final['confidence'],
                    'ranking_confidence' => $final['ranking_confidence'] ?? null,
                    'ai_confidence' => $final['ai_confidence'] ?? null,
                    'final_confidence' => $final['final_confidence'] ?? $final['confidence'],
                    'reason' => $final['reason'],
                    'warnings' => $final['warnings'],
                    'risk_level' => $final['risk_level'],
                    'block_reason_code' => $final['block_reason_code'] ?? null,
                    'block_reason' => $final['block_reason'] ?? null,
                    'status' => SuggestionStatus::Pending->value,
                    'ai_payload' => $aiResult['payload'] ?? null,
                    'ai_raw_response' => $aiResult['raw'] ?? null,
                    'ai_parsed_response' => $aiResult['parsed'] ?? null,
                    'ai_validation_status' => isset($aiResult['error']) ? 'failed' : 'completed',
                    'ai_validation_attempts' => isset($aiResult['error']) ? 1 : 0,
                    'ai_validation_next_retry_at' => isset($aiResult['error']) ? now()->addMinutes(5) : null,
                    'ai_validation_error' => $aiResult['error'] ?? null,
                    'ranking_payload' => $ranking,
                ];

                if ($existingSuggestion) {
                    $existingSuggestion->update($payload);

                    return $existingSuggestion->fresh();
                }

                return GlpiAiAssignmentSuggestion::query()->create($payload);
            });

            if (! (bool) config('glpi-ai.require_human_approval', true) && ! $run->dry_run && (bool) config('glpi-ai.auto_assign') && $final['recommended_action'] !== RecommendedAction::ManualTriage->value) {
                AssignGlpiTicketJob::dispatch($suggestion, true)->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
            }

            if (isset($aiResult['error']) && $suggestion->ai_validation_attempts < 3) {
                RevalidateSuggestionAiJob::dispatch($suggestion->id)
                    ->delay(now()->addMinutes(5))
                    ->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
            }

            return $suggestion;
        } catch (Throwable $throwable) {
            $run->update([
                'finished_at' => now(),
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
                'status' => 'failed',
                'recommended_action' => RecommendedAction::ManualTriage->value,
                'error_message' => $throwable->getMessage(),
                'error_trace' => config('app.debug') ? $throwable->getTraceAsString() : null,
            ]);

            $payload = [
                'analysis_run_id' => $run->id,
                'glpi_ticket_id' => $ticket['glpi_ticket_id'],
                'title' => $ticket['title'] ?? null,
                'recommended_action' => RecommendedAction::ManualTriage->value,
                'confidence' => 0,
                'reason' => 'Falha segura: '.$throwable->getMessage(),
                'risk_level' => 'high',
                'status' => SuggestionStatus::Failed->value,
                'error_message' => $throwable->getMessage(),
            ];

            if ($existingSuggestion) {
                $existingSuggestion->update($payload);

                return $existingSuggestion->fresh();
            }

            return GlpiAiAssignmentSuggestion::query()->create($payload);
        }
    }

    public function revalidateAi(GlpiAiAssignmentSuggestion $suggestion): GlpiAiAssignmentSuggestion
    {
        $suggestion->loadMissing('analysisRun');
        $run = $suggestion->analysisRun;

        if (! $run instanceof GlpiAiAnalysisRun) {
            throw new \RuntimeException("Suggestion {$suggestion->id} has no analysis run.");
        }

        $ranking = (array) ($suggestion->ranking_payload ?: $run->deterministic_decision ?: []);
        if ($ranking === []) {
            throw new \RuntimeException("Suggestion {$suggestion->id} has no ranking payload.");
        }

        $sensitive = (array) ($run->sensitive_words_found ?? []);
        $ticket = [
            'glpi_ticket_id' => $suggestion->glpi_ticket_id,
            'title' => $suggestion->title,
            'category_id' => $suggestion->category_id,
            'category_name' => $suggestion->category_name,
            'canonical_text' => $run->canonical_text,
            'content' => $run->normalized_text,
        ];

        $attempts = (int) $suggestion->ai_validation_attempts + 1;
        $suggestion->update([
            'ai_validation_status' => 'running',
            'ai_validation_attempts' => $attempts,
            'ai_validation_error' => null,
            'ai_validation_next_retry_at' => null,
        ]);

        try {
            $aiResult = $this->ai->validateRecommendation($ticket, $ranking, $sensitive);
            $final = $this->decisions->finalDecision($ranking, $aiResult['parsed'] ?? null, $sensitive);

            DB::transaction(function () use ($suggestion, $run, $aiResult, $final): void {
                $run->update([
                    'ai_decision' => $aiResult['parsed'] ?? null,
                    'final_decision' => $final,
                    'recommended_action' => $final['recommended_action'],
                    'recommended_technician_id' => $final['recommended_technician_id'],
                    'recommended_group_id' => $final['recommended_group_id'],
                    'confidence' => $final['confidence'],
                    'risk_level' => $final['risk_level'],
                ]);

                $suggestion->update([
                    'recommended_action' => $final['recommended_action'],
                    'recommended_technician_id' => $final['recommended_technician_id'],
                    'recommended_technician_name' => collect($suggestion->ranking_payload['technicians'] ?? [])->firstWhere('technician_id', $final['recommended_technician_id'])['technician_name'] ?? null,
                    'recommended_group_id' => $final['recommended_group_id'],
                    'recommended_group_name' => collect($suggestion->ranking_payload['groups'] ?? [])->firstWhere('group_id', $final['recommended_group_id'])['group_name'] ?? null,
                    'confidence' => $final['confidence'],
                    'ranking_confidence' => $final['ranking_confidence'] ?? null,
                    'ai_confidence' => $final['ai_confidence'] ?? null,
                    'final_confidence' => $final['final_confidence'] ?? $final['confidence'],
                    'reason' => $final['reason'],
                    'warnings' => $final['warnings'],
                    'risk_level' => $final['risk_level'],
                    'block_reason_code' => $final['block_reason_code'] ?? null,
                    'block_reason' => $final['block_reason'] ?? null,
                    'ai_payload' => $aiResult['payload'] ?? null,
                    'ai_raw_response' => $aiResult['raw'] ?? null,
                    'ai_parsed_response' => $aiResult['parsed'] ?? null,
                    'ai_validation_status' => 'completed',
                    'ai_validation_error' => null,
                    'ai_validation_next_retry_at' => null,
                ]);
            });

            return $suggestion->fresh();
        } catch (Throwable $throwable) {
            $suggestion->update([
                'ai_validation_status' => 'failed',
                'ai_validation_error' => $throwable->getMessage(),
                'ai_validation_next_retry_at' => $attempts < 3 ? now()->addMinutes(5 * $attempts) : null,
            ]);

            throw $throwable;
        }
    }
}

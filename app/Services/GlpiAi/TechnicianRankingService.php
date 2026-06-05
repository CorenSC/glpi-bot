<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Enums\RecommendedAction;
use App\Models\GlpiAiBlockedTechnician;
use App\Models\GlpiAiTicketHistory;
use App\Repositories\Glpi\GlpiTicketApiRepository;
use Illuminate\Support\Collection;

final class TechnicianRankingService
{
    public function __construct(
        private readonly GlpiTicketApiRepository $glpiTickets,
        private readonly HumanFeedbackLearningService $feedbackLearning,
    )
    {
    }

    /**
     * @param Collection<int, mixed> $similarTickets
     * @return array<string, mixed>
     */
    public function rank(Collection $similarTickets, array $ticket, array $sensitiveWords): array
    {
        $weights = config('glpi-ai.ranking_weights');
        $totalSimilarTickets = max(1, $similarTickets->count());
        $topEvidenceTicketIds = $similarTickets
            ->sortByDesc(fn ($similar): float => (float) ($similar->final_similarity_score ?? $similar->similarity_score ?? 0))
            ->take(3)
            ->map(fn ($similar): int => (int) ($similar->glpi_ticket_id ?? 0))
            ->filter()
            ->values()
            ->all();
        $blockedTechnicians = collect(config('glpi-ai.blocked_technicians', []))
            ->merge(GlpiAiBlockedTechnician::query()->where('active', true)->pluck('technician_id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $scores = [];
        foreach ($similarTickets as $similar) {
            $candidateId = $similar->solver_technician_id ?: $similar->assigned_technician_id;
            if (! $candidateId) {
                continue;
            }

            $id = (int) $candidateId;
            $base = $scores[$id] ?? [
                'technician_id' => $id,
                'technician_name' => $similar->solver_technician_name ?: $similar->assigned_technician_name,
                'group_id' => $similar->assigned_group_id,
                'group_name' => $similar->assigned_group_name,
                'text_similarity_score' => 0.0,
                'category_score' => 0.0,
                'context_score' => 0.0,
                'history_score' => 0.0,
                'recency_score' => 0.0,
                'workload_score' => 0.0,
                'similar_tickets_count' => 0,
                'is_active' => true,
                'is_blocked' => in_array($id, $blockedTechnicians, true),
                'blocked_reason' => null,
                'metadata' => [
                    'context_matches' => [],
                    'weighted_similarity_sum' => 0.0,
                    'top_evidence_count' => 0,
                ],
            ];

            $similarity = (float) ($similar->similarity_score ?? 0);
            $category = $this->categoryScore($ticket, $similar);
            $context = $this->contextScore($ticket, $similar);
            $recency = $similar->solved_at ? max(0, 1 - $similar->solved_at->diffInDays(now()) / 365) : 0.2;
            $history = $similar->solver_technician_id ? 1.0 : 0.65;

            $base['text_similarity_score'] += $similarity;
            $base['category_score'] += $category;
            $base['context_score'] += $context['score'];
            $base['history_score'] += $history;
            $base['recency_score'] += $recency;
            $base['similar_tickets_count']++;
            $base['metadata']['context_matches'][] = $context;
            $base['metadata']['weighted_similarity_sum'] += $similarity * (0.75 + ((float) $context['score'] * 0.25));
            if (in_array((int) ($similar->glpi_ticket_id ?? 0), $topEvidenceTicketIds, true)) {
                $base['metadata']['top_evidence_count']++;
            }
            $scores[$id] = $base;
        }

        $maxSimilarTicketsByTechnician = max(1, collect($scores)->max('similar_tickets_count') ?? 1);
        $maxWeightedSimilarity = max(0.0001, collect($scores)->map(fn (array $score): float => (float) ($score['metadata']['weighted_similarity_sum'] ?? 0))->max() ?? 0.0001);
        $categoryOwnership = $this->categoryOwnershipByTechnician($ticket, array_keys($scores));
        $maxCategoryOwnership = max(1, max($categoryOwnership ?: [1]));
        $feedbackSignals = $this->feedbackLearning->signalsForTicket($ticket, array_keys($scores));

        $ranked = collect($scores)->map(function (array $score) use ($weights, $totalSimilarTickets, $topEvidenceTicketIds, $maxSimilarTicketsByTechnician, $maxWeightedSimilarity, $categoryOwnership, $maxCategoryOwnership, $feedbackSignals): array {
            $count = max(1, $score['similar_tickets_count']);
            $workload = $this->safeWorkloadScore((int) $score['technician_id']);
            $ownershipCount = (int) ($categoryOwnership[(int) $score['technician_id']] ?? 0);
            $ownershipScore = min(1.0, $ownershipCount / $maxCategoryOwnership);
            $feedback = $feedbackSignals[(int) $score['technician_id']] ?? ['score' => 0.5, 'positive' => 0, 'negative' => 0, 'total' => 0];
            $feedbackAdjustment = ((float) $feedback['score'] - 0.5) * 12;
            $score['is_active'] = $this->safeIsActive((int) $score['technician_id']);
            $score['workload_score'] = $workload;
            foreach (['text_similarity_score', 'category_score', 'context_score', 'history_score', 'recency_score'] as $key) {
                $score[$key] = $score[$key] / $count;
            }

            $frequencyScore = min(1.0, $count / $maxSimilarTicketsByTechnician);
            $evidenceShare = min(1.0, $count / $totalSimilarTickets);
            $topEvidenceCount = (int) ($score['metadata']['top_evidence_count'] ?? 0);
            $topEvidenceShare = $topEvidenceTicketIds === [] ? 0.0 : min(1.0, $topEvidenceCount / count($topEvidenceTicketIds));
            $weightedEvidenceScore = min(1.0, ((float) ($score['metadata']['weighted_similarity_sum'] ?? 0)) / $maxWeightedSimilarity);
            $solverRoleScore = $score['history_score'];
            $score['history_score'] = ($solverRoleScore * 0.25)
                + ($frequencyScore * 0.20)
                + ($evidenceShare * 0.25)
                + ($topEvidenceShare * 0.15)
                + ($ownershipScore * 0.15);
            $contextAdjustment = ((float) $score['context_score'] - 0.5) * 18;
            $dominanceAdjustment = $this->dominanceAdjustment($evidenceShare, $topEvidenceShare, $weightedEvidenceScore, (float) $score['context_score']);
            if ($evidenceShare >= 0.5) {
                $score['workload_score'] = max((float) $score['workload_score'], 0.4);
            }
            $score['metadata'] = [
                'frequency_score' => round($frequencyScore, 4),
                'evidence_share' => round($evidenceShare, 4),
                'top_evidence_count' => $topEvidenceCount,
                'top_evidence_share' => round($topEvidenceShare, 4),
                'weighted_evidence_score' => round($weightedEvidenceScore, 4),
                'dominance_adjustment' => round($dominanceAdjustment, 2),
                'dominant_evidence_rule_applied' => $evidenceShare >= 0.5,
                'category_ownership_count' => $ownershipCount,
                'category_ownership_score' => round($ownershipScore, 4),
                'context_score' => round((float) $score['context_score'], 4),
                'context_adjustment' => round($contextAdjustment, 2),
                'context_matches' => collect($score['metadata']['context_matches'] ?? [])
                    ->sortByDesc('score')
                    ->take(5)
                    ->values()
                    ->all(),
                'human_feedback_score' => round((float) $feedback['score'], 4),
                'human_feedback_positive' => (int) $feedback['positive'],
                'human_feedback_negative' => (int) $feedback['negative'],
                'human_feedback_total' => (int) $feedback['total'],
                'human_feedback_adjustment' => round($feedbackAdjustment, 2),
                'ranking_note' => 'Histórico combina papel de solucionador, quantidade de chamados similares, presença entre os melhores resultados, domínio histórico da categoria, contexto da solicitação e feedback humano auditável.',
            ];

            $baseFinalScore = 100 * (
                $score['text_similarity_score'] * $weights['text_similarity'] +
                $score['category_score'] * $weights['category'] +
                $score['history_score'] * $weights['history'] +
                $score['recency_score'] * $weights['recency'] +
                $score['workload_score'] * $weights['workload']
            );
            $score['final_score'] = round(max(0, min(100, $baseFinalScore + $contextAdjustment + $feedbackAdjustment + $dominanceAdjustment)), 2);

            if (! $score['is_active']) {
                $score['is_blocked'] = true;
                $score['blocked_reason'] = 'Técnico inativo no GLPI.';
                $score['final_score'] = 0;
            }

            if ($score['is_blocked']) {
                $score['blocked_reason'] ??= 'Técnico bloqueado para autoatribuição.';
                $score['final_score'] = 0;
            }

            return $score;
        })->sortByDesc('final_score')->values()->map(function (array $score, int $index): array {
            $score['rank_position'] = $index + 1;

            return $score;
        });

        $top = $ranked->first();
        $second = $ranked->get(1);
        $confidence = (float) ($top['final_score'] ?? 0);
        $gap = $second ? $confidence - (float) $second['final_score'] : $confidence;
        $topContextScore = (float) data_get($top, 'metadata.context_score', 0);
        $minimum = (int) config('glpi-ai.minimum_similar_tickets', 3);
        $minimumContext = (float) config('glpi-ai.minimum_context_score_for_technician', 0.35);
        $action = RecommendedAction::ManualTriage->value;
        $explanation = 'Dados insuficientes para recomendação automática.';
        $warnings = [];
        $allowGroupRecommendation = (bool) config('glpi-ai.allow_group_recommendation', false);
        $technicianThreshold = (int) config('glpi-ai.confidence_threshold_technician', 60);
        $groupThreshold = (int) config('glpi-ai.confidence_threshold_group', 45);

        if ($sensitiveWords !== []) {
            $explanation = 'Termos sensiveis encontrados exigem triagem manual.';
        } elseif ($ranked->isEmpty()) {
            $explanation = 'Nenhum técnico histórico válido foi encontrado nos chamados similares.';
        } elseif ($similarTickets->count() < $minimum) {
            $explanation = 'Poucos chamados similares encontrados.';
        } elseif ($topContextScore > 0 && $topContextScore < $minimumContext) {
            $warnings[] = 'Os chamados similares encontrados batem em termos genericos, mas o contexto especifico esta fraco.';
            $explanation = 'Contexto dos chamados similares insuficiente para recomendar técnico com segurança.';
        } elseif ($confidence >= $technicianThreshold || (! $allowGroupRecommendation && $confidence >= $groupThreshold)) {
            $action = RecommendedAction::AssignToTechnician->value;
            $explanation = 'Histórico e similaridade sustentam recomendação do técnico mais bem ranqueado.';

            if ($gap < (float) config('glpi-ai.minimum_gap_between_candidates', 3)) {
                $warnings[] = 'Diferença pequena entre os melhores técnicos; validar manualmente antes de atribuir.';
                $explanation = 'Técnico sugerido para validação humana; diferença pequena entre candidatos indica confiança moderada.';
            }
        } elseif ($allowGroupRecommendation && $confidence >= $groupThreshold) {
            $action = RecommendedAction::AssignToGroup->value;
            $explanation = 'Confiança intermediária; sugerir grupo em vez de técnico.';
        }

        return [
            'technicians' => $ranked->all(),
            'groups' => $ranked->groupBy('group_id')->filter(fn ($items, $id) => $id)->map(fn ($items) => [
                'group_id' => $items->first()['group_id'],
                'group_name' => $items->first()['group_name'],
                'final_score' => round($items->avg('final_score'), 2),
                'similar_tickets_count' => $items->sum('similar_tickets_count'),
            ])->sortByDesc('final_score')->values()->all(),
            'confidence' => $confidence,
            'recommended_action' => $action,
            'recommended_technician_id' => $action === RecommendedAction::AssignToTechnician->value ? ($top['technician_id'] ?? null) : null,
            'recommended_group_id' => in_array($action, [RecommendedAction::AssignToTechnician->value, RecommendedAction::AssignToGroup->value], true) ? ($top['group_id'] ?? null) : null,
            'explanation' => $explanation,
            'gap' => $gap,
            'warnings' => $warnings,
        ];
    }

    private function safeWorkloadScore(int $technicianId): float
    {
        $workload = $this->glpiTickets->getTechnicianCurrentWorkload($technicianId);

        return max(0, 1 - min($workload, 20) / 20);
    }

    private function dominanceAdjustment(float $evidenceShare, float $topEvidenceShare, float $weightedEvidenceScore, float $contextScore): float
    {
        $adjustment = 0.0;

        if ($evidenceShare >= 0.5) {
            $adjustment += 10.0;
        } elseif ($evidenceShare <= 0.15) {
            $adjustment -= 4.0;
        }

        $adjustment += ($evidenceShare - 0.25) * 16;
        $adjustment += $topEvidenceShare * 5;
        $adjustment += ($weightedEvidenceScore - 0.5) * 6;

        if ($contextScore < 0.35) {
            $adjustment = min($adjustment, 3.0);
            $adjustment -= (0.35 - $contextScore) * 20;
        }

        return max(-8.0, min(18.0, $adjustment));
    }

    private function safeIsActive(int $technicianId): bool
    {
        return $this->glpiTickets->isTechnicianActive($technicianId);
    }

    private function categoryScore(array $ticket, mixed $similar): float
    {
        $ticketCategories = $this->categoryCandidates(
            $ticket['title_category'] ?? null,
            $ticket['category_path'] ?? null,
            $ticket['category_name'] ?? null,
            $ticket['title'] ?? null,
        );
        $similarCategories = $this->categoryCandidates(
            $similar->title_category ?? null,
            $similar->category_path ?? null,
            $similar->category_name ?? null,
            $similar->title ?? null,
        );

        if ($ticketCategories !== [] && $similarCategories !== []) {
            return array_intersect($ticketCategories, $similarCategories) !== [] ? 1.0 : 0.0;
        }

        if (! empty($ticket['category_id']) && ! empty($similar->category_id)) {
            return (int) $ticket['category_id'] === (int) $similar->category_id ? 1.0 : 0.0;
        }

        $ticketCategory = $this->normalizeCategoryText($ticket['category_path'] ?? $ticket['category_name'] ?? $ticket['title_category'] ?? null);
        $similarCategory = $this->normalizeCategoryText($similar->category_path ?? $similar->category_name ?? null);

        if ($ticketCategory === '' || $similarCategory === '') {
            return 0.5;
        }

        return $ticketCategory === $similarCategory ? 1.0 : 0.0;
    }

    /**
     * @return list<string>
     */
    private function categoryCandidates(mixed ...$values): array
    {
        $categories = [];

        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text === '') {
                continue;
            }

            if (preg_match('/^\s*\[([^\]]+)\]/u', $text, $matches) === 1) {
                $categories[] = $this->normalizeCategoryText($matches[1]);
            }

            $categories[] = $this->normalizeCategoryText($text);
        }

        return array_values(array_filter(array_unique($categories)));
    }

    private function normalizeCategoryText(mixed $value): string
    {
        $value = mb_strtolower(trim((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    /**
     * @return array{score: float, system_match: bool, intent_overlap: list<string>, term_overlap: list<string>}
     */
    private function contextScore(array $ticket, mixed $similar): array
    {
        $current = $this->contextSignals($this->ticketContextText($ticket));
        $history = $this->contextSignals(implode("\n", array_filter([
            $similar->title ?? null,
            $similar->category_path ?? null,
            $similar->category_name ?? null,
            $similar->clean_content ?? null,
            $similar->canonical_text ?? null,
            $similar->solution_text ?? null,
        ])));

        $systemMatch = $current['systems'] !== [] && array_intersect($current['systems'], $history['systems']) !== [];
        $intentOverlap = array_values(array_intersect($current['intents'], $history['intents']));
        $termOverlap = array_values(array_intersect($current['terms'], $history['terms']));

        $score = 0.15;
        if ($systemMatch) {
            $score += 0.20;
        }

        if ($intentOverlap !== []) {
            $score += min(0.55, count($intentOverlap) * 0.22);
        }

        if ($termOverlap !== []) {
            $score += min(0.25, count($termOverlap) * 0.05);
        }

        if ($systemMatch && $intentOverlap === []) {
            $score -= 0.15;
        }

        $currentWantsMailboxAdministration = in_array('email_caixa_compartilhada', $current['intents'], true)
            || in_array('email_transferencia_administracao', $current['intents'], true);
        $historyIsOnlyTemporaryRedirect = in_array('email_redirecionamento_temporario', $history['intents'], true)
            && ! in_array('email_caixa_compartilhada', $history['intents'], true)
            && ! in_array('email_transferencia_administracao', $history['intents'], true);

        if ($currentWantsMailboxAdministration && $historyIsOnlyTemporaryRedirect) {
            $score -= 0.25;
        }

        return [
            'score' => round(max(0.0, min(1.0, $score)), 4),
            'system_match' => $systemMatch,
            'intent_overlap' => $intentOverlap,
            'term_overlap' => array_slice($termOverlap, 0, 8),
        ];
    }

    private function ticketContextText(array $ticket): string
    {
        return implode("\n", array_filter([
            $ticket['title'] ?? null,
            $ticket['title_category'] ?? null,
            $ticket['category_name'] ?? null,
            $ticket['category_path'] ?? null,
            $ticket['content'] ?? null,
            $ticket['original_content'] ?? null,
            $ticket['solution_text'] ?? null,
        ]));
    }

    /**
     * @return array{systems: list<string>, intents: list<string>, terms: list<string>}
     */
    private function contextSignals(string $text): array
    {
        $normalized = $this->normalizeContextText($text);

        $systems = $this->matchedTerms($normalized, [
            'agiliza', 'sicsp', 'sicsp 2.0', 'sicsp2', 'presente', 'sigen', 'wordpress', 'portal transparencia',
            'relatorios', 'power bi', 'sei', 'email', 'e-mail', 'corensc.gov.br', 'processos.eticos', 'denuncias eticas',
        ]);

        $intentRules = [
            'acesso_perfil' => ['acesso', 'perfil', 'perfis', 'liberar', 'liberacao', 'liberado', 'permissao', 'permissoes', 'direitos', 'habilitar', 'controladoria', 'arquivar'],
            'senha_login' => ['senha', 'login', 'credenciais', 'autenticacao', '2 fatores', 'mfa'],
            'erro_sistema' => ['erro', 'problema', 'falha', 'nao abre', 'sem conexao', 'travando', 'bug'],
            'cadastro_alteracao' => ['cadastro', 'alterar', 'alteracao', 'atualizar', 'incluir', 'inserir', 'editar'],
            'relatorio' => ['relatorio', 'relatorios', 'rop', 'power bi', 'indicador'],
            'documento_anexo' => ['documento', 'anexo', 'arquivo', 'planilha', 'pdf', 'docx', 'xlsx'],
            'publicacao_portal' => ['publicacao', 'publicar', 'portal transparencia', 'transparencia'],
            'email_caixa_compartilhada' => ['processos.eticos', 'denuncias eticas', 'caixa', 'conta', 'administracao', 'minha administracao', 'recebidos'],
            'email_transferencia_administracao' => ['transferencia', 'transferir', 'trazer', 'migrar', 'administracao', 'recebidos'],
            'email_redirecionamento_temporario' => ['redirecionamento', 'redirecionar', 'direcionar', 'ferias', 'afastamento', 'afastamentos', 'periodo', 'retornei', 'interromper'],
        ];

        $intents = [];
        foreach ($intentRules as $intent => $terms) {
            if ($this->matchedTerms($normalized, $terms) !== []) {
                $intents[] = $intent;
            }
        }

        $terms = $this->matchedTerms($normalized, [
            'karla', 'marlete', 'controladoria', 'assessora', 'assessor', 'juridica', 'gabinete',
            'liberar', 'perfil', 'perfis', 'acesso', 'permissao', 'direitos', 'arquivar', 'habilitar',
            'portaria', 'periodo', 'processos', 'fiscalizacao', 'usuario', 'movimentacoes',
            'processos.eticos', 'denuncias eticas', 'transferencia', 'transferir', 'trazer', 'administracao',
            'redirecionamento', 'redirecionar', 'ferias', 'afastamento', 'afastamentos', 'joao', 'fabricia',
        ]);

        return [
            'systems' => $systems,
            'intents' => $intents,
            'terms' => $terms,
        ];
    }

    /**
     * @param list<string> $terms
     * @return list<string>
     */
    private function matchedTerms(string $text, array $terms): array
    {
        return array_values(array_filter(array_unique($terms), fn (string $term): bool => str_contains($text, $this->normalizeContextText($term))));
    }

    private function normalizeContextText(string $text): string
    {
        $text = mb_strtolower($text);
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = is_string($converted) ? $converted : $text;
        $text = preg_replace('/[^a-z0-9\s.]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param array<int, int|string> $candidateIds
     * @return array<int, int>
     */
    private function categoryOwnershipByTechnician(array $ticket, array $candidateIds): array
    {
        $candidateIds = array_values(array_unique(array_map('intval', $candidateIds)));
        if ($candidateIds === []) {
            return [];
        }

        $category = trim((string) ($ticket['title_category'] ?? $ticket['category_name'] ?? $ticket['category_path'] ?? ''));
        if ($category === '') {
            return [];
        }

        $query = GlpiAiTicketHistory::query()
            ->where(function ($query) use ($category): void {
                $query
                    ->where('category_name', $category)
                    ->orWhere('category_path', $category)
                    ->orWhere('title', 'like', '['.$category.']%');
            })
            ->where(function ($query) use ($candidateIds): void {
                $query
                    ->whereIn('solver_technician_id', $candidateIds)
                    ->orWhereIn('assigned_technician_id', $candidateIds);
            });

        $counts = [];
        $query->get(['solver_technician_id', 'assigned_technician_id'])->each(function (GlpiAiTicketHistory $history) use (&$counts, $candidateIds): void {
            $id = (int) ($history->solver_technician_id ?: $history->assigned_technician_id);
            if ($id > 0 && in_array($id, $candidateIds, true)) {
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        });

        return $counts;
    }
}

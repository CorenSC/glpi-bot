<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Enums\RecommendedAction;

final class AssignmentDecisionService
{
    public function finalDecision(array $ranking, ?array $ai, array $sensitiveWords): array
    {
        $warnings = [];
        $action = $ranking['recommended_action'] ?? RecommendedAction::ManualTriage->value;
        $risk = $ai['risk_level'] ?? 'low';

        if ($sensitiveWords !== []) {
            $warnings[] = 'Termos de atenção encontrados: '.implode(', ', $sensitiveWords).'. Isso não bloqueia recomendação de técnico, mas exige validação humana.';
        }

        if ($risk === 'high') {
            $action = RecommendedAction::ManualTriage->value;
            $warnings[] = 'Risco alto bloqueia autoatribuição.';
        }

        $rankingHasCandidate = ! empty($ranking['recommended_technician_id']) || ! empty($ranking['recommended_group_id']);

        if ($ai === null && ! $rankingHasCandidate) {
            $action = RecommendedAction::ManualTriage->value;
            $warnings[] = 'Falha ou ausência de validação por IA.';
        } elseif ($ai === null && $rankingHasCandidate) {
            $warnings[] = 'Falha ou timeout na validação por IA; mantendo sugestão do ranking para validação humana.';
        }

        if ($ai && ($ai['recommended_action'] ?? null) === RecommendedAction::ManualTriage->value && ! $rankingHasCandidate) {
            $action = RecommendedAction::ManualTriage->value;
        } elseif ($ai && ($ai['recommended_action'] ?? null) === RecommendedAction::ManualTriage->value && $rankingHasCandidate) {
            $warnings[] = 'A IA sugeriu triagem manual, mas o ranking histórico encontrou candidato; manter como sugestão para validação humana.';
        }

        $rankingConfidence = (float) ($ranking['confidence'] ?? 0);
        $aiConfidence = $ai === null ? null : (float) ($ai['confidence'] ?? 0);
        $finalConfidence = $action === RecommendedAction::ManualTriage->value
            ? min($rankingConfidence, (float) ($aiConfidence ?? 100))
            : $rankingConfidence;
        [$blockReasonCode, $blockReason] = $this->blockReason($action, $ranking, $ai, $sensitiveWords, $warnings);

        return [
            'recommended_action' => $action,
            'recommended_technician_id' => $action === RecommendedAction::AssignToTechnician->value ? ($ranking['recommended_technician_id'] ?? null) : null,
            'recommended_group_id' => in_array($action, [RecommendedAction::AssignToTechnician->value, RecommendedAction::AssignToGroup->value], true) ? ($ranking['recommended_group_id'] ?? null) : null,
            'confidence' => $finalConfidence,
            'ranking_confidence' => $rankingConfidence,
            'ai_confidence' => $aiConfidence,
            'final_confidence' => $finalConfidence,
            'block_reason_code' => $blockReasonCode,
            'block_reason' => $blockReason,
            'risk_level' => $risk,
            'warnings' => array_values(array_unique(array_merge($warnings, (array) ($ranking['warnings'] ?? []), (array) ($ai['warnings'] ?? [])))),
            'reason' => $ranking['explanation'] ?? $ai['reason'] ?? 'Triagem manual por segurança.',
            'dry_run' => (bool) config('glpi-ai.dry_run', true),
            'auto_assign' => (bool) config('glpi-ai.auto_assign', false),
        ];
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function blockReason(string $action, array $ranking, ?array $ai, array $sensitiveWords, array $warnings): array
    {
        if ($action !== RecommendedAction::ManualTriage->value) {
            if ($warnings !== []) {
                return ['human_validation_required', 'A sugestão pode ser usada, mas exige validação humana por haver avisos no ranking ou na IA.'];
            }

            return [null, null];
        }

        if ($sensitiveWords !== []) {
            return ['sensitive_terms', 'O chamado contém termos de atenção e precisa de validação humana.'];
        }

        if (($ranking['technicians'] ?? []) === []) {
            return ['no_candidates', 'Nenhum técnico histórico válido entrou no ranking.'];
        }

        if ((float) ($ranking['confidence'] ?? 0) < (float) config('glpi-ai.confidence_threshold_group', 45)) {
            return ['low_confidence', 'A confiança do ranking ficou abaixo do mínimo operacional.'];
        }

        if ($ai === null) {
            return ['ai_unavailable', 'A IA não validou a recomendação por falha ou timeout.'];
        }

        return ['manual_review', 'A recomendação foi direcionada para revisão humana por regra de segurança ou baixa clareza.'];
    }
}

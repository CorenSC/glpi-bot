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
            $warnings[] = 'Termos de atencao encontrados: '.implode(', ', $sensitiveWords).'. Isso nao bloqueia recomendacao de tecnico, mas exige validacao humana.';
        }

        if ($risk === 'high') {
            $action = RecommendedAction::ManualTriage->value;
            $warnings[] = 'Risco alto bloqueia autoatribuicao.';
        }

        $rankingHasCandidate = ! empty($ranking['recommended_technician_id']) || ! empty($ranking['recommended_group_id']);

        if ($ai === null && ! $rankingHasCandidate) {
            $action = RecommendedAction::ManualTriage->value;
            $warnings[] = 'Falha ou ausencia de validacao por IA.';
        } elseif ($ai === null && $rankingHasCandidate) {
            $warnings[] = 'Falha ou timeout na validacao por IA; mantendo sugestao do ranking para validacao humana.';
        }

        if ($ai && ($ai['recommended_action'] ?? null) === RecommendedAction::ManualTriage->value && ! $rankingHasCandidate) {
            $action = RecommendedAction::ManualTriage->value;
        } elseif ($ai && ($ai['recommended_action'] ?? null) === RecommendedAction::ManualTriage->value && $rankingHasCandidate) {
            $warnings[] = 'A IA sugeriu triagem manual, mas o ranking historico encontrou candidato; manter como sugestao para validacao humana.';
        }

        return [
            'recommended_action' => $action,
            'recommended_technician_id' => $action === RecommendedAction::AssignToTechnician->value ? ($ranking['recommended_technician_id'] ?? null) : null,
            'recommended_group_id' => in_array($action, [RecommendedAction::AssignToTechnician->value, RecommendedAction::AssignToGroup->value], true) ? ($ranking['recommended_group_id'] ?? null) : null,
            'confidence' => $action === RecommendedAction::ManualTriage->value
                ? min((float) ($ranking['confidence'] ?? 0), (float) ($ai['confidence'] ?? 100))
                : (float) ($ranking['confidence'] ?? 0),
            'risk_level' => $risk,
            'warnings' => array_values(array_unique(array_merge($warnings, (array) ($ranking['warnings'] ?? []), (array) ($ai['warnings'] ?? [])))),
            'reason' => $ranking['explanation'] ?? $ai['reason'] ?? 'Triagem manual por seguranca.',
            'dry_run' => (bool) config('glpi-ai.dry_run', true),
            'auto_assign' => (bool) config('glpi-ai.auto_assign', false),
        ];
    }
}

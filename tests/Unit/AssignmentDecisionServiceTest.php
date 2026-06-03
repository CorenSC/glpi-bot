<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\RecommendedAction;
use App\Services\GlpiAi\AssignmentDecisionService;
use Tests\TestCase;

class AssignmentDecisionServiceTest extends TestCase
{
    public function test_sensitive_word_keeps_candidate_with_human_validation_warning(): void
    {
        $decision = (new AssignmentDecisionService())->finalDecision([
            'recommended_action' => RecommendedAction::AssignToTechnician->value,
            'recommended_technician_id' => 10,
            'recommended_group_id' => 24,
            'confidence' => 95,
        ], [
            'risk_level' => 'low',
            'confidence' => 95,
            'warnings' => [],
            'reason' => 'ok',
        ], ['senha']);

        $this->assertSame(RecommendedAction::AssignToTechnician->value, $decision['recommended_action']);
        $this->assertSame(10, $decision['recommended_technician_id']);
        $this->assertNotEmpty($decision['warnings']);
    }

    public function test_missing_ai_validation_keeps_ranking_candidate_for_human_validation(): void
    {
        $decision = (new AssignmentDecisionService())->finalDecision([
            'recommended_action' => RecommendedAction::AssignToTechnician->value,
            'recommended_technician_id' => 10,
            'recommended_group_id' => 24,
            'confidence' => 95,
        ], null, []);

        $this->assertSame(RecommendedAction::AssignToTechnician->value, $decision['recommended_action']);
        $this->assertNotEmpty($decision['warnings']);
    }

    public function test_missing_ai_validation_without_candidate_forces_manual_triage(): void
    {
        $decision = (new AssignmentDecisionService())->finalDecision([
            'recommended_action' => RecommendedAction::ManualTriage->value,
            'confidence' => 20,
        ], null, []);

        $this->assertSame(RecommendedAction::ManualTriage->value, $decision['recommended_action']);
    }
}

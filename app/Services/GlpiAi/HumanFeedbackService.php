<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Models\GlpiAiAssignmentSuggestion;
use App\Models\GlpiAiHumanFeedback;
use Illuminate\Http\Request;

final class HumanFeedbackService
{
    public function record(GlpiAiAssignmentSuggestion $suggestion, string $action, string $newStatus, Request $request): void
    {
        $previous = $suggestion->status;
        $selectedTechnicianId = $request->integer('technician_id') ?: $suggestion->recommended_technician_id;
        $effectiveAction = $action;
        $reasonCode = $request->string('reason_code')->toString() ?: null;
        $observation = $request->string('observation')->toString();

        if ($action === 'approve' && $selectedTechnicianId && (int) $selectedTechnicianId !== (int) $suggestion->recommended_technician_id) {
            $effectiveAction = 'assign_other_technician';
            $candidate = collect($suggestion->ranking_payload['technicians'] ?? [])->firstWhere('technician_id', (int) $selectedTechnicianId);
            $suggestion->recommended_technician_id = (int) $selectedTechnicianId;
            $suggestion->recommended_technician_name = is_array($candidate) ? ($candidate['technician_name'] ?? $suggestion->recommended_technician_name) : $suggestion->recommended_technician_name;
        }

        if ($action === 'assign_recommended_technician' && $selectedTechnicianId && (int) $selectedTechnicianId !== (int) $suggestion->recommended_technician_id) {
            $effectiveAction = 'assign_other_technician';
            $candidate = collect($suggestion->ranking_payload['technicians'] ?? [])->firstWhere('technician_id', (int) $selectedTechnicianId);
            $suggestion->recommended_technician_id = (int) $selectedTechnicianId;
            $suggestion->recommended_technician_name = is_array($candidate) ? ($candidate['technician_name'] ?? $suggestion->recommended_technician_name) : $suggestion->recommended_technician_name;
        }

        $learningWeight = $this->learningWeight(
            $effectiveAction,
            $reasonCode,
            $observation,
            (int) $selectedTechnicianId,
            (int) $suggestion->recommended_technician_id,
        );

        $suggestion->update([
            'status' => $newStatus,
            'human_observation' => $observation ?: $suggestion->human_observation,
            'action_taken' => $effectiveAction,
            'action_taken_at' => now(),
            'recommended_technician_id' => $suggestion->recommended_technician_id,
            'recommended_technician_name' => $suggestion->recommended_technician_name,
            'approved_by_user_id' => str_contains($effectiveAction, 'approve') || str_contains($effectiveAction, 'assign') ? $request->user()?->id : $suggestion->approved_by_user_id,
            'rejected_by_user_id' => str_contains($effectiveAction, 'reject') ? $request->user()?->id : $suggestion->rejected_by_user_id,
        ]);

        GlpiAiHumanFeedback::query()->create([
            'assignment_suggestion_id' => $suggestion->id,
            'analysis_run_id' => $suggestion->analysis_run_id,
            'user_id' => $request->user()?->id,
            'action' => $effectiveAction,
            'reason_code' => $reasonCode,
            'learning_weight' => $learningWeight,
            'previous_status' => $previous,
            'new_status' => $newStatus,
            'selected_technician_id' => $selectedTechnicianId,
            'selected_group_id' => $request->integer('group_id') ?: $suggestion->recommended_group_id,
            'observation' => $observation,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'recommended_technician_id' => $suggestion->recommended_technician_id,
                'recommended_group_id' => $suggestion->recommended_group_id,
                'learning_signal' => $learningWeight !== 0.0,
            ],
        ]);
    }

    private function learningWeight(string $action, ?string $reasonCode, string $observation, int $selectedTechnicianId, int $recommendedTechnicianId): float
    {
        $hasReason = $reasonCode !== null || trim($observation) !== '';
        $changedTechnician = $selectedTechnicianId > 0 && $recommendedTechnicianId > 0 && $selectedTechnicianId !== $recommendedTechnicianId;

        return match ($action) {
            'assign_other_technician' => $hasReason ? 1.0 : 0.6,
            'assign_recommended_technician' => $hasReason ? 0.55 : 0.25,
            'approve' => $hasReason ? 0.35 : 0.15,
            'reject', 'mark_incorrect' => $hasReason ? -0.75 : -0.35,
            'send_to_manual_triage' => $hasReason ? -0.35 : -0.15,
            default => $changedTechnician ? 0.5 : 0.0,
        };
    }
}

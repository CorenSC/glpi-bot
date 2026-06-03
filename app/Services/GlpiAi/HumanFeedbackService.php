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

        $suggestion->update([
            'status' => $newStatus,
            'human_observation' => $request->string('observation')->toString() ?: $suggestion->human_observation,
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
            'previous_status' => $previous,
            'new_status' => $newStatus,
            'selected_technician_id' => $selectedTechnicianId,
            'selected_group_id' => $request->integer('group_id') ?: $suggestion->recommended_group_id,
            'observation' => $request->string('observation')->toString(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'recommended_technician_id' => $suggestion->recommended_technician_id,
                'recommended_group_id' => $suggestion->recommended_group_id,
                'learning_signal' => true,
            ],
        ]);
    }
}

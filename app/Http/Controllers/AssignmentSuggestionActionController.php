<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RecommendedAction;
use App\Enums\SuggestionStatus;
use App\Http\Requests\HumanSuggestionActionRequest;
use App\Jobs\AssignGlpiTicketJob;
use App\Jobs\RecalculateSuggestionJob;
use App\Jobs\RevalidateSuggestionAiJob;
use App\Models\GlpiAiAssignmentSuggestion;
use App\Services\GlpiAi\HumanFeedbackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;

class AssignmentSuggestionActionController extends Controller
{
    public function approve(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        $feedback->record($suggestion, 'approve', SuggestionStatus::Accepted->value, $request);

        $suggestion->refresh();
        $this->applyHumanAssignmentChoice($suggestion, $request);

        $canAssignAfterHumanApproval = ! (bool) config('glpi-ai.dry_run')
            && $this->hasAssignableTarget($suggestion);

        if ($canAssignAfterHumanApproval) {
            $this->dispatchAssignment($suggestion);

            return back()->with('success', 'Sugestão aprovada e atribuição enviada para o GLPI.');
        }

        return back()->with('success', config('glpi-ai.dry_run') ? 'Sugestão aprovada em dry-run.' : 'Sugestão aprovada.');
    }

    public function reject(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        $feedback->record($suggestion, 'reject', SuggestionStatus::Rejected->value, $request);

        return back()->with('success', 'Sugestão rejeitada.');
    }

    public function assignTechnician(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('executeAssignment', $suggestion);
        $feedback->record($suggestion, 'assign_recommended_technician', SuggestionStatus::Accepted->value, $request);
        $suggestion->refresh();
        $this->applyHumanAssignmentChoice($suggestion, $request, RecommendedAction::AssignToTechnician);
        $this->dispatchAssignment($suggestion);

        return back()->with('success', config('glpi-ai.dry_run') ? 'Simulação registrada em dry-run.' : 'Atribuição enviada para fila.');
    }

    public function assignGroup(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('executeAssignment', $suggestion);
        $feedback->record($suggestion, 'assign_recommended_group', SuggestionStatus::Accepted->value, $request);
        $suggestion->refresh();
        $suggestion->update(['recommended_action' => RecommendedAction::AssignToGroup->value]);
        $this->dispatchAssignment($suggestion);

        return back()->with('success', config('glpi-ai.dry_run') ? 'Simulação registrada em dry-run.' : 'Atribuição enviada para fila.');
    }

    public function manualTriage(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        $feedback->record($suggestion, 'send_to_manual_triage', SuggestionStatus::ManualTriage->value, $request);

        return back()->with('success', 'Sugestão enviada para triagem manual.');
    }

    public function recalculate(GlpiAiAssignmentSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        $suggestion->update([
            'action_taken' => 'recalculate_requested',
            'action_taken_at' => now(),
            'error_message' => null,
        ]);

        RecalculateSuggestionJob::dispatch($suggestion->id)
            ->onConnection('database')
            ->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));

        return back()->with('success', 'Recálculo enviado para a fila.');
    }

    public function revalidateAi(GlpiAiAssignmentSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        RevalidateSuggestionAiJob::dispatch($suggestion->id)
            ->onConnection('database')
            ->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));

        return back()->with('success', 'Reanálise da IA enviada para a fila.');
    }

    private function dispatchAssignment(GlpiAiAssignmentSuggestion $suggestion): void
    {
        $suggestion->update([
            'action_taken' => 'human_assignment_queued',
            'action_taken_at' => now(),
            'error_message' => null,
        ]);

        AssignGlpiTicketJob::dispatch($suggestion->refresh(), false)
            ->onConnection((string) Config::get('queue.default', 'database'))
            ->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));
    }

    private function applyHumanAssignmentChoice(GlpiAiAssignmentSuggestion $suggestion, HumanSuggestionActionRequest $request, ?RecommendedAction $forcedAction = null): void
    {
        $selectedTechnicianId = $request->integer('technician_id') ?: $suggestion->recommended_technician_id;

        if ($forcedAction === RecommendedAction::AssignToTechnician || $selectedTechnicianId) {
            $candidate = collect($suggestion->ranking_payload['technicians'] ?? [])->firstWhere('technician_id', (int) $selectedTechnicianId);
            $suggestion->update([
                'recommended_action' => RecommendedAction::AssignToTechnician->value,
                'recommended_technician_id' => $selectedTechnicianId ? (int) $selectedTechnicianId : $suggestion->recommended_technician_id,
                'recommended_technician_name' => is_array($candidate) ? ($candidate['technician_name'] ?? $suggestion->recommended_technician_name) : $suggestion->recommended_technician_name,
            ]);

            return;
        }

        if ($suggestion->recommended_group_id) {
            $suggestion->update(['recommended_action' => RecommendedAction::AssignToGroup->value]);
        }
    }

    private function hasAssignableTarget(GlpiAiAssignmentSuggestion $suggestion): bool
    {
        return ($suggestion->recommended_action === RecommendedAction::AssignToTechnician->value && $suggestion->recommended_technician_id)
            || ($suggestion->recommended_action === RecommendedAction::AssignToGroup->value && $suggestion->recommended_group_id);
    }
}

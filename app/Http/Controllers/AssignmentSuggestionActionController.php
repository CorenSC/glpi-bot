<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\SuggestionStatus;
use App\Enums\RecommendedAction;
use App\Http\Requests\HumanSuggestionActionRequest;
use App\Jobs\AssignGlpiTicketJob;
use App\Jobs\RecalculateSuggestionJob;
use App\Models\GlpiAiAssignmentSuggestion;
use App\Services\GlpiAi\HumanFeedbackService;
use Illuminate\Http\RedirectResponse;

class AssignmentSuggestionActionController extends Controller
{
    public function approve(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        $feedback->record($suggestion, 'approve', SuggestionStatus::Accepted->value, $request);

        $suggestion->refresh();

        $canAssign = ! (bool) config('glpi-ai.dry_run')
            && (bool) config('glpi-ai.auto_assign')
            && in_array($suggestion->recommended_action, [
                RecommendedAction::AssignToTechnician->value,
                RecommendedAction::AssignToGroup->value,
            ], true);

        if ($canAssign) {
            AssignGlpiTicketJob::dispatch($suggestion);

            return back()->with('success', 'Sugestao aprovada e atribuicao enviada para o GLPI.');
        }

        return back()->with('success', config('glpi-ai.dry_run') ? 'Sugestao aprovada em dry-run.' : 'Sugestao aprovada.');
    }

    public function reject(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        $feedback->record($suggestion, 'reject', SuggestionStatus::Rejected->value, $request);

        return back()->with('success', 'Sugestao rejeitada.');
    }

    public function assignTechnician(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('executeAssignment', $suggestion);
        $feedback->record($suggestion, 'assign_recommended_technician', SuggestionStatus::Accepted->value, $request);
        AssignGlpiTicketJob::dispatch($suggestion);

        return back()->with('success', config('glpi-ai.dry_run') ? 'Simulacao registrada em dry-run.' : 'Atribuicao enviada para fila.');
    }

    public function assignGroup(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('executeAssignment', $suggestion);
        $feedback->record($suggestion, 'assign_recommended_group', SuggestionStatus::Accepted->value, $request);
        AssignGlpiTicketJob::dispatch($suggestion);

        return back()->with('success', config('glpi-ai.dry_run') ? 'Simulacao registrada em dry-run.' : 'Atribuicao enviada para fila.');
    }

    public function manualTriage(HumanSuggestionActionRequest $request, GlpiAiAssignmentSuggestion $suggestion, HumanFeedbackService $feedback): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        $feedback->record($suggestion, 'send_to_manual_triage', SuggestionStatus::ManualTriage->value, $request);

        return back()->with('success', 'Sugestao enviada para triagem manual.');
    }

    public function recalculate(GlpiAiAssignmentSuggestion $suggestion): RedirectResponse
    {
        $this->authorize('approve', $suggestion);
        RecalculateSuggestionJob::dispatch($suggestion->id)
            ->onConnection('database')
            ->onQueue((string) config('glpi-ai.queue_name', 'glpi-ai'));

        return back()->with('success', 'Recalculo enviado para a fila. Processe pelo terminal com queue:work.');
    }
}

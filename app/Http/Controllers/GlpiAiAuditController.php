<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GlpiAiAnalysisRun;
use App\Models\GlpiAiHumanFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class GlpiAiAuditController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $this->authorize('viewGlpiAiDashboard');

        $search = $request->string('search')->toString();
        $status = $request->string('status')->toString();
        $action = $request->string('action')->toString();
        $from = $request->date('from');
        $to = $request->date('to');

        $runs = GlpiAiAnalysisRun::query()
            ->with('suggestion:id,analysis_run_id,status,recommended_technician_name,recommended_group_name')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('glpi_ticket_id', $search)
                        ->orWhere('recommended_technician_id', $search)
                        ->orWhere('recommended_group_id', $search)
                        ->orWhere('final_decision->reason', 'like', '%'.$search.'%')
                        ->orWhere('error_message', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($action !== '', fn ($query) => $query->where('recommended_action', $action))
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from->startOfDay()))
            ->when($to, fn ($query) => $query->where('created_at', '<=', $to->endOfDay()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $feedbacks = GlpiAiHumanFeedback::query()
            ->with(['suggestion:id,glpi_ticket_id,title,status,recommended_technician_name,recommended_group_name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('assignment_suggestion_id', $search)
                        ->orWhere('selected_technician_id', $search)
                        ->orWhere('selected_group_id', $search)
                        ->orWhere('observation', 'like', '%'.$search.'%')
                        ->orWhereHas('suggestion', fn ($query) => $query->where('glpi_ticket_id', $search)->orWhere('title', 'like', '%'.$search.'%'));
                });
            })
            ->when($action !== '', fn ($query) => $query->where('action', $action))
            ->when($from, fn ($query) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($query) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()))
            ->latest()
            ->limit(80)
            ->get();

        return Inertia::render('GlpiAi/Audit/Index', [
            'runs' => $runs,
            'feedbacks' => $feedbacks,
            'filters' => $request->only(['search', 'status', 'action', 'from', 'to']),
            'glpiWebBaseUrl' => (string) config('glpi-ai.glpi_api.web_base_url'),
            'dryRun' => (bool) config('glpi-ai.dry_run'),
            'autoAssign' => (bool) config('glpi-ai.auto_assign'),
        ]);
    }
}

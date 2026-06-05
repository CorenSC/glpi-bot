<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\GlpiAiAssignmentSuggestion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentSuggestionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', GlpiAiAssignmentSuggestion::class);

        $view = $request->string('view', 'pending')->toString();
        $validViews = ['pending', 'accepted', 'manual_triage', 'rejected', 'glpi_closed', 'failed', 'all'];

        if (! in_array($view, $validViews, true)) {
            $view = 'pending';
        }

        $statusByView = [
            'pending' => 'pending',
            'accepted' => 'accepted',
            'manual_triage' => 'manual_triage',
            'rejected' => 'rejected',
            'glpi_closed' => 'glpi_closed',
            'failed' => 'failed',
        ];

        $suggestions = GlpiAiAssignmentSuggestion::query()
            ->when($view !== 'all', fn ($query) => $query->whereNull('archived_at'))
            ->when($view !== 'all' && ! $request->filled('status'), fn ($query) => $query->where('status', $statusByView[$view]))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('title', 'like', '%'.$search.'%')
                        ->orWhere('glpi_ticket_id', $search);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('risk'), fn ($query) => $query->where('risk_level', $request->string('risk')->toString()))
            ->when($request->filled('action'), fn ($query) => $query->where('recommended_action', $request->string('action')->toString()))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $tabCounts = GlpiAiAssignmentSuggestion::query()
            ->whereNull('archived_at')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        return Inertia::render('GlpiAi/Suggestions/Index', [
            'suggestions' => $suggestions,
            'filters' => array_merge($request->only(['search', 'status', 'risk', 'action']), ['view' => $view]),
            'tabCounts' => [
                'pending' => $tabCounts['pending'] ?? 0,
                'accepted' => $tabCounts['accepted'] ?? 0,
                'manual_triage' => $tabCounts['manual_triage'] ?? 0,
                'rejected' => $tabCounts['rejected'] ?? 0,
                'glpi_closed' => $tabCounts['glpi_closed'] ?? 0,
                'failed' => $tabCounts['failed'] ?? 0,
                'all' => array_sum($tabCounts),
            ],
            'dryRun' => (bool) config('glpi-ai.dry_run'),
            'glpiWebBaseUrl' => (string) config('glpi-ai.glpi_api.web_base_url'),
        ]);
    }

    public function show(GlpiAiAssignmentSuggestion $suggestion): Response
    {
        $this->authorize('view', $suggestion);

        $suggestion->load(['analysisRun.similarTickets', 'analysisRun.technicianScores', 'feedbacks']);

        return Inertia::render('GlpiAi/Suggestions/Show', [
            'suggestion' => $suggestion,
            'dryRun' => (bool) config('glpi-ai.dry_run'),
            'autoAssign' => (bool) config('glpi-ai.auto_assign'),
            'glpiWebBaseUrl' => (string) config('glpi-ai.glpi_api.web_base_url'),
        ]);
    }
}

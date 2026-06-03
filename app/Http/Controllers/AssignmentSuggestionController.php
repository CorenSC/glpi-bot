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

        $suggestions = GlpiAiAssignmentSuggestion::query()
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

        return Inertia::render('GlpiAi/Suggestions/Index', [
            'suggestions' => $suggestions,
            'filters' => $request->only(['search', 'status', 'risk', 'action']),
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

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ManualAnalysisRequest;
use App\Services\GlpiAi\GlpiAiAnalysisOrchestrator;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ManualAnalysisController extends Controller
{
    public function create(): Response
    {
        $this->authorize('runManualGlpiAiAnalysis');

        return Inertia::render('GlpiAi/Analysis/Manual', [
            'dryRun' => (bool) config('glpi-ai.dry_run'),
            'autoAssign' => (bool) config('glpi-ai.auto_assign'),
        ]);
    }

    public function store(ManualAnalysisRequest $request, GlpiAiAnalysisOrchestrator $orchestrator): RedirectResponse
    {
        $this->authorize('runManualGlpiAiAnalysis');
        $suggestion = $orchestrator->analyzeTicketId($request->integer('glpi_ticket_id'), true);

        return redirect()->route('glpi-ai.suggestions.show', $suggestion)->with('success', 'Análise dry-run concluída.');
    }
}

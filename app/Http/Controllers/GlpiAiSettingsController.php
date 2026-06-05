<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GlpiAi\GlpiAiSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlpiAiSettingsController extends Controller
{
    public function index(GlpiAiSettingsService $settings): Response
    {
        $this->authorize('manageGlpiAiSettings');

        return Inertia::render('GlpiAi/Settings/Index', [
            'settings' => $settings->effectivePublicSettings(),
            'editableSettings' => $settings->editableValues(),
            'definitions' => $settings->editableDefinitions(),
            'editable' => (bool) config('glpi-ai.enable_dashboard_settings_edit', false),
            'dryRun' => (bool) config('glpi-ai.dry_run'),
            'autoAssign' => (bool) config('glpi-ai.auto_assign'),
        ]);
    }

    public function update(Request $request, GlpiAiSettingsService $settings): RedirectResponse
    {
        $this->authorize('manageGlpiAiSettings');
        abort_unless((bool) config('glpi-ai.enable_dashboard_settings_edit', false), 403);

        $settings->update($request->all(), $request->user()?->id);

        return back()->with('success', 'Configurações operacionais atualizadas.');
    }
}

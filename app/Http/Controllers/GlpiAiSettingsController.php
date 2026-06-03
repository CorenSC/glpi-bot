<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GlpiAiSettingsController extends Controller
{
    public function index(): Response
    {
        $this->authorize('manageGlpiAiSettings');

        return Inertia::render('GlpiAi/Settings/Index', [
            'settings' => $this->publicSettings(),
            'editable' => (bool) config('glpi-ai.enable_dashboard_settings_edit', false),
            'dryRun' => (bool) config('glpi-ai.dry_run'),
            'autoAssign' => (bool) config('glpi-ai.auto_assign'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manageGlpiAiSettings');
        abort_unless((bool) config('glpi-ai.enable_dashboard_settings_edit', false), 403);

        return back()->with('success', 'Edicao via painel esta preparada, mas deve persistir em glpi_ai_settings conforme politica operacional.');
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSettings(): array
    {
        $settings = config('glpi-ai');

        data_set($settings, 'openrouter.api_key', '********');
        data_set($settings, 'glpi_api.app_token', '********');
        data_set($settings, 'glpi_api.user_token', '********');

        return $settings;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\GlpiAi\DashboardMetricsService;
use Inertia\Inertia;
use Inertia\Response;

class GlpiAiDashboardController extends Controller
{
    public function __invoke(DashboardMetricsService $metrics): Response
    {
        $this->authorize('viewGlpiAiDashboard');

        return Inertia::render('GlpiAi/Dashboard', [
            'metrics' => $metrics->metrics(),
            'dryRun' => (bool) config('glpi-ai.dry_run'),
            'autoAssign' => (bool) config('glpi-ai.auto_assign'),
        ]);
    }
}

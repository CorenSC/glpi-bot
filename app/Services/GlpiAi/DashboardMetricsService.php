<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Models\GlpiAiAnalysisRun;
use App\Models\GlpiAiAssignmentSuggestion;

final class DashboardMetricsService
{
    public function metrics(): array
    {
        return [
            'dry_run' => (bool) config('glpi-ai.dry_run', true),
            'auto_assign' => (bool) config('glpi-ai.auto_assign', false),
            'total_analyzed' => GlpiAiAnalysisRun::query()->count(),
            'pending' => GlpiAiAssignmentSuggestion::query()->where('status', 'pending')->count(),
            'accepted' => GlpiAiAssignmentSuggestion::query()->where('status', 'accepted')->count(),
            'rejected' => GlpiAiAssignmentSuggestion::query()->where('status', 'rejected')->count(),
            'auto_assigned' => GlpiAiAssignmentSuggestion::query()->where('status', 'auto_assigned')->count(),
            'manual_triage' => GlpiAiAssignmentSuggestion::query()->where('recommended_action', 'manual_triage')->count(),
            'average_confidence' => round((float) GlpiAiAssignmentSuggestion::query()->avg('confidence'), 2),
            'last_24h' => GlpiAiAnalysisRun::query()->where('created_at', '>=', now()->subDay())->count(),
            'last_7d' => GlpiAiAnalysisRun::query()->where('created_at', '>=', now()->subDays(7))->count(),
            'recent_errors' => GlpiAiAnalysisRun::query()->whereNotNull('error_message')->latest()->limit(5)->get(['id', 'glpi_ticket_id', 'error_message', 'created_at']),
            'top_technicians' => GlpiAiAssignmentSuggestion::query()->whereNotNull('recommended_technician_id')->selectRaw('recommended_technician_name as name, count(*) as total')->groupBy('recommended_technician_name')->orderByDesc('total')->limit(5)->get(),
            'top_groups' => GlpiAiAssignmentSuggestion::query()->whereNotNull('recommended_group_id')->selectRaw('recommended_group_name as name, count(*) as total')->groupBy('recommended_group_name')->orderByDesc('total')->limit(5)->get(),
        ];
    }
}

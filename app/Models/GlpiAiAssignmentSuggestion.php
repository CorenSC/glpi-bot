<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlpiAiAssignmentSuggestion extends Model
{
    protected $guarded = [];

    protected $casts = [
        'warnings' => 'array',
        'ai_payload' => 'array',
        'ai_parsed_response' => 'array',
        'ranking_payload' => 'array',
        'glpi_payload' => 'array',
        'glpi_api_response' => 'array',
        'action_taken_at' => 'datetime',
    ];

    public function analysisRun(): BelongsTo
    {
        return $this->belongsTo(GlpiAiAnalysisRun::class, 'analysis_run_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(GlpiAiHumanFeedback::class, 'assignment_suggestion_id');
    }
}

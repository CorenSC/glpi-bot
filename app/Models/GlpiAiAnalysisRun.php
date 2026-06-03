<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GlpiAiAnalysisRun extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'dry_run' => 'boolean',
        'auto_assign_enabled' => 'boolean',
        'sensitive_words_found' => 'array',
        'deterministic_decision' => 'array',
        'ai_decision' => 'array',
        'final_decision' => 'array',
        'metadata' => 'array',
    ];

    public function similarTickets(): HasMany
    {
        return $this->hasMany(GlpiAiSimilarTicket::class, 'analysis_run_id');
    }

    public function technicianScores(): HasMany
    {
        return $this->hasMany(GlpiAiTechnicianScore::class, 'analysis_run_id');
    }

    public function suggestion(): HasOne
    {
        return $this->hasOne(GlpiAiAssignmentSuggestion::class, 'analysis_run_id');
    }
}

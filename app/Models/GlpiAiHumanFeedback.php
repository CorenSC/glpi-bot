<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlpiAiHumanFeedback extends Model
{
    protected $table = 'glpi_ai_human_feedbacks';

    protected $guarded = [];

    protected $casts = [
        'learning_weight' => 'float',
        'metadata' => 'array',
    ];

    public function suggestion(): BelongsTo
    {
        return $this->belongsTo(GlpiAiAssignmentSuggestion::class, 'assignment_suggestion_id');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlpiAiTicketHistory extends Model
{
    protected $guarded = [];

    protected $casts = [
        'embedding' => 'array',
        'metadata' => 'array',
        'opened_at' => 'datetime',
        'updated_at_glpi' => 'datetime',
        'solved_at' => 'datetime',
        'closed_at' => 'datetime',
        'embedding_generated_at' => 'datetime',
    ];
}

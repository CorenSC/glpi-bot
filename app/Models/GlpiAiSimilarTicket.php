<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlpiAiSimilarTicket extends Model
{
    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'solved_at' => 'datetime',
    ];
}

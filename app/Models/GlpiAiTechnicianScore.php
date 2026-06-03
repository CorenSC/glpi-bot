<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlpiAiTechnicianScore extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_blocked' => 'boolean',
        'metadata' => 'array',
    ];
}

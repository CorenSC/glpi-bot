<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlpiAiSetting extends Model
{
    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
        'is_sensitive' => 'boolean',
    ];
}

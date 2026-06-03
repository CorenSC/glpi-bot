<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlpiAiBlockedCategory extends Model
{
    protected $guarded = [];

    protected $casts = ['active' => 'boolean'];
}

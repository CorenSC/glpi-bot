<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GlpiAi\SensitiveWordDetector;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SensitiveWordDetectorTest extends TestCase
{
    public function test_sensitive_words_are_case_insensitive(): void
    {
        Config::set('glpi-ai.sensitive_words', ['senha', 'ransomware']);

        $found = app(SensitiveWordDetector::class)->detect('Usuario informou SENHA em chamado de Ransomware.');

        $this->assertSame(['senha', 'ransomware'], $found);
    }
}

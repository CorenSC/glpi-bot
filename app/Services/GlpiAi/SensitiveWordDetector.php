<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

final class SensitiveWordDetector
{
    /**
     * @return list<string>
     */
    public function detect(string $text): array
    {
        $found = [];
        foreach ((array) config('glpi-ai.sensitive_words', []) as $word) {
            if ($word !== '' && mb_stripos($text, (string) $word) !== false) {
                $found[] = (string) $word;
            }
        }

        return array_values(array_unique($found));
    }
}

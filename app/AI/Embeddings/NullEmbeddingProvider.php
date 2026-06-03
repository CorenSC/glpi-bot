<?php

declare(strict_types=1);

namespace App\AI\Embeddings;

final class NullEmbeddingProvider implements EmbeddingProviderInterface
{
    public function embed(string $text): array
    {
        $dimension = $this->dimensions();
        $hash = hash('sha256', $text);
        $values = [];
        for ($i = 0; $i < $dimension; $i++) {
            $byte = hexdec(substr($hash, ($i * 2) % 64, 2));
            $values[] = round(($byte / 255) * 2 - 1, 6);
        }

        return $values;
    }

    public function providerName(): string
    {
        return 'null';
    }

    public function modelName(): string
    {
        return 'deterministic-local-fallback';
    }

    public function dimensions(): int
    {
        return (int) config('glpi-ai.embedding_dimension', 1536);
    }
}

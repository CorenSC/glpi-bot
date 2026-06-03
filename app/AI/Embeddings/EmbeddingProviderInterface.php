<?php

declare(strict_types=1);

namespace App\AI\Embeddings;

interface EmbeddingProviderInterface
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;

    public function providerName(): string;

    public function modelName(): string;

    public function dimensions(): int;
}

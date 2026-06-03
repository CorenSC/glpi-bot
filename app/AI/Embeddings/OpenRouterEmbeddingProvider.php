<?php

declare(strict_types=1);

namespace App\AI\Embeddings;

use App\Integrations\OpenRouter\OpenRouterClient;

final class OpenRouterEmbeddingProvider implements EmbeddingProviderInterface
{
    public function __construct(private readonly OpenRouterClient $client)
    {
    }

    public function embed(string $text): array
    {
        return array_map('floatval', $this->client->embeddings($text, $this->modelName()));
    }

    public function providerName(): string
    {
        return 'openrouter';
    }

    public function modelName(): string
    {
        return (string) config('glpi-ai.embedding_model');
    }

    public function dimensions(): int
    {
        return (int) config('glpi-ai.embedding_dimension', 1536);
    }
}

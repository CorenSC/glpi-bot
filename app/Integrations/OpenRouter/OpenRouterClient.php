<?php

declare(strict_types=1);

namespace App\Integrations\OpenRouter;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenRouterClient
{
    public function chat(array $messages, ?string $model = null): array
    {
        $response = $this->request()->post('/chat/completions', [
            'model' => $model ?: config('glpi-ai.openrouter_model'),
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter chat failed with HTTP '.$response->status());
        }

        return $response->json();
    }

    public function embeddings(string $text, ?string $model = null): array
    {
        $response = $this->request()->post('/embeddings', [
            'model' => $model ?: config('glpi-ai.embedding_model'),
            'input' => $text,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenRouter embeddings failed with HTTP '.$response->status());
        }

        return $response->json('data.0.embedding') ?? [];
    }

    private function request(): PendingRequest
    {
        $apiKey = config('glpi-ai.openrouter.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            throw new RuntimeException('OPENROUTER_API_KEY is not configured.');
        }

        return Http::baseUrl((string) config('glpi-ai.openrouter.base_url'))
            ->timeout((int) config('glpi-ai.request_timeout', 30))
            ->retry(2, 500, throw: false)
            ->withOptions(['verify' => (bool) config('glpi-ai.openrouter.verify_ssl', true)])
            ->withToken($apiKey)
            ->withHeaders(array_filter([
                'HTTP-Referer' => config('glpi-ai.openrouter.site_url'),
                'X-Title' => config('glpi-ai.openrouter.app_name'),
            ]));
    }
}

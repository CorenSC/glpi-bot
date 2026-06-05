<?php

declare(strict_types=1);

namespace App\Providers;

use App\AI\Embeddings\EmbeddingProviderInterface;
use App\AI\Embeddings\NullEmbeddingProvider;
use App\AI\Embeddings\OpenRouterEmbeddingProvider;
use App\Models\GlpiAiAssignmentSuggestion;
use App\Policies\GlpiAiAssignmentSuggestionPolicy;
use App\Policies\GlpiAiPolicy;
use App\Services\GlpiAi\GlpiAiSettingsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ((bool) config('app.debug') && class_exists(\Barryvdh\Debugbar\ServiceProvider::class)) {
            $this->app->register(\Barryvdh\Debugbar\ServiceProvider::class);
        }

        $this->app->bind(EmbeddingProviderInterface::class, function ($app): EmbeddingProviderInterface {
            return config('glpi-ai.embedding_provider') === 'openrouter'
                ? $app->make(OpenRouterEmbeddingProvider::class)
                : $app->make(NullEmbeddingProvider::class);
        });
    }

    public function boot(): void
    {
        app(GlpiAiSettingsService::class)->applyDatabaseOverrides();

        Gate::policy(GlpiAiAssignmentSuggestion::class, GlpiAiAssignmentSuggestionPolicy::class);

        $policy = app(GlpiAiPolicy::class);
        Gate::define('viewGlpiAiDashboard', fn ($user): bool => $policy->viewGlpiAiDashboard($user));
        Gate::define('runManualGlpiAiAnalysis', fn ($user): bool => $policy->runManualGlpiAiAnalysis($user));
        Gate::define('manageGlpiAiSettings', fn ($user): bool => $policy->manageGlpiAiSettings($user));
    }
}

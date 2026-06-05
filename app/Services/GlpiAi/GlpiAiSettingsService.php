<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Models\GlpiAiOperationalRun;
use App\Models\GlpiAiSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class GlpiAiSettingsService
{
    /**
     * @return array<string, array{label: string, description: string, type: string, min?: int|float, max?: int|float}>
     */
    public function editableDefinitions(): array
    {
        return [
            'dry_run' => [
                'label' => 'Modo dry-run',
                'description' => 'Quando ativo, nenhuma escrita real é feita no GLPI.',
                'type' => 'boolean',
            ],
            'auto_assign' => [
                'label' => 'Autoatribuição via API',
                'description' => 'Permite escrita real no GLPI quando o dry-run estiver desligado.',
                'type' => 'boolean',
            ],
            'require_human_approval' => [
                'label' => 'Exigir aprovação humana',
                'description' => 'Quando ativo, análises automáticas apenas criam sugestões.',
                'type' => 'boolean',
            ],
            'confidence_threshold_technician' => [
                'label' => 'Confiança mínima para técnico',
                'description' => 'Percentual mínimo para sugerir um técnico específico.',
                'type' => 'integer',
                'min' => 0,
                'max' => 100,
            ],
            'confidence_threshold_group' => [
                'label' => 'Confiança mínima para grupo',
                'description' => 'Percentual mínimo para sugerir grupo quando permitido.',
                'type' => 'integer',
                'min' => 0,
                'max' => 100,
            ],
            'minimum_gap_between_candidates' => [
                'label' => 'Diferença mínima entre candidatos',
                'description' => 'Diferença em pontos para evitar empate técnico fraco.',
                'type' => 'float',
                'min' => 0,
                'max' => 50,
            ],
            'minimum_context_score_for_technician' => [
                'label' => 'Contexto mínimo para técnico',
                'description' => 'Score mínimo de contexto para aceitar recomendação de pessoa.',
                'type' => 'float',
                'min' => 0,
                'max' => 1,
            ],
            'max_similar_tickets' => [
                'label' => 'Máximo de chamados similares',
                'description' => 'Quantidade máxima de históricos usados como evidência.',
                'type' => 'integer',
                'min' => 3,
                'max' => 50,
            ],
            'minimum_similar_tickets' => [
                'label' => 'Mínimo de chamados similares',
                'description' => 'Quantidade mínima de evidências para recomendar.',
                'type' => 'integer',
                'min' => 1,
                'max' => 20,
            ],
            'request_timeout' => [
                'label' => 'Timeout das APIs',
                'description' => 'Tempo máximo em segundos para chamadas externas.',
                'type' => 'integer',
                'min' => 5,
                'max' => 180,
            ],
            'analyze_new_tickets_interval_minutes' => [
                'label' => 'Intervalo de análise de novos chamados',
                'description' => 'Intervalo em minutos usado pelo Scheduler.',
                'type' => 'integer',
                'min' => 1,
                'max' => 60,
            ],
            'archive_after_days' => [
                'label' => 'Arquivar finalizadas após',
                'description' => 'Quantidade de dias para remover finalizadas da fila principal.',
                'type' => 'integer',
                'min' => 1,
                'max' => 365,
            ],
            'allow_group_recommendation' => [
                'label' => 'Permitir sugestão de grupo',
                'description' => 'No fluxo atual, normalmente o grupo é apenas contexto.',
                'type' => 'boolean',
            ],
            'ignore_group_assignment_for_new_tickets' => [
                'label' => 'Ignorar grupo já atribuído em chamados novos',
                'description' => 'Permite analisar chamados novos que já entram com o grupo da TI.',
                'type' => 'boolean',
            ],
            'new_ticket_statuses' => [
                'label' => 'Status analisados como novos',
                'description' => 'Lista de IDs de status separados por vírgula. Normalmente 1.',
                'type' => 'integer_list',
            ],
            'historical_ticket_statuses' => [
                'label' => 'Status usados no histórico',
                'description' => 'Lista de IDs de status separados por vírgula. Normalmente 5,6.',
                'type' => 'integer_list',
            ],
        ];
    }

    public function applyDatabaseOverrides(): void
    {
        try {
            if (! Schema::hasTable('glpi_ai_settings')) {
                return;
            }

            GlpiAiSetting::query()
                ->whereIn('key', array_keys($this->editableDefinitions()))
                ->get()
                ->each(function (GlpiAiSetting $setting): void {
                    config(['glpi-ai.'.$setting->key => $this->storedValue($setting)]);
                });
        } catch (\Throwable) {
            // Durante migrate/config:cache o banco pode ainda não estar disponível.
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function effectivePublicSettings(): array
    {
        $settings = config('glpi-ai');

        data_set($settings, 'openrouter.api_key', '********');
        data_set($settings, 'glpi_api.app_token', '********');
        data_set($settings, 'glpi_api.user_token', '********');

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function editableValues(): array
    {
        return collect($this->editableDefinitions())
            ->mapWithKeys(fn (array $definition, string $key): array => [$key => config('glpi-ai.'.$key)])
            ->all();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(array $input, ?int $userId): void
    {
        $definitions = $this->editableDefinitions();
        $changes = [];

        foreach ($definitions as $key => $definition) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $newValue = $this->normalizeValue($key, $input[$key], $definition);
            $oldValue = config('glpi-ai.'.$key);

            if ($newValue === $oldValue) {
                continue;
            }

            $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
        }

        if ($changes === []) {
            return;
        }

        DB::transaction(function () use ($changes, $definitions, $userId): void {
            foreach ($changes as $key => $change) {
                GlpiAiSetting::query()->updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => ['value' => $change['new']],
                        'type' => $definitions[$key]['type'],
                        'description' => $definitions[$key]['description'],
                        'is_sensitive' => false,
                        'updated_by_user_id' => $userId,
                    ],
                );
            }

            GlpiAiOperationalRun::query()->create([
                'command' => 'settings:update',
                'status' => 'completed',
                'started_at' => now(),
                'finished_at' => now(),
                'duration_ms' => 0,
                'summary' => 'Configurações operacionais atualizadas pelo painel.',
                'metadata' => [
                    'user_id' => $userId,
                    'changes' => $changes,
                ],
            ]);
        });
    }

    /**
     * @param array{type: string, min?: int|float, max?: int|float} $definition
     */
    private function normalizeValue(string $key, mixed $value, array $definition): mixed
    {
        $normalized = match ($definition['type']) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            'integer' => filter_var($value, FILTER_VALIDATE_INT),
            'float' => filter_var($value, FILTER_VALIDATE_FLOAT),
            'integer_list' => $this->normalizeIntegerList($value),
            default => is_string($value) ? trim($value) : $value,
        };

        if ($normalized === null || ($normalized === false && $definition['type'] !== 'boolean') || $normalized === []) {
            throw ValidationException::withMessages([$key => 'Valor inválido para esta configuração.']);
        }

        if (isset($definition['min']) && is_numeric($normalized) && $normalized < $definition['min']) {
            throw ValidationException::withMessages([$key => 'Valor abaixo do mínimo permitido.']);
        }

        if (isset($definition['max']) && is_numeric($normalized) && $normalized > $definition['max']) {
            throw ValidationException::withMessages([$key => 'Valor acima do máximo permitido.']);
        }

        return $definition['type'] === 'integer' ? (int) $normalized : $normalized;
    }

    /**
     * @return array<int, int>
     */
    private function normalizeIntegerList(mixed $value): array
    {
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return collect($items)
            ->map(fn (mixed $item): int => (int) trim((string) $item))
            ->filter(fn (int $item): bool => $item > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function storedValue(GlpiAiSetting $setting): mixed
    {
        $value = $setting->value;

        return is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
    }
}

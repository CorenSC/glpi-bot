<?php

declare(strict_types=1);

namespace App\Services\GlpiAi;

use App\Enums\RecommendedAction;
use App\Integrations\OpenRouter\OpenRouterClient;
use RuntimeException;

final class AiAnalysisService
{
    public function __construct(private readonly OpenRouterClient $client)
    {
    }

    public function validateRecommendation(array $ticket, array $ranking, array $sensitiveWords): array
    {
        $payload = [
            'ticket' => [
                'glpi_ticket_id' => $ticket['glpi_ticket_id'] ?? null,
                'title' => $ticket['title'] ?? null,
                'category_id' => $ticket['category_id'] ?? null,
                'category_path' => $ticket['category_path'] ?? null,
                'canonical_text' => $ticket['canonical_text'] ?? null,
            ],
            'sensitive_words_found' => $sensitiveWords,
            'ranking' => $ranking,
            'dry_run' => (bool) config('glpi-ai.dry_run', true),
            'auto_assign' => (bool) config('glpi-ai.auto_assign', false),
            'rules' => [
                'no_user_final_response' => true,
                'valid_technician_ids' => collect($ranking['technicians'] ?? [])->pluck('technician_id')->values()->all(),
                'valid_group_ids' => collect($ranking['groups'] ?? [])->pluck('group_id')->values()->all(),
                'manual_triage_on_sensitive_words' => false,
                'sensitive_words_are_warnings' => true,
            ],
        ];

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'user', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        ];

        $response = $this->client->chat($messages);
        $raw = (string) data_get($response, 'choices.0.message.content', '');
        $parsed = json_decode($raw, true);

        if (! is_array($parsed)) {
            throw new RuntimeException('OpenRouter returned invalid JSON.');
        }

        return [
            'payload' => $payload,
            'raw' => $raw,
            'parsed' => $this->validateParsed($parsed, $ranking),
        ];
    }

    private function validateParsed(array $parsed, array $ranking): array
    {
        $action = $parsed['recommended_action'] ?? RecommendedAction::ManualTriage->value;
        if (! in_array($action, array_column(RecommendedAction::cases(), 'value'), true)) {
            $action = RecommendedAction::ManualTriage->value;
        }

        $validTechnicians = collect($ranking['technicians'] ?? [])->pluck('technician_id')->map(fn ($id) => (int) $id)->all();
        $validGroups = collect($ranking['groups'] ?? [])->pluck('group_id')->map(fn ($id) => (int) $id)->all();
        $technicianId = isset($parsed['recommended_technician_id']) ? (int) $parsed['recommended_technician_id'] : null;
        $groupId = isset($parsed['recommended_group_id']) ? (int) $parsed['recommended_group_id'] : null;

        if ($technicianId !== null && ! in_array($technicianId, $validTechnicians, true)) {
            throw new RuntimeException('AI recommended an unknown technician id.');
        }

        if ($groupId !== null && ! in_array($groupId, $validGroups, true)) {
            throw new RuntimeException('AI recommended an unknown group id.');
        }

        return [
            'category_interpretation' => (string) ($parsed['category_interpretation'] ?? ''),
            'risk_level' => in_array($parsed['risk_level'] ?? 'low', ['low', 'medium', 'high'], true) ? $parsed['risk_level'] : 'high',
            'recommended_action' => $action,
            'recommended_technician_id' => $technicianId,
            'recommended_group_id' => $groupId,
            'confidence' => max(0, min(100, (float) ($parsed['confidence'] ?? 0))),
            'reason' => $this->normalizePortugueseReason((string) ($parsed['reason'] ?? '')),
            'warnings' => array_values(array_map(fn ($warning): string => $this->normalizePortugueseReason((string) $warning), (array) ($parsed['warnings'] ?? []))),
        ];
    }

    private function normalizePortugueseReason(string $text): string
    {
        $replacements = [
            'Technician ' => 'O tecnico ',
            ' has the highest final score ' => ' tem o maior score final ',
            ' based on text similarity, history, recency and workload' => ' com base em similaridade textual, historico, recencia e carga de trabalho',
            ' within the ' => ' dentro do grupo ',
            ' group' => '',
            'Small score gap' => 'Pequena diferenca de pontuacao',
            'between top technicians' => 'entre os principais tecnicos',
            'consider manual verification before assignment' => 'considere validacao manual antes da atribuicao',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Voce e um assistente tecnico de triagem de chamados ITSM.
Sua tarefa e validar e explicar uma recomendacao gerada por um sistema algoritmico.
Responda sempre em portugues do Brasil.
Voce NAO deve responder ao usuario final.
Voce NAO pode inventar tecnicos, grupos ou IDs.
Voce so pode recomendar tecnicos ou grupos presentes no ranking fornecido.
Considere evidencia forte quando um tecnico concentra muitos chamados similares, aparece entre os primeiros similares e possui weighted_evidence_score alto.
Nao valide como melhor escolha um tecnico com pouca evidencia quando outro candidato domina a maioria dos chamados similares usados como referencia.
Se o ranking for fraco, houver risco alto real, categoria bloqueada ou dados insuficientes, recomende manual_triage.
Palavras como acesso, permissao ou senha podem indicar atencao, mas nao devem bloquear recomendacao de tecnico quando o ranking historico estiver consistente.
Retorne exclusivamente JSON valido:
{"category_interpretation":"string","risk_level":"low|medium|high","recommended_action":"assign_to_technician|assign_to_group|manual_triage","recommended_technician_id":number|null,"recommended_group_id":number|null,"confidence":number,"reason":"string","warnings":["string"]}
Os campos reason e warnings devem estar em portugues do Brasil.
Nao inclua markdown. Nao inclua comentarios fora do JSON.
PROMPT;
    }
}

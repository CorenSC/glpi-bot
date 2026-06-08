<?php

declare(strict_types=1);

namespace App\Integrations\Glpi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GlpiApiClient
{
    private ?string $sessionToken = null;
    private bool $entityChanged = false;

    public function __destruct()
    {
        $this->closeSession();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getItems(string $itemType, array $query = []): Collection
    {
        return $this->withSession(function (string $sessionToken) use ($itemType, $query): Collection {
            $response = $this->request()
                ->withHeader('Session-Token', $sessionToken)
                ->get('/'.trim($itemType, '/'), $query);

            if ($response->failed()) {
                throw new RuntimeException("Falha na API do GLPI ao listar {$itemType}: HTTP ".$response->status());
            }

            $data = $response->json();

            return collect(is_array($data) ? $data : []);
        });
    }

    /**
     * @return array{items: Collection<int, array<string, mixed>>, total: int|null}
     */
    public function getItemsWithMeta(string $itemType, array $query = []): array
    {
        return $this->withSession(function (string $sessionToken) use ($itemType, $query): array {
            $response = $this->request()
                ->withHeader('Session-Token', $sessionToken)
                ->get('/'.trim($itemType, '/'), $query);

            if ($response->failed()) {
                throw new RuntimeException("Falha na API do GLPI ao listar {$itemType}: HTTP ".$response->status());
            }

            $data = $response->json();

            return [
                'items' => collect(is_array($data) ? $data : []),
                'total' => $this->extractTotalFromContentRange($response->header('Content-Range')),
            ];
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getItem(string $itemType, int $id, array $query = []): ?array
    {
        return $this->withSession(function (string $sessionToken) use ($itemType, $id, $query): ?array {
            $response = $this->request()
                ->withHeader('Session-Token', $sessionToken)
                ->get('/'.trim($itemType, '/').'/'.$id, $query);

            if ($response->status() === 404) {
                return null;
            }

            if ($response->failed()) {
                throw new RuntimeException("Falha na API do GLPI ao ler {$itemType}#{$id}: HTTP ".$response->status());
            }

            $data = $response->json();

            return is_array($data) ? $data : null;
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getSubItems(string $itemType, int $id, string $subItemType, array $query = []): Collection
    {
        return $this->withSession(function (string $sessionToken) use ($itemType, $id, $subItemType, $query): Collection {
            $response = $this->request()
                ->withHeader('Session-Token', $sessionToken)
                ->get('/'.trim($itemType, '/').'/'.$id.'/'.trim($subItemType, '/'), array_merge([
                    'expand_dropdowns' => false,
                    'get_hateoas' => false,
                ], $query));

            if ($response->status() === 404) {
                return collect();
            }

            if ($response->failed()) {
                throw new RuntimeException("Falha na API do GLPI ao ler {$itemType}#{$id}/{$subItemType}: HTTP ".$response->status());
            }

            $data = $response->json();

            return collect(is_array($data) ? $data : []);
        });
    }

    public function assignTechnician(int $ticketId, int $technicianId): array
    {
        return $this->safeWrite(function (string $sessionToken) use ($ticketId, $technicianId): array {
            $payload = ['input' => ['tickets_id' => $ticketId, 'users_id' => $technicianId, 'type' => 2]];
            $response = $this->request()->withHeader('Session-Token', $sessionToken)->post('/Ticket_User', $payload);

            if ($response->failed()) {
                throw new RuntimeException('Falha na API do GLPI ao atribuir técnico: HTTP '.$response->status().' - '.$response->body());
            }

            return ['payload' => $payload, 'response' => $response->json(), 'status' => $response->status()];
        });
    }

    public function assignGroup(int $ticketId, int $groupId): array
    {
        return $this->safeWrite(function (string $sessionToken) use ($ticketId, $groupId): array {
            $payload = ['input' => ['tickets_id' => $ticketId, 'groups_id' => $groupId, 'type' => 2]];
            $response = $this->request()->withHeader('Session-Token', $sessionToken)->post('/Group_Ticket', $payload);

            if ($response->failed()) {
                throw new RuntimeException('Falha na API do GLPI ao atribuir grupo: HTTP '.$response->status().' - '.$response->body());
            }

            return ['payload' => $payload, 'response' => $response->json(), 'status' => $response->status()];
        });
    }

    private function safeWrite(callable $callback): array
    {
        if ((bool) config('glpi-ai.dry_run', true)) {
            return ['dry_run' => true, 'skipped' => true, 'reason' => 'Dry-run ativo.'];
        }

        return $this->withSession($callback);
    }

    private function withSession(callable $callback): mixed
    {
        $sessionToken = $this->initSession();
        if (! $this->entityChanged) {
            $this->changeActiveEntities($sessionToken);
            $this->entityChanged = true;
        }

        return $callback($sessionToken);
    }

    private function closeSession(): void
    {
        if ($this->sessionToken === null) {
            return;
        }

        try {
            $this->request()->withHeader('Session-Token', $this->sessionToken)->get('/killSession');
        } catch (\Throwable) {
            // A sessao expira sozinha no GLPI; nao vale derrubar command/job no encerramento.
        } finally {
            $this->sessionToken = null;
            $this->entityChanged = false;
        }
    }

    private function initSession(): string
    {
        if ($this->sessionToken !== null) {
            return $this->sessionToken;
        }

        $response = $this->request()
            ->withHeader('Authorization', 'user_token '.config('glpi-ai.glpi_api.user_token'))
            ->get('/initSession');

        if ($response->failed() || ! $response->json('session_token')) {
            throw new RuntimeException('Falha na autenticação da API do GLPI.');
        }

        $this->sessionToken = (string) $response->json('session_token');

        return $this->sessionToken;
    }

    private function changeActiveEntities(string $sessionToken): void
    {
        $entityId = config('glpi-ai.glpi_api.entity_id', 'all');
        $response = $this->request()
            ->withHeader('Session-Token', $sessionToken)
            ->post('/changeActiveEntities', [
                'entities_id' => is_numeric($entityId) ? (int) $entityId : $entityId,
                'is_recursive' => (bool) config('glpi-ai.glpi_api.entity_recursive', true),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Falha na API do GLPI ao alterar a entidade ativa: HTTP '.$response->status());
        }
    }

    private function request(): PendingRequest
    {
        $baseUrl = config('glpi-ai.glpi_api.base_url');
        $appToken = config('glpi-ai.glpi_api.app_token');
        if (! is_string($baseUrl) || $baseUrl === '' || ! is_string($appToken) || $appToken === '') {
            throw new RuntimeException('GLPI API is not configured.');
        }

        return Http::baseUrl(rtrim($baseUrl, '/'))
            ->timeout((int) config('glpi-ai.request_timeout', 30))
            ->retry(2, 500, throw: false)
            ->withOptions(['verify' => (bool) config('glpi-ai.glpi_api.verify_ssl', true)])
            ->withHeader('App-Token', $appToken)
            ->acceptJson();
    }

    private function extractTotalFromContentRange(?string $contentRange): ?int
    {
        if (! $contentRange || ! str_contains($contentRange, '/')) {
            return null;
        }

        $total = substr(strrchr($contentRange, '/'), 1);

        return is_numeric($total) ? (int) $total : null;
    }
}

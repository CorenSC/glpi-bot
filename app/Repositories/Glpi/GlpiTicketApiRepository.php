<?php

declare(strict_types=1);

namespace App\Repositories\Glpi;

use App\Integrations\Glpi\GlpiApiClient;
use App\Models\GlpiAiTicketHistory;
use App\Services\GlpiAi\GlpiTicketNormalizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GlpiTicketApiRepository
{
    /** @var array<string, string|null> */
    private array $nameCache = [];

    /** @var array<int, int> */
    private array $workloadCache = [];

    /** @var array<int, bool> */
    private array $activeUserCache = [];

    public function __construct(private GlpiApiClient $client, private GlpiTicketNormalizer $normalizer)
    {
    }

    public function findHistoricalTicketsForImport(int $limit = 500): Collection
    {
        return $this->findHistoricalTicketsForImportPage(0, $limit)['items'];
    }

    /**
     * @return array{items: Collection<int, array<string, mixed>>, total: int|null}
     */
    public function findHistoricalTicketsForImportPage(int $offset, int $limit, bool $skipExisting = true): array
    {
        $result = $this->client->getItemsWithMeta('Ticket', [
            'range' => $offset.'-'.max($offset, $offset + $limit - 1),
            'expand_dropdowns' => false,
            'get_hateoas' => false,
            'sort' => 'date_mod',
            'order' => 'DESC',
        ]);

        $items = $result['items']
            ->filter(fn (array $ticket): bool => in_array((int) ($ticket['status'] ?? 0), (array) config('glpi-ai.historical_ticket_statuses', [5, 6]), true))
            ->values();

        $historicalCount = $items->count();

        if ($skipExisting && $items->isNotEmpty()) {
            $existingIds = GlpiAiTicketHistory::query()
                ->whereIn('glpi_ticket_id', $items->pluck('id')->map(fn ($id) => (int) $id)->filter()->all())
                ->pluck('glpi_ticket_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $items = $items->reject(fn (array $ticket): bool => in_array((int) ($ticket['id'] ?? 0), $existingIds, true))->values();
        }

        return [
            'items' => $items->map(fn (array $ticket): array => $this->hydrateTicket($ticket, true))->values(),
            'total' => $result['total'],
            'historical_count' => $historicalCount,
            'skipped_existing' => $historicalCount - $items->count(),
        ];
    }

    public function findNewUnassignedTickets(int $limit = 50): Collection
    {
        return $this->findNewTicketsInspection($limit)['items'];
    }

    /**
     * @return array{items: Collection<int, array<string, mixed>>, scanned: int, status_matched: int, assigned_filtered: int}
     */
    public function findNewTicketsInspection(int $limit = 50): array
    {
        $apiLimit = max($limit * 5, 250);
        $raw = $this->client->getItems('Ticket', [
            'range' => '0-'.max(0, $apiLimit - 1),
            'expand_dropdowns' => false,
            'get_hateoas' => false,
            'sort' => 'date',
            'order' => 'DESC',
        ]);
        $statuses = (array) config('glpi-ai.new_ticket_statuses', [1, 2]);
        $statusMatched = $raw->filter(fn (array $ticket): bool => in_array((int) ($ticket['status'] ?? 0), $statuses, true));
        $hydrated = $statusMatched->map(fn (array $ticket): array => $this->hydrateTicket($ticket, false));
        $onlyUnassigned = (bool) config('glpi-ai.only_unassigned_new_tickets', true);
        $ignoreGroup = (bool) config('glpi-ai.ignore_group_assignment_for_new_tickets', true);
        $items = $onlyUnassigned
            ? $hydrated->filter(fn (array $ticket): bool => empty($ticket['assigned_technician_id']) && ($ignoreGroup || empty($ticket['assigned_group_id'])))
            : $hydrated;

        return [
            'items' => $items->take($limit)->values(),
            'scanned' => $raw->count(),
            'status_matched' => $statusMatched->count(),
            'assigned_filtered' => $onlyUnassigned ? $hydrated->count() - $items->count() : 0,
        ];
    }

    public function findTicketById(int $glpiTicketId): ?array
    {
        try {
            $ticket = $this->client->getItem('Ticket', $glpiTicketId, [
                'expand_dropdowns' => false,
                'get_hateoas' => false,
            ]);
        } catch (Throwable $throwable) {
            Log::warning('Direct GLPI ticket read failed; falling back to Ticket list search.', [
                'glpi_ticket_id' => $glpiTicketId,
                'error' => $throwable->getMessage(),
            ]);

            $ticket = $this->findTicketByIdFromList($glpiTicketId);
        }

        return $ticket ? $this->hydrateTicket($ticket, false) : null;
    }

    public function getTechnicianCurrentWorkload(int $technicianId): int
    {
        if (array_key_exists($technicianId, $this->workloadCache)) {
            return $this->workloadCache[$technicianId];
        }

        try {
            $workload = $this->client->getItems('Ticket_User', [
                'range' => '0-200',
                'get_hateoas' => false,
            ])
                ->filter(fn (array $row): bool => (int) ($row['users_id'] ?? 0) === $technicianId && (int) ($row['type'] ?? 0) === 2)
                ->count();

            return $this->workloadCache[$technicianId] = $workload;
        } catch (Throwable $throwable) {
            Log::warning('GLPI API workload lookup failed; using neutral workload.', ['error' => $throwable->getMessage()]);

            return $this->workloadCache[$technicianId] = 0;
        }
    }

    public function isTechnicianActive(int $technicianId): bool
    {
        if (array_key_exists($technicianId, $this->activeUserCache)) {
            return $this->activeUserCache[$technicianId];
        }

        try {
            $user = $this->client->getItem('User', $technicianId, ['get_hateoas' => false]);

            return $this->activeUserCache[$technicianId] = $user === null || ! array_key_exists('is_active', $user) || (bool) $user['is_active'];
        } catch (Throwable $throwable) {
            Log::warning('GLPI API user active lookup failed; keeping candidate active for manual validation.', ['error' => $throwable->getMessage()]);

            return $this->activeUserCache[$technicianId] = true;
        }
    }

    private function hydrateTicket(array $ticket, bool $includeHistoricalDetails): array
    {
        $id = (int) ($ticket['id'] ?? $ticket['glpi_ticket_id'] ?? 0);
        $users = $this->optionalCollection(fn () => $this->client->getSubItems('Ticket', $id, 'Ticket_User', ['expand_dropdowns' => false]));
        $groups = $this->optionalCollection(fn () => $this->client->getSubItems('Ticket', $id, 'Group_Ticket', ['expand_dropdowns' => false]));
        $solutions = $includeHistoricalDetails && (bool) config('glpi-ai.hydrate_ticket_solutions', true)
            ? $this->optionalCollection(fn () => $this->client->getSubItems('Ticket', $id, 'ITILSolution'))
            : collect();
        $followups = $includeHistoricalDetails && (bool) config('glpi-ai.hydrate_ticket_followups', false)
            ? $this->optionalCollection(fn () => $this->client->getSubItems('Ticket', $id, 'ITILFollowup'))
            : collect();
        $assignedUser = $users->first(fn (array $row): bool => (int) ($row['type'] ?? 0) === 2);
        $assignedGroup = $groups->first(fn (array $row): bool => (int) ($row['type'] ?? 0) === 2) ?? $groups->first();
        $solution = $solutions->sortByDesc('id')->first();
        $title = $ticket['name'] ?? $ticket['title'] ?? null;
        $titleCategory = $this->normalizer->extractTitleCategory(is_string($title) ? $title : null);
        $categoryId = $this->normalizeId($ticket['itilcategories_id'] ?? $ticket['category_id'] ?? null);
        $assignedUserId = $this->normalizeId($assignedUser['users_id'] ?? null);
        $assignedGroupId = $this->normalizeId($assignedGroup['groups_id'] ?? null);
        $categoryName = $this->normalizeName($ticket['itilcategories_id'] ?? $ticket['category_name'] ?? null) ?? $this->resolveName('ITILCategory', $categoryId) ?? $titleCategory;

        return [
            'glpi_ticket_id' => $id,
            'title' => $title,
            'title_category' => $titleCategory,
            'original_content' => $ticket['content'] ?? $ticket['original_content'] ?? null,
            'content' => $ticket['content'] ?? $ticket['original_content'] ?? null,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'category_path' => $this->normalizeName($ticket['itilcategories_id'] ?? $ticket['category_path'] ?? null) ?? $categoryName,
            'assigned_group_id' => $assignedGroupId,
            'assigned_group_name' => $this->normalizeName($assignedGroup['groups_id'] ?? null) ?? $this->resolveName('Group', $assignedGroupId),
            'assigned_technician_id' => $assignedUserId,
            'assigned_technician_name' => $this->normalizeName($assignedUser['users_id'] ?? null) ?? $this->resolveName('User', $assignedUserId),
            'solver_technician_id' => $assignedUserId,
            'solver_technician_name' => $this->normalizeName($assignedUser['users_id'] ?? null) ?? $this->resolveName('User', $assignedUserId),
            'status' => $ticket['status'] ?? null,
            'opened_at' => $ticket['date'] ?? $ticket['opened_at'] ?? null,
            'updated_at_glpi' => $ticket['date_mod'] ?? $ticket['updated_at_glpi'] ?? null,
            'solved_at' => $ticket['solvedate'] ?? $ticket['solved_at'] ?? null,
            'closed_at' => $ticket['closedate'] ?? $ticket['closed_at'] ?? null,
            'solution_text' => $solution['content'] ?? null,
            'followup_summary' => $followups->pluck('content')->filter()->take(5)->implode("\n---\n"),
            'ticket_users' => $users->values()->all(),
            'ticket_groups' => $groups->values()->all(),
            'api_payload' => $ticket,
        ];
    }

    private function optionalCollection(callable $callback): Collection
    {
        try {
            return collect($callback());
        } catch (Throwable $throwable) {
            Log::warning('Optional GLPI API read failed; continuing with degraded data.', ['error' => $throwable->getMessage()]);

            return collect();
        }
    }

    private function findTicketByIdFromList(int $glpiTicketId): ?array
    {
        $limit = 3000;
        $batch = 200;

        for ($offset = 0; $offset < $limit; $offset += $batch) {
            $result = $this->client->getItemsWithMeta('Ticket', [
                'range' => $offset.'-'.($offset + $batch - 1),
                'expand_dropdowns' => false,
                'get_hateoas' => false,
                'sort' => 'id',
                'order' => 'DESC',
            ]);

            $ticket = $result['items']->first(fn (array $item): bool => (int) ($item['id'] ?? 0) === $glpiTicketId);
            if ($ticket) {
                return $ticket;
            }

            if ($result['total'] !== null && ($offset + $batch) >= $result['total']) {
                break;
            }
        }

        return null;
    }

    private function normalizeId(mixed $value): ?int
    {
        if (is_array($value)) {
            $value = $value['id'] ?? null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    private function normalizeName(mixed $value): ?string
    {
        if (is_array($value)) {
            return isset($value['name']) ? (string) $value['name'] : null;
        }

        return is_string($value) && ! is_numeric($value) ? $value : null;
    }

    private function resolveName(string $itemType, ?int $id): ?string
    {
        if (! $id) {
            return null;
        }

        $key = $itemType.':'.$id;
        if (array_key_exists($key, $this->nameCache)) {
            return $this->nameCache[$key];
        }

        try {
            $item = $this->client->getItem($itemType, $id, ['get_hateoas' => false]);
            $this->nameCache[$key] = isset($item['name']) ? (string) $item['name'] : null;
        } catch (Throwable $throwable) {
            Log::warning('Optional GLPI API name lookup failed.', ['item_type' => $itemType, 'id' => $id, 'error' => $throwable->getMessage()]);
            $this->nameCache[$key] = null;
        }

        return $this->nameCache[$key];
    }
}

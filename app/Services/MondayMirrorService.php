<?php

namespace App\Services;

use App\Models\MondayBoard;
use App\Models\MondayColumn;
use App\Models\MondayGroup;
use App\Models\MondayItem;
use App\Models\MondayItemValue;
use App\Models\MondayItemMovement;
use App\Models\MondaySyncRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class MondayMirrorService
{
    public function __construct(private readonly MondayApiClient $client) {}

    /**
     * Inventories boards and returns the IDs that should be synchronized.
     * Missing boards are only marked after a complete, unfiltered inventory.
     */
    public function discoverBoards(?array $onlyBoardIds = null): array
    {
        $filtered = $onlyBoardIds !== null && $onlyBoardIds !== [];
        $run = MondaySyncRun::start($filtered ? 'account_filtered' : 'account');
        $seen = [];

        try {
            if ($filtered) {
                foreach (array_chunk(array_values(array_unique(array_map('strval', $onlyBoardIds))), 100) as $ids) {
                    foreach ($this->client->boardsByIds($ids) as $board) {
                        $this->persistBoard($board, $run->token, false);
                        $seen[] = (string) $board['id'];
                    }
                }
            } else {
                $page = 1;
                do {
                    $boards = $this->client->boardsPage($page, 100);
                    foreach ($boards as $board) {
                        $this->persistBoard($board, $run->token, false);
                        $seen[] = (string) $board['id'];
                    }
                    $page++;
                } while (count($boards) === 100);

                MondayBoard::query()
                    ->whereNotIn('id', $seen ?: ['__none__'])
                    ->whereNull('remote_deleted_at')
                    ->update(['remote_deleted_at' => now()]);
            }

            $active = MondayBoard::query()
                ->whereIn('id', $seen)
                ->whereNull('remote_deleted_at')
                ->where(function ($query): void {
                    $query->whereNull('state')->orWhere('state', 'active');
                })
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all();

            $run->complete([
                'boards' => count($seen),
                'requests' => $this->client->requestCount(),
            ]);

            return $active;
        } catch (\Throwable $e) {
            $run->fail($e);
            throw $e;
        }
    }

    public function syncAccount(?array $onlyBoardIds = null): array
    {
        $result = [];
        foreach ($this->discoverBoards($onlyBoardIds) as $boardId) {
            $result[$boardId] = $this->syncBoard($boardId);
        }

        return $result;
    }

    public function syncBoard(string $boardId): array
    {
        $run = MondaySyncRun::start('board', $boardId);
        $counts = ['items' => 0, 'subitems' => 0, 'values' => 0];
        $requestsBefore = $this->client->requestCount();

        try {
            $snapshot = $this->client->boardSnapshot($boardId, $this->pageSize());
            if ($snapshot === null) {
                MondayBoard::query()->whereKey($boardId)->update([
                    'state' => 'deleted',
                    'remote_deleted_at' => now(),
                ]);
                $run->complete(['requests' => $this->client->requestCount() - $requestsBefore]);

                return $counts;
            }

            DB::transaction(function () use ($snapshot, $run, $boardId): void {
                $this->persistBoard($snapshot, $run->token, false);
                $this->persistStructure($boardId, $snapshot, $run->token, $run->started_at);
            });

            $page = (array) ($snapshot['items_page'] ?? []);
            while (true) {
                DB::transaction(function () use ($page, $boardId, $run, &$counts): void {
                    foreach ((array) ($page['items'] ?? []) as $item) {
                        $this->persistItem($boardId, (array) $item, $run->token, null, $counts, 'reconciliation');
                    }
                });

                $cursor = trim((string) ($page['cursor'] ?? ''));
                if ($cursor === '') {
                    break;
                }

                $page = $this->client->nextItemsPage($cursor, $this->pageSize());
            }

            DB::transaction(function () use ($boardId, $run): void {
                MondayItem::query()
                    ->where('board_id', $boardId)
                    ->where(function ($query) use ($run): void {
                        $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $run->started_at);
                    })
                    ->where(function ($query) use ($run): void {
                        $query->whereNull('last_seen_sync_token')
                            ->orWhere('last_seen_sync_token', '!=', $run->token);
                    })
                    ->update(['state' => 'deleted', 'deleted_at' => now()]);

                MondayBoard::query()->whereKey($boardId)->update([
                    'last_synced_at' => now(),
                    'remote_deleted_at' => null,
                ]);
            });

            $run->complete(array_merge($counts, [
                'boards' => 1,
                'requests' => $this->client->requestCount() - $requestsBefore,
            ]));

            Log::info('Monday mirror board synchronized', array_merge(['board_id' => $boardId], $counts));

            return $counts;
        } catch (\Throwable $e) {
            $run->fail($e);
            Log::error('Monday mirror board failed', [
                'board_id' => $boardId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function syncItem(
        string $itemId,
        ?string $eventBoardId = null,
        ?string $parentItemId = null,
        string $source = 'webhook'
    ): ?MondayItem
    {
        $existing = MondayItem::withTrashed()->find($itemId);
        $item = $this->client->item($itemId);

        if ($item === null) {
            // A move between boards can emit the source-board removal after
            // the target-board creation. Never let that late event erase the
            // already reconciled destination row.
            if ($eventBoardId !== null && $existing !== null && $existing->board_id !== $eventBoardId) {
                return $existing;
            }
            $this->markItemRemoved($itemId);
            return null;
        }

        $actualBoardId = (string) data_get($item, 'board.id');
        $boardId = $parentItemId !== null
            ? ($eventBoardId ?: $existing?->board_id ?: $actualBoardId)
            : ($actualBoardId ?: $eventBoardId ?: $existing?->board_id);
        if ($boardId === '') {
            throw new RuntimeException("Monday no indicó el tablero del item {$itemId}.");
        }

        MondayBoard::query()->updateOrCreate(
            ['id' => $boardId],
            ['name' => data_get($item, 'board.name'), 'last_seen_at' => now(), 'remote_deleted_at' => null]
        );

        $counts = ['items' => 0, 'subitems' => 0, 'values' => 0];
        $token = (string) Str::uuid();
        DB::transaction(fn () => $this->persistItem($boardId, $item, $token, $parentItemId, $counts, $source));

        return MondayItem::query()->find($itemId);
    }

    public function markItemRemoved(string $itemId): void
    {
        MondayItem::query()->whereKey($itemId)->update([
            'state' => 'deleted',
            'deleted_at' => now(),
            'last_synced_at' => now(),
        ]);
    }

    private function persistBoard(array $data, string $token, bool $fullySynced): MondayBoard
    {
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            throw new RuntimeException('Monday devolvió un tablero sin ID.');
        }

        return MondayBoard::query()->updateOrCreate(['id' => $id], [
            'name' => $data['name'] ?? null,
            'state' => $data['state'] ?? null,
            'kind' => $data['board_kind'] ?? null,
            'permissions' => $data['permissions'] ?? null,
            'workspace_id' => data_get($data, 'workspace.id'),
            'workspace_name' => data_get($data, 'workspace.name'),
            'items_count' => isset($data['items_count']) ? (int) $data['items_count'] : null,
            'raw_data' => Arr::except($data, ['groups', 'columns', 'items_page']),
            'last_seen_sync_token' => $token,
            'last_seen_at' => now(),
            'last_synced_at' => $fullySynced ? now() : MondayBoard::find($id)?->last_synced_at,
            'remote_deleted_at' => null,
        ]);
    }

    private function persistStructure(string $boardId, array $data, string $token, Carbon $startedAt): void
    {
        foreach ((array) ($data['groups'] ?? []) as $group) {
            MondayGroup::withTrashed()->updateOrCreate([
                'board_id' => $boardId,
                'monday_id' => (string) $group['id'],
            ], [
                'title' => $group['title'] ?? null,
                'color' => $group['color'] ?? null,
                'position' => isset($group['position']) ? (string) $group['position'] : null,
                'raw_data' => $group,
                'last_seen_sync_token' => $token,
                'last_synced_at' => now(),
                'deleted_at' => null,
            ]);
        }

        MondayGroup::query()
            ->where('board_id', $boardId)
            ->where(function ($query) use ($startedAt): void {
                $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $startedAt);
            })
            ->where(function ($query) use ($token): void {
                $query->whereNull('last_seen_sync_token')->orWhere('last_seen_sync_token', '!=', $token);
            })
            ->delete();

        foreach ((array) ($data['columns'] ?? []) as $column) {
            MondayColumn::withTrashed()->updateOrCreate([
                'board_id' => $boardId,
                'monday_id' => (string) $column['id'],
            ], [
                'title' => $column['title'] ?? null,
                'type' => $column['type'] ?? null,
                'description' => $column['description'] ?? null,
                'settings' => $this->decodeJson($column['settings_str'] ?? null),
                'raw_data' => $column,
                'last_seen_sync_token' => $token,
                'last_synced_at' => now(),
                'deleted_at' => null,
            ]);
        }

        MondayColumn::query()
            ->where('board_id', $boardId)
            ->where(function ($query) use ($startedAt): void {
                $query->whereNull('last_synced_at')->orWhere('last_synced_at', '<', $startedAt);
            })
            ->where(function ($query) use ($token): void {
                $query->whereNull('last_seen_sync_token')->orWhere('last_seen_sync_token', '!=', $token);
            })
            ->delete();
    }

    private function persistItem(
        string $rootBoardId,
        array $data,
        string $token,
        ?string $parentItemId,
        array &$counts,
        string $source
    ): void {
        $id = (string) ($data['id'] ?? '');
        if ($id === '') {
            return;
        }

        $state = strtolower((string) ($data['state'] ?? 'active'));
        $removed = in_array($state, ['archived', 'deleted'], true);
        $existing = MondayItem::withTrashed()->find($id);
        $newGroupId = data_get($data, 'group.id');
        $locationChanged = $existing !== null && (
            (string) $existing->board_id !== $rootBoardId
            || (string) ($existing->group_id ?? '') !== (string) ($newGroupId ?? '')
            || (string) ($existing->parent_item_id ?? '') !== (string) ($parentItemId ?? '')
        );

        MondayItem::withTrashed()->updateOrCreate(['id' => $id], [
            'board_id' => $rootBoardId,
            'source_board_id' => (string) (data_get($data, 'board.id') ?: $rootBoardId),
            'group_id' => $newGroupId,
            'parent_item_id' => $parentItemId,
            'name' => $data['name'] ?? null,
            'state' => $state,
            'creator_id' => isset($data['creator_id']) ? (string) $data['creator_id'] : null,
            'relative_link' => $data['relative_link'] ?? null,
            'monday_created_at' => $this->date($data['created_at'] ?? null),
            'monday_updated_at' => $this->date($data['updated_at'] ?? null),
            'raw_data' => $data,
            'last_seen_sync_token' => $token,
            'last_synced_at' => now(),
            'deleted_at' => $removed ? now() : null,
        ]);

        if ($locationChanged) {
            MondayItemMovement::create([
                'item_id' => $id,
                'from_board_id' => $existing->board_id,
                'to_board_id' => $rootBoardId,
                'from_group_id' => $existing->group_id,
                'to_group_id' => $newGroupId,
                'from_parent_item_id' => $existing->parent_item_id,
                'to_parent_item_id' => $parentItemId,
                'source' => $source,
                'moved_at' => now(),
            ]);
        }

        if ($parentItemId === null) {
            $counts['items']++;
        } else {
            $counts['subitems']++;
        }

        $seenColumns = [];
        foreach ((array) ($data['column_values'] ?? []) as $value) {
            $columnId = (string) ($value['id'] ?? '');
            if ($columnId === '') {
                continue;
            }
            $seenColumns[] = $columnId;
            MondayItemValue::query()->updateOrCreate([
                'item_id' => $id,
                'monday_column_id' => $columnId,
            ], [
                'board_id' => $rootBoardId,
                'type' => $value['type'] ?? null,
                'text' => $value['text'] ?? null,
                'value' => $this->decodeJson($value['value'] ?? null),
                'raw_data' => $value,
                'last_seen_sync_token' => $token,
                'last_synced_at' => now(),
            ]);
            $counts['values']++;
        }

        $staleValues = MondayItemValue::query()->where('item_id', $id);
        if ($seenColumns !== []) {
            $staleValues->whereNotIn('monday_column_id', $seenColumns);
        }
        $staleValues->delete();

        foreach ((array) ($data['subitems'] ?? []) as $subitem) {
            $this->persistItem($rootBoardId, (array) $subitem, $token, $id, $counts, $source);
        }

    }

    private function decodeJson(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function pageSize(): int
    {
        return max(1, min(500, (int) config('services.monday.mirror_page_size', 100)));
    }
}

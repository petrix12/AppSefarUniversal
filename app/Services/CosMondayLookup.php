<?php

namespace App\Services;

use App\Models\MondayBoard;
use App\Models\MondayColumn;
use App\Models\MondayItem;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class CosMondayLookup
{
    /** Null means incomplete/unavailable replica; an item=null result confirms absence. */
    public function localFor(User $user): ?array
    {
        if (! (bool) config('services.monday.mirror_reads', true)) return null;
        foreach (['monday_boards', 'monday_items', 'monday_item_values', 'monday_columns'] as $table) {
            if (! Schema::hasTable($table)) return null;
        }

        if (filled($user->monday_id)) {
            $item = MondayItem::withTrashed()->find((string) $user->monday_id, ['id', 'board_id', 'name', 'state', 'deleted_at', 'last_synced_at']);
            $board = $item ? MondayBoard::find($item->board_id) : null;
            if (! $board || (! $board->last_synced_at && ! $item->last_synced_at)) return null;
            $active = ! $item->trashed() && in_array($item->state, [null, 'active'], true)
                && ! $board->remote_deleted_at && in_array($board->state, [null, 'active'], true);
            return [
                'item' => $active ? $this->itemPayload($item, $board) : null,
                'synced_at' => ($item->last_synced_at ?? $board->last_synced_at)?->toIso8601String(),
            ];
        }

        $ids = array_keys(config('cos_snapshot.monday_search_boards', []));
        if (blank($user->passport) || $ids === [] || ! $this->mirrorReady($ids)) return null;
        $boards = MondayBoard::whereIn('id', $ids)->get(['id', 'last_synced_at']);
        return [
            'item' => $this->findLocally(trim($user->passport), $ids),
            'synced_at' => $boards->min('last_synced_at')?->toIso8601String(),
        ];
    }

    public function find(?string $passport, User $user, bool $forceRefresh = false): ?array
    {
        $passport = trim((string) $passport);
        $ids = array_keys(config('cos_snapshot.monday_search_boards', []));
        if ($passport === '' || $ids === []) {
            return null;
        }

        $mirrorReady = $this->mirrorReady($ids);

        $key = 'cos.monday.lookup.' . hash('sha256', json_encode([$user->id, $passport, $ids]));
        // Wrap null so a successful search with no matches is cached too.
        $search = function () use ($passport, $ids, $mirrorReady) {
            if ($mirrorReady) {
                return ['item' => $this->findLocally($passport, $ids)];
            }

            $token = config('services.monday.token');
            if (blank($token)) {
                throw new RuntimeException('Falta configurar MONDAY_TOKEN.');
            }

            $url = json_encode('https://app.sefaruniversal.com/tree/' . $passport, JSON_THROW_ON_ERROR);
            $fields = [];
            foreach ($ids as $index => $id) {
                if (! ctype_digit((string) $id)) {
                    throw new RuntimeException('ID de tablero COS inválido.');
                }
                $fields[] = "b{$index}: items_page_by_column_values(limit: 1, board_id: {$id}, columns: [{column_id: \"enlace\", column_values: [{$url}]}]) { items { id name board { id name } column_values { id text value column { title type } } } }";
            }

            $started = microtime(true);
            $data = ['data' => []];
            // Bounded concurrency: never start more than 12 requests at once.
            foreach (array_chunk($fields, 12) as $chunk) {
                $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($chunk, $token) {
                    return array_map(fn ($field) => $pool->withToken($token)
                        ->withHeaders(['API-Version' => (string) config('services.monday.api_version', '2026-10')])
                        ->connectTimeout(5)->timeout(20)
                        ->post('https://api.monday.com/v2', ['query' => 'query { ' . $field . ' }']), $chunk);
                });
                foreach ($responses as $response) {
                    if ($response instanceof \Throwable) {
                        throw new RuntimeException('Monday no completó la búsqueda COS.', 0, $response);
                    }
                    $response->throw();
                    $part = $response->json();
                    if (! empty($part['errors']) || ! is_array($part['data'] ?? null)) {
                        throw new RuntimeException('Monday no completó la búsqueda COS; no se cachea como cliente ausente.');
                    }
                    $data['data'] = array_replace($data['data'], $part['data']);
                }
            }
            foreach ($ids as $index => $id) {
                if (! is_array($data['data']["b{$index}"]['items'] ?? null)) {
                    throw new RuntimeException('Monday devolvió una búsqueda COS incompleta.');
                }
            }
            Log::info('COS búsqueda Monday', [
                'boards' => count($ids), 'http_requests' => count($fields), 'mode' => 'parallel',
                'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);

            // Keep the configured priority regardless of response key order.
            foreach ($ids as $index => $id) {
                $item = $data['data']["b{$index}"]['items'][0] ?? null;
                if (! empty($item['id'])) {
                    return ['item' => $item];
                }
            }

            return ['item' => null];
        };

        if ($forceRefresh) {
            $result = $search();
            Cache::put($key, $result, 60);
        } else {
            $result = Cache::remember($key, 60, $search);
        }

        if ($item = $result['item']) {
            $user->monday_id = $item['id'];
            $user->save();
        }

        return $item;
    }

    private function mirrorReady(array $boardIds): bool
    {
        if (! (bool) config('services.monday.mirror_reads', true) || ! Schema::hasTable('monday_boards')) {
            return false;
        }

        return MondayBoard::available()
            ->whereIn('id', $boardIds)
            ->whereNotNull('last_synced_at')
            ->count() === count(array_unique($boardIds));
    }

    private function findLocally(string $passport, array $boardIds): ?array
    {
        $url = 'https://app.sefaruniversal.com/tree/'.$passport;

        foreach ($boardIds as $boardId) {
            $candidates = MondayItem::query()
                ->where('board_id', (string) $boardId)
                ->whereHas('values', function ($query) use ($url): void {
                    $query->where('monday_column_id', 'enlace')
                        ->where('text', 'like', '%'.$url.'%');
                })
                ->where(fn ($query) => $query->whereNull('state')->orWhere('state', 'active'))
                ->orderBy('id')
                ->select(['id', 'board_id', 'name'])
                ->cursor();

            foreach ($candidates as $item) {
                // A passport prefix (ABC versus ABC123) is not the same client.
                $links = $item->values()->where('monday_column_id', 'enlace')->pluck('text');
                if ($links->contains(fn ($text) => preg_match('~'.preg_quote($url, '~').'/?(?=$|[?#\s"<>),])~u', (string) $text))) {
                    return $this->itemPayload($item, MondayBoard::find((string) $boardId));
                }
            }
        }

        return null;
    }

    private function itemPayload(MondayItem $item, MondayBoard $board): array
    {
        $item->load(['values' => fn ($query) => $query->select(['id', 'item_id', 'monday_column_id', 'type', 'text', 'value'])]);
        $columns = MondayColumn::where('board_id', $board->id)->get(['monday_id', 'title', 'type'])->keyBy('monday_id');
        return [
            'id' => (string) $item->id,
            'name' => (string) $item->name,
            'board' => ['id' => (string) $board->id, 'name' => (string) ($board->name ?? '')],
            'column_values' => $item->values->map(function ($value) use ($columns): array {
                $column = $columns->get($value->monday_column_id);
                return [
                    'id' => (string) $value->monday_column_id,
                    'text' => $value->text,
                    'value' => $value->value,
                    'column' => [
                        'title' => (string) ($column?->title ?? $value->monday_column_id),
                        'type' => (string) ($column?->type ?? $value->type),
                    ],
                ];
            })->values()->all(),
        ];
    }
}

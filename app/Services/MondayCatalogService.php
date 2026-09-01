<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MondayCatalogService
{
    private const API_URL = 'https://api.monday.com/v2';

    public function boards(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget('monday.catalog.boards');
        }

        return Cache::remember('monday.catalog.boards', now()->addMinutes(10), function () use ($refresh): array {
            $boards = collect();
            $page = 1;

            do {
                $pageBoards = collect($this->boardPage($page, $refresh));
                $boards = $boards->concat($pageBoards);
                $page++;
            } while ($pageBoards->count() === 100 && $page <= 100);

            return $boards
                ->map(fn (array $board): array => [
                    'id' => (string) $board['id'],
                    'name' => (string) $board['name'],
                ])
                ->unique('id')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        });
    }

    public function groups(string $boardId): array
    {
        return Cache::remember("monday.catalog.board.{$boardId}.groups", now()->addMinutes(10), function () use ($boardId): array {
            $data = $this->query(<<<'GRAPHQL'
                query ($boardId: ID!) {
                    boards(ids: [$boardId]) {
                        groups {
                            id
                            title
                        }
                    }
                }
                GRAPHQL, ['boardId' => $boardId]);

            if (empty(data_get($data, 'boards.0'))) {
                throw new RuntimeException('Monday no devolvió el tablero solicitado.');
            }

            return collect(data_get($data, 'boards.0.groups', []))
                ->map(fn (array $group): array => [
                    'id' => (string) $group['id'],
                    'name' => (string) $group['title'],
                ])
                ->values()
                ->all();
        });
    }

    public function columns(string $boardId, bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget("monday.catalog.board.{$boardId}.columns");
        }

        return Cache::remember("monday.catalog.board.{$boardId}.columns", now()->addMinutes(10), function () use ($boardId): array {
            $data = $this->query(<<<'GRAPHQL'
                query ($boardId: ID!) {
                    boards(ids: [$boardId]) {
                        columns {
                            id
                            title
                            type
                        }
                    }
                }
                GRAPHQL, ['boardId' => $boardId]);

            if (empty(data_get($data, 'boards.0'))) {
                throw new RuntimeException('Monday no devolvió el tablero solicitado.');
            }

            return collect(data_get($data, 'boards.0.columns', []))
                ->map(fn (array $column): array => [
                    'id' => (string) $column['id'],
                    'name' => (string) $column['title'],
                    'type' => (string) $column['type'],
                ])
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        });
    }

    /**
     * Returns one bounded page so large Monday accounts can be inventoried by
     * queued jobs without keeping a browser request open.
     */
    public function boardPage(int $page, bool $refresh = false): array
    {
        $page = max(1, min(100, $page));
        $cacheKey = "monday.catalog.boards.page.{$page}";
        if ($refresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($page): array {
            $data = $this->query(<<<'GRAPHQL'
                query ($page: Int!) {
                    boards(limit: 100, page: $page) {
                        id
                        name
                    }
                }
                GRAPHQL, ['page' => $page]);

            return collect(data_get($data, 'boards', []))
                ->map(fn (array $board): array => [
                    'id' => (string) $board['id'],
                    'name' => (string) $board['name'],
                ])
                ->unique('id')
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->all();
        });
    }

    private function query(string $query, array $variables = []): array
    {
        $token = config('services.monday.token');

        if (blank($token)) {
            throw new RuntimeException('Falta configurar MONDAY_TOKEN.');
        }

        $response = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/json',
        ])->timeout(20)->post(self::API_URL, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $data = $response->json();

        if (! empty($data['errors'])) {
            $messages = collect($data['errors'])
                ->pluck('message')
                ->filter()
                ->implode(' | ');

            throw new RuntimeException(
                "Monday rechazó la consulta (HTTP {$response->status()}): ".($messages ?: 'error GraphQL sin detalle.')
            );
        }

        if (! $response->successful()) {
            $message = data_get($data, 'error_message')
                ?? data_get($data, 'message')
                ?? 'respuesta sin detalle.';

            throw new RuntimeException("Monday respondió HTTP {$response->status()}: {$message}");
        }

        return (array) data_get($data, 'data', []);
    }
}

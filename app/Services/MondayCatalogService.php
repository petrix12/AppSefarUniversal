<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MondayCatalogService
{
    private const API_URL = 'https://api.monday.com/v2';

    public function boards(): array
    {
        return Cache::remember('monday.catalog.boards', now()->addMinutes(10), function (): array {
            $data = $this->query(<<<'GRAPHQL'
                query {
                    boards(limit: 1000) {
                        id
                        name
                    }
                }
                GRAPHQL);

            return collect(data_get($data, 'boards', []))
                ->map(fn (array $board): array => [
                    'id' => (string) $board['id'],
                    'name' => (string) $board['name'],
                ])
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

        if (! $response->successful()) {
            throw new RuntimeException("Monday respondió HTTP {$response->status()}.");
        }

        if (! empty($data['errors'])) {
            throw new RuntimeException((string) data_get($data, 'errors.0.message', 'Monday devolvió un error.'));
        }

        return (array) data_get($data, 'data', []);
    }
}

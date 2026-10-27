<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MondayApiClient
{
    private const API_URL = 'https://api.monday.com/v2';

    private int $requestCount = 0;

    public function requestCount(): int
    {
        return $this->requestCount;
    }

    public function boardsPage(int $page, int $limit = 100): array
    {
        $data = $this->query(<<<'GRAPHQL'
            query MirrorBoards($page: Int!, $limit: Int!) {
              boards(page: $page, limit: $limit, state: all) {
                id
                name
                state
                board_kind
                permissions
                items_count
                workspace { id name }
              }
            }
            GRAPHQL, [
                'page' => max(1, $page),
                'limit' => max(1, min(100, $limit)),
            ]);

        return (array) ($data['boards'] ?? []);
    }

    public function boardsByIds(array $boardIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $boardIds))));
        if ($ids === []) {
            return [];
        }

        $data = $this->query(<<<'GRAPHQL'
            query MirrorBoardsByIds($boardIds: [ID!]!) {
              boards(ids: $boardIds) {
                id
                name
                state
                board_kind
                permissions
                items_count
                workspace { id name }
              }
            }
            GRAPHQL, ['boardIds' => $ids]);

        return (array) ($data['boards'] ?? []);
    }

    public function boardSnapshot(string $boardId, int $limit = 100): ?array
    {
        $fields = $this->itemFields(true);
        $query = <<<GRAPHQL
            query MirrorBoard(\$boardId: ID!, \$limit: Int!) {
              boards(ids: [\$boardId]) {
                id
                name
                state
                board_kind
                permissions
                items_count
                workspace { id name }
                groups { id title color position }
                columns { id title type description settings_str }
                items_page(limit: \$limit) {
                  cursor
                  items { {$fields} }
                }
              }
            }
            GRAPHQL;

        $data = $this->query($query, [
            'boardId' => $boardId,
            'limit' => max(1, min(500, $limit)),
        ]);

        return isset($data['boards'][0]) ? (array) $data['boards'][0] : null;
    }

    public function nextItemsPage(string $cursor, int $limit = 100): array
    {
        $fields = $this->itemFields(true);
        $query = <<<GRAPHQL
            query MirrorNextItems(\$cursor: String!, \$limit: Int!) {
              next_items_page(cursor: \$cursor, limit: \$limit) {
                cursor
                items { {$fields} }
              }
            }
            GRAPHQL;

        $data = $this->query($query, [
            'cursor' => $cursor,
            'limit' => max(1, min(500, $limit)),
        ]);

        return (array) ($data['next_items_page'] ?? []);
    }

    public function item(string $itemId): ?array
    {
        $fields = $this->itemFields(true);
        $query = <<<GRAPHQL
            query MirrorItem(\$itemId: ID!) {
              items(ids: [\$itemId]) { {$fields} }
            }
            GRAPHQL;
        $data = $this->query($query, ['itemId' => $itemId]);

        return isset($data['items'][0]) ? (array) $data['items'][0] : null;
    }

    public function webhooks(string $boardId): array
    {
        $data = $this->query(<<<'GRAPHQL'
            query MirrorWebhooks($boardId: ID!) {
              webhooks(board_id: $boardId) { id board_id event config }
            }
            GRAPHQL, ['boardId' => $boardId]);

        return (array) ($data['webhooks'] ?? []);
    }

    public function createWebhook(string $boardId, string $url, string $event): array
    {
        $data = $this->query(<<<'GRAPHQL'
            mutation MirrorCreateWebhook($boardId: ID!, $url: String!, $event: WebhookEventType!) {
              create_webhook(board_id: $boardId, url: $url, event: $event) {
                id
                board_id
              }
            }
            GRAPHQL, [
                'boardId' => $boardId,
                'url' => $url,
                'event' => $event,
            ]);

        return (array) ($data['create_webhook'] ?? []);
    }

    public function query(string $query, array $variables = []): array
    {
        $token = trim((string) config('services.monday.token'));
        if ($token === '') {
            throw new RuntimeException('Falta configurar MONDAY_TOKEN.');
        }

        $this->requestCount++;
        $response = Http::withHeaders([
            'Authorization' => $token,
            'Content-Type' => 'application/json',
            'API-Version' => (string) config('services.monday.api_version', '2026-10'),
        ])
            ->connectTimeout((int) config('services.monday.connect_timeout', 10))
            ->timeout((int) config('services.monday.timeout', 45))
            ->retry([500, 1500, 3000], 0, function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if (! $exception instanceof RequestException) {
                    return false;
                }

                $status = $exception->response->status();

                return $status === 429 || $status >= 500;
            }, false)
            ->post(self::API_URL, [
                'query' => $query,
                'variables' => $variables,
            ]);

        return $this->validatedData($response);
    }

    private function validatedData(Response $response): array
    {
        $body = $response->json();

        if (! is_array($body)) {
            throw new RuntimeException("Monday respondió HTTP {$response->status()} sin JSON válido.");
        }

        if (! empty($body['errors'])) {
            $message = collect($body['errors'])
                ->pluck('message')
                ->filter()
                ->implode(' | ');

            throw new RuntimeException(
                "Monday rechazó la consulta (HTTP {$response->status()}): ".($message ?: 'error GraphQL sin detalle.')
            );
        }

        if (! $response->successful()) {
            $message = data_get($body, 'error_message') ?? data_get($body, 'message') ?? 'respuesta sin detalle.';
            throw new RuntimeException("Monday respondió HTTP {$response->status()}: {$message}");
        }

        if (! is_array($body['data'] ?? null)) {
            throw new RuntimeException('Monday devolvió una respuesta incompleta sin data.');
        }

        return $body['data'];
    }

    private function itemFields(bool $withSubitems): string
    {
        $base = <<<'GRAPHQL'
            id
            name
            state
            creator_id
            created_at
            updated_at
            relative_link
            board { id name }
            group { id title }
            column_values { id type text value }
            GRAPHQL;

        if (! $withSubitems) {
            return $base;
        }

        return $base.' subitems { '.$this->itemFields(false).' }';
    }
}

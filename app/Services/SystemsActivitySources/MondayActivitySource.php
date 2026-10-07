<?php

namespace App\Services\SystemsActivitySources;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MondayActivitySource implements SystemsActivitySource
{
    private const API_URL = 'https://api.monday.com/v2';

    public function collect(string $email, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $targetUser = collect($this->users())
            ->first(fn (array $user): bool => strtolower((string) ($user['email'] ?? '')) === strtolower($email));

        if ($targetUser === null) {
            return [];
        }

        $events = [];
        foreach ($this->boards() as $board) {
            $logs = $this->query(
                <<<'GRAPHQL'
                query ActivityForBoard($boardIds: [ID!]!, $userIds: [ID!], $from: ISO8601DateTime!, $to: ISO8601DateTime!) {
                  boards(ids: $boardIds) {
                    id
                    activity_logs(from: $from, to: $to, user_ids: $userIds, limit: 10000, page: 1) {
                      event
                      user_id
                      created_at
                    }
                  }
                }
                GRAPHQL,
                [
                    'boardIds' => [(string) $board['id']],
                    'userIds' => [(string) $targetUser['id']],
                    'from' => $from->utc()->toIso8601String(),
                    'to' => $to->utc()->toIso8601String(),
                ]
            );

            foreach (($logs[0]['activity_logs'] ?? []) as $log) {
                if ((string) ($log['user_id'] ?? '') !== (string) $targetUser['id']) {
                    continue;
                }

                $timestamp = $this->timestamp($log['created_at'] ?? null);
                if ($timestamp === null || ! $timestamp->greaterThanOrEqualTo($from) || ! $timestamp->lessThan($to)) {
                    continue;
                }

                $event = strtolower((string) ($log['event'] ?? ''));
                $events[] = [
                    'source' => 'monday.com',
                    'occurred_at' => $timestamp->setTimezone('America/Caracas')->toIso8601String(),
                    'kind' => $event ?: 'board_activity',
                    'title' => $this->eventLabel($event),
                    'detail' => '',
                ];
            }
        }

        return $events;
    }

    public function findUser(string $email): ?array
    {
        return collect($this->users())->first(fn (array $user): bool =>
            strtolower((string) ($user['email'] ?? '')) === strtolower($email)
        );
    }

    private function users(): array
    {
        return Cache::remember('systems_activity_report:monday_users', now()->addMinutes(30), function (): array {
            return $this->query('query { users { id name email } }');
        });
    }

    private function boards(): array
    {
        return Cache::remember('systems_activity_report:monday_boards', now()->addMinutes(30), function (): array {
            $boards = [];
            $page = 1;
            $size = 500;

            do {
                $pageBoards = $this->query(
                    'query BoardsPage($page: Int!, $limit: Int!) { boards(page: $page, limit: $limit) { id name } }',
                    ['page' => $page, 'limit' => $size]
                );
                $boards = array_merge($boards, $pageBoards);
                $page++;
            } while (count($pageBoards) === $size);

            return $boards;
        });
    }

    private function query(string $query, array $variables = []): array
    {
        $token = (string) config('services.monday.token');
        if ($token === '') {
            throw new RuntimeException('MONDAY_TOKEN is not configured.');
        }

        $response = Http::withToken($token)
            ->withHeaders(['API-Version' => '2026-07'])
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->post(self::API_URL, ['query' => $query, 'variables' => $variables]);

        if (! $response->successful()) {
            throw new RuntimeException('monday.com activity query failed with HTTP '.$response->status().'.');
        }

        $body = $response->json();
        if (! empty($body['errors'])) {
            throw new RuntimeException('monday.com activity query returned GraphQL errors.');
        }

        return $body['data']['boards'] ?? $body['data']['users'] ?? [];
    }

    private function timestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;
        $seconds = $number > 1000000000000000 ? $number / 10000000 : $number / 1000;

        return CarbonImmutable::createFromTimestampUTC($seconds);
    }

    private function eventLabel(string $event): string
    {
        return match ($event) {
            'create_pulse' => 'Creó un elemento de trabajo',
            'update_column_value' => 'Actualizó un campo de trabajo',
            'delete_pulse' => 'Eliminó un elemento de trabajo',
            'archive_pulse' => 'Archivó un elemento de trabajo',
            'restore_pulse' => 'Restauró un elemento de trabajo',
            'create_update' => 'Añadió una actualización de trabajo',
            default => 'Acción de tablero: '.str_replace('_', ' ', $event ?: 'actividad'),
        };
    }
}

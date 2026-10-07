<?php

namespace App\Services\SystemsActivitySources;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HubSpotActivitySource implements SystemsActivitySource
{
    private const API_URL = 'https://api.hubapi.com';

    public function collect(string $email, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $owner = collect($this->owners())->first(fn (array $owner): bool =>
            strtolower((string) ($owner['email'] ?? '')) === strtolower($email)
        );

        if ($owner === null || empty($owner['userId'])) {
            return [];
        }

        $events = [];
        foreach (['tasks', 'calls', 'meetings', 'notes'] as $objectType) {
            $after = null;
            do {
                $payload = [
                    'filterGroups' => [[
                        'filters' => [
                            ['propertyName' => 'hs_created_by_user_id', 'operator' => 'EQ', 'value' => (string) $owner['userId']],
                            ['propertyName' => 'hs_timestamp', 'operator' => 'GTE', 'value' => (string) ($from->utc()->getTimestamp() * 1000)],
                            ['propertyName' => 'hs_timestamp', 'operator' => 'LT', 'value' => (string) ($to->utc()->getTimestamp() * 1000)],
                        ],
                    ]],
                    'properties' => ['hs_timestamp'],
                    'limit' => 100,
                ];
                if ($after !== null) {
                    $payload['after'] = $after;
                }

                $response = Http::withToken($this->apiKey())
                    ->acceptJson()->asJson()->timeout(30)
                    ->post(self::API_URL.'/crm/v3/objects/'.$objectType.'/search', $payload);

                if (! $response->successful()) {
                    throw new RuntimeException('HubSpot activity query failed with HTTP '.$response->status().'.');
                }

                $body = $response->json();
                foreach (($body['results'] ?? []) as $item) {
                    $properties = (array) ($item['properties'] ?? []);
                    $timestamp = $properties['hs_timestamp'] ?? null;
                    if (! is_numeric($timestamp)) {
                        continue;
                    }
                    $occurredAt = CarbonImmutable::createFromTimestampUTC(((int) $timestamp) / 1000);
                    if (! $occurredAt->greaterThanOrEqualTo($from->utc()) || ! $occurredAt->lessThan($to->utc())) {
                        continue;
                    }

                    // Only generic activity types/statuses leave HubSpot. Notes and
                    // client-facing subjects can contain personal or confidential data.
                    $events[] = [
                        'source' => 'HubSpot',
                        'occurred_at' => $occurredAt->setTimezone('America/Caracas')->toIso8601String(),
                        'kind' => $objectType,
                        'title' => match ($objectType) {
                            'tasks' => 'Actividad de tarea registrada',
                            'calls' => 'Actividad de llamada registrada',
                            'meetings' => 'Actividad de reunión registrada',
                            default => 'Nota registrada (contenido excluido)',
                        },
                        'detail' => '',
                    ];
                }

                $after = data_get($body, 'paging.next.after');
            } while ($after !== null);
        }

        return $events;
    }

    public function findUser(string $email): ?array
    {
        return collect($this->owners())->first(fn (array $owner): bool =>
            strtolower((string) ($owner['email'] ?? '')) === strtolower($email)
        );
    }

    private function owners(): array
    {
        return Cache::remember('systems_activity_report:hubspot_owners', now()->addMinutes(30), function (): array {
            $owners = [];
            $after = null;
            do {
                $query = ['limit' => 100, 'archived' => 'false'];
                if ($after !== null) {
                    $query['after'] = $after;
                }
                $response = Http::withToken($this->apiKey())->acceptJson()->timeout(30)
                    ->get(self::API_URL.'/crm/v3/owners', $query);
                if (! $response->successful()) {
                    throw new RuntimeException('HubSpot users query failed with HTTP '.$response->status().'.');
                }
                $body = $response->json();
                $owners = array_merge($owners, $body['results'] ?? []);
                $after = data_get($body, 'paging.next.after');
            } while ($after !== null);

            return $owners;
        });
    }

    private function apiKey(): string
    {
        $key = (string) config('services.hubspot.key');
        if ($key === '') {
            throw new RuntimeException('HUBSPOT_KEY is not configured.');
        }
        return $key;
    }

}

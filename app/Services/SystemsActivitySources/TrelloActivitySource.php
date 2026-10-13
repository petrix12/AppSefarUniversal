<?php

namespace App\Services\SystemsActivitySources;

use App\Models\User;
use App\Services\TrelloOAuthService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TrelloActivitySource implements SystemsActivitySource
{
    private const API_URL = 'https://api.trello.com/1';
    private const OAUTH_API_URL = 'https://trello.com/1';

    public function __construct(private TrelloOAuthService $oauth)
    {
    }

    /** @return array<int, array{id:string,name:string}> */
    public function availableLists(): array
    {
        $board = (string) config('systems_activity_report.trello.board_shortlink');
        $response = $this->oauthGet('/boards/'.rawurlencode($board).'/lists', [
            'filter' => 'open',
            'fields' => 'id,name,closed',
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Trello list lookup failed with HTTP '.$response->status().'.');
        }

        return collect((array) $response->json())
            ->filter(fn (array $list): bool => empty($list['closed']))
            ->map(fn (array $list): array => ['id' => (string) $list['id'], 'name' => (string) $list['name']])
            ->values()
            ->all();
    }

    /** @return array{name:string,cards:array<int,array{name:string,description:string}>} */
    public function cardsInList(string $listId): array
    {
        $board = (string) config('systems_activity_report.trello.board_shortlink');

        // Confirm the submitted list belongs to the configured board before reading it.
        $listResponse = $this->oauthGet('/lists/'.rawurlencode($listId), [
            'fields' => 'id,name,idBoard,closed',
        ]);
        if (! $listResponse->successful()) {
            throw new RuntimeException('Trello list lookup failed with HTTP '.$listResponse->status().'.');
        }
        $list = (array) $listResponse->json();
        $boardResponse = $this->oauthGet('/boards/'.rawurlencode($board), ['fields' => 'id']);
        if (! $boardResponse->successful() || (string) data_get($list, 'idBoard') !== (string) $boardResponse->json('id')) {
            throw new RuntimeException('The selected Trello list does not belong to the configured board.');
        }

        $cardsResponse = $this->oauthGet('/lists/'.rawurlencode($listId).'/cards', [
            'filter' => 'open',
            'fields' => 'id,name,desc',
        ]);
        if (! $cardsResponse->successful()) {
            throw new RuntimeException('Trello card lookup failed with HTTP '.$cardsResponse->status().'.');
        }

        return [
            'name' => (string) ($list['name'] ?? 'Columna de Trello'),
            'cards' => collect((array) $cardsResponse->json())
                ->map(fn (array $card): array => [
                    'name' => trim((string) ($card['name'] ?? 'Tarjeta sin título')),
                    'description' => trim((string) ($card['desc'] ?? '')),
                ])
                ->values()
                ->all(),
        ];
    }

    private function oauthGet(string $path, array $query = []): \Illuminate\Http\Client\Response
    {
        $board = trim((string) config('systems_activity_report.trello.board_shortlink', ''));
        if ($board === '') {
            throw new RuntimeException('Configura TRELLO_SYSTEMS_BOARD_SHORTLINK en el archivo .env.');
        }

        return Http::withToken($this->oauth->accessToken())
            ->acceptJson()
            ->timeout(30)
            ->get(self::OAUTH_API_URL.$path, $query);
    }

    public function collect(string $email, CarbonImmutable $from, CarbonImmutable $to): array
    {
        if ($this->oauth->isConfigured() && $this->oauth->isConnected()) {
            return $this->collectWithOAuth($email, $from, $to);
        }

        $trello = (array) config('systems_activity_report.trello');
        $key = (string) ($trello['key'] ?? '');
        $token = (string) ($trello['token'] ?? '');
        $board = (string) ($trello['board_shortlink'] ?? '');
        if ($key === '' || $token === '' || $board === '') {
            throw new RuntimeException('Trello activity credentials or board are not configured.');
        }

        $auth = ['key' => $key, 'token' => $token];
        $userId = $this->trelloMemberId($email, $board, $auth, $trello);
        $actions = $this->actions($board, $from, $to, $auth);

        return collect($actions)
            ->filter(fn (array $action): bool => (string) ($action['idMemberCreator'] ?? '') === $userId)
            ->map(fn (array $action): array => $this->normalizeAction($action, $from->getTimezone()->getName()))
            ->filter()
            ->values()
            ->all();
    }

    private function collectWithOAuth(string $email, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $board = (string) config('systems_activity_report.trello.board_shortlink');
        $response = $this->oauthGet('/boards/'.rawurlencode($board).'/members', [
            'fields' => 'id,fullName,username,email',
        ]);
        if (! $response->successful()) {
            throw new RuntimeException('Trello board member lookup failed with HTTP '.$response->status().'.');
        }

        $members = (array) $response->json();
        $member = collect($members)->first(fn (array $candidate): bool =>
            strtolower((string) ($candidate['email'] ?? '')) === strtolower(trim($email))
        );
        if ($member === null) {
            $localName = trim((string) User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
                ->value('name'));
            if ($localName !== '') {
                $member = collect($members)->first(fn (array $candidate): bool =>
                    mb_strtolower(trim((string) ($candidate['fullName'] ?? ''))) === mb_strtolower($localName)
                );
            }
        }
        if (! is_array($member) || empty($member['id'])) {
            return [];
        }

        $actions = [];
        $before = null;
        do {
            $query = [
                'since' => $from->utc()->toIso8601String(),
                'before' => $to->utc()->toIso8601String(),
                'limit' => 1000,
                'fields' => 'id,type,date,idMemberCreator,data,display',
            ];
            if ($before !== null) {
                $query['before'] = $before;
            }
            $pageResponse = $this->oauthGet('/boards/'.rawurlencode($board).'/actions', $query);
            if (! $pageResponse->successful()) {
                throw new RuntimeException('Trello activity query failed with HTTP '.$pageResponse->status().'.');
            }
            $page = (array) $pageResponse->json();
            $actions = array_merge($actions, $page);
            $last = end($page);
            $before = count($page) === 1000 && is_array($last) && ! empty($last['id'])
                ? (string) $last['id']
                : null;
        } while ($before !== null);

        return collect($actions)
            ->filter(function (array $action) use ($member, $from, $to): bool {
                if ((string) ($action['idMemberCreator'] ?? '') !== (string) $member['id'] || empty($action['date'])) {
                    return false;
                }
                $occurredAt = CarbonImmutable::parse((string) $action['date']);

                return $occurredAt->greaterThanOrEqualTo($from->utc()) && $occurredAt->lessThan($to->utc());
            })
            ->map(fn (array $action): ?array => $this->normalizeAction($action, $from->getTimezone()->getName()))
            ->filter()
            ->values()
            ->all();
    }

    private function trelloMemberId(string $email, string $board, array $auth, array $config): string
    {
        $memberMap = (array) ($config['member_ids'] ?? []);
        if (isset($memberMap[strtolower(trim($email))]) && is_string($memberMap[strtolower(trim($email))])) {
            return $memberMap[strtolower(trim($email))];
        }

        $members = Cache::remember('systems_activity_report:trello_members:'.hash('sha256', $board), now()->addMinutes(30), function () use ($board, $auth): array {
            $response = Http::timeout(30)->get(self::API_URL.'/boards/'.rawurlencode($board).'/members', $auth + [
                'fields' => 'id,fullName,username,email',
            ]);
            if (! $response->successful()) {
                throw new RuntimeException('Trello board member lookup failed with HTTP '.$response->status().'.');
            }

            return (array) $response->json();
        });

        $member = collect($members)->first(fn (array $candidate): bool =>
            strtolower((string) ($candidate['email'] ?? '')) === strtolower(trim($email))
        );

        if ($member === null) {
            $localUser = User::query()
                ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
                ->first(['name']);
            $localName = trim((string) ($localUser?->name ?? ''));
            if ($localName !== '') {
                $member = collect($members)->first(fn (array $candidate): bool =>
                    mb_strtolower(trim((string) ($candidate['fullName'] ?? ''))) === mb_strtolower($localName)
                );
            }
        }

        if (! is_array($member) || empty($member['id'])) {
            throw new RuntimeException('Could not match the report user to a Trello board member.');
        }

        return (string) $member['id'];
    }

    private function actions(string $board, CarbonImmutable $from, CarbonImmutable $to, array $auth): array
    {
        $actions = [];
        $before = null;
        do {
            $query = [
                'since' => $from->utc()->toIso8601String(),
                'before' => $to->utc()->toIso8601String(),
                'limit' => 1000,
                'fields' => 'id,type,date,idMemberCreator,data,display',
            ];
            if ($before !== null) {
                $query['before'] = $before;
            }

            $response = Http::timeout(30)->get(self::API_URL.'/boards/'.rawurlencode($board).'/actions', $auth + $query);
            if (! $response->successful()) {
                throw new RuntimeException('Trello activity query failed with HTTP '.$response->status().'.');
            }

            $page = (array) $response->json();
            $actions = array_merge($actions, $page);
            $last = end($page);
            $before = count($page) === 1000 && is_array($last) && ! empty($last['id'])
                ? (string) $last['id']
                : null;
        } while ($before !== null);

        return collect($actions)
            ->filter(function (array $action) use ($from, $to): bool {
                if (empty($action['date'])) {
                    return false;
                }
                $occurredAt = CarbonImmutable::parse((string) $action['date']);

                return $occurredAt->greaterThanOrEqualTo($from->utc()) && $occurredAt->lessThan($to->utc());
            })
            ->unique('id')
            ->values()
            ->all();
    }

    private function normalizeAction(array $action, string $timezone): ?array
    {
        $occurredAt = $action['date'] ?? null;
        if (! is_string($occurredAt) || $occurredAt === '') {
            return null;
        }

        $type = strtolower((string) ($action['type'] ?? 'activity'));
        $data = (array) ($action['data'] ?? []);
        $title = match ($type) {
            'createcard', 'createcardfromcheckitem', 'createcardfromemail' => 'Creó una tarjeta en Trello',
            'commentcard', 'commentcardfromcopiedcard' => 'Comentó en una tarjeta de Trello',
            'addmembertocard' => 'Asignó un miembro a una tarjeta de Trello',
            'removememberfromcard' => 'Retiró un miembro de una tarjeta de Trello',
            'addattachmenttocard' => 'Adjuntó un archivo a una tarjeta de Trello',
            'addchecklisttocard' => 'Añadió una lista de verificación en Trello',
            'updatecheckitemstateoncard' => 'Actualizó una tarea de lista de verificación en Trello',
            'updatecard' => 'Actualizó una tarjeta en Trello',
            'copycard' => 'Copió una tarjeta en Trello',
            'updatecardclosed' => 'Archivó o restauró una tarjeta de Trello',
            default => 'Registró actividad en Trello',
        };

        $detail = '';
        $before = trim((string) data_get($data, 'listBefore.name', ''));
        $after = trim((string) data_get($data, 'listAfter.name', ''));
        if ($before !== '' && $after !== '') {
            $detail = 'Movió una tarjeta de «'.$this->safeListName($before).'» a «'.$this->safeListName($after).'». ';
        }

        return [
            'source' => 'Trello',
            'occurred_at' => CarbonImmutable::parse($occurredAt)->setTimezone($timezone)->toIso8601String(),
            'kind' => $type,
            'title' => $title,
            // Card names, comment text, attachment names and member names are omitted
            // because Trello cards can contain customer or employee personal data.
            'detail' => $detail,
        ];
    }

    private function safeListName(string $name): string
    {
        $name = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[correo omitido]', $name) ?? $name;

        return mb_substr($name, 0, 100);
    }
}

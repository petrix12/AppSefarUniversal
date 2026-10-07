<?php

namespace App\Services\SystemsActivitySources;

use App\Services\TeamleaderService;
use Carbon\CarbonImmutable;

class TeamleaderActivitySource implements SystemsActivitySource
{
    public function __construct(private TeamleaderService $teamleader)
    {
    }

    public function collect(string $email, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $user = $this->teamleader->getUserByEmail($email);
        if ($user === null) {
            return [];
        }

        $events = [];
        foreach ($this->teamleader->listCalendarEventsForUserBetween($user['id'], $from, $to) as $event) {
            if ((string) data_get($event, 'creator.id') !== (string) $user['id']) {
                continue;
            }
            $startedAt = data_get($event, 'starts_at') ?? data_get($event, 'start');
            if (! is_string($startedAt) || $startedAt === '') {
                continue;
            }
            $occurredAt = CarbonImmutable::parse($startedAt);
            if (! $occurredAt->greaterThanOrEqualTo($from) || ! $occurredAt->lessThan($to)) {
                continue;
            }

            // Event titles can contain customer names, so report only the category.
            $type = strtolower((string) (data_get($event, 'type') ?? 'event'));
            $events[] = [
                'source' => 'Teamleader',
                'occurred_at' => $occurredAt->setTimezone('America/Caracas')->toIso8601String(),
                'kind' => $type,
                'title' => match ($type) {
                    'meeting' => 'Reunión registrada',
                    'call' => 'Llamada registrada',
                    'task' => 'Tarea registrada',
                    default => 'Evento de calendario registrado',
                },
                'detail' => '',
            ];
        }

        return $events;
    }

    public function findUser(string $email): ?array
    {
        return $this->teamleader->getUserByEmail($email);
    }
}

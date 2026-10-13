<?php

namespace App\Services;

use App\Models\User;
use App\Services\SystemsActivitySources\AppActivitySource;
use App\Services\SystemsActivitySources\HubSpotActivitySource;
use App\Services\SystemsActivitySources\MondayActivitySource;
use App\Services\SystemsActivitySources\TeamleaderActivitySource;
use App\Services\SystemsActivitySources\TrelloActivitySource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ManualSystemsActivityReportService
{
    public function __construct(
        private TrelloActivitySource $trello,
        private AppActivitySource $app,
        private HubSpotActivitySource $hubspot,
        private TeamleaderActivitySource $teamleader,
        private MondayActivitySource $monday,
    ) {
    }

    public function generate(string $listId): string
    {
        $list = $this->trello->cardsInList($listId);
        [$from, $to] = $this->periodFromListName($list['name']);

        $emails = array_values(array_unique(array_filter(array_map(
            static fn (mixed $email): string => strtolower(trim((string) $email)),
            (array) config('systems_activity_report.user_emails', [])
        ))));
        if ($emails === []) {
            throw new RuntimeException('Configura SYSTEMS_ACTIVITY_REPORT_USERS con los correos del equipo de Sistemas antes de generar el informe.');
        }

        $sources = [
            'App Sefar Universal' => $this->app,
            'HubSpot' => $this->hubspot,
            'Teamleader' => $this->teamleader,
            'monday.com' => $this->monday,
            'Trello' => $this->trello,
        ];

        $lines = [
            'Informe unificado de actividad de Sistemas',
            'Periodo: '.$from->format('d/m/Y').' → '.$to->subDay()->format('d/m/Y'),
            'Columna de Trello: '.$list['name'],
            '',
            'RESUMEN POR FUENTE',
        ];

        $perSource = array_fill_keys(array_keys($sources), 0);
        $activityByPerson = [];
        $sourceFailures = [];

        foreach ($emails as $email) {
            $displayName = $this->displayName($email);
            $activityByPerson[] = ['name' => $displayName, 'sources' => []];
            $personIndex = array_key_last($activityByPerson);

            foreach ($sources as $label => $source) {
                try {
                    $events = $source->collect($email, $from, $to);
                    $activityByPerson[$personIndex]['sources'][$label] = $events;
                    $perSource[$label] += count($events);
                } catch (\Throwable $exception) {
                    Log::warning('Manual systems activity source failed.', [
                        'source' => $label,
                        'exception' => get_class($exception),
                    ]);
                    $activityByPerson[$personIndex]['sources'][$label] = null;
                    $sourceFailures[$label] = true;
                }
            }
        }

        foreach ($perSource as $source => $count) {
            $suffix = isset($sourceFailures[$source]) ? ' (consulta parcial: hubo un error)' : '';
            $lines[] = '- '.$source.': '.$count.' actividades'.$suffix;
        }

        $lines[] = '';
        $lines[] = 'TARJETAS DE LA COLUMNA SELECCIONADA';
        $lines[] = 'Tarjetas: '.count($list['cards']);
        $lines[] = 'La columna y sus iconos son contexto de Trello; no indican por sí solos que una tarea se haya completado.';
        foreach ($list['cards'] as $index => $card) {
            $lines[] = '';
            $lines[] = ($index + 1).'. '.$this->safeText($card['name'] ?? 'Tarjeta sin título', 240);
            $description = trim((string) ($card['description'] ?? ''));
            if ($description !== '') {
                $lines[] = '   '.$this->safeText($description, 1200);
            }
        }

        $lines[] = '';
        $lines[] = 'ACTIVIDAD POR PERSONA Y SISTEMA';
        foreach ($activityByPerson as $person) {
            $lines[] = '';
            $lines[] = $person['name'];
            foreach ($sources as $source => $_service) {
                $events = $person['sources'][$source] ?? [];
                $lines[] = '  '.$source.':';
                if ($events === null) {
                    $lines[] = '    No se pudo consultar esta fuente.';
                    continue;
                }
                if ($events === []) {
                    $lines[] = '    Sin actividad registrada en el periodo.';
                    continue;
                }

                usort($events, static fn (array $left, array $right): int => strcmp(
                    (string) ($left['occurred_at'] ?? ''),
                    (string) ($right['occurred_at'] ?? '')
                ));
                foreach ($events as $event) {
                    $date = $this->eventDate((string) ($event['occurred_at'] ?? ''));
                    $title = $this->safeText($event['title'] ?? 'Actividad registrada', 240);
                    $detail = $this->safeText($event['detail'] ?? '', 500);
                    $lines[] = '    - '.($date !== '' ? $date.' · ' : '').$title.($detail !== '' ? ' — '.$detail : '');
                }
            }
        }

        return implode("\n", $lines);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function periodFromListName(string $name): array
    {
        if (! preg_match('/(\d{2}\/\d{2}\/\d{4})\s*(?:->|→|↔|[-–—])\s*(\d{2}\/\d{2}\/\d{4})/u', $name, $matches)) {
            throw new RuntimeException('El nombre de la columna debe incluir un rango de fechas, por ejemplo: 05/10/2026->09/10/2026.');
        }

        $timezone = (string) config('systems_activity_report.timezone', 'America/Caracas');
        try {
            $from = CarbonImmutable::createFromFormat('!d/m/Y', $matches[1], $timezone);
            $lastDay = CarbonImmutable::createFromFormat('!d/m/Y', $matches[2], $timezone);
        } catch (\Throwable) {
            throw new RuntimeException('El rango de fechas del nombre de la columna no es válido.');
        }
        if (! $from || ! $lastDay || $from->format('d/m/Y') !== $matches[1] || $lastDay->format('d/m/Y') !== $matches[2] || $lastDay->lessThan($from)) {
            throw new RuntimeException('El rango de fechas del nombre de la columna no es válido.');
        }

        return [$from, $lastDay->addDay()];
    }

    private function displayName(string $email): string
    {
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first(['name']);
        $name = trim((string) ($user?->name ?? ''));
        if ($name !== '') {
            return $name;
        }

        foreach ([$this->hubspot, $this->teamleader, $this->monday] as $directory) {
            try {
                $person = $directory->findUser($email);
                if ($person) {
                    $name = trim(implode(' ', array_filter([
                        (string) ($person['firstName'] ?? $person['name'] ?? $person['firstname'] ?? ''),
                        (string) ($person['lastName'] ?? $person['lastname'] ?? ''),
                    ])));
                    if ($name !== '') {
                        return $name;
                    }
                }
            } catch (\Throwable) {
                // Directory lookup is optional; continue to the next source.
            }
        }

        return 'Usuario de Sistemas';
    }

    private function eventDate(string $value): string
    {
        if ($value === '') {
            return '';
        }
        try {
            return CarbonImmutable::parse($value)->setTimezone((string) config('systems_activity_report.timezone', 'America/Caracas'))->format('d/m H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private function safeText(mixed $value, int $maxLength): string
    {
        if (! is_scalar($value)) {
            return '';
        }
        $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string) $value) ?? '');
        $text = preg_replace('/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i', '[correo omitido]', $text) ?? $text;

        return mb_substr($text, 0, $maxLength);
    }
}

<?php

namespace App\Services;

use App\Models\SystemsActivityReport;
use App\Models\User;
use App\Services\SystemsActivitySources\AppActivitySource;
use App\Services\SystemsActivitySources\HubSpotActivitySource;
use App\Services\SystemsActivitySources\MondayActivitySource;
use App\Services\SystemsActivitySources\TeamleaderActivitySource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SystemsActivityReportService
{
    public function __construct(
        private SystemsActivitySummaryService $summarizer,
        private AppActivitySource $app,
        private MondayActivitySource $monday,
        private HubSpotActivitySource $hubspot,
        private TeamleaderActivitySource $teamleader,
    ) {
    }

    public function generate(?string $date = null): array
    {
        $timezone = (string) config('systems_activity_report.timezone', 'America/Caracas');
        $day = $date
            ? CarbonImmutable::parse($date, $timezone)->startOfDay()
            : CarbonImmutable::now($timezone)->subDay()->startOfDay();
        $from = $day;
        $to = $day->addDay();

        $this->assertConfigured();
        if (! Schema::hasTable('systems_activity_reports')) {
            throw new RuntimeException('Run the systems activity report migration before generating reports.');
        }

        $results = [];
        foreach (config('systems_activity_report.user_emails', []) as $email) {
            $results[] = $this->generateForUser((string) $email, $day, $from, $to);
        }

        return $results;
    }

    private function generateForUser(string $email, CarbonImmutable $day, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $hash = hash('sha256', strtolower(trim($email)));
        $report = SystemsActivityReport::query()
            ->where('report_date', $day->toDateString())
            ->where('subject_hash', $hash)
            ->first();
        if ($report?->status === 'complete' && $report->trello_card_id) {
            return ['status' => 'skipped', 'display_name' => $report->display_name];
        }

        $sources = [
            'AppSefarUniversal' => $this->app,
            'monday.com' => $this->monday,
            'HubSpot' => $this->hubspot,
            'Teamleader' => $this->teamleader,
        ];
        $events = [];
        $counts = [];
        $failures = [];
        foreach ($sources as $name => $source) {
            try {
                $sourceEvents = $source->collect($email, $from, $to);
                $events = array_merge($events, $sourceEvents);
                $counts[$name] = count($sourceEvents);
            } catch (\Throwable $exception) {
                Log::warning('Systems activity source failed.', [
                    'source' => $name,
                    'exception' => get_class($exception),
                ]);
                $counts[$name] = null;
                $failures[] = $name;
            }
        }

        if (count($failures) === count($sources)) {
            return ['status' => 'failed', 'display_name' => $this->displayName($email)];
        }

        $displayName = $this->displayName($email);
        $summary = $this->summarizer->summarize($day, $events);
        if ($failures !== []) {
            $summary .= "\n\nFuentes no disponibles: ".implode(', ', $failures).'. Este resumen es parcial.';
        }

        $report ??= new SystemsActivityReport();
        $report->fill([
            'report_date' => $day->toDateString(),
            'subject_hash' => $hash,
            'display_name' => $displayName,
            'source_counts' => $counts,
            'summary' => $summary,
            'status' => 'pending',
            'error_message' => null,
        ]);
        $report->save();

        try {
            $cardId = $this->saveTrelloCard($displayName, $day, $summary, $counts);
            $report->forceFill(['trello_card_id' => $cardId, 'status' => 'complete'])->save();
        } catch (\Throwable $exception) {
            Log::warning('Could not save Systems activity card in Trello.', [
                'exception' => get_class($exception),
            ]);
            $report->forceFill(['status' => 'failed', 'error_message' => 'No se pudo guardar el informe en Trello.'])->save();
            return ['status' => 'failed', 'display_name' => $displayName];
        }

        return ['status' => 'complete', 'display_name' => $displayName, 'events' => count($events)];
    }

    private function displayName(string $email): string
    {
        $localUser = User::query()->whereRaw('LOWER(email) = ?', [strtolower($email)])->first();
        $localName = trim((string) ($localUser?->name ?? ''));
        if ($localName !== '') {
            return $localName;
        }

        foreach ([$this->monday, $this->hubspot, $this->teamleader] as $directory) {
            try {
                $user = $directory->findUser($email);
                if ($user) {
                    $name = trim(implode(' ', array_filter([
                        (string) ($user['firstName'] ?? $user['name'] ?? $user['firstname'] ?? ''),
                        (string) ($user['lastName'] ?? $user['lastname'] ?? ''),
                    ])));
                    if ($name !== '') {
                        return $name;
                    }
                }
            } catch (\Throwable $exception) {
                Log::warning('Systems activity directory lookup failed.', [
                    'source' => get_class($directory),
                    'exception' => get_class($exception),
                ]);
            }
        }

        return 'Usuario de Sistemas';
    }

    private function saveTrelloCard(string $displayName, CarbonImmutable $day, string $summary, array $counts): string
    {
        $trello = (array) config('systems_activity_report.trello');
        $key = (string) ($trello['key'] ?? '');
        $token = (string) ($trello['token'] ?? '');
        if ($key === '' || $token === '') {
            throw new RuntimeException('Trello API credentials are not configured.');
        }

        $auth = ['key' => $key, 'token' => $token];
        $base = 'https://api.trello.com/1';
        $listsResponse = Http::timeout(30)->get($base.'/boards/'.rawurlencode((string) $trello['board_shortlink']).'/lists', $auth);
        if (! $listsResponse->successful()) {
            throw new RuntimeException('Trello list lookup failed with HTTP '.$listsResponse->status().'.');
        }
        $list = collect($listsResponse->json())->first(fn (array $list): bool =>
            strtolower((string) ($list['name'] ?? '')) === strtolower((string) $trello['list_name']) && empty($list['closed'])
        );
        if (! $list) {
            throw new RuntimeException('The configured Trello report list was not found.');
        }

        $name = 'Resumen diario · '.$displayName.' · '.$day->format('d/m/Y');
        $sources = collect($counts)->map(fn ($count, $source): string => $source.': '.($count === null ? 'no disponible' : $count))->implode("\n");
        $description = $summary."\n\nActividad registrada por fuente:\n".$sources;

        $existingCards = Http::timeout(30)->get($base.'/lists/'.rawurlencode((string) $list['id']).'/cards', $auth + ['filter' => 'all']);
        if (! $existingCards->successful()) {
            throw new RuntimeException('Trello card lookup failed with HTTP '.$existingCards->status().'.');
        }
        $existing = collect($existingCards->json())->first(fn (array $card): bool =>
            (string) ($card['name'] ?? '') === $name
        );
        if ($existing) {
            $update = Http::timeout(30)->put($base.'/cards/'.rawurlencode((string) $existing['id']), $auth + ['desc' => $description]);
            if (! $update->successful()) {
                throw new RuntimeException('Trello card update failed with HTTP '.$update->status().'.');
            }
            return (string) $existing['id'];
        }

        $create = Http::timeout(30)->post($base.'/cards', $auth + [
            'idList' => $list['id'],
            'name' => $name,
            'desc' => $description,
            'pos' => 'top',
        ]);
        if (! $create->successful() || ! is_string($create->json('id'))) {
            throw new RuntimeException('Trello card creation failed with HTTP '.$create->status().'.');
        }

        return $create->json('id');
    }

    private function assertConfigured(): void
    {
        if (! config('systems_activity_report.enabled')) {
            throw new RuntimeException('Systems activity reports are disabled. Set SYSTEMS_ACTIVITY_REPORT_ENABLED=true to enable them.');
        }
        if (config('systems_activity_report.user_emails') === []) {
            throw new RuntimeException('Set SYSTEMS_ACTIVITY_REPORT_USERS to the selected Systems users.');
        }
    }
}

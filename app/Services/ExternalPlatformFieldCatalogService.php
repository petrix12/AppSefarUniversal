<?php

namespace App\Services;

use App\Jobs\RefreshMondayFieldCatalogue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Maintains an explicitly refreshed, metadata-only catalogue for the
 * unification audit. It never calls list/info endpoints for CRM records and
 * does not create mappings, fields or synchronisations.
 */
class ExternalPlatformFieldCatalogService
{
    private const PROVIDERS = ['hubspot', 'teamleader', 'monday'];

    private const CACHE_PREFIX = 'unification.external-field-catalog.';

    private const MONDAY_PROGRESS_KEY = 'unification.external-field-catalog.monday-progress';

    public function __construct(
        private readonly HubspotService $hubspot,
        private readonly TeamleaderService $teamleader,
        private readonly MondayCatalogService $monday,
    ) {
    }

    /**
     * @param array<int, string> $providers
     * @return array{providers: array<string, array<string, mixed>>}
     */
    public function refresh(array $providers = self::PROVIDERS): array
    {
        $providers = array_values(array_unique(array_intersect(self::PROVIDERS, $providers)));
        $report = ['providers' => []];

        foreach ($providers as $provider) {
            if ($provider === 'monday') {
                $report['providers']['monday'] = $this->startMondayRefresh();
                continue;
            }

            try {
                $result = match ($provider) {
                    'hubspot' => $this->hubspotFields(),
                    'teamleader' => $this->teamleaderFields(),
                };

                $payload = [
                    'fetched_at' => now()->toIso8601String(),
                    'fields' => $result['fields'],
                    'meta' => $result['meta'],
                ];
                Cache::put($this->cacheKey($provider), $payload, now()->addHours(12));

                $report['providers'][$provider] = array_merge([
                    'ok' => true,
                    'cached' => false,
                    'field_count' => count($result['fields']),
                    'fetched_at' => $payload['fetched_at'],
                ], $result['meta']);
            } catch (Throwable $exception) {
                report($exception);
                $previous = $this->cachedProvider($provider);

                $report['providers'][$provider] = [
                    'ok' => false,
                    'cached' => $previous !== null,
                    'field_count' => count($previous['fields'] ?? []),
                    'fetched_at' => $previous['fetched_at'] ?? null,
                    'message' => $this->safeError($exception),
                ];
            }
        }

        return $report;
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function cachedFields(): array
    {
        $catalogues = [];
        foreach (self::PROVIDERS as $provider) {
            $catalogues[$provider] = $this->cachedProvider($provider)['fields'] ?? [];
        }

        return $catalogues;
    }

    /**
     * @return array<string, array{field_count:int,fetched_at:?string}>
     */
    public function status(): array
    {
        $status = [];
        foreach (self::PROVIDERS as $provider) {
            $catalogue = $this->cachedProvider($provider);
            $status[$provider] = [
                'field_count' => count($catalogue['fields'] ?? []),
                'fetched_at' => $catalogue['fetched_at'] ?? null,
            ];
            if ($provider === 'monday') {
                $status[$provider]['refresh'] = $this->mondayProgress();
            }
        }

        return $status;
    }

    /**
     * Monday may have hundreds of boards. Queue its full metadata crawl so an
     * admin HTTP request never times out. The job writes only cache entries.
     *
     * @return array<string, mixed>
     */
    public function startMondayRefresh(): array
    {
        $progress = $this->mondayProgress();
        if (in_array($progress['status'] ?? null, ['queued', 'running'], true)) {
            return $this->mondayRefreshReport($progress);
        }

        Cache::put(self::MONDAY_PROGRESS_KEY, [
            'status' => 'queued',
            'processed' => 0,
            'total' => null,
            'phase' => 'boards',
            'board_page' => 1,
            'boards' => [],
            'fields' => [],
            'unavailable_boards' => [],
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ], now()->addDay());
        RefreshMondayFieldCatalogue::dispatch();

        return $this->mondayRefreshReport($this->mondayProgress());
    }

    /**
     * Processes a bounded number of boards. It is invoked only by the queued
     * job and never from the browser request.
     */
    public function refreshMondayChunk(): void
    {
        $progress = $this->mondayProgress();
        if (! in_array($progress['status'] ?? null, ['queued', 'running'], true)) {
            return;
        }

        try {
            $phase = $progress['phase'] ?? 'boards';
            if ($phase === 'boards') {
                $page = max(1, (int) ($progress['board_page'] ?? 1));
                $pageBoards = collect($this->monday->boardPage($page, true));
                $boards = collect($progress['boards'] ?? [])
                    ->concat($pageBoards)
                    ->unique('id')
                    ->values();
                $progress['boards'] = $boards->all();
                $progress['board_page'] = $page + 1;
                $progress['phase'] = 'boards';
                $progress['status'] = 'running';

                if ($pageBoards->count() < 100 || $page >= 100) {
                    $progress['phase'] = 'fields';
                    $progress['total'] = $boards->count();
                }
                $progress['updated_at'] = now()->toIso8601String();
                Cache::put(self::MONDAY_PROGRESS_KEY, $progress, now()->addDay());
                RefreshMondayFieldCatalogue::dispatch();

                return;
            }

            $boards = collect($progress['boards'] ?? []);
            $chunkSize = max(1, min(25, (int) config('services.monday.catalogue_boards_per_job', 8)));
            $offset = (int) ($progress['processed'] ?? 0);
            $fields = collect($progress['fields'] ?? []);
            $unavailableBoards = $progress['unavailable_boards'] ?? [];

            foreach ($boards->slice($offset, $chunkSize) as $board) {
                try {
                    foreach ($this->monday->columns((string) $board['id'], true) as $column) {
                        $fields->push($this->field(
                            'monday',
                            'item',
                            (string) $board['id'],
                            $column['id'],
                            $column['name'],
                            $column['type'] ?: null,
                            'Monday · tablero remoto',
                            $board['name'],
                        ));
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $unavailableBoards[] = [
                        'id' => (string) $board['id'],
                        'name' => (string) $board['name'],
                    ];
                }
            }

            $progress['processed'] = min($offset + $chunkSize, $boards->count());
            $progress['fields'] = $fields
                ->unique(fn (array $field) => $field['scope_key'].'|'.$field['key'])
                ->values()
                ->all();
            $progress['unavailable_boards'] = array_values(array_unique($unavailableBoards, SORT_REGULAR));
            $progress['updated_at'] = now()->toIso8601String();
            $progress['status'] = $progress['processed'] >= $boards->count() ? 'completed' : 'running';

            $payload = [
                'fetched_at' => $progress['status'] === 'completed'
                    ? now()->toIso8601String()
                    : (data_get($this->cachedProvider('monday'), 'fetched_at') ?: null),
                'fields' => $progress['fields'],
                'meta' => [
                    'modules' => $boards->pluck('name')->values()->all(),
                    'board_count' => $boards->count(),
                    'unavailable_boards' => $progress['unavailable_boards'],
                    'refresh_status' => $progress['status'],
                    'processed_boards' => $progress['processed'],
                ],
            ];
            Cache::put($this->cacheKey('monday'), $payload, now()->addHours(12));
            Cache::put(self::MONDAY_PROGRESS_KEY, $progress, now()->addDay());

            if ($progress['status'] !== 'completed') {
                RefreshMondayFieldCatalogue::dispatch();
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->markMondayRefreshFailed($exception);
            throw $exception;
        }
    }

    public function markMondayRefreshFailed(Throwable $exception): void
    {
        $progress = $this->mondayProgress();
        $progress['status'] = 'failed';
        $progress['message'] = $this->safeError($exception);
        $progress['updated_at'] = now()->toIso8601String();
        Cache::put(self::MONDAY_PROGRESS_KEY, $progress, now()->addDay());
    }

    /** @return array{fields: array<int, array<string, mixed>>, meta: array<string, mixed>} */
    private function hubspotFields(): array
    {
        $fields = collect([
            ['object' => 'contacts', 'entity_type' => 'contact', 'module' => 'Contacts'],
            ['object' => 'deals', 'entity_type' => 'deal', 'module' => 'Deals'],
        ])->flatMap(function (array $definition): array {
            return collect($this->hubspot->propertyCatalog($definition['object']))
                ->map(function (array $field) use ($definition): array {
                    return $this->field(
                        'hubspot',
                        $definition['entity_type'],
                        '*',
                        $field['key'],
                        $field['label'],
                        $this->typeLabel($field['type'] ?? null, $field['field_type'] ?? null),
                        'HubSpot · '.$definition['module'],
                        $definition['module'],
                        ['group' => $field['group'] ?? null, 'description' => $field['description'] ?? null],
                    );
                })
                ->all();
        })->values()->all();

        return [
            'fields' => $fields,
            'meta' => [
                'modules' => ['Contacts', 'Deals'],
                'contact_fields' => count(array_filter($fields, fn (array $field) => $field['entity_type'] === 'contact')),
                'deal_fields' => count(array_filter($fields, fn (array $field) => $field['entity_type'] === 'deal')),
            ],
        ];
    }

    /** @return array{fields: array<int, array<string, mixed>>, meta: array<string, mixed>} */
    private function teamleaderFields(): array
    {
        $definitions = collect();
        for ($page = 1; $page <= 50; $page++) {
            $response = $this->teamleader->listCustomFieldDefinitions($page, 100);
            $pageDefinitions = collect($response['data'] ?? []);
            $definitions = $definitions->concat($pageDefinitions);

            if ($pageDefinitions->count() < 100) {
                break;
            }
        }

        $standard = collect($this->teamleaderStandardFields());
        $custom = $definitions
            ->map(function (array $definition): ?array {
                $id = (string) ($definition['id'] ?? '');
                if ($id === '') {
                    return null;
                }

                $context = Str::lower((string) ($definition['context'] ?? 'contact'));
                $entityType = match ($context) {
                    'project', 'projects' => 'project',
                    'deal', 'deals' => 'deal',
                    default => 'contact',
                };
                $module = match ($entityType) {
                    'project' => 'Projects',
                    'deal' => 'Deals',
                    default => 'Contacts / Companies',
                };

                return $this->field(
                    'teamleader',
                    $entityType,
                    '*',
                    $id,
                    (string) ($definition['label'] ?? $id),
                    (string) ($definition['type'] ?? 'custom'),
                    'Teamleader · definición remota',
                    $module,
                    ['context' => $context, 'custom' => true],
                );
            })
            ->filter()
            ->values();

        $fields = $standard
            ->concat($custom)
            ->unique(fn (array $field) => $field['entity_type'].'|'.$field['key'])
            ->sortBy(fn (array $field) => $field['entity_type'].'|'.$field['label'], SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'fields' => $fields,
            'meta' => [
                'modules' => ['Contacts / Companies', 'Deals', 'Projects'],
                'custom_field_definitions' => $custom->count(),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function teamleaderStandardFields(): array
    {
        $definitions = [
            'contact' => [
                ['id', 'ID'], ['first_name', 'Nombre'], ['last_name', 'Apellido'], ['salutation', 'Tratamiento'],
                ['email', 'Email'], ['telephone', 'Teléfono'], ['mobile', 'Móvil'], ['language', 'Idioma'],
                ['birthdate', 'Fecha de nacimiento'], ['gender', 'Género'], ['tags', 'Etiquetas'], ['added_at', 'Creado'], ['updated_at', 'Actualizado'],
            ],
            'deal' => [
                ['id', 'ID'], ['title', 'Título'], ['status', 'Estado'], ['customer', 'Cliente'], ['responsible_user', 'Responsable'],
                ['source', 'Origen'], ['estimated_closing_date', 'Fecha estimada de cierre'], ['amount', 'Importe'], ['currency', 'Moneda'], ['added_at', 'Creado'], ['updated_at', 'Actualizado'],
            ],
            'project' => [
                ['id', 'ID'], ['title', 'Título'], ['status', 'Estado'], ['customer', 'Cliente'], ['responsible_user', 'Responsable'],
                ['description', 'Descripción'], ['starts_on', 'Fecha de inicio'], ['due_on', 'Fecha límite'], ['budget', 'Presupuesto'], ['added_at', 'Creado'], ['updated_at', 'Actualizado'],
            ],
        ];

        return collect($definitions)->flatMap(function (array $fields, string $entityType): array {
            $module = match ($entityType) {
                'deal' => 'Deals',
                'project' => 'Projects',
                default => 'Contacts / Companies',
            };

            return array_map(fn (array $field): array => $this->field(
                'teamleader',
                $entityType,
                '*',
                $field[0],
                $field[1],
                'standard',
                'Teamleader · campo estándar',
                $module,
                ['custom' => false],
            ), $fields);
        })->values()->all();
    }

    /** @return array<string, mixed> */
    private function field(
        string $provider,
        string $entityType,
        string $scopeKey,
        string $key,
        string $label,
        ?string $type,
        string $source,
        string $scopeLabel,
        array $metadata = [],
    ): array {
        return [
            'provider' => $provider,
            'entity_type' => $entityType,
            'scope_key' => $scopeKey,
            'scope_label' => $scopeLabel,
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'source' => $source,
            'metadata' => array_filter($metadata, fn ($value) => $value !== null && $value !== ''),
        ];
    }

    private function typeLabel(?string $type, ?string $fieldType): ?string
    {
        return collect([$type, $fieldType])->filter()->implode(' · ') ?: null;
    }

    /** @return array<string, mixed>|null */
    private function cachedProvider(string $provider): ?array
    {
        $value = Cache::get($this->cacheKey($provider));

        return is_array($value) ? $value : null;
    }

    private function cacheKey(string $provider): string
    {
        return self::CACHE_PREFIX.$provider;
    }

    private function safeError(Throwable $exception): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $exception->getMessage()) ?: 'Error sin detalle.'), 800);
    }

    /** @return array<string, mixed> */
    private function mondayProgress(): array
    {
        $progress = Cache::get(self::MONDAY_PROGRESS_KEY, []);

        return is_array($progress) ? $progress : [];
    }

    /** @return array<string, mixed> */
    private function mondayRefreshReport(array $progress): array
    {
        return [
            'ok' => true,
            'in_progress' => in_array($progress['status'] ?? null, ['queued', 'running'], true),
            'status' => $progress['status'] ?? 'queued',
            'field_count' => count($progress['fields'] ?? ($this->cachedProvider('monday')['fields'] ?? [])),
            'processed_boards' => (int) ($progress['processed'] ?? 0),
            'board_count' => $progress['total'] ?? null,
            'message' => 'La lectura completa de tableros y columnas de Monday se ejecuta en segundo plano.',
        ];
    }
}

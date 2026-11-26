<?php

namespace App\Services;

use App\Exceptions\CosIdentityConflictException;
use App\Exceptions\CosSourceRefreshException;
use App\Models\MondayData;
use App\Models\MondayFormBuilder;
use App\Models\Negocio;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Monday;

class ClientCosSnapshotService
{
    private const DEFAULT_CACHE_TTL_DAYS = 30;


    public function __construct(
        private HubspotService $hubspotService,
        private TeamleaderService $teamleaderService,
        private CosHelperService $cosHelper,
        private HubspotDealLocalSyncService $dealLocalSync,
    ) {
    }

    public function get(User $user, bool $forceRefresh = false, bool $syncExternal = true): array
    {
        $startedAt = microtime(true);
        $user = $user->fresh() ?? $user;

        if (! $forceRefresh && $this->hasFreshCachedCos($user)) {
            return $this->fromCache($user, $startedAt);
        }

        return $this->refresh($user, $syncExternal, $forceRefresh);
    }

    /** Render from local data only, even when the global queue uses sync. */
    public function forPage(User $user): array
    {
        $localReads = CrmContactMirrorService::localReadsEnabled();
        $negocios = Negocio::where('user_id', $user->id)->get();
        $storedMonday = $this->storedMondayData($user);
        $monday = $this->localMondayData($user, $storedMonday);
        $mondayChanged = $monday['mondaydataforAI'] !== $storedMonday['mondaydataforAI'];

        if (! $localReads && $this->hasFreshCachedCos($user)) {
            $statuses = is_array($user->arraycos) ? $user->arraycos : [];
        } else {
            // A stale snapshot remains visible until the worker replaces it.
            $statuses = is_array($user->arraycos) ? $user->arraycos : [];
            if ($localReads || $statuses === []) {
                $statuses = [];
                foreach ($negocios as $negocio) {
                    if (blank($negocio->servicio_solicitado2) && blank($negocio->servicio_solicitado)) {
                        continue;
                    }
                    $calculator = new CosService(clone $negocio, $user, $negocios, $monday['mondaydataforAI'], false);
                    $statuses[] = $calculator->calculateProgress($calculator->calculateStatus());
                }
                if ($negocios->isEmpty()) {
                    $service = filled($user->servicio)
                        ? Servicio::where('id_hubspot', 'like', $user->servicio . '%')->first()
                        : null;
                    $statuses[] = $this->handleNoNegocios($service);
                }
                $statuses = $this->removeDuplicatesAndSort($statuses);
            }

        }

        $refreshPending = false;
        if (! $localReads && ($mondayChanged || ! $this->hasFreshCachedCos($user) || ! \Illuminate\Support\Facades\Cache::has('cos.page_refreshed.' . $user->id))) {
            try {
                Cache::put('cos.snapshot_refresh.pending.' . $user->id, true, 600);
                \App\Jobs\RefreshClientCosSnapshot::dispatch($user->id, true);
                $refreshPending = Cache::has('cos.snapshot_refresh.pending.' . $user->id);
            } catch (\Throwable $exception) {
                Cache::forget('cos.snapshot_refresh.pending.' . $user->id);
                Log::warning('COS: no se pudo encolar la actualización', [
                    'user_id' => $user->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $definitions = $this->cosHelper->get();
        $statuses = array_values(array_filter($statuses, fn ($status) =>
            ! empty($status['servicio']) && array_key_exists($status['servicio'], $definitions)
        ));

        return [
            'cos' => CosPresentation::statuses($statuses),
            'negocios' => $negocios,
            'monday_data' => $monday,
            'refresh_pending' => $refreshPending,
        ];
    }

    public function refresh(User $user, bool $syncExternal = true, bool $forceExternalRefresh = false): array
    {
        if (CrmContactMirrorService::localReadsEnabled()) {
            $syncExternal = false;
            $forceExternalRefresh = false;
        }
        $startedAt = microtime(true);
        $user = $user->fresh() ?? $user;
        $sync = [
            'external' => $syncExternal,
            'cache' => ['hit' => false, 'ttl_days' => $this->cacheTtlDays()],
            'hubspot' => ['attempted' => false, 'contact' => false, 'deals' => 0, 'linked_contact' => false],
            'teamleader' => ['attempted' => false, 'contact' => false, 'deals' => 0],
            'local_deals' => ['received' => 0, 'inserted' => 0, 'updated' => 0, 'deleted' => 0, 'preserved_associations' => 0],
            'user_fields_updated' => [],
        ];

        if ($syncExternal) {
            $sync = $this->refreshExternalData($user, $sync, $forceExternalRefresh);
            if (! empty($sync['error']) || (filled($user->hs_id) && empty($sync['hubspot']['contact']))) {
                throw new CosSourceRefreshException('hubspot');
            }
            $user = $user->fresh() ?? $user;
        }

        $cos = $this->cosHelper->get(! $forceExternalRefresh);
        $negocios = Negocio::where('user_id', $user->id)->get();
        try {
            $mondayData = $this->refreshMondayData($user, $forceExternalRefresh);
        } catch (\Throwable $exception) {
            throw new CosSourceRefreshException('monday', $exception);
        }
        $cosuserFinal = $this->calculateStatuses(
            $user, $negocios, $mondayData, $cos, ! CrmContactMirrorService::localReadsEnabled(), $forceExternalRefresh,
        );
        $this->persistStatuses($user, $cosuserFinal, $cos, $mondayData['fallback']);

        Log::info('COS/MCP snapshot actualizado', [
            'user_id' => $user->id,
            'negocios_count' => $negocios->count(),
            'cos_count' => count($cosuserFinal),
            'sync_external' => $syncExternal,
        ]);

        return [
            'client' => $user->fresh(),
            'cos' => $cosuserFinal,
            'cosready' => (bool) $user->cosready,
            'arraycos_expire' => optional($user->arraycos_expire)->toIso8601String(),
            'negocios_count' => $negocios->count(),
            'sync' => $sync,
            'monday' => [
                'id' => $user->monday_id,
                'board' => $mondayData['mondayUserDetails']['board']['name'] ?? null,
                'has_data' => ! empty($mondayData['mondayUserDetails']),
                'source' => $mondayData['source'],
                'synced_at' => $mondayData['synced_at'],
                'fallback' => $mondayData['fallback'],
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    public function rebuildLocal(User $user): bool
    {
        $user = $user->fresh() ?? $user;
        $negocios = Negocio::where('user_id', $user->id)->get();
        $monday = $this->localMondayData($user, $this->storedMondayData($user));
        $cos = $this->cosHelper->get();
        $statuses = $this->calculateStatuses($user, $negocios, $monday, $cos, false);

        User::withoutEvents(fn () => User::withoutTimestamps(fn () => $this->persistStatuses($user, $statuses, $cos, false)));
        Cache::put('cos.page_refreshed.'.$user->id, true, $user->arraycos_expire);

        return (bool) $user->cosready;
    }

    private function calculateStatuses(
        User $user,
        \Illuminate\Database\Eloquent\Collection $negocios,
        array $mondayData,
        array $cos,
        bool $allowRemoteAI,
        bool $forceExternalRefresh = false,
    ): array {
        $servicename = $negocios->isEmpty() && $user->servicio
            ? Servicio::where('id_hubspot', 'like', $user->servicio . '%')->first()
            : null;

        $cosuser = [];

        if ($negocios->isNotEmpty()) {
            foreach ($negocios as $negocio) {
                $statusService = new CosService(
                    $negocio,
                    $user,
                    $negocios,
                    $mondayData['mondaydataforAI'] ?? [],
                    forceRefresh: $forceExternalRefresh,
                    allowRemoteAI: $allowRemoteAI,
                    cos: $forceExternalRefresh ? $cos : null,
                );

                $status = $statusService->calculateStatus();
                $cosuser[] = $statusService->calculateProgress($status);
            }
        } else {
            $cosuser[] = $this->handleNoNegocios($servicename, $cos);
        }

        $cosuserFinal = $this->removeDuplicatesAndSort($cosuser);
        return array_values(array_filter($cosuserFinal, function ($proceso) use ($cos) {
            return ! empty($proceso['servicio']) && array_key_exists($proceso['servicio'], $cos);
        }));
    }

    private function persistStatuses(User $user, array $statuses, array $cos, bool $mondayFallback): void
    {
        $user->arraycos = $statuses;
        $user->arraycos_expire = $mondayFallback
            ? Carbon::now()->addMinutes(5)
            : Carbon::now()->addDays($this->cacheTtlDays());
        $user->cosready = $this->checkCosReady($statuses, $cos);
        $user->save();
    }

    private function storedMondayData(User $user): array
    {
        $stored = Schema::hasTable((new MondayData)->getTable())
            ? MondayData::where('user_id', $user->id)->value('data')
            : null;
        $details = is_array($stored) ? $stored : json_decode($stored ?: 'null', true);

        return $this->mondayCalculationData(is_array($details) ? $details : null, 'stored_snapshot');
    }

    private function localMondayData(User $user, array $storedMonday): array
    {
        $local = app(CosMondayLookup::class)->localFor($user);

        return $local === null
            ? $storedMonday
            : $this->mondayCalculationData($local['item'], 'local_mirror', $local['synced_at']);
    }

    private function hasFreshCachedCos(User $user): bool
    {
        if ($user->arraycos === null || ! $user->arraycos_expire) {
            return false;
        }

        return Carbon::parse($user->arraycos_expire)->isFuture();
    }

    private function cacheTtlDays(): int
    {
        return max(1, (int) config('cos_snapshot.cache_ttl_days', self::DEFAULT_CACHE_TTL_DAYS));
    }

    private function fromCache(User $user, float $startedAt): array
    {
        $storedMonday = MondayData::where('user_id', $user->id)->value('data');
        $mondayUserDetails = $storedMonday ? json_decode($storedMonday, true) : null;

        return [
            'client' => $user,
            'cos' => array_values(is_array($user->arraycos) ? $user->arraycos : []),
            'cosready' => (bool) $user->cosready,
            'arraycos_expire' => optional($user->arraycos_expire)->toIso8601String(),
            'negocios_count' => Negocio::where('user_id', $user->id)->count(),
            'sync' => [
                'external' => false,
                'cache' => [
                    'hit' => true,
                    'expires_at' => optional($user->arraycos_expire)->toIso8601String(),
                ],
                'hubspot' => ['attempted' => false, 'contact' => false, 'deals' => 0, 'skipped' => 'cache_fresh'],
                'teamleader' => ['attempted' => false, 'contact' => false, 'deals' => 0, 'skipped' => 'cache_fresh'],
                'local_deals' => ['received' => 0, 'inserted' => 0, 'updated' => 0, 'deleted' => 0, 'preserved_associations' => 0],
                'user_fields_updated' => [],
            ],
            'monday' => [
                'id' => $user->monday_id,
                'board' => $mondayUserDetails['board']['name'] ?? null,
                'has_data' => ! empty($mondayUserDetails),
                'source' => 'stored_snapshot',
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    private function refreshExternalData(User $user, array $sync, bool $forceExternalRefresh = false): array
    {
        $syncService = new UserSyncService($this->hubspotService, $this->teamleaderService);

        if (! filled($user->hs_id)) {
            try {
                $linkedContact = $this->linkExistingHubspotContact($user, $forceExternalRefresh);
            } catch (\Throwable $exception) {
                if ($exception instanceof CosSourceRefreshException) {
                    throw $exception;
                }
                throw new CosSourceRefreshException('hubspot', $exception);
            }

            if ($linkedContact) {
                $sync['hubspot']['linked_contact'] = true;
                $sync['hubspot']['linked_by'] = $linkedContact['source'];
                $user = $user->fresh() ?? $user;
            }
        }

        $callbacks = [];
        if (filled($user->hs_id)) {
            $callbacks['hubspot'] = fn () => $syncService->syncWithHubspot($user, $forceExternalRefresh);
        } else {
            $sync['hubspot']['skipped'] = 'missing_hs_id_no_existing_contact';
        }
        $callbacks['teamleader'] = fn () => $syncService->syncWithTeamleader($user, $forceExternalRefresh);

        $sourceErrors = [];
        $guardedCallbacks = [];
        foreach ($callbacks as $source => $callback) {
            $guardedCallbacks[$source] = function () use ($callback, $source, &$sourceErrors) {
                try {
                    return $callback();
                } catch (\Throwable $exception) {
                    if ($exception instanceof CosIdentityConflictException) {
                        throw $exception;
                    }
                    $sourceErrors[$source] = $exception;

                    return null;
                }
            };
        }

        try {
            $apiResults = $this->hubspotService->executeConcurrent($guardedCallbacks);
        } catch (\Throwable $e) {
            if ($e instanceof CosSourceRefreshException) {
                throw $e;
            }
            Log::error('COS/MCP: error refrescando fuentes externas', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            throw new CosSourceRefreshException('external', $e);
        }

        if ($sourceErrors !== []) {
            $source = array_key_first($sourceErrors);

            if ($sourceErrors[$source] instanceof CosSourceRefreshException) {
                throw $sourceErrors[$source];
            }
            throw new CosSourceRefreshException($source, $sourceErrors[$source]);
        }

        $hubspot = $apiResults['hubspot'] ?? [];
        $teamleader = $apiResults['teamleader'] ?? [];

        // Never reconcile deals or renew a snapshot after an incomplete fetch.
        if (filled($user->hs_id) && (empty($hubspot['contact']) || ! is_array($hubspot['deals'] ?? null))) {
            throw new CosSourceRefreshException('hubspot');
        }

        if ($forceExternalRefresh && filled($user->tl_id)
            && (empty($teamleader['contact']) || ! is_array($teamleader['deals'] ?? null))) {
            throw new CosSourceRefreshException('teamleader');
        }

        $sync['hubspot']['attempted'] = filled($user->hs_id);
        $sync['hubspot']['contact'] = ! empty($hubspot['contact']);
        $sync['hubspot']['deals'] = count($hubspot['deals'] ?? []);
        $sync['teamleader']['attempted'] = true;
        $sync['teamleader']['contact'] = ! empty($teamleader['contact']);
        $sync['teamleader']['deals'] = count($teamleader['deals'] ?? []);

        if (! empty($hubspot['contact'])) {
            $sync['user_fields_updated'] = $this->applyHubspotUpdatesToUser($user, $hubspot['contact'], $syncService);
        }

        if (is_array($hubspot['deals'] ?? null)) {
            $sync['local_deals'] = $this->dealLocalSync->sync($user->fresh() ?? $user, $hubspot['deals']);

            // Refresh only reads the external platforms and updates local
            // information. Association changes belong to the explicit linking
            // action, never to a COS refresh or its enrichment side effects.
            $sync['automatic_associations'] = ['linked' => 0, 'skipped' => 'refresh_only'];
            app(TeamleaderDealEnrichmentService::class)->refreshLinkedDeals(
                $user,
                $hubspot['deals'],
                $teamleader['deals'] ?? [],
            );
        }

        return $sync;
    }

    private function linkExistingHubspotContact(User $user, bool $failOnError = false): ?array
    {
        $lookups = [
            'email' => fn () => filled($user->email) ? $this->hubspotService->searchContactByEmail($user->email) : null,
            'passport' => fn () => filled($user->passport) ? $this->hubspotService->searchContactByPassport($user->passport) : null,
        ];

        $lookupError = null;
        foreach ($lookups as $source => $lookup) {
            try {
                $contact = $lookup();
            } catch (\Throwable $e) {
                $lookupError = $e;
                Log::warning('COS/MCP: no se pudo buscar contacto existente en HubSpot', [
                    'user_id' => $user->id,
                    'source' => $source,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if (empty($contact['id'])) {
                continue;
            }

            app(HubspotClientIdentityGuard::class)->assertForUser($user, $contact);
            $user->hs_id = (string) $contact['id'];
            $user->save();

            Log::info('COS/MCP: contacto HubSpot existente vinculado', [
                'user_id' => $user->id,
                'source' => $source,
                'hs_id' => $contact['id'],
            ]);

            return [
                'source' => $source,
                'contact' => $contact,
            ];
        }

        if ($failOnError && $lookupError) {
            throw new \RuntimeException('No se pudo comprobar el contacto del cliente en HubSpot.', 0, $lookupError);
        }

        return null;
    }

    private function applyHubspotUpdatesToUser(User $user, array $contact, UserSyncService $syncService): array
    {
        $identity = app(HubspotClientIdentityGuard::class);
        // Validate even if the remote timestamp is older or no fields change:
        // deals and files must not be imported for an ambiguous identity.
        $identity->assertForUser($user, $contact);
        try {
            $fieldUpdates = $syncService->calculateFieldUpdates($user, $contact);
        } catch (\Throwable $e) {
            Log::warning('COS/MCP: no se pudieron calcular actualizaciones de HubSpot', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $userColumns = Schema::getColumnListing($user->getTable());
        $updatesToDB = array_intersect_key(
            $fieldUpdates['updatesToDB'] ?? [],
            array_flip($userColumns)
        );

        if (empty($updatesToDB)) {
            return [];
        }

        foreach ($updatesToDB as $field => $value) {
            $user->{$field} = $value;
        }

        try {
            $user->save();
        } catch (UniqueConstraintViolationException $exception) {
            // Another request may claim an identity after the preflight check.
            $identity->assertForUser($user, $contact, $exception);
            throw $exception;
        }

        Log::info('COS/MCP: usuario actualizado desde HubSpot', [
            'user_id' => $user->id,
            'fields_updated' => array_keys($updatesToDB),
        ]);

        return array_keys($updatesToDB);
    }

    private function refreshMondayData(User $user, bool $forceRefresh = false): array
    {
        $cacheKey = "monday_data_{$user->id}_{$user->monday_id}";
        $local = app(CosMondayLookup::class)->localFor($user);
        if ($local !== null) {
            $details = $local['item'];
            if ($details && blank($user->monday_id)) {
                $user->monday_id = $details['id'];
                $user->save();
            }
            $this->storeMondayUserData($user, $details);
            $data = $this->mondayCalculationData($details, 'local_mirror', $local['synced_at']);
            Cache::put("monday_data_{$user->id}_{$user->monday_id}", $data, 600);
            return $data;
        }

        if (CrmContactMirrorService::localReadsEnabled()) {
            $stored = MondayData::where('user_id', $user->id)->value('data');
            return $this->mondayCalculationData(is_array($stored) ? $stored : json_decode($stored ?: 'null', true), 'stored_snapshot');
        }

        $fetch = function () use ($user, $forceRefresh) {
            $mondayUserDetailsPre = null;
            if (! $user->monday_id) {
                $mondayUserDetailsPre = $this->searchUserInMonday($user->passport, $user, $forceRefresh);
                $user->refresh();
            }

            if (! $user->monday_id) {
                $this->storeMondayUserData($user, null);

                return [
                    'mondayUserDetails' => null,
                    'mondaydataforAI' => [],
                ];
            }

            $query = "
                items(ids: [{$user->monday_id}]) {
                    id
                    name
                    board {
                        id
                        name
                    }
                    column_values {
                        id
                        column {
                            title
                            type
                        }
                        text
                        value
                    }
                }
            ";

            if ($mondayUserDetailsPre === null) {
                $result = json_decode(json_encode(Monday::customQuery($query)), true);
                if (! empty($result['errors']) || ! is_array($result['items'] ?? null)) {
                    throw new \RuntimeException('Monday no devolvió los datos del cliente; se conserva el COS anterior.');
                }
                $mondayUserDetailsPre = $result['items'][0] ?? null;
            }

            $this->storeMondayUserData($user, $mondayUserDetailsPre);
            if ($mondayUserDetailsPre) {
                $boardId = $mondayUserDetailsPre['board']['id'] ?? null;

                if ($boardId) {
                    $this->storeMondayBoardColumns($boardId, $forceRefresh);
                }
            }

            $mondaydataforAI = [];

            if (isset($mondayUserDetailsPre['board']['name'])) {
                $mondaydataforAI['tablero'] = $mondayUserDetailsPre['board']['name'];
            }

            foreach ($mondayUserDetailsPre['column_values'] ?? [] as $column) {
                if (($column['id'] ?? null) === 'men__desplegable') {
                    $mondaydataforAI['etiquetas'] = $column['text'];
                    break;
                }
            }

            return [
                'mondayUserDetails' => $mondayUserDetailsPre,
                'mondaydataforAI' => $mondaydataforAI,
            ];
        };

        try {
            $data = $fetch();
            $data += ['source' => 'api', 'synced_at' => now()->toIso8601String(), 'fallback' => false];
        } catch (\Throwable $exception) {
            $stored = MondayData::where('user_id', $user->id)->first();
            $details = $stored ? (is_array($stored->data) ? $stored->data : json_decode($stored->data ?: 'null', true)) : null;
            // Never substitute a previous association or an empty/invalid record for a failed refresh.
            if (! is_array($details) || blank($details['id'] ?? null) || blank($details['board']['name'] ?? null)
                || ! is_array($details['column_values'] ?? null)
                || (filled($user->monday_id) && (string) $details['id'] !== (string) $user->monday_id)) {
                throw $exception;
            }
            $data = $this->mondayCalculationData($details, 'stored_snapshot_fallback', $stored->updated_at?->toIso8601String(), true);
            Log::warning('COS: se utiliza la última copia local de Monday', [
                'user_id' => $user->id, 'synced_at' => $data['synced_at'], 'error' => $exception->getMessage(),
            ]);
        }
        Cache::put($cacheKey, $data, 600);

        return $data;
    }

    private function mondayCalculationData(?array $details, string $source, ?string $syncedAt = null, bool $fallback = false): array
    {
        return [
            'mondayUserDetails' => $details,
            'mondaydataforAI' => $details ? [
                'tablero' => $details['board']['name'] ?? '',
                'etiquetas' => collect($details['column_values'] ?? [])->firstWhere('id', 'men__desplegable')['text'] ?? '',
            ] : [],
            'source' => $source, 'synced_at' => $syncedAt, 'fallback' => $fallback,
        ];
    }

    private function searchUserInMonday(?string $passport, User $user, bool $forceRefresh = false): ?array
    {
        return app(CosMondayLookup::class)->find($passport, $user, $forceRefresh);
    }

    private function storeMondayUserData(User $user, ?array $mondayUserDetailsPre): void
    {
        MondayData::updateOrCreate(
            ['user_id' => $user->id],
            ['data' => json_encode($mondayUserDetailsPre)]
        );
    }

    private function storeMondayBoardColumns($boardId, bool $forceRefresh = false): void
    {
        $fetch = function () use ($boardId) {
            $query = "
                boards(ids: [$boardId]) {
                    columns {
                        id
                        title
                        type
                        settings_str
                    }
                }
            ";

            $result = json_decode(json_encode(Monday::customQuery($query)), true);
            if (! empty($result['errors']) || ! is_array($result['boards'][0]['columns'] ?? null)) {
                throw new \RuntimeException('Monday no devolvió las columnas del tablero; se reintentará su actualización.');
            }
            $columns = $result['boards'][0]['columns'];

            foreach ($columns as $column) {
                $settings = $column['settings_str'] ? json_decode($column['settings_str'], true) : [];

                $tagIds = [];
                if (in_array($column['type'], ['tags', 'multi-select']) && isset($settings['tags'])) {
                    $tagIds = array_column($settings['tags'], 'id');
                }

                MondayFormBuilder::updateOrCreate(
                    ['board_id' => $boardId, 'column_id' => $column['id']],
                    [
                        'title' => $column['title'],
                        'type' => $column['type'],
                        'settings' => $column['settings_str'] ?: null,
                        'tag_ids' => $tagIds,
                    ]
                );
            }
            return true;
        };

        $cacheKey = 'cos.monday.columns.' . $boardId;
        if ($forceRefresh) {
            Cache::put($cacheKey, $fetch(), 3600);
        } else {
            Cache::remember($cacheKey, 3600, $fetch);
        }
    }

    private function handleNoNegocios(?Servicio $servicename, ?array $cos = null): array
    {
        $cos ??= $this->cosHelper->get();
        $serviceName = $servicename['id_hubspot'] ?? null;

        if (! $serviceName) {
            return [
                'servicio' => null,
                'serviceExists' => false,
                'error' => 'El usuario no tiene servicio asignado (id_hubspot es null).',
                'currentStepName' => null,
                'currentStepDetails' => null,
                'certificadoDescargado' => 0,
                'currentStepGen' => -1,
                'currentStepJur' => -1,
                'totalStepsGen' => 0,
            ];
        }

        if (! isset($cos[$serviceName])) {
            return [
                'servicio' => $serviceName,
                'serviceExists' => false,
                'error' => "Servicio no encontrado en COS: {$serviceName}",
                'currentStepName' => null,
                'currentStepDetails' => null,
                'certificadoDescargado' => 0,
                'currentStepGen' => -1,
                'currentStepJur' => -1,
                'totalStepsGen' => 0,
            ];
        }

        $genealogico = $cos[$serviceName]['genealogico'] ?? [];
        if (! is_array($genealogico) || empty($genealogico) || ! isset($genealogico[0])) {
            return [
                'servicio' => $serviceName,
                'serviceExists' => true,
                'error' => 'El servicio existe pero no tiene flujo genealogico configurado.',
                'currentStepName' => null,
                'currentStepDetails' => null,
                'certificadoDescargado' => 0,
                'currentStepGen' => -1,
                'currentStepJur' => -1,
                'totalStepsGen' => is_array($genealogico) ? count($genealogico) : 0,
            ];
        }

        $first = $genealogico[0];

        return [
            'servicio' => $serviceName,
            'serviceExists' => true,
            'error' => null,
            'currentStepName' => $first['nombre_largo'] ?? null,
            'currentStepDetails' => [
                'promesa' => $first['promesa'] ?? '',
                'promesa_pasado' => $first['promesa_pasado'] ?? $first['promesa'] ?? '',
                'textos_adicionales' => $first['textos_adicionales'] ?? [],
                'ctas' => $first['ctas'] ?? [],
            ],
            'certificadoDescargado' => 0,
            'currentStepGen' => 0,
            'currentStepJur' => -1,
            'totalStepsGen' => count($genealogico),
        ];
    }

    private function checkCosReady(array $cosuserFinal, array $cos): int
    {
        $cosKeys = array_map(fn ($key) => mb_strtolower($key), array_keys($cos));

        foreach ($cosuserFinal as $item) {
            $servicio = trim(mb_strtolower($item['servicio'] ?? ''));

            if ($servicio !== '' && in_array($servicio, $cosKeys)) {
                return 1;
            }
        }

        return 0;
    }

    private function removeDuplicatesAndSort(array $cosuser): array
    {
        $cosuserFinal = [];

        foreach ($cosuser as $item) {
            $servicio = $item['servicio'] ?? null;

            if (! $servicio) {
                continue;
            }

            if (! isset($cosuserFinal[$servicio])) {
                $cosuserFinal[$servicio] = $item;
                continue;
            }

            $existente = $cosuserFinal[$servicio];

            if (
                ($item['currentStepGen'] ?? 0) > ($existente['currentStepGen'] ?? 0)
                || ($item['currentStepJur'] ?? 0) > ($existente['currentStepJur'] ?? 0)
            ) {
                $cosuserFinal[$servicio] = $item;
            }
        }

        uasort($cosuserFinal, function ($a, $b) {
            $sumaA = ($a['currentStepGen'] ?? 0) + ($a['currentStepJur'] ?? 0);
            $sumaB = ($b['currentStepGen'] ?? 0) + ($b['currentStepJur'] ?? 0);

            return $sumaB <=> $sumaA;
        });

        return $cosuserFinal;
    }
}

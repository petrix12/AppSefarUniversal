<?php

namespace App\Services;

use App\Models\MondayData;
use App\Models\MondayFormBuilder;
use App\Models\Negocio;
use App\Models\Servicio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Monday;

class ClientCosSnapshotService
{
    private const CACHE_TTL_DAYS = 5;

    private const MONDAY_BOARD_IDS = [
        878831315,
        6524058079,
        3950637564,
        815474056,
        3639222742,
        3469085450,
        2213224176,
        1910043474,
        1845710504,
        1845706367,
        1845701215,
        1016436921,
        1026956491,
        815474056,
        815471640,
        807173414,
        803542982,
        765394861,
        742896377,
        708128239,
        708123651,
        669590637,
        625187241,
    ];

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

        return $this->refresh($user, $syncExternal);
    }

    public function refresh(User $user, bool $syncExternal = true): array
    {
        $startedAt = microtime(true);
        $user = $user->fresh() ?? $user;
        $sync = [
            'external' => $syncExternal,
            'cache' => ['hit' => false, 'ttl_days' => self::CACHE_TTL_DAYS],
            'hubspot' => ['attempted' => false, 'contact' => false, 'deals' => 0, 'linked_contact' => false],
            'teamleader' => ['attempted' => false, 'contact' => false, 'deals' => 0],
            'local_deals' => ['received' => 0, 'inserted' => 0, 'updated' => 0],
            'user_fields_updated' => [],
        ];

        if ($syncExternal) {
            $sync = $this->refreshExternalData($user, $sync);
            $user = $user->fresh() ?? $user;
        }

        $cos = $this->cosHelper->get();
        $negocios = Negocio::where('user_id', $user->id)->get();
        $mondayData = $this->getMondayDataCached($user);
        $servicename = $user->servicio
            ? Servicio::where('id_hubspot', 'like', $user->servicio . '%')->first()
            : null;

        $cosuser = [];

        if ($negocios->isNotEmpty()) {
            foreach ($negocios as $negocio) {
                $statusService = new CosService(
                    $negocio,
                    $user,
                    $negocios,
                    $mondayData['mondaydataforAI'] ?? []
                );

                $status = $statusService->calculateStatus();
                $cosuser[] = $statusService->calculateProgress($status);
            }
        } else {
            $cosuser[] = $this->handleNoNegocios($servicename);
        }

        $cosuserFinal = $this->removeDuplicatesAndSort($cosuser);
        $cosuserFinal = array_filter($cosuserFinal, function ($proceso) use ($cos) {
            return ! empty($proceso['servicio']) && array_key_exists($proceso['servicio'], $cos);
        });

        $user->arraycos = array_values($cosuserFinal);
        $user->arraycos_expire = Carbon::now()->addDays(self::CACHE_TTL_DAYS);
        $user->cosready = $this->checkCosReady($cosuserFinal, $cos);
        $user->save();

        Log::info('COS/MCP snapshot actualizado', [
            'user_id' => $user->id,
            'negocios_count' => $negocios->count(),
            'cos_count' => count($cosuserFinal),
            'sync_external' => $syncExternal,
        ]);

        return [
            'client' => $user->fresh(),
            'cos' => array_values($cosuserFinal),
            'cosready' => (bool) $user->cosready,
            'arraycos_expire' => optional($user->arraycos_expire)->toIso8601String(),
            'negocios_count' => $negocios->count(),
            'sync' => $sync,
            'monday' => [
                'id' => $user->monday_id,
                'board' => $mondayData['mondayUserDetails']['board']['name'] ?? null,
                'has_data' => ! empty($mondayData['mondayUserDetails']),
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    private function hasFreshCachedCos(User $user): bool
    {
        if ($user->arraycos === null || ! $user->arraycos_expire) {
            return false;
        }

        return Carbon::parse($user->arraycos_expire)->isFuture();
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
                'local_deals' => ['received' => 0, 'inserted' => 0, 'updated' => 0],
                'user_fields_updated' => [],
            ],
            'monday' => [
                'id' => $user->monday_id,
                'board' => $mondayUserDetails['board']['name'] ?? null,
                'has_data' => ! empty($mondayUserDetails),
            ],
            'generated_at' => Carbon::now()->toIso8601String(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    private function refreshExternalData(User $user, array $sync): array
    {
        $syncService = new UserSyncService($this->hubspotService, $this->teamleaderService);

        if (! filled($user->hs_id)) {
            $linkedContact = $this->linkExistingHubspotContact($user);

            if ($linkedContact) {
                $sync['hubspot']['linked_contact'] = true;
                $sync['hubspot']['linked_by'] = $linkedContact['source'];
                $user = $user->fresh() ?? $user;
            }
        }

        $callbacks = [
            'teamleader' => fn () => $syncService->syncWithTeamleader($user),
        ];

        if (filled($user->hs_id)) {
            $callbacks['hubspot'] = fn () => $syncService->syncWithHubspot($user);
        } else {
            $sync['hubspot']['skipped'] = 'missing_hs_id_no_existing_contact';
        }

        try {
            $apiResults = $this->hubspotService->executeConcurrent($callbacks);
        } catch (\Throwable $e) {
            Log::error('COS/MCP: error refrescando fuentes externas', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $sync['error'] = $e->getMessage();

            return $sync;
        }

        $hubspot = $apiResults['hubspot'] ?? [];
        $teamleader = $apiResults['teamleader'] ?? [];

        $sync['hubspot']['attempted'] = filled($user->hs_id);
        $sync['hubspot']['contact'] = ! empty($hubspot['contact']);
        $sync['hubspot']['deals'] = count($hubspot['deals'] ?? []);
        $sync['teamleader']['attempted'] = true;
        $sync['teamleader']['contact'] = ! empty($teamleader['contact']);
        $sync['teamleader']['deals'] = count($teamleader['deals'] ?? []);

        if (! empty($hubspot['contact'])) {
            $sync['user_fields_updated'] = $this->applyHubspotUpdatesToUser($user, $hubspot['contact'], $syncService);
        }

        if (! empty($hubspot['deals']) && is_array($hubspot['deals'])) {
            $sync['local_deals'] = $this->dealLocalSync->sync($user->fresh() ?? $user, $hubspot['deals']);
        }

        return $sync;
    }

    private function linkExistingHubspotContact(User $user): ?array
    {
        $lookups = [
            'email' => fn () => filled($user->email) ? $this->hubspotService->searchContactByEmail($user->email) : null,
            'passport' => fn () => filled($user->passport) ? $this->hubspotService->searchContactByPassport($user->passport) : null,
        ];

        foreach ($lookups as $source => $lookup) {
            try {
                $contact = $lookup();
            } catch (\Throwable $e) {
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

        return null;
    }

    private function applyHubspotUpdatesToUser(User $user, array $contact, UserSyncService $syncService): array
    {
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

        $user->save();

        Log::info('COS/MCP: usuario actualizado desde HubSpot', [
            'user_id' => $user->id,
            'fields_updated' => array_keys($updatesToDB),
        ]);

        return array_keys($updatesToDB);
    }

    private function getMondayDataCached(User $user): array
    {
        $cacheKey = "monday_data_{$user->id}_{$user->monday_id}";

        return Cache::remember($cacheKey, 600, function () use ($user) {
            if (! $user->monday_id) {
                $this->searchUserInMonday($user->passport, $user);
                $user->refresh();
            }

            if (! $user->monday_id) {
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

            $result = json_decode(json_encode(Monday::customQuery($query)), true);
            $mondayUserDetailsPre = $result['items'][0] ?? null;

            if ($mondayUserDetailsPre) {
                $this->storeMondayUserData($user, $mondayUserDetailsPre);
                $boardId = $mondayUserDetailsPre['board']['id'] ?? null;

                if ($boardId) {
                    $this->storeMondayBoardColumns($boardId);
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
        });
    }

    private function searchUserInMonday(?string $passport, User $user): ?array
    {
        if (! $passport) {
            return null;
        }

        $searchUrl = 'https://app.sefaruniversal.com/tree/' . $passport;

        foreach (self::MONDAY_BOARD_IDS as $boardId) {
            $query = "
                items_page_by_column_values(
                    limit: 50,
                    board_id: {$boardId},
                    columns: [{column_id: \"enlace\", column_values: [\"{$searchUrl}\"]}]
                ) {
                    cursor
                    items {
                        id
                        name
                        board {
                            name
                        }
                        column_values {
                            id
                            column {
                                title
                            }
                            text
                        }
                    }
                }
            ";

            $result = json_decode(json_encode(Monday::customQuery($query)), true);

            if (! empty($result['items_page_by_column_values']['items'])) {
                $item = $result['items_page_by_column_values']['items'][0];
                $user->monday_id = $item['id'];
                $user->save();

                return $item;
            }
        }

        return null;
    }

    private function storeMondayUserData(User $user, array $mondayUserDetailsPre): void
    {
        MondayData::updateOrCreate(
            ['user_id' => $user->id],
            ['data' => json_encode($mondayUserDetailsPre)]
        );
    }

    private function storeMondayBoardColumns($boardId): void
    {
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
        $columns = $result['boards'][0]['columns'] ?? [];

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
    }

    private function handleNoNegocios(?Servicio $servicename): array
    {
        $cos = $this->cosHelper->get();
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

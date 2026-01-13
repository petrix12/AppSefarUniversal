<?php

namespace App\Http\Controllers;

use App\Mail\CargaCliente;
use App\Mail\CargaSefar;
use App\Models\Agcliente;
use App\Models\Coupon;
use App\Models\Servicio;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\Compras;
use App\Models\HsReferido;
use App\Models\Factura;
use App\Models\User;
use App\Models\File;
use App\Models\Negocio;
use App\Models\TFile;
use Monday;
use App\Models\Hermano;
use App\Models\Alert as Alertas;
use App\Models\GeneralCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Stripe;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\CardException;
use Stripe\Exception\RateLimitException;
use Stripe\Exception\InvalidRequestException;
use Stripe\Exception\AuthenticationException;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use RealRashid\SweetAlert\Facades\Alert;
use Exception;
use HubSpot;
use HubSpot\Client\Crm\Deals\Model\AssociationSpec;
use HubSpot\Client\Crm\Associations\Model\BatchInputPublicObjectId;
use HubSpot\Client\Crm\Associations\Model\PublicObjectId;
use HubSpot\Factory;
use HubSpot\Client\Crm\Contacts\ApiException;
use HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput;
use Barryvdh\DomPDF\Facade\Pdf;
use Mail;
use Illuminate\Support\Facades\Mail as Mail2;
use Carbon\Carbon;
use App\Services\TeamleaderService;
use App\Services\HubspotService;
use Illuminate\Support\Facades\Schema;
use App\Models\MondayData;
use App\Models\MondayFormBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use App\Models\DocumentRequest;
use Illuminate\Support\Facades\Storage;
use App\Services\UserSyncService;
use App\Services\GenealogyService;
use App\Jobs\SyncUserDealsJob;
use App\Services\CosService;
use Illuminate\Support\Facades\Cache;
class ClienteController extends Controller
{
    protected $teamleaderService;
    protected $hubspotService;

    public function __construct(TeamleaderService $teamleaderService, HubspotService $hubspotService)
    {
        $this->teamleaderService = $teamleaderService;
        $this->hubspotService = $hubspotService;
    }

    public function pagospendientes(){
        $user = Auth::user()->id;

        $compras = Compras::where('id_user', $user)->where('pagado', 0)->whereNotNull("deal_id")->get();

        return view('clientes.pagospendientes', compact('compras'));
    }

    private function searchUserInMonday($passport, User $user)
    {
        $boardIds = [
            878831315,
            6524058079, 3950637564, 815474056, 3639222742, 3469085450, 2213224176,
            1910043474, 1845710504, 1845706367, 1845701215, 1016436921,
            1026956491, 815474056, 815471640, 807173414,
            803542982, 765394861, 742896377, 708128239, 708123651,
            669590637, 625187241
        ];

        $searchUrl = "https://app.sefaruniversal.com/tree/" . $passport;

        foreach ($boardIds as $boardId) {
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

            if (!empty($result['items_page_by_column_values']['items'])) {
                $item = $result['items_page_by_column_values']['items'][0];
                $user->monday_id = $item['id']; // Guardar el ID de Monday
                $user->save();
                return $item;
            }
        }

        return null;
    }

    private function getRandomImages(): array
    {
        $cacheKey = "random_images";

        return Cache::remember($cacheKey, 3600, function () {
            $path = public_path('img/IMAGENESCOS/');

            if (!is_dir($path)) {
                return [];
            }

            $files = scandir($path);

            $images = array_filter($files, function($file) {
                return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file);
            });

            $images = array_values($images);
            shuffle($images);

            return array_map(function($image) {
                return asset('img/IMAGENESCOS/' . $image);
            }, $images);
        });
    }

    public function status(){
        $user = Auth::user();

        $startTime = microtime(true);

        // ==========================================
        // CACHE BÁSICO
        // ==========================================
        $imageUrls = $this->getRandomImages();
        $cos = app(\App\Services\CosHelperService::class)->get();

        // ==========================================
        // SINCRONIZACIÓN CONCURRENTE DE APIS
        // ==========================================
        $syncService = new UserSyncService($this->hubspotService, $this->teamleaderService);

        // Ejecutar sincronizaciones en paralelo usando el método existente
        $apiResults = $this->hubspotService->executeConcurrent([
            'hubspot' => fn() => $syncService->syncWithHubspot($user),
            'teamleader' => fn() => $syncService->syncWithTeamleader($user),
        ]);

        // Extraer resultados
        $HScontact = $apiResults['hubspot']['contact'] ?? null;
        $HScontactFiles = $apiResults['hubspot']['files'] ?? [];
        $deals = $apiResults['hubspot']['deals'] ?? [];
        $TLcontact = $apiResults['teamleader']['contact'] ?? null;
        $TLdeals = $apiResults['teamleader']['deals'] ?? [];

        if (!$HScontact) {
            Log::error("No se pudo obtener datos de HubSpot para el usuario", ['user_id' => $user->id]);
            abort(500, "Error al sincronizar con HubSpot");
        }

        // ==========================================
        // OBTENER ETAPAS DE PIPELINES
        // ==========================================
        $pipelineStages = [];
        $usedPipelineIds = array_unique(array_filter(
            array_column(array_column($deals, 'properties'), 'pipeline')
        ));

        foreach ($usedPipelineIds as $pipelineId) {
            try {
                $pipelineStages[$pipelineId] = $this->hubspotService->getDealStagesByPipeline($pipelineId);
            } catch (\Exception $e) {
                Log::warning("Error obteniendo etapas del pipeline {$pipelineId}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Procesar deals con etapas
        $dealsWithStages = array_map(function ($deal) use ($pipelineStages) {
            $properties = $deal['properties'];
            $dealstageId = $properties['dealstage'] ?? null;
            $pipelineId = $properties['pipeline'] ?? null;

            $dealstageName = null;
            $dealstageOptions = [];

            if ($pipelineId && isset($pipelineStages[$pipelineId])) {
                $dealstageName = collect($pipelineStages[$pipelineId])
                    ->firstWhere('id', $dealstageId)['name'] ?? null;
                $dealstageOptions = $pipelineStages[$pipelineId];
            }

            return array_merge($deal, [
                'dealstage_name' => $dealstageName,
                'dealstage_options' => $dealstageOptions,
            ]);
        }, $deals);

        // ==========================================
        // SINCRONIZACIÓN DE CAMPOS
        // ==========================================
        $fieldUpdates = $syncService->calculateFieldUpdates($user, $HScontact);

        // Actualizar DB si hay cambios
        if (!empty($fieldUpdates['updatesToDB'])) {
            foreach ($fieldUpdates['updatesToDB'] as $field => $value) {
                $user->{$field} = $value;
            }
            $user->save();

            Log::info("Usuario actualizado desde HubSpot", [
                'user_id' => $user->id,
                'fields_updated' => array_keys($fieldUpdates['updatesToDB'])
            ]);
        }

        // Actualizar HubSpot de forma asíncrona si hay cambios
        if (!empty($fieldUpdates['updatesToHubSpot'])) {
            dispatch(new \App\Jobs\UpdateHubspotContactJob(
                $user->hs_id,
                $fieldUpdates['updatesToHubSpot'],
                $this->hubspotService
            ));
        }

        // ==========================================
        // SINCRONIZACIÓN DE DEALS (ASÍNCRONO)
        // ==========================================
        dispatch(new SyncUserDealsJob($user));

        // Obtener deals existentes de la DB
        $negocios = Negocio::where("user_id", $user->id)->get();

        // ==========================================
        // MONDAY (CON CACHE)
        // ==========================================
        $mondayData = $this->getMondayDataCached($user);

        // ==========================================
        // GENEALOGÍA (CON CACHE)
        // ==========================================
        $genealogyService = new GenealogyService();
        $genealogyData = $genealogyService->getProcessedTree($user->passport);

        $columnasparatabla = $genealogyData['columnasparatabla'];
        $hayTatarabuelo = $genealogyData['hayTatarabuelo'];

        // ==========================================
        // CUSTOMER ORDER STATUS
        // ==========================================
        $servicename = Servicio::where("id_hubspot", "like", $user->servicio."%")->first();

        $cosuser = [];

        if (count($negocios) > 0) {
            foreach ($negocios as $negocio) {
                $statusService = new CosService(
                    $negocio,
                    $user,
                    $negocios,
                    $mondayData['mondaydataforAI'] ?? []
                );

                $status = $statusService->calculateStatus();
                $status = $statusService->calculateProgress($status);

                $cosuser[] = $status;
            }
        } else {
            $cosuser[] = $this->handleNoNegocios($user, $servicename);
        }

        // Procesar y eliminar duplicados
        $cosuserFinal = $this->removeDuplicatesAndSort($cosuser);

        $cosuser = array_filter($cosuserFinal, function ($proceso) use ($cos) {
                        return array_key_exists($proceso['servicio'], $cos);
                    });

        $cosuserFinal = $cosuser;

        // Guardar en usuario
        $user->arraycos = array_values($cosuserFinal);
        $user->arraycos_expire = Carbon::now()->addDays(2);
        $user->cosready = $this->checkCosReady($cosuserFinal, $cos);
        $user->save();

        // ==========================================
        // DATOS ADICIONALES
        // ==========================================
        $comprasConDealNoPagadas = Compras::where('deal_id', '!=', null)
            ->where('pagado', 0)
            ->where('id_user', $user->id)
            ->get();

        $comprasSinDealNoPagadas = Compras::whereNull('deal_id')
            ->where('pagado', 0)
            ->where('id_user', $user->id)
            ->get();

        $documentRequests = DocumentRequest::where('user_id', $user->id)
            ->latest()
            ->get();

        $archivos = File::where("IDCliente", $user->passport)->get();

        $facturas = Factura::with('compras')
            ->where('id_cliente', $user->id)
            ->get();

        // ==========================================
        // PREPARAR DATOS PARA VISTA
        // ==========================================
        $usuariosMonday = $this->getUsersForSelect()->original ?? [];
        $roles = Role::all();
        $permissions = Permission::all();
        $servicios = Servicio::all();

        // Procesar URLs de archivos (ya existente en tu código)
        $urls = $this->hubspotService->getEngagementsByContactId($user->hs_id);
        $processedUrls = $this->processFilesConcurrently($urls, $user, $this->hubspotService);
        $processedContactFiles = $this->processFilesConcurrently($HScontactFiles, $user, $this->hubspotService);

        // ==========================================
        // LOG DE RENDIMIENTO
        // ==========================================
        $executionTime = microtime(true) - $startTime;
        Log::info("Edit ejecutado", [
            'user_id' => $user->id,
            'execution_time' => round($executionTime, 2) . 's',
            'negocios_count' => count($negocios)
        ]);

        // ==========================================
        // RENDERIZAR VISTA
        // ==========================================
        $cosuser = array_values($cosuserFinal);

        // Extraer datos de Monday para vista
        $dataMonday = [];
        $mondayFormBuilder = [];
        $mondayUserDetails = [];
        $boardId = 0;
        $boardName = "";

        if (isset($mondayData['mondayUserDetails']) && !empty($mondayData['mondayUserDetails'])) {
            $mondayUserDetailsPre = $mondayData['mondayUserDetails'];
            $boardId = $mondayUserDetailsPre['board']['id'] ?? 0;
            $boardName = $mondayUserDetailsPre['board']['name'] ?? "";

            // Obtener form builder si hay board
            if ($boardId) {
                $mondayFormBuilder = MondayFormBuilder::where('board_id', $boardId)
                    ->get()
                    ->map(function ($item) {

                        // settings puede venir como string (data vieja) o array (casts / data nueva)
                        if (is_string($item->settings)) {
                            $item->settings = json_decode($item->settings, true) ?: [];
                        } elseif (is_null($item->settings)) {
                            $item->settings = [];
                        }

                        // tag_ids igual (por si lo usas en la vista)
                        if (is_string($item->tag_ids)) {
                            $item->tag_ids = json_decode($item->tag_ids, true) ?: [];
                        } elseif (is_null($item->tag_ids)) {
                            $item->tag_ids = [];
                        }

                        return $item;
                    })
                    ->toArray();
            }

            // Procesar columnas
            $dataMonday = [];
            if (isset($mondayUserDetailsPre['column_values'])) {
                foreach($mondayUserDetailsPre['column_values'] as $campo){
                    $dataMonday[$campo["id"]] = $campo["text"];
                }
            }

            // Preparar detalles del usuario
            $mondayUserDetails = [
                "nombre" => $mondayUserDetailsPre["name"],
                "id" => $mondayUserDetailsPre["id"],
                "propiedades" => []
            ];

            foreach($mondayUserDetailsPre["column_values"] as $element){
                $mondayUserDetails["propiedades"][$element["id"]] = [
                    $element["column"]["title"],
                    $element["text"]
                ];
            }
        }

        $html = view('crud.users.edit', compact(
            'documentRequests',
            'comprasConDealNoPagadas',
            'comprasSinDealNoPagadas',
            'imageUrls',
            'cosuser',
            'cos',
            'servicename',
            'negocios',
            'usuariosMonday',
            'dataMonday',
            'mondayData',
            'mondayFormBuilder',
            'mondayUserDetails',
            'boardId',
            'boardName',
            'archivos',
            'user',
            'roles',
            'permissions',
            'facturas',
            'servicios',
            'columnasparatabla'
        ))->render();

        return $html;
    }

    private function checkCosReady($cosuserFinal, $cos): int
    {
        foreach ($cosuserFinal as $item) {
            $servicio = trim(mb_strtolower($item['servicio']));
            $cosKeys = array_map(fn($k) => mb_strtolower($k), array_keys($cos));

            if (in_array($servicio, $cosKeys)) {
                return 1;
            }
        }

        return 0;
    }

    private function handleNoNegocios($user, $servicename): array
    {
        $cos = app(\App\Services\CosHelperService::class)->get();

        // Servicio solicitado (NO inventamos ninguno)
        $serviceName = $servicename["id_hubspot"] ?? null;

        // Si no viene servicio, también es inválido (decides si esto debe ser error duro)
        if (!$serviceName) {
            return [
                "servicio" => null,
                "serviceExists" => false,
                "error" => "El usuario no tiene servicio asignado (id_hubspot es null).",
                "currentStepName" => null,
                "currentStepDetails" => null,
                "certificadoDescargado" => 0,
                "currentStepGen" => -1,
                "currentStepJur" => -1,
                "totalStepsGen" => 0,
            ];
        }

        // Validar que exista EXACTAMENTE ese servicio en COS (sin fallback)
        if (!isset($cos[$serviceName])) {
            return [
                "servicio" => $serviceName,
                "serviceExists" => false,
                "error" => "Servicio no encontrado en COS: {$serviceName}",
                "currentStepName" => null,
                "currentStepDetails" => null,
                "certificadoDescargado" => 0,
                "currentStepGen" => -1,
                "currentStepJur" => -1,
                "totalStepsGen" => 0,
            ];
        }

        // Validar que tenga genealogico y al menos 1 paso
        $genealogico = $cos[$serviceName]["genealogico"] ?? [];
        if (!is_array($genealogico) || empty($genealogico) || !isset($genealogico[0])) {
            return [
                "servicio" => $serviceName,
                "serviceExists" => true,
                "error" => "El servicio existe pero no tiene flujo genealógico configurado.",
                "currentStepName" => null,
                "currentStepDetails" => null,
                "certificadoDescargado" => 0,
                "currentStepGen" => -1,
                "currentStepJur" => -1,
                "totalStepsGen" => is_array($genealogico) ? count($genealogico) : 0,
            ];
        }

        $first = $genealogico[0];

        return [
            "servicio" => $serviceName,
            "serviceExists" => true,
            "error" => null,
            "currentStepName" => $first["nombre_largo"] ?? null,
            "currentStepDetails" => [
                "promesa" => $first["promesa"] ?? "",
                "textos_adicionales" => $first["textos_adicionales"] ?? [],
                "ctas" => $first["ctas"] ?? [],
            ],
            "certificadoDescargado" => 0,
            "currentStepGen" => 0,
            "currentStepJur" => -1,
            "totalStepsGen" => count($genealogico),
        ];
    }

    private function removeDuplicatesAndSort(array $cosuser): array
    {
        $cosuserFinal = [];

        foreach ($cosuser as $item) {
            $servicio = $item['servicio'];

            if (!isset($cosuserFinal[$servicio])) {
                $cosuserFinal[$servicio] = $item;
            } else {
                $existente = $cosuserFinal[$servicio];

                if (
                    ($item['currentStepGen'] ?? 0) > ($existente['currentStepGen'] ?? 0) ||
                    ($item['currentStepJur'] ?? 0) > ($existente['currentStepJur'] ?? 0)
                ) {
                    $cosuserFinal[$servicio] = $item;
                }
            }
        }

        uasort($cosuserFinal, function ($a, $b) {
            $sumaA = ($a['currentStepGen'] ?? 0) + ($a['currentStepJur'] ?? 0);
            $sumaB = ($b['currentStepGen'] ?? 0) + ($b['currentStepJur'] ?? 0);
            return $sumaB <=> $sumaA;
        });

        return $cosuserFinal;
    }

    private function getMondayDataCached(User $user): array
    {
        $cacheKey = "monday_data_{$user->id}_{$user->monday_id}";

        return Cache::remember($cacheKey, 600, function () use ($user) {
            if (!$user->monday_id) {
                $this->searchUserInMonday($user->passport, $user);
            }

            if (!$user->monday_id) {
                return [
                    'mondayUserDetails' => null,
                    'mondaydataforAI' => []
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

            // Extraer etiquetas si existen
            if (isset($mondayUserDetailsPre['column_values'])) {
                foreach ($mondayUserDetailsPre['column_values'] as $column) {
                    if ($column['id'] === 'men__desplegable') {
                        $mondaydataforAI['etiquetas'] = $column['text'];
                        break;
                    }
                }
            }

            return [
                'mondayUserDetails' => $mondayUserDetailsPre,
                'mondaydataforAI' => $mondaydataforAI
            ];
        });
    }

    private function verificarNegocioActivo($negocios, $servicioSolicitado, $palabrasClave = []) {
        // Campos que indican que hay un pago
        $camposPago = [
            'fase_0_pagado', 'fase_0_pagado__teamleader_',
            'fase_1_pagado', 'fase_1_pagado__teamleader_', 'fase_1_preestab',
            'fase_2_pagado', 'fase_2_pagado__teamleader_', 'fase_2_preestab',
            'fase_3_pagado', 'fase_3_pagado__teamleader_', 'fase_3_preestab',
            'fcje_pagado', 'fcje_preestab',
            'fecha_fase_0_pagado', 'fecha_fase_1_pagado', 'fecha_fase_2_pagado', 'fecha_fase_3_pagado',
            'monto_fase_1_pagado', 'monto_fase_2_pagado', 'monto_fase_3_pagado'
        ];

        foreach ($negocios as $negocioItem) {
            // Buscar por servicio_solicitado exacto
            $coincideServicio = isset($negocioItem->servicio_solicitado) &&
                            $negocioItem->servicio_solicitado === $servicioSolicitado;

            // Buscar por palabras clave en dealname
            $coincideDealname = false;
            if (!empty($palabrasClave) && isset($negocioItem->dealname)) {
                $dealnameLower = mb_strtolower($negocioItem->dealname);
                $todasCoinciden = true;

                foreach ($palabrasClave as $palabra) {
                    if (strpos($dealnameLower, mb_strtolower($palabra)) === false) {
                        $todasCoinciden = false;
                        break;
                    }
                }

                $coincideDealname = $todasCoinciden;
            }

            // Si encontramos el negocio (por servicio O por dealname)
            if ($coincideServicio || $coincideDealname) {
                // Verificar si tiene algún pago
                foreach ($camposPago as $campo) {
                    if (isset($negocioItem->{$campo}) && !empty($negocioItem->{$campo})) {
                        return true; // ✅ Negocio encontrado Y con pagos
                    }
                }

                // Negocio encontrado pero SIN pagos (está en "interesado")
                return false;
            }
        }

        // No se encontró ningún negocio relacionado
        return false;
    }

     protected function getHubspotData($hsId)
    {
        // Ejecutar llamadas concurrentes
        $result = $this->hubspotService->executeConcurrent([
            'contact' => fn() => $this->hubspotService->getContactByIdPromise($hsId),
            'files' => fn() => $this->hubspotService->getEngagementsByContactIdPromise($hsId),
            'urls' => fn() => $this->hubspotService->getContactFileFieldsPromise($hsId),
            'deals' => fn() => $this->hubspotService->getDealsByContactIdPromise($hsId)
        ]);

        return $result;
    }

    protected function syncUserWithHubspot($user)
    {
        $HScontact = $this->hubspotService->searchContactByEmail($user->email)
                ?? $this->hubspotService->searchContactByPassport($user->passport);

        if (!$HScontact) {
            throw new \Exception("El contacto no se encontró en HubSpot ni por email ni por pasaporte.");
        }

        $user->update([
            'hs_id' => $HScontact['id'],
            'email' => $HScontact['properties']['email'] ?? $user->email,
            'nombres' => $HScontact['properties']['firstname'] ?? $user->nombres,
            'apellidos' => $HScontact['properties']['lastname'] ?? $user->apellidos
        ]);
    }



    /**
     * Procesa archivos de forma concurrente desde URLs de HubSpot
     *
     * @param array $urls Array de URLs a procesar
     * @param mixed $user Objeto del usuario
     * @param HubspotService $hubspotService Instancia del servicio
     * @return array Archivos procesados exitosamente
     */
    private function processFilesConcurrently(array $urls, $user, HubspotService $hubspotService): array
    {
        // 1. Configuración inicial
        $client = new Client(['timeout' => 15]);
        $processedFiles = [];

        // 2. Obtener archivos existentes en una sola consulta
        $existingFiles = File::where('IDCliente', $user->passport)
            ->pluck('file')
            ->toArray();

        // 3. Preparar promesas para descargas concurrentes
        $promises = [];
        $validFiles = [];

        foreach ($urls as $url) {
            try {
                $fileUrl = $hubspotService->getFileUrlFromFormIntegrations($url);
                if (!$fileUrl) continue;

                $filename = basename(parse_url($fileUrl, PHP_URL_PATH));
                $s3Path = "public/doc/{$user->passport}/{$filename}";

                // Verificar si ya existe
                if (in_array($filename, $existingFiles)) {
                    continue;
                }
                if (Storage::disk('s3')->exists($s3Path)) {
                    continue;
                }

                $validFiles[$filename] = $s3Path;
                $promises[$filename] = $client->getAsync($fileUrl);

            } catch (\Exception $e) {
                \Log::error("Error preparando descarga: {$e->getMessage()}");
            }
        }

        // 4. Ejecutar descargas concurrentes
        $responses = Promise\Utils::settle($promises)->wait();

        // 5. Procesar resultados
        foreach ($responses as $filename => $response) {
            if ($response['state'] !== 'fulfilled') {
                \Log::warning("Fallo descarga: {$filename}");
                continue;
            }

            try {
                $fileContent = $response['value']->getBody();
                $s3Path = $validFiles[$filename];

                // Subir a S3
                Storage::disk('s3')->put($s3Path, $fileContent);

                // Registrar en DB
                File::create([
                    'file' => $filename,
                    'location' => "public/doc/{$user->passport}/",
                    'IDCliente' => $user->passport,
                ]);

                $processedFiles[] = $filename;

            } catch (\Exception $e) {
                \Log::error("Error procesando {$filename}: {$e->getMessage()}");
            }
        }

        return $processedFiles;
    }

    private function obtenerValorPorTitulo($items, $tituloBuscado) {
        // Asegurarse de que hay items y column_values
        if (!isset($items['items'][0]['column_values'])) {
            return null;
        }

        foreach ($items['items'][0]['column_values'] as $columna) {
            if (isset($columna['column']['title']) &&
                strcasecmp(trim($columna['column']['title']), trim($tituloBuscado)) === 0) {
                return $columna['text'] ?? null;
            }
        }

        return null;
    }

    private function syncDealFieldsBetweenPlatforms($hubspotDeals, $teamleaderDeals, $camposRelacionados, $user)
    {
        $updatesToHubspotAll = [];
        $updatesToTeamleaderAll = [];
        $updatesToDBAll = [];

        // Indexar por dealname
        $hubspotByName = collect($hubspotDeals)->keyBy(fn($d) => $d['properties']['dealname'] ?? '');
        $teamleaderByName = collect($teamleaderDeals)->keyBy(fn($d) => $d['title'] ?? '');

        // 1. Crear faltantes en HubSpot
        foreach ($teamleaderByName as $dealName => $tlDeal) {
            if (!$hubspotByName->has($dealName)) {
                try {
                    $newHsDeal = $this->hubspotService->createDealInHubspotFromTL($tlDeal, $user->hs_id ?? null, $camposRelacionados);
                    $hubspotByName->put($dealName, $newHsDeal); // actualiza colección
                    Log::info("Trato creado en HubSpot desde TL: " . $dealName);
                } catch (\Exception $e) {
                    Log::error("Error creando trato en HubSpot desde TL: " . $e->getMessage());
                }
            }
        }

        // 2. Crear faltantes en Teamleader
        foreach ($hubspotByName as $dealName => $hsDeal) {
            if (!$teamleaderByName->has($dealName)) {
                try {
                    $newTLDeal = $this->teamleaderService->createProjectFromHubspotDeal($hsDeal, $user->tl_id, $camposRelacionados);
                    $teamleaderByName->put($dealName, array_merge($newTLDeal, ['title' => $dealName]));
                    Log::info("Trato creado en Teamleader desde HubSpot: " . $dealName);
                } catch (\Exception $e) {
                    Log::error("Error creando trato en TL desde HubSpot: " . $e->getMessage());
                }
            }
        }

        // 3. Sincronizar valores
        foreach ($hubspotByName as $dealName => $hsDeal) {
            $hubspotId = $hsDeal['id'] ?? null;
            $hubspotProps = $hsDeal['properties'] ?? [];

            $tlDeal = $teamleaderByName->get($dealName);
            $teamleaderId = $tlDeal['id'] ?? null;
            $existingFields = $tlDeal['custom_fields'] ?? [];

            $hubspotLastMod = new \DateTime($hubspotProps['lastmodifieddate'] ?? '1970-01-01');
            $teamleaderLastMod = new \DateTime($tlDeal['updated_at'] ?? '1970-01-01');

            $tlCustomFields = [];
            $hsUpdates = [];
            $dbUpdates = [];

            foreach ($camposRelacionados as $hsField => $tlFieldId) {
                $hsValue = $hubspotProps[$hsField] ?? null;
                $tlField = collect($existingFields)->firstWhere('definition.id', $tlFieldId);
                $tlValue = $tlField['value'] ?? null;

                // 🚨 Excepción: HubSpot manda en servicio_solicitado
                if ($hsField === 'servicio_solicitado2') {
                    if (!is_null($hsValue)) {
                        // Guardamos SIEMPRE en DB desde HubSpot
                        $dbUpdates['servicio_solicitado2'] = $hsValue;
                        $dbUpdates['servicio_solicitado']  = $hsValue; // ✅ Mantén sincronizado también servicio_solicitado

                        // Opcional: actualiza también en HubSpot el campo "servicio_solicitado"
                        $hsUpdates['servicio_solicitado'] = $hsValue;

                        // Y en Teamleader
                        $tlCustomFields[] = [
                            'id' => $tlFieldId,
                            'value' => $hsValue
                        ];
                    }
                    continue;
                }

                // --- Lógica normal para los demás campos ---
                $finalValue = null;
                if ($hsValue && (!$tlValue || $hubspotLastMod > $teamleaderLastMod)) {
                    $finalValue = $hsValue;
                } else if ($tlValue) {
                    $finalValue = $tlValue;
                }

                if (!is_null($finalValue)) {
                    $tlCustomFields[] = [
                        'id' => $tlFieldId,
                        'value' => $finalValue
                    ];

                    if ($tlValue && (!$hsValue || $teamleaderLastMod > $hubspotLastMod)) {
                        $hsUpdates[$hsField] = $tlValue;
                    }

                    $dbUpdates[$hsField] = $finalValue;
                }
            }

            if ($teamleaderId && !empty($tlCustomFields)) {
                $updatesToTeamleaderAll[$teamleaderId] = [
                    'custom_fields' => $tlCustomFields
                ];
            }

            if (!empty($hsUpdates) && $hubspotId) {
                $updatesToHubspotAll[$hubspotId] = $hsUpdates;
            }

            if (!empty($dbUpdates)) {
                $updatesToDBAll[] = [
                    'hubspot_id' => $hubspotId,
                    'teamleader_id' => $teamleaderId,
                    'user_id' => $user->id,
                    'fields' => $dbUpdates
                ];
            }
        }

        // Actualizar HubSpot
        foreach ($updatesToHubspotAll as $dealId => $fields) {
            $this->hubspotService->updateDeals($dealId, $fields);
        }

        // Actualizar Teamleader
        foreach ($updatesToTeamleaderAll as $tlDealId => $payload) {
            $this->teamleaderService->updateProject($tlDealId, $payload);
        }

        // Actualizar DB

        foreach ($updatesToDBAll as $entry) {
            $negocio = Negocio::firstOrNew([
                'hubspot_id' => $entry['hubspot_id']
            ]);
            $negocio->user_id = $entry['user_id'];
            $negocio->teamleader_id = $entry['teamleader_id'];

            foreach ($entry['fields'] as $field => $value) {
                if (Schema::hasColumn((new Negocio)->getTable(), $field) && $field != 'documentos') {
                    if (is_array($value)) {
                        // Puedes usar json_encode o implode dependiendo del caso
                        $negocio->{$field} = json_encode($value); // o implode(', ', $value)
                    } else {
                        $negocio->{$field} = $value;
                    }
                }
            }

            $negocio->save();
        }
    }

    protected function processParent(array &$currentGen, array $parent, string $field, string $sex, array $peopleMap): void
    {
        $j = count($currentGen);

        if (empty($parent[$field])) {
            $currentGen[$j]['showbtn'] = ($parent['showbtn'] == 0) ? 0 : 1;
            $currentGen[$j]['showbtnsex'] = $sex;
        } else {
            if (isset($peopleMap[$parent[$field]])) {
                $currentGen[$j] = $peopleMap[$parent[$field]];
                $currentGen[$j]['showbtn'] = 2;
            }
        }
    }

    /**
     * Genera el texto de relación genealógica
     */
    protected function generateRelationshipText(int $i, int $key): string
    {
        $text = "";
        $multiplicador = 4;

        for ($j = 1; $j <= $key; $j++) {
            $text .= (($i % $multiplicador) < ($multiplicador / 2) ? "P " : "M ");
            $multiplicador *= 2;
        }

        $text .= ($i < 2 * ($key + 1) ? "P" : "M");
        return $text;
    }

    private function analizarEtiquetasYDevolverJSON($mondaydataforAI)
    {
        $apiKey = env('OPENROUTER_API_KEY');

        // Construye el prompt dinámicamente con los valores actuales del arreglo
        $inputJSON = json_encode([
            'tablero' => $mondaydataforAI['tablero'] ?? '',
            'etiquetas' => $mondaydataforAI['etiquetas'] ?? '',
            'información_genealogia' => $mondaydataforAI['información_genealogia'] ?? '',
            'información_ventas' => $mondaydataforAI['información_ventas'] ?? '',
        ], JSON_UNESCAPED_UNICODE);

        $mensaje = [
            [
                "role" => "system",
                "content" => "Eres una IA especializada en genealogía legal. Evalúa el siguiente objeto y responde SOLO con un JSON con claves booleanas. No agregues explicación. El JSON será procesado automáticamente por backend."
            ],
            [
                "role" => "user",
                "content" => "
                        INPUT:

                        Nombre del tablero: {$mondaydataforAI['tablero']}
                        Etiquetas: " . ($mondaydataforAI['etiquetas'] ?? 'NO TIENE ETIQUETAS TODAVIA'). "

                        REGLAS:

                        1. **otrosProcesos**: 'true' si las etiquetas incluyen 'no apto', 'apto para otros procesos' o similares.
                        2. **pericial**: 'true' si alguna etiqueta contiene 'Informe Pericial' o 'Defensa Jurídica'.
                        3. **genealogiaAprobada**: 'true' si alguna etiqueta contiene 'aprobado' o 'aceptado' algo que indique aprobación explícita de genealogía.
                        4. **genealogia**: 'true' si 'genealogiaAprobada' es true.
                        5. **investigacionProfunda**: 'true' si hay una etiqueta con 'Investigación más profunda'.
                        6. **investigacionInSitu**: 'true' si hay una etiqueta con 'Investigación in situ'.
                        7. **analisisYCorreccion**: Devuelve 'true' si hay evidencia de que se realizó análisis o corrección del árbol genealógico. Para esto, revisa si existen campos como 'Solicitud cliente', 'respuesta de la Solicitud', o si se indica que el 'Arbol fue Cargado' en el campo de Arbol Cargado.
                        NOTA: Solicitud cliente y respuesta de la solicitud son campos que se encuentran en el tablero 'Analisis preliminar'. Si el nombre del tablero no es ese, entonces, analisisYCorreccion será false.
                        8. **investigacionIntuituPersonae**: Devuelve 'true' si el tablero actual es Análisis. De resto, es 'false'.
                        9. **inicioInvestigacion**: Devuelve 'true' si el tablero actual es 'Análisis' (ojo, no 'Analisis preliminar'). De resto, es 'false'.
                        Ejemplo de respuesta esperada:
                        {
                            'otrosProcesos': false,
                            'pericial': true,
                            'genealogiaAprobada': false,
                            'genealogia': true,
                            'investigacionProfunda': false,
                            'investigacionInSitu': true,
                            'analisisYCorreccion': true,
                            'investigacionIntuituPersonae': false
                        }
                    "
            ]
        ];

        // Llamada a OpenRouter
        $response = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post("https://openrouter.ai/api/v1/chat/completions", [
            'model' => 'openai/gpt-4.1-mini', // o GPT compatible
            'messages' => $mensaje
        ]);

        if ($response->successful()) {
            $json = $response->json()['choices'][0]['message']['content'];

            // Intentar decodificar el JSON
            $resultado = json_decode($json, true);

            if (is_array($resultado)) {
                return $resultado;
            } else {
                Log::warning("Respuesta IA no válida: $json");
                return [];
            }
        }

        // Fallback si la IA falla
        return [];
    }

    public function listProjectsWithProductoField()
    {
        $projects = $this->teamleaderService->listAllProjectsWithDetails();

        dd($projects);
    }

    public function savePersonalData(Request $request){
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $request->id,
            'phone' => 'required|string|max:15',
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'date_of_birth' => 'required|date',
            'passport' => 'required|string|max:20|unique:users,passport,' . $request->id,
        ], [
            'correo.required' => 'El campo correo es obligatorio.',
            'correo.email' => 'El correo debe ser válido.',
            'correo.unique' => 'Este correo ya está registrado.',
            'phone.required' => 'El campo teléfono es obligatorio.',
            'nombres.required' => 'El campo nombres es obligatorio.',
            'apellidos.required' => 'El campo apellidos es obligatorio.',
            'date_of_birth.required' => 'El campo fecha de nacimiento es obligatorio.',
            'date_of_birth.date' => 'La fecha de nacimiento debe ser una fecha válida.',
            'passport.required' => 'El campo pasaporte es obligatorio.',
            'passport.unique' => 'Este pasaporte ya está registrado.',
        ]);

        $hubspotFields = [
            'fecha_nac' => 'date_of_birth',
            'nombres' => 'firstname',
            'updated_at' => 'lastmodifieddate',
            'apellidos' => 'lastname' ,
            'referido_por' => 'n000__referido_por__clonado_',
            'passport' => 'numero_de_pasaporte',
            'servicio' => 'servicio_solicitado',
        ];

        $user = User::findOrFail($request->id);

        // Obtener los datos actuales de la base de datos
        $currentData = $user->toArray();

        // Filtrar el request eliminando valores NULL que ya son NULL en la base de datos
        $filteredRequest = collect($request->all())
            ->filter(function ($value, $key) use ($currentData) {
                return !is_null($value) || !array_key_exists($key, $currentData) || !is_null($currentData[$key]);
            })
            ->except(['_token', 'id']);

        if ($filteredRequest->has('vinculo_antepasados')) {
            $filteredRequest['vinculo_antepasados'] = implode(';', $filteredRequest->get('vinculo_antepasados'));
        }

        $hubspotData = [];

        foreach ($filteredRequest as $key=>$data){
            if ($key != "pay" && $key != "contrato"){
                if (isset($hubspotFields[$key])){
                    $hubspotData[$hubspotFields[$key]] = $data;
                } else {
                    $hubspotData[$key] = $data;
                }
            }
        }

        // Inspeccionar resultados
        $user->update($filteredRequest->toArray());

        // Llamar a la API de HubSpot para actualizar los datos
        $this->hubspotService->updateContact($user->hs_id, $hubspotData);

        // Retornar respuesta exitosa
        return response()->json(['message' => 'Datos actualizados correctamente.']);
    }

    public function getUsersForSelect()
    {
        // Consulta GraphQL para obtener los usuarios
        $query = '
        users {
            id
            name
            email
            enabled
        }';

        // Ejecuta la consulta (suponiendo que tienes un método para esto)
        $users = Monday::customQuery($query);

        // Filtra los usuarios habilitados
        $enabledUsers = collect($users['users'])
            ->filter(fn($user) => $user['enabled']) // Solo usuarios habilitados
            ->map(fn($user) => [
                'id'   => $user['id'],
                'name' => $user['name'],
                'email' => $user['email']
            ])
            ->values();

        // Devuelve los usuarios listos para usarse en un select
        return response()->json($enabledUsers);
    }

    private function storeMondayBoardColumns($boardId)
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

            // Extraer los IDs de los tags si la columna es de tipo "tags" o "multi-select"
            $tagIds = [];
            if (in_array($column['type'], ['tags', 'multi-select']) && isset($settings['tags'])) {
                $tagIds = array_column($settings['tags'], 'id');
            }

            MondayFormBuilder::updateOrCreate(
                ['board_id' => $boardId, 'column_id' => $column['id']],
                [
                    'title' => $column['title'],
                    'type' => $column['type'],
                    'settings' => $column['settings_str'] ? $column['settings_str'] : null,
                    'tag_ids' => $tagIds, // Guardar los IDs de los tags
                ]
            );
        }
    }

    public function tree(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->pay==1 || auth()->user()->pay == 3){
                return redirect()->route('clientes.getinfo');
            }
            if(Auth::user()->contrato==0){
                return redirect()->route('cliente.contrato');
            }
        }
        $IDCliente = Auth::user()->passport;

        $user = Auth::user();

        $cliente[0] = Auth::user();

        //revisar padres

        $searchCliente = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->first();
        $existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();

        $padreQuery = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',2)->first();

        $madreQuery = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',3)->first();

        if ($padreQuery || $madreQuery) {
            $data = [
                'migradoNuevoID' => 1,
            ];

            // Si existe el padre, añade 'idPadreNew' y 'IDPadre' al array de actualización
            if ($padreQuery) {
                $data['idPadreNew'] = $padreQuery->id;
                $data['IDPadre'] = 2;
            }

            // Si existe la madre, añade 'idMadreNew' y 'IDMadre' al array de actualización
            if ($madreQuery) {
                $data['idMadreNew'] = $madreQuery->id;
                $data['IDMadre'] = 3;
            }

            // Ejecuta la actualización
            DB::table('agclientes')
                ->where('id', $searchCliente->id)
                ->update($data);
        }

        $people = json_decode(json_encode(Agcliente::where("IDCliente",$IDCliente)->get()),true);

        //Asignar ids de padres al nodo 0 (en caso de no tenerlo)
        if (count($people)>2){
            if(!isset($people[0]['IDMadre'])){
                if($people[1]["Sexo"]=="F"){
                    $people[0]['IDMadre']=$people[1]['IDPersona'];
                    $people[0]['IDPadre']=$people[2]['IDPersona'];
                } else {
                    $people[0]['IDMadre']=$people[2]['IDPersona'];
                    $people[0]['IDPadre']=$people[1]['IDPersona'];
                }
            }
        }

        //Eliminar basura de los ids de los padres
        foreach ($people as $key => $person) {
            if ($person['IDMadre']<1){
                $people[$key]['IDMadre']=null;
            }
            if ($person['IDPadre']<1){
                $people[$key]['IDPadre']=null;
            }
        }

        $idPersonaToIdMap = [];
        foreach ($people as $item) {
            $idPersonaToIdMap[$item['IDPersona']] = $item['id'];
        }

        foreach ($people as &$item) {
            if ($person["migradoNuevoID"]==0){
                if (isset($item['IDPadre']) && isset($idPersonaToIdMap[$item['IDPadre']])) {
                    $item['idPadreNew'] = $idPersonaToIdMap[$item['IDPadre']];
                } else {
                    $item['idPadreNew'] = null;
                }
                if (isset($item['IDMadre']) && isset($idPersonaToIdMap[$item['IDMadre']])) {
                    $item['idMadreNew'] = $idPersonaToIdMap[$item['IDMadre']];
                } else {
                    $item['idMadreNew'] = null;
                }
            }
        }

        foreach ($people as $person) {
            if($person["migradoNuevoID"]==0){
                DB::table('agclientes')
                ->where('id', $person['id'])
                ->update([
                    'idPadreNew' => $person['idPadreNew'],
                    'idMadreNew' => $person['idMadreNew'],
                    'migradoNuevoID' => 1
                ]);
            }
        }

        $arreglo = $people;
        $generaciones = array();

        foreach ($arreglo as $id => $persona) {
            if ($persona['idPadreNew'] === null && $persona['idMadreNew'] === null) {
                $generaciones[$persona["id"]] = 1;
            }
        }

        $cambio = true;
        while ($cambio) {
            $cambio = false;
            foreach ($arreglo as $id => $persona) {
                $generacionPadre = isset($generaciones[$persona['idPadreNew']]) ? $generaciones[$persona['idPadreNew']] : 0;
                $generacionMadre = isset($generaciones[$persona['idMadreNew']]) ? $generaciones[$persona['idMadreNew']] : 0;
                $generacionActual = max($generacionPadre, $generacionMadre) + 1;

                if (!isset($generaciones[$persona["id"]]) || $generaciones[$persona["id"]] != $generacionActual) {
                    $generaciones[$persona["id"]] = $generacionActual;
                    $cambio = true;
                }
            }
        }

        $maxGeneraciones = max($generaciones);
        echo "El árbol genealógico tiene " . $maxGeneraciones . " generaciones.";
        $maxGeneraciones++;

        $columnasparatabla = array();

        for ($i=0; $i<$maxGeneraciones; $i++){
            if ($i == 0){
                if(!isset($columnasparatabla[$i])){
                    $columnasparatabla[$i] = [];
                }

                $columnasparatabla[$i][] =  $arreglo[0];
                $columnasparatabla[$i][0]["showbtn"] = 2;  //2 es persona, 1 es boton de añadir, 0 es nada
            } else {
                foreach ($columnasparatabla[$i-1] as $key2 => $persona2){

                    if(!isset($columnasparatabla[$i])){
                        $columnasparatabla[$i] = [];
                        $j = 0;
                    } else {
                        $j = sizeof($columnasparatabla[$i]);
                    }

                    //padre

                    if (@$persona2["idPadreNew"]==null){

                        if ($persona2["showbtn"] == 0) {
                            $columnasparatabla[$i][$j]["showbtn"] = 0;
                        } else if ($persona2["showbtn"] == 1) {
                            $columnasparatabla[$i][$j]["showbtn"] = 0;
                        } else {
                            $columnasparatabla[$i][$j]["showbtn"] = 1;
                            $columnasparatabla[$i][$j]["showbtnsex"] = "m";
                            $columnasparatabla[$i][$j]["id_hijo"] = $persona2["id"];
                        }

                    } else {
                        foreach ($arreglo as $key => $persona) {
                            if ($persona2["idPadreNew"] == $arreglo[$key]["id"]){
                                $columnasparatabla[$i][$j] = $arreglo[$key];
                                $columnasparatabla[$i][$j]["showbtn"] = 2;
                                break;
                            }
                        }

                    }

                    $j++;

                    // madre

                    if (@$persona2["idMadreNew"]==null){

                        if ($persona2["showbtn"] == 0) {
                            $columnasparatabla[$i][$j]["showbtn"] = 0;
                        } else if ($persona2["showbtn"] == 1) {
                            $columnasparatabla[$i][$j]["showbtn"] = 0;
                        } else {
                            $columnasparatabla[$i][$j]["showbtn"] = 1;
                            $columnasparatabla[$i][$j]["showbtnsex"] = "f";
                            $columnasparatabla[$i][$j]["id_hijo"] = $persona2["id"];
                        }

                    } else {

                        foreach ($arreglo as $key => $persona) {
                            if ($persona2["idMadreNew"] == $arreglo[$key]["id"]){
                                $columnasparatabla[$i][$j] = $arreglo[$key];
                                $columnasparatabla[$i][$j]["showbtn"] = 2;
                                break;
                            }
                        }

                    }
                }
            }
        }

        $tipoarchivos = TFile::all();

        $parentescos = [];
        $parentescos_post_padres = [
            "Abuel",
            "Bisabuel",
            "Tatarabuel",
            "Trastatarabuel",
            "Retatarabuel",
            "Sestarabuel",
            "Setatarabuel",
            "Octatarabuel",
            "Nonatarabuel",
            "Decatarabuel",
            "Undecatarabuel",
            "Duodecatarabuel",
            "Trececatarabuel",
            "Catorcatarabuel",
            "Quincecatarabuel",
            "Deciseiscatarabuel",
            "Decisietecatarabuel",
            "Deciochocatarabuel",
            "Decinuevecatarabuel",
            "Vigecatarabuel",
            "Vigecimoprimocatarabuel",
            "Vigecimosegundocatarabuel",
            "Vigecimotercercatarabuel",
            "Vigecimocuartocatarabuel",
            "Vigecimoquintocatarabuel",
            "Vigecimosextocatarabuel",
            "Vigecimoseptimocatarabuel",
            "Vigecimooctavocatarabuel",
            "Vigecimonovenocatarabuel",
            "Trigecatarabuel",
            "Trigecimoprimocatarabuel",
            "Trigecimosegundocatarabuel",
            "Trigecimotercercatarabuel",
            "Trigecimocuartocatarabuel",
            "Trigecimoquintocatarabuel",
            "Trigecimosextocatarabuel",
            "Trigecimoseptimocatarabuel",
            "Trigecimooctavocatarabuel",
            "Trigecimonovenocatarabuel",
            "Cuarentacatarabuel",
            "Cuarentaprimocatarabuel",
            "Cuarentasegundocatarabuel",
            "Cuarentatercercatarabuel",
        ];
        $prepar = 4;

        function generarTexto($i, $key) {
            $text = "";
            $multiplicador = 4;

            for ($j = 1; $j <= $key; $j++) {
                $text .= (($i % $multiplicador) < ($multiplicador / 2) ? "P " : "M ");
                $multiplicador *= 2;
            }

            $text .= ($i < 2 * ($key + 1) ? "P" : "M");
            return $text;
        }

        foreach ($parentescos_post_padres as $key => $parentesco) {
            if($key <= sizeof($columnasparatabla)){
                $parentescos[$key] = [];

                for ($i = 0; $i < $prepar; $i++) {
                    $textparentesco = $parentesco . ($i % 2 == 0 ? "o" : "a");
                    $text = generarTexto($i, $key);
                    $parentescos[$key][] = $textparentesco . " " . $text;
                }

                $prepar *= 2;
            }
        }

        $temparr = [];
        $var = 5;
        foreach ($columnasparatabla as $key => $columna){
            if($key<$var){
                $temparr[] = $columna;
            }
            foreach ($columna as $key2 => $persona) {
                if ($persona["showbtn"] == 2) {
                    if ($persona["PersonaIDNew"] == null || $persona["PersonaIDNew"] == "null"){
                        DB::table('agclientes')
                        ->where('id', $persona['id'])
                        ->update([
                            'PersonaIDNew' => $key2
                        ]);
                        $columnasparatabla[$key][$key2]["PersonaIDNew"] = $key2;
                    }
                }
            }
        }

        $columnasparatabla = $temparr;

        $checkBtn = "no";
        $generacionBase = 0;

        $parentnumber = 0;

        $htmlGenerado = view('arboles.vistatree', compact('generacionBase', 'columnasparatabla', 'parentescos', 'checkBtn', 'parentnumber'))->render();

        return view('arboles.tree', compact('generacionBase', 'user', 'IDCliente', 'people', 'columnasparatabla', 'cliente', 'tipoarchivos', 'parentescos', 'htmlGenerado', 'checkBtn', 'generacionBase', 'parentnumber'));
    }

    private function storeMondayUserData($user, $mondayUserDetailsPre)
    {
        MondayData::updateOrCreate(
            ['user_id' => $user->id],
            ['data' => json_encode($mondayUserDetailsPre)]
        );
    }

    public function hermanoscliente(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->pay==1 || auth()->user()->pay == 3){
                return redirect()->route('clientes.getinfo');
            }
            if(Auth::user()->contrato==0){
                return redirect()->route('cliente.contrato');
            }
        }

        $usermain = User::where('id', '=', auth()->user()->id)->get();
        $hermanos = Hermano::with('usuarioPrincipal', 'hermano')->where('id_main', '=', auth()->user()->id)->get();

        return view('clientes.hermanos', compact('usermain', 'hermanos'));
    }

    public function contrato(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->pay==1 || auth()->user()->pay == 3){
                return redirect()->route('clientes.getinfo');
            }
            if(Auth::user()->contrato==1){
                return redirect('/tree');
            }
        }
        return view('clientes.contrato');
    }

    public function checkContrato(){
        DB::table('users')->where('id', auth()->user()->id)->update(['contrato' => 1]); // no borrar esta linea
        return redirect('/tree')->with('exito', 'contrato enviado');
    }

    public function salir(Request $request){
        // Envía un correo al cliente que ha culminado la carga
        $mail_cliente = new CargaCliente(Auth::user());
        Mail2::to(Auth::user()->email)->send($mail_cliente);

        // Envía un correo al equipo de Sefar
        $mail_sefar = new CargaSefar(Auth::user());
        Mail2::to([
            'pedro.bazo@sefarvzla.com',
            'sistemasccs@sefarvzla.com',
            'automatizacion@sefarvzla.com',
            'sistemascol@sefarvzla.com',
            'asistentedeproduccion@sefarvzla.com',
            'organizacionrrhh@sefarvzla.com',
            'arodriguez@sefarvzla.com',
            '20053496@bcc.hubspot.com'
            /* 'organizacionrrhh@sefarvzla.com' */
        ])->send($mail_sefar);

        // Realiza logout de la aplicación
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

    public function procesar(Request $request){
        $user = Auth()->user();
        // Validación
        $request->validate([
            'passport' => 'required|min:6|unique:users,passport,'.$user->id,
            'nombres' => 'required',
            'apellidos' => 'required',
            'email' => 'email|required|unique:users,email,'.$user->id,
            'fnacimiento' => 'required',
            'cnacimiento' => 'required',
            'pnacimiento' => 'required',
            'sexo' => 'required'
        ]);

        // Actualizar usuario
        $user->name = trim($request->nombres) . ' ' . trim($request->apellidos);
        $user->email = $request->email;
        $user->passport = trim($request->passport);
        $user->save();

        // Verificar si el usuario esta registrado en agclientes
        $agcliente = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',1)->count();
        if($agcliente == 0){
            // Si no existe crea el árbol del cliente
            $fnacimiento = $request->fnacimiento;
            $fnacimiento_entero = strtotime($fnacimiento);
            Agcliente::create([
                'IDCliente' => trim($user->passport),
                'IDPersona' => 1,
                'Nombres' => trim($request->nombres),
                'Apellidos' => trim($request->apellidos),
                'NPasaporte' => trim($user->passport),
                'Sexo' => trim($request->sexo),
                'AnhoNac' => date("Y", $fnacimiento_entero),
                'MesNac' => date("m", $fnacimiento_entero),
                'DiaNac' => date("d", $fnacimiento_entero),
                'LugarNac' => trim($request->cnacimiento),
                'PaisNac' => trim($request->pnacimiento),
                'NombresF' => trim($request->nombre_f),
                'NPasaporteF' => trim($request->pasaporte_f),
                'FRegistro' => date('Y-m-d H:i:s'),
                'PNacimiento' => trim($request->pnacimiento),
                'LNacimiento' => trim($request->cnacimiento),
                'FUpdate' => date('Y-m-d H:i:s'),
                'referido' => trim($request->referido),
                'Usuario' => trim($request->email),
            ]);
        }

        // Asignar rol de cliente
        $user->assignRole('Cliente');

        return redirect()->route('clientes.tree', $user->passport);
    }

    public function getinfo(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
            if(Auth::user()->pay==2 || auth()->user()->cerocerouno == 1){
                return redirect('/tree');
            }
        }
        return view('clientes.getinfo');
    }

    public function gracias(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
        }
        return view('clientes.gracias');
    }

    public function procesargetinfo(Request $request, HubspotService $hubspotService){
        /*

            Aqui recibo y organizo el arreglo que viene del Jquery

        */

        $contactData = $this->hubspotService->check001(auth()->user()->hs_id);

        if (!$contactData) {
            return response()->json(['error' => 'El formulario no se ha completado en HubSpot aún'], 504);
        }

        if (auth()->user()->pay == 3){
            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]); // no borrar esta linea
            auth()->user()->revokePermissionTo('finish.register');
        } else {
            $inputdata = json_decode(json_encode($request->all()),true);

            $input_u = $inputdata["data"];

            $input = array();

            foreach ($input_u as $key => $value) {
                if($input_u[$key]["name"]!="hs_context") {
                    if ($input_u[$key]["name"] == "vinculo_antepasados") {
                        $input[$input_u[$key]["name"]][] = $input_u[$key]["value"];
                    } else {
                        $input[$input_u[$key]["name"]] = $input_u[$key]["value"];
                    }
                }
            }

            $input['vinculo_antepasados'] = isset($input['vinculo_antepasados'])
                ? implode(';', $input['vinculo_antepasados'])
                : null;

            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2, 'cerocerouno' => 1]); // no borrar esta linea
            auth()->user()->revokePermissionTo('finish.register');

            /* Aquí actualizo la base de datos */

            //print_r('de php');
            //print_r($input['referido_por']);
            $user = Auth()->user();

            // Actualizando el árbol genenalógico
            // Cliente
            $agcliente = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',1)->first();
            if($agcliente){
                @$agcliente->Sexo = $input['genero'] == 'MASCULINO / MALE' ? 'M' : 'F';
                @$user->genero = $agcliente->Sexo;

                @$user->date_of_birth = $input['fecha_nac'];
                if($user->date_of_birth){
                    @$agcliente->AnhoNac = date("Y", strtotime($user->date_of_birth));
                    @$agcliente->MesNac = date("m", strtotime($user->date_of_birth));
                    @$agcliente->DiaNac = date("d", strtotime($user->date_of_birth));
                }

                @$agcliente->LugarNac = trim($input['ciudad_de_nacimiento']);
                @$agcliente->PaisNac = trim($input['pais_de_nacimiento']);

                @$agcliente->FRegistro = date('Y-m-d H:i:s');
                @$agcliente->PNacimiento = trim($input['pais_de_nacimiento']);
                @$agcliente->LNacimiento = trim($input['ciudad_de_nacimiento']);
                @$user->ciudad_de_nacimiento = $agcliente->LNacimiento;
                @$agcliente->PaisPasaporte = trim($input['pais_de_expedicion_del_pasaporte']);

                @$agcliente->ParentescoF = trim($input['vinculo_miembro_de_familia_1']);
                @$agcliente->NombresF = trim($input['nombre_miembro_de_familia_1']);
                @$agcliente->ApellidosF = trim($input['apellidos_miembro_de_familia_1']);
                // $agcliente->NPasaporteF = trim($input['pasaporte_f']);

                @$agcliente->Observaciones = (($agcliente->Observaciones == null) ? '' : $agcliente->Observaciones . '. ')
                    . 'Phone: ' . trim($input['phone'])
                    . ' E-mail:' . trim($input['email'])
                    . ' Adress:' . trim($input['address']);
                $agcliente->save();
                $user->save();
            }

            // Padre
            @$nombres_y_apellidos_del_padre = trim($input['nombres_y_apellidos_del_padre']);
            if($nombres_y_apellidos_del_padre){
                @$padre = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',2)->first();
                if(!$padre) {
                    $agcliente = Agcliente::create([
                        'IDCliente' => $user->passport,
                        'Nombres' => $nombres_y_apellidos_del_padre,
                        'IDPersona' => 2,
                        'Sexo' => 'M',
                        'IDPadre' => 4,
                        'IDMadre' => 5,
                        'Generacion' => 2,
                        'FUpdate' => date('Y-m-d H:i:s'),
                        'Usuario' => $user->email,
                    ]);
                }
            }

            // Madre
            @$nombres_y_apellidos_de_madre = trim($input['nombres_y_apellidos_de_madre']);
            if($nombres_y_apellidos_de_madre){
                @$madre = Agcliente::where('IDCliente',$user->passport)->where('IDPersona',3)->first();
                if(!$madre) {
                    $agcliente = Agcliente::create([
                        'IDCliente' => $user->passport,
                        'Nombres' => $nombres_y_apellidos_de_madre,
                        'IDPersona' => 3,
                        'Sexo' => 'F',
                        'IDPadre' => 6,
                        'IDMadre' => 7,
                        'Generacion' => 2,
                        'FUpdate' => date('Y-m-d H:i:s'),
                        'Usuario' => $user->email,
                    ]);
                }
            }

            /* Fin de la actualización en Base de Datos */

            /* Añade info a Monday */
            $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

            $datos_factura = json_decode(json_encode(DB::select($query)),true);

            $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

            $servicios = "";

            foreach ($productos as $key => $value) {
                $servicios = $servicios . $value["servicio_hs_id"];
                if ($key != count($productos)-1){
                    $servicios = $servicios . ", ";
                }
            }

            $token = env('MONDAY_TOKEN');
            $apiUrl = 'https://api.monday.com/v2';
            $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

            $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

            $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

            if (is_null(auth()->user()->apellidos) || is_null(auth()->user()->nombres)){
                $clientname = auth()->user()->name;
            } else {
                $clientname = auth()->user()->apellidos." ".auth()->user()->nombres;
            }

            $vars = [
                'myItemName' => $clientname,
                'columnVals' => json_encode([
                    'texto' => auth()->user()->passport,
                    'fecha75' => ['date' => date("Y-m-d", strtotime($input['fecha_nac']))],
                    'texto_largo8' => $nombres_y_apellidos_del_padre,
                    'texto_largo75' => $nombres_y_apellidos_de_madre,
                    'enlace' => $link . " " . $link,
                    'estado54' => 'Arbol Incompleto',
                    'texto1' => $servicios,
                    'texto4' => auth()->user()->hs_id,
                    'texto_largo88' => auth()->user()->nombre_de_familiar_realizando_procesos
                ])
            ];

            foreach ($productos as $key => $value) {
                if (isset($value)) {
                    $servicio_hs_id = $value['servicio_hs_id'];

                    if (isset($servicio_hs_id) && ($servicio_hs_id === "Española LMD" || $servicio_hs_id == "Española LMD")) {
                        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 765394861, group_id: "grupo_nuevo97011", item_name:$myItemName, column_values:$columnVals) { id } }';

                        $vars = [
                            'myItemName' => $clientname,
                            'columnVals' => json_encode([
                                'texto' => auth()->user()->passport,
                                'fecha75' => ['date' => date("Y-m-d", strtotime($input['fecha_nac']))],
                                'texto_largo4' => $nombres_y_apellidos_del_padre,
                                'texto_largo75' => $nombres_y_apellidos_de_madre,
                                'enlace' => $link . " " . $link,
                                'estado54' => 'Arbol Incompleto',
                                'texto1' => $servicios,
                                'texto6' => auth()->user()->hs_id,
                                'texto_largo2' => auth()->user()->nombre_de_familiar_realizando_procesos,
                                'color' => trim($input['tengo_certeza_de_mi_antepasado_espanol_']),
                                'text' => trim($input['vinculo_antepasados'])
                            ])
                        ];
                    }
                }
            }

            $data = @file_get_contents($apiUrl, false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => $headers,
                        'content' => json_encode(['query' => $query, 'variables' => $vars]),
                    ]
                ]
            ));

            $responseContent = json_decode($data,true);

            echo json_encode($responseContent);
        }



    }

    public function gotopayfases(Request $request){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if(Auth::user()->pay==1 || Auth::user()->pay==3){
                return redirect()->route('clientes.getinfo');
            } else if(Auth::user()->pay==0){
                return redirect()->route('clientes.pay');
            }
        }
        $compras = Compras::where('id_user', auth()->user()->id)->where('id', $request->id)->where('pagado', 0)->whereNotNull('deal_id')->get();

        if (auth()->user()->tiene_hermanos==1 || auth()->user()->tiene_hermanos=="1" || auth()->user()->tiene_hermanos=="Si") {
            $servicio = Servicio::where('id_hubspot', auth()->user()->servicio." - Hermano")->get();
        } else {
            $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();
        }

        $cps = json_decode(json_encode($compras),true);

        if (count($cps)==0){
            $hss = json_decode(json_encode($servicio),true);

            if(auth()->user()->servicio == "Recurso de Alzada"){
                $monto = $hss[0]["precio"] * auth()->user()->cantidad_alzada;
            } else {
                $monto = $hss[0]["precio"];
            }

            if( auth()->user()->servicio == "Española LMD" || auth()->user()->servicio == "Italiana" ) {
                $desc = "Pago Fase Inicial: Investigación Preliminar y Preparatoria: " . $hss[0]["nombre"];
                if (auth()->user()->servicio == "Española LMD"){
                    if (auth()->user()->antepasados==0){
                        $monto = 299;
                    }
                }
                if (auth()->user()->servicio == "Italiana"){
                    if (auth()->user()->antepasados==1){
                        $desc = $desc . " + (Consulta Gratuita)";
                    }
                }
            } elseif ( auth()->user()->servicio == "Gestión Documental" ) {
                $desc = $hss[0]["nombre"];
            } elseif ($servicio[0]['tipov'] == 1) {
                $desc = "Servicios para Vinculaciones: " . $hss[0]["nombre"];
            } else {
                $desc = "Análisis genealógico: " . $hss[0]["nombre"];
            }

            Compras::create([
                'id_user' => auth()->user()->id,
                'servicio_hs_id' => auth()->user()->servicio,
                'descripcion' => $desc,
                'pagado' => 0,
                'monto' => $monto
            ]);

            $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
        }

        $alertas = $today = Carbon::today();
        $alertas = Alertas::where('start_date', '<=', $today)
                        ->where('end_date', '>=', $today)
                        ->get();

        $compraid = $request->id;

        return view('clientes.payfases', compact('compraid', 'servicio', 'compras', 'alertas'));
    }

    public function pay(){
        if (Auth::user()->roles->first()->name == "Cliente"){
            if (Auth::user()->pay==2){
                $IDCliente = Auth::user()->passport;
                return redirect('/tree');
            } else if(Auth::user()->pay==1 || Auth::user()->pay==3){
                return redirect()->route('clientes.getinfo');
            }
        }
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNull('deal_id')->get();

        if (auth()->user()->tiene_hermanos==1 || auth()->user()->tiene_hermanos=="1" || auth()->user()->tiene_hermanos=="Si") {
            $servicio = Servicio::where('id_hubspot', 'like', auth()->user()->servicio . '% - Hermano')->get();
        } else {
            $servicio = Servicio::where('id_hubspot', "like", auth()->user()->servicio."%")->get();
        }

        $cps = json_decode(json_encode($compras),true);

        if (count($cps)==0){
            $hss = json_decode(json_encode($servicio),true);

            if(auth()->user()->servicio == "Recurso de Alzada"){
                $monto = $hss[0]["precio"] * auth()->user()->cantidad_alzada;
            } else {
                $monto = $hss[0]["precio"];
            }

            if( auth()->user()->servicio == "Española LMD" || auth()->user()->servicio == "Italiana" ) {
                $desc = "Pago Fase Inicial: Investigación Preliminar y Preparatoria: " . $hss[0]["nombre"];
                if (auth()->user()->servicio == "Española LMD"){
                    if (auth()->user()->antepasados==0){
                        $monto = 299;
                    }
                }
                if (auth()->user()->servicio == "Italiana"){
                    if (auth()->user()->antepasados==1){
                        $desc = $desc . " + (Consulta Gratuita)";
                    }
                }
            } elseif ( auth()->user()->servicio == "Gestión Documental" ) {
                $desc = $hss[0]["nombre"];
            } elseif ($servicio[0]['tipov'] == 1) {
                $desc = "Servicios para Vinculaciones: " . $hss[0]["nombre"];
            } else {
                $desc = "Análisis genealógico: " . $hss[0]["nombre"];
            }

            Compras::create([
                'id_user' => auth()->user()->id,
                'servicio_hs_id' => auth()->user()->servicio,
                'descripcion' => $desc,
                'pagado' => 0,
                'monto' => $monto
            ]);

            $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
        }

        $alertas = $today = Carbon::today();
        $alertas = Alertas::where('start_date', '<=', $today)
                        ->where('end_date', '>=', $today)
                        ->get();

        return view('clientes.pay', compact('servicio', 'compras', 'alertas'));
    }

    public function revisarcupon(Request $request){
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNull('deal_id')->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        $data = json_decode(json_encode($request->all()),true);

        $datos = json_decode(json_encode(DB::table('users')->where('id', auth()->user()->id)->get()),true);

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $cupontest = strtoupper(str_replace(' ', '', $data["cpn"]));

        $couponGENERAL = GeneralCoupon::where('title', $cupontest)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        if ($couponGENERAL) {
            foreach ($compras as $compra) {
                $compra->update([
                    'monto' => $couponGENERAL->newdiscount,
                    'cuponaplicado' => 1,
                    'montooriginal' => $compra->monto,
                    'porcentajedescuento' => "Oferta: {$couponGENERAL->title}"
                ]);
            }
            return response()->json([
                'status' => "promo",
                'percentage' => "Oferta: {$couponGENERAL->title}"
            ]);
        }

        foreach ($cupones as $cupon) {
            if( $data["cpn"] == $cupon["couponcode"] ){
                if(!is_null($cupon["expire"]) && $cupon["expire"]<date('Y-m-d')){
                    return response()->json([
                        'status' => "fechabad"
                    ]);
                }
                if($cupon["enabled"] == 0){
                    return response()->json([
                        'status' => "false"
                    ]);
                }
                if($cupon["percentage"]<100){
                    foreach ($compras as $compra) {
                        if ($compra->cuponaplicado != 1){
                            $montoDescuento = $compra->monto - ($compra->monto * ($cupon["percentage"] / 100));
                            $montoFinal = round($montoDescuento, 2);

                            $compra->update([
                                'monto' => $montoFinal,
                                'cuponaplicado' => 1,
                                'montooriginal' => $compra->monto,
                                'porcentajedescuento' => $cupon["percentage"]
                            ]);
                        }
                    }

                    DB::table('coupons')->where('couponcode', $cupon["couponcode"])->update(['enabled' => 0]);

                    return response()->json([
                        'status' => "halftrue",
                        'percentage' => $cupon["percentage"]."%"
                    ]);
                } else {
                    $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                    $hash_factura = "sef_".$this->generate_string($permitted_chars, 50);

                    Factura::create([
                        'id_cliente' => auth()->user()->id,
                        'hash_factura' => $hash_factura,
                        'met' => 'cupon',
                    ]);

                    foreach ($compras as $key => $compra) {
                        DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
                    }

                    if (isset($datos[0]["id_pago"])){
                        if(is_array(json_decode($datos[0]["id_pago"],true))) {
                            $cargostemp = json_decode($datos[0]["id_pago"],true);
                            $cargostemp[] = '';
                            $cargos = json_encode($cargostemp);
                        } else {
                            $cargostemp[] = $datos[0]["id_pago"];
                            $cargostemp[] = '';
                            $cargos = json_encode($cargostemp);
                        }
                    } else {
                        $cargostemp[] = '';
                        $cargos = json_encode($cargostemp);
                    }

                    if (isset($datos[0]["pago_cupon"])){
                        if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                            $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                            $cuponestemp[] = $data["cpn"];
                            $cupones = json_encode($cuponestemp);
                        } else {
                            $cuponestemp[] = $datos[0]["pago_cupon"];
                            $cuponestemp[] = $data["cpn"];
                            $cupones = json_encode($cuponestemp);
                        }
                    } else {
                        $cuponestemp[] = $data["cpn"];
                        $cupones = json_encode($cuponestemp);
                    }

                    if (isset($datos[0]["pago_registro_hist"])){
                        if(is_array(json_decode($datos[0]["pago_registro_hist"],true))) {
                            $pago_registrotemp = json_decode($datos[0]["pago_registro_hist"],true);
                            $pago_registrotemp[] = 0;
                            $pago_registro = json_encode($pago_registrotemp);
                        } else {
                            $pago_registrotemp[] = $datos[0]["pago_registro_hist"];
                            $pago_registrotemp[] = 0;
                            $pago_registro = json_encode($pago_registrotemp);
                        }
                    } else {
                        $pago_registrotemp[] = 0;
                        $pago_registro = json_encode($pago_registrotemp);
                    }

                    DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1, 'pago_registro_hist' => $pago_registro, 'pago_registro' => 0, 'id_pago' => $cargos, 'pago_cupon' => $cupones, 'contrato' => 0 ]);

                    $setto2 = 1;

                    foreach ($compras as $key => $compra) {
                        $servicio = Servicio::where('id_hubspot', $compra["servicio_hs_id"])->get();
                        if ($compra["servicio_hs_id"] == 'Árbol genealógico de Deslinde' || $compra["servicio_hs_id"] == 'Acumulación de linajes' || $compra["servicio_hs_id"] == 'Procedimiento de Urgencia' || $compra["servicio_hs_id"] == 'Recurso de Alzada' || $compra["servicio_hs_id"] == 'Gestión Documental' || $servicio[0]['tipov']==1){
                            $setto2 = 1;
                        } else {
                            $setto2 = 0;
                            break;
                        }
                    }

                    if ($setto2==1) {
                        DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]);
                        auth()->user()->revokePermissionTo('finish.register');
                    }

                    DB::table('coupons')->where('couponcode', $cupon["couponcode"])->update(['enabled' => 0]);
                    auth()->user()->revokePermissionTo('pay.services');

                    $idcontact = "";

                    $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
                    $filter
                        ->setOperator('EQ')
                        ->setPropertyName('email')
                        ->setValue(auth()->user()->email);

                    $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
                    $filterGroup->setFilters([$filter]);

                    $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
                    $searchRequest->setFilterGroups([$filterGroup]);

                    //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

                    $searchRequest->setProperties([
                        "registro_pago",
                        "registro_cupon"
                    ]);

                    //Hago la busqueda del cliente
                    $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

                    if ($contactHS['total'] != 0){
                        $valuehscupon = "";
                        //sago solo el id del contacto:
                        $idcontact = $contactHS['results'][0]['id'];

                        DB::table('users')->where('id', auth()->user()->id)->update(['hs_id' => $idcontact]);

                        $properties1 = [
                            'registro_pago' => '0',
                            'registro_cupon' => $cupones,
                            'transaction_id' => $cargos,
                            'hist_pago_registro' => $pago_registro
                        ];
                        $simplePublicObjectInput = new SimplePublicObjectInput([
                            'properties' => $properties1,
                        ]);

                        $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);

                        $dealInput = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();

                        foreach ($compras as $key => $compra) {
                            $dealInput->setProperties([
                                'dealname' => auth()->user()->name . ' - ' . $compra['servicio_hs_id'],
                                'pipeline' => "94794",
                                'dealstage' => "429097",
                                'servicio_solicitado' => $compra['servicio_hs_id'],
                                'servicio_solicitado2' => $compra['servicio_hs_id'],
                            ]);

                            $apiResponse = json_decode(json_encode($hubspot->crm()->deals()->basicApi()->create($dealInput)),true);

                            $iddeal = $apiResponse["id"];

                            $associationSpec1 = new AssociationSpec([
                                'association_category' => 'HUBSPOT_DEFINED',
                                'association_type_id' => 3
                            ]);

                            $asocdeal = $hubspot->crm()->deals()->associationsApi()->create($iddeal, 'contacts', $idcontact, [$associationSpec1]);
                            sleep(2);
                        }
                    }

                    $user = User::findOrFail(auth()->user()->id);
                    $pdfContent = $this->createPDF($hash_factura);

                    Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                        $m->to([
                            auth()->user()->email
                        ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                    });

                    Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                        $m->to([
                            'pedro.bazo@sefarvzla.com',
                            'sistemasccs@sefarvzla.com',
                            'crisantoantonio@gmail.com',
                            'automatizacion@sefarvzla.com',
                            'sistemascol@sefarvzla.com',
                            'asistentedeproduccion@sefarvzla.com',
                            'organizacionrrhh@sefarvzla.com',
                            'organizacionrrhh@sefarvzla.com',
                            '20053496@bcc.hubspot.com',
                            'contabilidad@sefaruniversal.com',
                            'operacionesc@sefarvzla.com',
                            'yeinsondiaz@sefarvzla.com',
                            'dpm.ladera@sefarvzla.com'
                        ])->subject(strtoupper($user->name) . ' (ID: ' .
                            strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                    });

                    if ($setto2==1) {

                        $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

                        $datos_factura = json_decode(json_encode(DB::select($query)),true);

                        $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

                        $servicios = "";

                        foreach ($productos as $key => $value) {
                            $servicios = $servicios . $value["servicio_hs_id"];
                            if ($key != count($productos)-1){
                                $servicios = $servicios . ", ";
                            }
                        }

                        $token = env('MONDAY_TOKEN');
                        $apiUrl = 'https://api.monday.com/v2';
                        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

                        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

                        $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

                        $vars = [
                            'myItemName' => auth()->user()->apellidos." ".auth()->user()->nombres,
                            'columnVals' => json_encode([
                                'texto' => auth()->user()->passport,
                                'enlace' => $link . " " . $link,
                                'estado54' => 'Arbol Incompleto',
                                'texto1' => $servicios,
                                'texto4' => auth()->user()->hs_id,
                                'texto_largo88' => auth()->user()->nombre_de_familiar_realizando_procesos
                            ])
                        ];

                        $data = @file_get_contents($apiUrl, false, stream_context_create([
                                'http' => [
                                    'method' => 'POST',
                                    'header' => $headers,
                                    'content' => json_encode(['query' => $query, 'variables' => $vars]),
                                ]
                            ]
                        ));

                        $responseContent = json_decode($data, true);

                    }

                    return response()->json([
                        'status' => "true"
                    ]);
                }

            }
        }
        return response()->json([
            'status' => "false"
        ]);
    }

    public function procesarPaypal(Request $request){
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));

        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $datos = json_decode(json_encode(DB::table('users')->where('id', auth()->user()->id)->get()),true);

        $finalcupon = "";

        $errorcod = "error";

        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNull('deal_id')->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cps = json_decode(json_encode($compras),true);

        $hss = json_decode(json_encode($servicio),true);

        $monto = 0;

        foreach ($compras as $key => $compra) {
            $monto = $monto + $compra["monto"];
        }

        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $hash_factura = "sef_".$this->generate_string($permitted_chars, 50);

        Factura::create([
            'id_cliente' => auth()->user()->id,
            'hash_factura' => $hash_factura,
            'met' => 'paypal'
        ]);

        foreach ($compras as $key => $compra) {
            DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
        }

        $cargostemp = [];

        if (isset($datos[0]["id_pago"])){
            if(is_array(json_decode($datos[0]["id_pago"],true))) {
                $cargostemp = json_decode($datos[0]["id_pago"],true);
                $cargos = json_encode($cargostemp);
            } else {
                $cargostemp[] = $datos[0]["id_pago"];
                $cargos = json_encode($cargostemp);
            }
        } else {
            $cargos = json_encode($cargostemp);
        }

        $cuponestemp = [];

        if (isset($finalcupon)){
            DB::table('coupons')->where('couponcode', $finalcupon)->update(['enabled' => 0]);
            if (isset($datos[0]["pago_cupon"])){
                if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                    $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                    $cuponestemp[] = $finalcupon;
                    $cupones = json_encode($cuponestemp);
                } else {
                    $cuponestemp[] = $datos[0]["pago_cupon"];
                    $cuponestemp[] = $finalcupon;
                    $cupones = json_encode($cuponestemp);
                }
            } else {
                $cuponestemp[] = $finalcupon;
                $cupones = json_encode($cuponestemp);
            }
        } else {
            if (isset($datos[0]["pago_cupon"])){
                if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                    $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                    $cuponestemp[] = '';
                    $cupones = json_encode($cuponestemp);
                } else {
                    $cuponestemp[] = $datos[0]["pago_cupon"];
                    $cuponestemp[] = '';
                    $cupones = json_encode($cuponestemp);
                }
            } else {
                $cuponestemp[] = '';
                $cupones = json_encode($cuponestemp);
            }
        }

        $pago_registrotemp = [];

        if (isset($datos[0]["pago_registro_hist"])){
            if(is_array(json_decode($datos[0]["pago_registro_hist"],true))) {
                $pago_registrotemp = json_decode($datos[0]["pago_registro_hist"],true);
                $pago_registrotemp[] = $monto;
                $pago_registro = json_encode($pago_registrotemp);
            } else {
                $pago_registrotemp[] = $datos[0]["pago_registro"];
                $pago_registrotemp[] = $monto;
                $pago_registro = json_encode($pago_registrotemp);
            }
        } else {
            $pago_registrotemp[] = $monto;
            $pago_registro = json_encode($pago_registrotemp);
        }

        DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1, 'pago_registro_hist' => $pago_registro, 'pago_registro' => $monto, 'id_pago' => $cargos, 'pago_cupon' => $cupones, 'contrato' => 0]);

        $setto2 = 1;

        foreach ($compras as $key => $compra) {
            $servicio = Servicio::where('id_hubspot', $compra["servicio_hs_id"])->get();
            if ($compra["servicio_hs_id"] == 'Árbol genealógico de Deslinde' || $compra["servicio_hs_id"] == 'Acumulación de linajes' || $compra["servicio_hs_id"] == 'Procedimiento de Urgencia' || $compra["servicio_hs_id"] == 'Recurso de Alzada' || $compra["servicio_hs_id"] == 'Gestión Documental' || $servicio[0]['tipov']==1){
                $setto2 = 1;
            } else {
                $setto2 = 0;
                break;
            }
        }

        if ($setto2==1) {
            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]);
            auth()->user()->revokePermissionTo('finish.register');
        }

        auth()->user()->revokePermissionTo('pay.services');
        $idcontact = "";

        $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
        $filter
            ->setOperator('EQ')
            ->setPropertyName('email')
            ->setValue(auth()->user()->email);

        $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
        $filterGroup->setFilters([$filter]);

        $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
        $searchRequest->setFilterGroups([$filterGroup]);

        //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

        $searchRequest->setProperties([
            "registro_pago",
            "registro_cupon",
            "transaction_id"
        ]);

        //Hago la busqueda del cliente
        $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

        if ($contactHS['total'] != 0){
            $valuehscupon = "";
            //sago solo el id del contacto:
            $idcontact = $contactHS['results'][0]['id'];

            DB::table('users')->where('id', auth()->user()->id)->update(['hs_id' => $idcontact]);

            $properties1 = [
                'registro_pago' => $servicio[0]["precio"],
                'registro_cupon' => $cupones,
                'transaction_id' => $cargos,
                'hist_pago_registro' => $pago_registro
            ];
            $simplePublicObjectInput = new SimplePublicObjectInput([
                'properties' => $properties1,
            ]);

            $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);

            $dealInput = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();

            foreach ($compras as $key => $compra) {
                $dealInput->setProperties([
                    'dealname' => auth()->user()->name . ' - ' . $compra['servicio_hs_id'],
                    'pipeline' => "94794",
                    'dealstage' => "429097",
                    'servicio_solicitado' => $compra['servicio_hs_id'],
                    'servicio_solicitado2' => $compra['servicio_hs_id'],
                ]);

                $apiResponse = json_decode(json_encode($hubspot->crm()->deals()->basicApi()->create($dealInput)),true);

                $iddeal = $apiResponse["id"];

                $associationSpec1 = new AssociationSpec([
                    'association_category' => 'HUBSPOT_DEFINED',
                    'association_type_id' => 3
                ]);

                $asocdeal = $hubspot->crm()->deals()->associationsApi()->create($iddeal, 'contacts', $idcontact, [$associationSpec1]);
                sleep(2);
            }

        }
        $user = User::findOrFail(auth()->user()->id);
        $pdfContent = $this->createPDF($hash_factura);

        Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
            $m->to([
                auth()->user()->email
            ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });

        $pdfContent2 = $this->createPDFintel($hash_factura);

        Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $request, $user) {
            $m->to([
                'pedro.bazo@sefarvzla.com',
                'crisantoantonio@gmail.com',
                'sistemasccs@sefarvzla.com',
                'automatizacion@sefarvzla.com',
                'sistemascol@sefarvzla.com',
                'asistentedeproduccion@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                '20053496@bcc.hubspot.com',
                'contabilidad@sefaruniversal.com',
                'operacionesc@sefarvzla.com',
                'yeinsondiaz@sefarvzla.com',
                            'dpm.ladera@sefarvzla.com'
            ])->subject(strtoupper($user->name) . ' (ID: ' .
                strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });

        if ($setto2==1) {

            $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

            $datos_factura = json_decode(json_encode(DB::select($query)),true);

            $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

            $servicios = "";

            foreach ($productos as $key => $value) {
                $servicios = $servicios . $value["servicio_hs_id"];
                if ($key != count($productos)-1){
                    $servicios = $servicios . ", ";
                }
            }

            $token = env('MONDAY_TOKEN');
            $apiUrl = 'https://api.monday.com/v2';
            $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

            $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

            $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

            $vars = [
                'myItemName' => auth()->user()->apellidos." ".auth()->user()->nombres,
                'columnVals' => json_encode([
                    'texto' => auth()->user()->passport,
                    'enlace' => $link . " " . $link,
                    'estado54' => 'Arbol Incompleto',
                    'texto1' => $servicios,
                    'texto4' => auth()->user()->hs_id,
                    'texto_largo88' => auth()->user()->nombre_de_familiar_realizando_procesos
                ])
            ];

            $data = @file_get_contents($apiUrl, false, stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => $headers,
                        'content' => json_encode(['query' => $query, 'variables' => $vars]),
                    ]
                ]
            ));

            $responseContent = json_decode($data, true);

            echo json_encode($responseContent);

        }
    }

    public function procesarpaypalfases(Request $request) {
        $compras = Compras::where('id_user', auth()->user()->id)->where('id', $request->compraid)->where('pagado', 0)->whereNotNull('deal_id')->get();

        $monto = 0;

        foreach ($compras as $key => $compra) {
            $monto = $monto + $compra["monto"];
        }

        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $hash_factura = "sef_".$this->generate_string($permitted_chars, 50);

        Factura::create([
            'id_cliente' => auth()->user()->id,
            'hash_factura' => $hash_factura,
            'met' => 'paypal',
        ]);

        foreach ($compras as $key => $compra) {
            DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);

            $deal = Negocio::find($compra->deal_id);
            $fechaActual = Carbon::now()->format('Y/m/d');

            if($compra->phasenum == 1){
                $deal->fase_1_pagado = $compra->monto . " " . $fechaActual;
                $deal->fecha_fase_1_pagado = $fechaActual;
                $deal->monto_fase_1_pagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "a1b50c58-8175-0d13-9856-f661e783dc08") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'monto_fase_1_pagado' => $compra->monto,
                        'fecha_fase_1_pagado' => $timestamp,
                        'fase_1_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            } else if ($compra->phasenum == 2) {
                $deal->fase_2_pagado = $compra->monto . " " . $fechaActual;
                $deal->fecha_fase_2_pagado = $fechaActual;
                $deal->monto_fase_2_pagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "a5b94ccc-3ea8-06fc-b259-0a487073dc0d") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'monto_fase_2_pagado' => $compra->monto,
                        'fecha_fase_2_pagado' => $timestamp,
                        'fase_2_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            } else if ($compra->phasenum == 3) {
                $deal->fase_3_pagado = $compra->monto . " " . $fechaActual;
                $deal->fecha_fase_3_pagado = $fechaActual;
                $deal->monto_fase_3_pagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "9a1df9b7-c92f-09e5-b156-96af3f83dc0e") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'monto_fase_3_pagado' => $compra->monto,
                        'fecha_fase_3_pagado' => $timestamp,
                        'fase_3_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            } else if ($compra->phasenum == 99) { //cil
                $deal->cil___fcje_pagado = $compra->monto . " " . $fechaActual;
                $deal->cilfcje_fechapagado = $fechaActual;
                $deal->cilfcje_montopagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "f23fbe3b-5d13-0a41-a857-e9ab1c63dc42") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'cil___fcje_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            } else if ($compra->phasenum == 98) { //cnat
                $deal->carta_nat_preestab = $compra->monto . " " . $fechaActual;
                $deal->carta_nat_fechapagado = $fechaActual;
                $deal->carta_nat_montopagado = $compra->monto;
                $deal->save();

                if ($deal->teamleader_id) {
                    $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                    // Conservar los campos existentes
                    $existingFields = $currentProject['custom_fields'];

                    // Actualizar solo el campo necesario sin borrar los demás
                    $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                        if ($field['definition']['id'] === "4339375f-ed77-02d9-a157-7da9f9e4bfac") {
                            $field['value'] = $compra->monto . " " . $fechaActual;
                        }

                        $field['id'] = $field['definition']['id'];

                        unset($field['definition']);

                        return $field;
                    }, $existingFields);

                    $campoTeamleader = ['custom_fields' => $updatedFields];

                    $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                }

                if ($deal->hubspot_id) {
                    // Establecer la zona horaria en UTC
                    $utcTimezone = new \DateTimeZone('UTC');

                    // Obtener la fecha actual a medianoche en UTC
                    $midnightUTC = new \DateTime('now', $utcTimezone);
                    $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                    // Convertir a timestamp en milisegundos
                    $timestamp = $midnightUTC->getTimestamp() * 1000;

                    // Datos para enviar a HubSpot
                    $campoHubspot = [
                        'carta_nat_pagado' =>  $compra->monto . " " . $fechaActual
                    ];

                    $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                }
            }
        }

        $user = User::find(auth()->user()->id);
        $user->pay = $user->pay-10;
        $user->save();

        $pdfContent = $this->createPDF($hash_factura);

        Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
            $m->to([
                auth()->user()->email
            ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });

        $pdfContent2 = $this->createPDFintel($hash_factura);

        Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $request, $user) {
            $m->to([
                'pedro.bazo@sefarvzla.com',
                'crisantoantonio@gmail.com',
                'sistemasccs@sefarvzla.com',
                'automatizacion@sefarvzla.com',
                'sistemascol@sefarvzla.com',
                'asistentedeproduccion@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                'organizacionrrhh@sefarvzla.com',
                '20053496@bcc.hubspot.com',
                'contabilidad@sefaruniversal.com',
                'operacionesc@sefarvzla.com',
                'yeinsondiaz@sefarvzla.com',
                            'dpm.ladera@sefarvzla.com'
            ])->subject(strtoupper($user->name) . ' (ID: ' .
                strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });
    }

    public function procesarpay(Request $request) {
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        //Lo que va dentro de la Funcion

        if (auth()->user()->servicio == 'Constitución de Empresa' || auth()->user()->servicio == 'Representante Fiscal' || auth()->user()->servicio == 'Codigo  Fiscal' || auth()->user()->servicio == 'Apertura de cuenta' || auth()->user()->servicio == 'Trimestre contable' || auth()->user()->servicio == 'Cooperativa 10 años' || auth()->user()->servicio == 'Cooperativa 5 años' || auth()->user()->servicio == 'Portuguesa Sefardi' || auth()->user()->servicio == 'Portuguesa Sefardi - Subsanación' || auth()->user()->servicio == 'Certificación de Documentos - Portugal') {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET_PORT'));
        } else {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        }


        $variable = json_decode(json_encode($request->all()),true);

        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $datos = json_decode(json_encode(DB::table('users')->where('id', auth()->user()->id)->get()),true);

        $finalcupon = "";

        $errorcod = "error";

        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->whereNull('deal_id')->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cps = json_decode(json_encode($compras),true);

        $hss = json_decode(json_encode($servicio),true);

        $monto = 0;

        foreach ($compras as $key => $compra) {
            $monto = $monto + $compra["monto"];
        }

        try {
            $customer = Stripe\Customer::create(array(
                "email" => auth()->user()->email,
                "name" => $request->nameoncard,
                "source" => $request->stripeToken
            ));
            $charged = Stripe\Charge::create ([
                "amount" => $monto*100,
                "currency" => "eur",
                "customer" => $customer->id,
                "description" => "Sefar Universal: Gestión de Pago Múltiple (Carrito)"
            ]);
        } catch(CardException $e) {
            $errorcod= "errorx";
        } catch (RateLimitException $e) {
            $errorcod= "error1";
        } catch (InvalidRequestException $e) {
            $errorcod= "error2";
        } catch (AuthenticationException $e) {
            $errorcod= "error3";
        } catch (ApiConnectionException $e) {
            $errorcod= "error4";
        } catch (ApiErrorException $e) {
            $errorcod= "error5";
        } catch (Exception $e) {
            $errorcod= "error6";
        }

        if ($errorcod== "errorx"){
            return redirect()->route('clientes.pay')->with("status","errorx")->with("code",$e->getError()->code);
        }

        if ($errorcod== "error1"){
            return redirect()->route('clientes.pay')->with("status","error1");
        }

        if ($errorcod== "error2"){
            return redirect()->route('clientes.pay')->with("status","error2");
        }

        if ($errorcod== "error3"){
            return redirect()->route('clientes.pay')->with("status","error3");
        }

        if ($errorcod== "error4"){
            return redirect()->route('clientes.pay')->with("status","error4");
        }

        if ($errorcod== "error5"){
            return redirect()->route('clientes.pay')->with("status","error5");
        }

        if ($errorcod== "error6"){
            return redirect()->route('clientes.pay')->with("status","error6");
        }

        if ($charged->status == "succeeded"){
            if (isset($charged->id)){

                $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                $hash_factura = "sef_".$this->generate_string($permitted_chars, 50);

                Factura::create([
                    'id_cliente' => auth()->user()->id,
                    'hash_factura' => $hash_factura,
                    'met' => 'stripe',
                    'idcus' => $charged->customer,
                    'idcharge' => $charged->id
                ]);

                foreach ($compras as $key => $compra) {
                    DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
                }

                $cargostemp = [];

                if (isset($datos[0]["id_pago"])){
                    if(is_array(json_decode($datos[0]["id_pago"],true))) {
                        $cargostemp = json_decode($datos[0]["id_pago"],true);
                        $cargostemp[] = $charged->id;
                        $cargos = json_encode($cargostemp);
                    } else {
                        $cargostemp[] = $datos[0]["id_pago"];
                        $cargostemp[] = $charged->id;
                        $cargos = json_encode($cargostemp);
                    }
                } else {
                    $cargostemp[] = $charged->id;
                    $cargos = json_encode($cargostemp);
                }

                $cuponestemp = [];

                if (isset($finalcupon)){
                    DB::table('coupons')->where('couponcode', $finalcupon)->update(['enabled' => 0]);
                    if (isset($datos[0]["pago_cupon"])){
                        if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                            $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                            $cuponestemp[] = $finalcupon;
                            $cupones = json_encode($cuponestemp);
                        } else {
                            $cuponestemp[] = $datos[0]["pago_cupon"];
                            $cuponestemp[] = $finalcupon;
                            $cupones = json_encode($cuponestemp);
                        }
                    } else {
                        $cuponestemp[] = $finalcupon;
                        $cupones = json_encode($cuponestemp);
                    }
                } else {
                    if (isset($datos[0]["pago_cupon"])){
                        if(is_array(json_decode($datos[0]["pago_cupon"],true))) {
                            $cuponestemp = json_decode($datos[0]["pago_cupon"],true);
                            $cuponestemp[] = '';
                            $cupones = json_encode($cuponestemp);
                        } else {
                            $cuponestemp[] = $datos[0]["pago_cupon"];
                            $cuponestemp[] = '';
                            $cupones = json_encode($cuponestemp);
                        }
                    } else {
                        $cuponestemp[] = '';
                        $cupones = json_encode($cuponestemp);
                    }
                }

                $pago_registrotemp = [];

                if (isset($datos[0]["pago_registro_hist"])){
                    if(is_array(json_decode($datos[0]["pago_registro_hist"],true))) {
                        $pago_registrotemp = json_decode($datos[0]["pago_registro_hist"],true);
                        $pago_registrotemp[] = $monto;
                        $pago_registro = json_encode($pago_registrotemp);
                    } else {
                        $pago_registrotemp[] = $datos[0]["pago_registro"];
                        $pago_registrotemp[] = $monto;
                        $pago_registro = json_encode($pago_registrotemp);
                    }
                } else {
                    $pago_registrotemp[] = $monto;
                    $pago_registro = json_encode($pago_registrotemp);
                }

                DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 1, 'pago_registro_hist' => $pago_registro, 'pago_registro' => $monto, 'id_pago' => $cargos, 'pago_cupon' => $cupones, 'stripe_cus_id' => $charged->customer, 'contrato' => 0]);

                $setto2 = 1;

                foreach ($compras as $key => $compra) {
                    $servicio = Servicio::where('id_hubspot', $compra["servicio_hs_id"])->get();
                    if ($compra["servicio_hs_id"] == 'Árbol genealógico de Deslinde' || $compra["servicio_hs_id"] == 'Acumulación de linajes' || $compra["servicio_hs_id"] == 'Procedimiento de Urgencia' || $compra["servicio_hs_id"] == 'Recurso de Alzada' || $compra["servicio_hs_id"] == 'Gestión Documental' || $servicio[0]['tipov']==1){
                        $setto2 = 1;
                    } else {
                        $setto2 = 0;
                        break;
                    }
                }

                if ($setto2==1) {
                    DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]);
                    auth()->user()->revokePermissionTo('finish.register');
                }

                auth()->user()->revokePermissionTo('pay.services');
                $idcontact = "";

                $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
                $filter
                    ->setOperator('EQ')
                    ->setPropertyName('email')
                    ->setValue(auth()->user()->email);

                $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
                $filterGroup->setFilters([$filter]);

                $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
                $searchRequest->setFilterGroups([$filterGroup]);

                //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

                $searchRequest->setProperties([
                    "registro_pago",
                    "registro_cupon",
                    "transaction_id"
                ]);

                //Hago la busqueda del cliente
                $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

                if ($contactHS['total'] != 0){
                    $valuehscupon = "";
                    //sago solo el id del contacto:
                    $idcontact = $contactHS['results'][0]['id'];

                    DB::table('users')->where('id', auth()->user()->id)->update(['hs_id' => $idcontact]);

                    $properties1 = [
                        'registro_pago' => $servicio[0]["precio"],
                        'registro_cupon' => $cupones,
                        'transaction_id' => $cargos,
                        'hist_pago_registro' => $pago_registro
                    ];
                    $simplePublicObjectInput = new SimplePublicObjectInput([
                        'properties' => $properties1,
                    ]);

                    $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);

                    $dealInput = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();

                    foreach ($compras as $key => $compra) {
                        $dealInput->setProperties([
                            'dealname' => auth()->user()->name . ' - ' . $compra['servicio_hs_id'],
                            'pipeline' => "94794",
                            'dealstage' => "429097",
                            'servicio_solicitado' => $compra['servicio_hs_id'],
                            'servicio_solicitado2' => $compra['servicio_hs_id'],
                        ]);

                        $apiResponse = json_decode(json_encode($hubspot->crm()->deals()->basicApi()->create($dealInput)),true);

                        $iddeal = $apiResponse["id"];

                        $associationSpec1 = new AssociationSpec([
                            'association_category' => 'HUBSPOT_DEFINED',
                            'association_type_id' => 3
                        ]);

                        $asocdeal = $hubspot->crm()->deals()->associationsApi()->create($iddeal, 'contacts', $idcontact, [$associationSpec1]);
                        sleep(2);
                    }

                }
                $user = User::findOrFail(auth()->user()->id);
                $pdfContent = $this->createPDF($hash_factura);

                Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                    $m->to([
                        auth()->user()->email
                    ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                $pdfContent2 = $this->createPDFintel($hash_factura);

                Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $request, $user) {
                    $m->to([
                        'pedro.bazo@sefarvzla.com',
                        'crisantoantonio@gmail.com',
                        'sistemasccs@sefarvzla.com',
                        'automatizacion@sefarvzla.com',
                        'sistemascol@sefarvzla.com',
                        'asistentedeproduccion@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        '20053496@bcc.hubspot.com',
                        'contabilidad@sefaruniversal.com',
                        'operacionesc@sefarvzla.com',
                        'yeinsondiaz@sefarvzla.com',
                            'dpm.ladera@sefarvzla.com'
                    ])->subject(strtoupper($user->name) . ' (ID: ' .
                        strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                if ($setto2==1) {

                    $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND b.passport='".$user->passport."' ORDER BY a.id DESC LIMIT 1;";

                    $datos_factura = json_decode(json_encode(DB::select($query)),true);

                    $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

                    $servicios = "";

                    foreach ($productos as $key => $value) {
                        $servicios = $servicios . $value["servicio_hs_id"];
                        if ($key != count($productos)-1){
                            $servicios = $servicios . ", ";
                        }
                    }

                    $token = env('MONDAY_TOKEN');
                    $apiUrl = 'https://api.monday.com/v2';
                    $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

                    $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

                    $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

                    $vars = [
                        'myItemName' => auth()->user()->apellidos." ".auth()->user()->nombres,
                        'columnVals' => json_encode([
                            'texto' => auth()->user()->passport,
                            'enlace' => $link . " " . $link,
                            'estado54' => 'Arbol Incompleto',
                            'texto1' => $servicios,
                            'texto4' => auth()->user()->hs_id,
                            'texto_largo88' => auth()->user()->nombre_de_familiar_realizando_procesos
                        ])
                    ];

                    $data = @file_get_contents($apiUrl, false, stream_context_create([
                            'http' => [
                                'method' => 'POST',
                                'header' => $headers,
                                'content' => json_encode(['query' => $query, 'variables' => $vars]),
                            ]
                        ]
                    ));

                    $responseContent = json_decode($data, true);

                    echo json_encode($responseContent);

                }

                return redirect()->route('gracias')->with("status","exito");
            } else {
                return redirect()->route('clientes.pay')->with("status","error6");
            }
        }
    }

    public function procesarpayfases(Request $request) {
        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        //Lo que va dentro de la Funcion

        if (auth()->user()->servicio == 'Constitución de Empresa' || auth()->user()->servicio == 'Representante Fiscal' || auth()->user()->servicio == 'Codigo  Fiscal' || auth()->user()->servicio == 'Apertura de cuenta' || auth()->user()->servicio == 'Trimestre contable' || auth()->user()->servicio == 'Cooperativa 10 años' || auth()->user()->servicio == 'Cooperativa 5 años' || auth()->user()->servicio == 'Portuguesa Sefardi' || auth()->user()->servicio == 'Portuguesa Sefardi - Subsanación' || auth()->user()->servicio == 'Certificación de Documentos - Portugal') {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET_PORT'));
        } else {
            Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
        }

        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $cupones = json_decode(json_encode(Coupon::all()),true);

        $errorcod = "error";

        $compras = Compras::where('id_user', auth()->user()->id)->where('id', $request->compraid)->where('pagado', 0)->whereNotNull('deal_id')->get();
        $servicio = Servicio::where('id_hubspot', auth()->user()->servicio)->get();

        $monto = 0;

        foreach ($compras as $key => $compra) {
            $monto = $monto + $compra["monto"];
        }

        try {
            $customer = Stripe\Customer::create(array(
                "email" => auth()->user()->email,
                "name" => $request->nameoncard,
                "source" => $request->stripeToken
            ));
            $charged = Stripe\Charge::create ([
                "amount" => $monto*100,
                "currency" => "eur",
                "customer" => $customer->id,
                "description" => "Sefar Universal: Gestión de Pago de Fases"
            ]);
        } catch(CardException $e) {
            $errorcod= "errorx";
        } catch (RateLimitException $e) {
            $errorcod= "error1";
        } catch (InvalidRequestException $e) {
            $errorcod= "error2";
        } catch (AuthenticationException $e) {
            $errorcod= "error3";
        } catch (ApiConnectionException $e) {
            $errorcod= "error4";
        } catch (ApiErrorException $e) {
            $errorcod= "error5";
        } catch (Exception $e) {
            $errorcod= "error6";
        }

        if ($errorcod== "errorx"){
            return redirect()->route('clientes.pay')->with("status","errorx")->with("code",$e->getError()->code);
        }

        if ($errorcod== "error1"){
            return redirect()->route('clientes.pay')->with("status","error1");
        }

        if ($errorcod== "error2"){
            return redirect()->route('clientes.pay')->with("status","error2");
        }

        if ($errorcod== "error3"){
            return redirect()->route('clientes.pay')->with("status","error3");
        }

        if ($errorcod== "error4"){
            return redirect()->route('clientes.pay')->with("status","error4");
        }

        if ($errorcod== "error5"){
            return redirect()->route('clientes.pay')->with("status","error5");
        }

        if ($errorcod== "error6"){
            return redirect()->route('clientes.pay')->with("status","error6");
        }

        if ($charged->status == "succeeded"){
            if (isset($charged->id)){

                $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                $hash_factura = "sef_".$this->generate_string($permitted_chars, 50);

                Factura::create([
                    'id_cliente' => auth()->user()->id,
                    'hash_factura' => $hash_factura,
                    'met' => 'stripe',
                    'idcus' => $charged->customer,
                    'idcharge' => $charged->id
                ]);

                foreach ($compras as $key => $compra) {
                    DB::table('compras')->where('id', $compra['id'])->update(['pagado' => 1, 'hash_factura' => $hash_factura]);

                    $deal = Negocio::find($compra->deal_id);
                    $fechaActual = Carbon::now()->format('Y/m/d');

                    if($compra->phasenum == 1){
                        $deal->fase_1_pagado = $compra->monto . " " . $fechaActual;
                        $deal->fecha_fase_1_pagado = $fechaActual;
                        $deal->monto_fase_1_pagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "a1b50c58-8175-0d13-9856-f661e783dc08") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'monto_fase_1_pagado' => $compra->monto,
                                'fecha_fase_1_pagado' => $timestamp,
                                'fase_1_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    } else if ($compra->phasenum == 2) {
                        $deal->fase_2_pagado = $compra->monto . " " . $fechaActual;
                        $deal->fecha_fase_2_pagado = $fechaActual;
                        $deal->monto_fase_2_pagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "a5b94ccc-3ea8-06fc-b259-0a487073dc0d") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'monto_fase_2_pagado' => $compra->monto,
                                'fecha_fase_2_pagado' => $timestamp,
                                'fase_2_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    } else if ($compra->phasenum == 3) {
                        $deal->fase_3_pagado = $compra->monto . " " . $fechaActual;
                        $deal->fecha_fase_3_pagado = $fechaActual;
                        $deal->monto_fase_3_pagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "9a1df9b7-c92f-09e5-b156-96af3f83dc0e") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'monto_fase_3_pagado' => $compra->monto,
                                'fecha_fase_3_pagado' => $timestamp,
                                'fase_3_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    } else if ($compra->phasenum == 99) { //cil
                        $deal->cil___fcje_pagado = $compra->monto . " " . $fechaActual;
                        $deal->cilfcje_fechapagado = $fechaActual;
                        $deal->cilfcje_montopagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "f23fbe3b-5d13-0a41-a857-e9ab1c63dc42") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'cil___fcje_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    } else if ($compra->phasenum == 98) { //cnat
                        $deal->carta_nat_preestab = $compra->monto . " " . $fechaActual;
                        $deal->carta_nat_fechapagado = $fechaActual;
                        $deal->carta_nat_montopagado = $compra->monto;
                        $deal->save();

                        if ($deal->teamleader_id) {
                            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

                            // Conservar los campos existentes
                            $existingFields = $currentProject['custom_fields'];

                            // Actualizar solo el campo necesario sin borrar los demás
                            $updatedFields = array_map(function($field) use ($compra, $fechaActual) {
                                if ($field['definition']['id'] === "4339375f-ed77-02d9-a157-7da9f9e4bfac") {
                                    $field['value'] = $compra->monto . " " . $fechaActual;
                                }

                                $field['id'] = $field['definition']['id'];

                                unset($field['definition']);

                                return $field;
                            }, $existingFields);

                            $campoTeamleader = ['custom_fields' => $updatedFields];

                            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
                        }

                        if ($deal->hubspot_id) {
                            // Establecer la zona horaria en UTC
                            $utcTimezone = new \DateTimeZone('UTC');

                            // Obtener la fecha actual a medianoche en UTC
                            $midnightUTC = new \DateTime('now', $utcTimezone);
                            $midnightUTC->setTime(0, 0, 0);  // Establecer hora en 00:00:00

                            // Convertir a timestamp en milisegundos
                            $timestamp = $midnightUTC->getTimestamp() * 1000;

                            // Datos para enviar a HubSpot
                            $campoHubspot = [
                                'carta_nat_pagado' =>  $compra->monto . " " . $fechaActual
                            ];

                            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
                        }
                    }
                }

                $user = User::find(auth()->user()->id);
                $user->pay = $user->pay-10;
                $user->save();



                $pdfContent = $this->createPDF($hash_factura);

                Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                    $m->to([
                        auth()->user()->email
                    ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                $pdfContent2 = $this->createPDFintel($hash_factura);

                Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $request, $user) {
                    $m->to([
                        'pedro.bazo@sefarvzla.com',
                        'crisantoantonio@gmail.com',
                        'sistemasccs@sefarvzla.com',
                        'automatizacion@sefarvzla.com',
                        'sistemascol@sefarvzla.com',
                        'asistentedeproduccion@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        'organizacionrrhh@sefarvzla.com',
                        '20053496@bcc.hubspot.com',
                        'contabilidad@sefaruniversal.com',
                        'operacionesc@sefarvzla.com',
                        'yeinsondiaz@sefarvzla.com',
                            'dpm.ladera@sefarvzla.com'
                    ])->subject(strtoupper($user->name) . ' (ID: ' .
                        strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                return redirect()->route('gracias')->with("status","exito");
            } else {
                return redirect()->route('clientes.pay')->with("status","error6");
            }
        }
    }

    public function procesarPagoStripe(Request $request) {
        try {
            $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));

            // Determinar qué clave de Stripe usar
            /*
            if (auth()->user()->servicio == 'Constitución de Empresa' ||
                auth()->user()->servicio == 'Representante Fiscal' ||
                auth()->user()->servicio == 'Codigo  Fiscal' ||
                auth()->user()->servicio == 'Apertura de cuenta' ||
                auth()->user()->servicio == 'Trimestre contable' ||
                auth()->user()->servicio == 'Cooperativa 10 años' ||
                auth()->user()->servicio == 'Cooperativa 5 años' ||
                auth()->user()->servicio == 'Portuguesa Sefardi' ||
                auth()->user()->servicio == 'Portuguesa Sefardi - Subsanación' ||
                auth()->user()->servicio == 'Certificación de Documentos - Portugal') {
                \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_PORT'));
            } else {

            }
                */
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            // Obtener datos del request
            // ✅ CORRECTO
            $data = json_decode($request->getContent(), true);
            $paymentMethodId = $data['payment_method_id'] ?? null;

            // Validar que existe el payment_method_id
            if (!$paymentMethodId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibió el método de pago.'
                ], 400);
            }

            // Obtener compras pendientes
            $compras = Compras::where('id_user', auth()->user()->id)
                ->where('pagado', 0)
                ->whereNull('deal_id')
                ->get();

            // Calcular monto total
            $monto = 0;
            foreach ($compras as $compra) {
                $monto += $compra->monto;
            }

            // Validar que haya compras
            if ($compras->isEmpty() || $monto <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hay compras pendientes de pago.'
                ], 400);
            }

            $errorcod = "error";
            $customer = null;
            $paymentIntent = null;

            try {
                // 1. Crear o recuperar el cliente en Stripe
                if (auth()->user()->stripe_cus_id) {
                    // Cliente ya existe
                    $customer = \Stripe\Customer::retrieve(auth()->user()->stripe_cus_id);

                    // Actualizar información del cliente
                    \Stripe\Customer::update(auth()->user()->stripe_cus_id, [
                        'name' => $data['first_name'] . ' ' . $data['last_name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'] ?? null,
                        'address' => [
                            'line1' => $data['address_line1'],
                            'line2' => $data['address_line2'] ?? null,
                            'city' => $data['city'],
                            'state' => $data['state'] ?? null,
                            'postal_code' => $data['postal_code'],
                            'country' => $data['country'],
                        ]
                    ]);
                } else {
                    // Crear nuevo cliente
                    $customer = \Stripe\Customer::create([
                        'name' => $data['first_name'] . ' ' . $data['last_name'],
                        'email' => $data['email'],
                        'phone' => $data['phone'] ?? null,
                        'address' => [
                            'line1' => $data['address_line1'],
                            'line2' => $data['address_line2'] ?? null,
                            'city' => $data['city'],
                            'state' => $data['state'] ?? null,
                            'postal_code' => $data['postal_code'],
                            'country' => $data['country'],
                        ]
                    ]);

                    // Guardar el customer ID en la base de datos
                    DB::table('users')
                        ->where('id', auth()->user()->id)
                        ->update(['stripe_cus_id' => $customer->id]);
                }

                // 2. Adjuntar el método de pago al cliente
                \Stripe\PaymentMethod::retrieve($paymentMethodId)->attach([
                    'customer' => $customer->id
                ]);

                // 3. Establecer como método de pago predeterminado
                \Stripe\Customer::update($customer->id, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentMethodId
                    ]
                ]);

                // 4. Crear el Payment Intent
                $paymentIntent = \Stripe\PaymentIntent::create([
                    'amount' => $monto * 100, // Stripe trabaja en centavos
                    'currency' => 'eur',
                    'customer' => $customer->id,
                    'payment_method' => $paymentMethodId,
                    'off_session' => false,
                    'confirm' => true,
                    'description' => 'Sefar Universal: Gestión de Pago Múltiple (Carrito)',
                    'automatic_payment_methods' => [
                        'enabled' => true,
                        'allow_redirects' => 'never' // ✅ Deshabilita métodos que redirigen
                    ],
                    'metadata' => [
                        'user_id' => auth()->user()->id,
                        'user_email' => auth()->user()->email,
                        'user_passport' => auth()->user()->passport ?? 'N/A',
                    ]
                ]);

                $errorcod = "success";

            } catch(\Stripe\Exception\CardException $e) {
                $errorcod = "errorx";
                $errorCode = $e->getError()->code;
                return response()->json([
                    'success' => false,
                    'message' => $this->getStripeErrorMessage($e->getError()->code),
                    'error_code' => $errorCode
                ], 400);

            } catch (\Stripe\Exception\RateLimitException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Se realizaron varios intentos sin éxito. Por favor, comunicarse con el emisor de su tarjeta.'
                ], 429);

            } catch (\Stripe\Exception\InvalidRequestException $e) {
                \Log::error('Stripe InvalidRequestException: ' . $e->getMessage());
                \Log::error('Stripe Error: ' . json_encode($e->getJsonBody()));

                return response()->json([
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage(), // <-- Cambia esto para ver el error real
                    'debug' => $e->getJsonBody() // <-- Agregado para debug
                ], 400);
            } catch (\Stripe\Exception\AuthenticationException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de autenticación con Stripe. Por favor, comunicar este error a Sistemas.'
                ], 401);

            } catch (\Stripe\Exception\ApiConnectionException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error conectándose a la pasarela de pago. Por favor, comunicar este error a Sistemas.'
                ], 500);

            } catch (\Stripe\Exception\ApiErrorException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'En este momento, la pasarela de pago está en mantenimiento. Por favor, intente pagar más tarde.'
                ], 503);

            } catch (\Exception $e) {
                \Log::error('Error procesando pago Stripe: ' . $e->getMessage());
                return response()->json([
                    'success' => false,
                    'message' => 'Ha ocurrido un error desconocido al realizar su pago.'
                ], 500);
            }

            // Verificar que el pago fue exitoso
            if ($paymentIntent && $paymentIntent->status === 'succeeded') {

                // Generar hash de factura
                $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $hash_factura = "sef_" . $this->generate_string($permitted_chars, 50);

                // Crear factura
                Factura::create([
                    'id_cliente' => auth()->user()->id,
                    'hash_factura' => $hash_factura,
                    'met' => 'stripe',
                    'idcus' => $customer->id,
                    'idcharge' => $paymentIntent->id
                ]);

                // Actualizar compras a pagado
                foreach ($compras as $compra) {
                    DB::table('compras')
                        ->where('id', $compra->id)
                        ->update([
                            'pagado' => 1,
                            'hash_factura' => $hash_factura
                        ]);
                }

                // Obtener datos del usuario
                $datos = DB::table('users')
                    ->where('id', auth()->user()->id)
                    ->first();

                // Actualizar array de cargos
                $cargostemp = [];
                if (isset($datos->id_pago) && !empty($datos->id_pago)) {
                    $existingCargos = json_decode($datos->id_pago, true);
                    if (is_array($existingCargos)) {
                        $cargostemp = $existingCargos;
                    } else {
                        $cargostemp[] = $datos->id_pago;
                    }
                }
                $cargostemp[] = $paymentIntent->id;
                $cargos = json_encode($cargostemp);

                // Actualizar array de cupones (vacío si no se usó cupón)
                $cuponestemp = [];
                if (isset($datos->pago_cupon) && !empty($datos->pago_cupon)) {
                    $existingCupones = json_decode($datos->pago_cupon, true);
                    if (is_array($existingCupones)) {
                        $cuponestemp = $existingCupones;
                    } else {
                        $cuponestemp[] = $datos->pago_cupon;
                    }
                }
                $cuponestemp[] = ''; // No se usó cupón en este pago
                $cupones = json_encode($cuponestemp);

                // Actualizar historial de pagos
                $pago_registrotemp = [];
                if (isset($datos->pago_registro_hist) && !empty($datos->pago_registro_hist)) {
                    $existingPagos = json_decode($datos->pago_registro_hist, true);
                    if (is_array($existingPagos)) {
                        $pago_registrotemp = $existingPagos;
                    } else {
                        $pago_registrotemp[] = $datos->pago_registro;
                    }
                }
                $pago_registrotemp[] = $monto;
                $pago_registro = json_encode($pago_registrotemp);

                // Actualizar usuario
                DB::table('users')
                    ->where('id', auth()->user()->id)
                    ->update([
                        'pay' => 1,
                        'pago_registro_hist' => $pago_registro,
                        'pago_registro' => $monto,
                        'id_pago' => $cargos,
                        'pago_cupon' => $cupones,
                        'stripe_cus_id' => $customer->id,
                        'contrato' => 0
                    ]);

                // Verificar si debe setear pay = 2
                $setto2 = 1;
                foreach ($compras as $compra) {
                    $servicio = Servicio::where('id_hubspot', $compra->servicio_hs_id)->first();

                    if ($compra->servicio_hs_id == 'Árbol genealógico de Deslinde' ||
                        $compra->servicio_hs_id == 'Acumulación de linajes' ||
                        $compra->servicio_hs_id == 'Procedimiento de Urgencia' ||
                        $compra->servicio_hs_id == 'Recurso de Alzada' ||
                        $compra->servicio_hs_id == 'Gestión Documental' ||
                        ($servicio && $servicio->tipov == 1)) {
                        $setto2 = 1;
                    } else {
                        $setto2 = 0;
                        break;
                    }
                }

                if ($setto2 == 1) {
                    DB::table('users')
                        ->where('id', auth()->user()->id)
                        ->update(['pay' => 2]);

                    auth()->user()->revokePermissionTo('finish.register');
                }

                auth()->user()->revokePermissionTo('pay.services');

                $this->registrarVentaEnMonday($customer, $paymentIntent, $monto, $compras);

                // Actualizar HubSpot
                try {
                    $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
                    $filter->setOperator('EQ')
                        ->setPropertyName('email')
                        ->setValue(auth()->user()->email);

                    $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
                    $filterGroup->setFilters([$filter]);

                    $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
                    $searchRequest->setFilterGroups([$filterGroup]);
                    $searchRequest->setProperties([
                        "registro_pago",
                        "registro_cupon",
                        "transaction_id"
                    ]);

                    $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

                    if ($contactHS['total'] != 0) {
                        $idcontact = $contactHS['results'][0]['id'];

                        DB::table('users')
                            ->where('id', auth()->user()->id)
                            ->update(['hs_id' => $idcontact]);

                        $properties1 = [
                            'registro_pago' => $monto,
                            'registro_cupon' => $cupones,
                            'transaction_id' => $cargos,
                            'hist_pago_registro' => $pago_registro
                        ];

                        $simplePublicObjectInput = new \HubSpot\Client\Crm\Contacts\Model\SimplePublicObjectInput([
                            'properties' => $properties1,
                        ]);

                        $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);

                        // Crear deals
                        foreach ($compras as $compra) {
                            $dealInput = new \HubSpot\Client\Crm\Deals\Model\SimplePublicObjectInput();
                            $dealInput->setProperties([
                                'dealname' => auth()->user()->name . ' - ' . $compra->servicio_hs_id,
                                'pipeline' => "94794",
                                'dealstage' => "429097",
                                'servicio_solicitado' => $compra->servicio_hs_id,
                                'servicio_solicitado2' => $compra->servicio_hs_id,
                            ]);

                            $dealResponse = $hubspot->crm()->deals()->basicApi()->create($dealInput);
                            $iddeal = $dealResponse->id;

                            $associationSpec1 = new \HubSpot\Client\Crm\Associations\Model\AssociationSpec([
                                'association_category' => 'HUBSPOT_DEFINED',
                                'association_type_id' => 3
                            ]);

                            $hubspot->crm()->deals()->associationsApi()->create(
                                $iddeal,
                                'contacts',
                                $idcontact,
                                [$associationSpec1]
                            );

                            sleep(2);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Error actualizando HubSpot: ' . $e->getMessage());
                }

                // Enviar emails
                try {
                    $user = User::findOrFail(auth()->user()->id);
                    $pdfContent = $this->createPDF($hash_factura);

                    Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $user) {
                        $m->to(auth()->user()->email)
                            ->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')
                            ->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                    });

                    $pdfContent2 = $this->createPDFintel($hash_factura);

                    Mail::send('mail.comprobante-mail-intel', ['user' => $user], function ($m) use ($pdfContent2, $user) {
                        $m->to([
                            'pedro.bazo@sefarvzla.com',
                            'crisantoantonio@gmail.com',
                            'sistemasccs@sefarvzla.com',
                            'automatizacion@sefarvzla.com',
                            'sistemascol@sefarvzla.com',
                            'asistentedeproduccion@sefarvzla.com',
                            'organizacionrrhh@sefarvzla.com',
                            '20053496@bcc.hubspot.com',
                            'contabilidad@sefaruniversal.com',
                            'operacionesc@sefarvzla.com',
                            'yeinsondiaz@sefarvzla.com',
                            'dpm.ladera@sefarvzla.com'
                        ])->subject(strtoupper($user->name) . ' (ID: ' .
                            strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')
                            ->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                    });
                } catch (\Exception $e) {
                    \Log::error('Error enviando emails: ' . $e->getMessage());
                }

                // Crear item en Monday si corresponde
                if ($setto2 == 1) {
                    try {
                        $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro
                                FROM facturas as a, users as b
                                WHERE a.id_cliente = b.id AND b.passport='" . $user->passport . "'
                                ORDER BY a.id DESC LIMIT 1;";

                        $datos_factura = DB::select($query);
                        $productos = Compras::where("hash_factura", $datos_factura[0]->hash_factura)->get();

                        $servicios = "";
                        foreach ($productos as $key => $value) {
                            $servicios .= $value->servicio_hs_id;
                            if ($key != count($productos) - 1) {
                                $servicios .= ", ";
                            }
                        }

                        $token = env('MONDAY_TOKEN');
                        $apiUrl = 'https://api.monday.com/v2';
                        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

                        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) {
                            create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) {
                                id
                            }
                        }';

                        $link = 'https://app.sefaruniversal.com/tree/' . auth()->user()->passport;

                        $vars = [
                            'myItemName' => auth()->user()->apellidos . " " . auth()->user()->nombres,
                            'columnVals' => json_encode([
                                'texto' => auth()->user()->passport,
                                'enlace' => $link . " " . $link,
                                'estado54' => 'Arbol Incompleto',
                                'texto1' => $servicios,
                                'texto4' => auth()->user()->hs_id,
                                'texto_largo88' => auth()->user()->nombre_de_familiar_realizando_procesos
                            ])
                        ];

                        @file_get_contents($apiUrl, false, stream_context_create([
                            'http' => [
                                'method' => 'POST',
                                'header' => $headers,
                                'content' => json_encode(['query' => $query, 'variables' => $vars]),
                            ]
                        ]));
                    } catch (\Exception $e) {
                        \Log::error('Error creando item en Monday: ' . $e->getMessage());
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Pago procesado exitosamente',
                    'payment_intent_id' => $paymentIntent->id
                ]);

            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'El pago no pudo ser completado. Estado: ' . ($paymentIntent->status ?? 'desconocido')
                ], 400);
            }

        } catch (\Exception $e) {
            \Log::error('Error general en procesarPagoStripe: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ha ocurrido un error inesperado. Por favor, intente nuevamente.'
            ], 500);
        }
    }

    /**
 * Mapea los nombres de servicios de la base de datos a los valores del dropdown de Monday
 *
 * @param string $servicio Nombre del servicio en la base de datos
 * @return string Nombre del servicio según Monday
 */
    protected function mapearServicioParaMonday($servicio)
    {
        // Mapeo completo de servicios
        $mapa = [
            // Servicios principales
            'Española LMD' => 'Ley De Memoria Democrática (Supuesto 1)',
            'Italiana' => 'Nacionalidad Italiana',
            'Española Sefardi' => 'Carta de Naturaleza Sefardí',
            'Portuguesa Sefardi' => 'Sefardí Portugal',
            'Portuguesa Sefardi - Subsanación' => 'Subsanación de Documentos',
            'Española Sefardi - Subsanación' => 'Subsanación de Documentos',
            'Española - Carta de Naturaleza' => 'Carta De Naturaleza',
            'Análisis por semana' => 'Análisis Por Semana',
            'Recurso de Alzada' => 'Recurso de Alzada',
            'Gestión Documental' => 'Asistencia Documental',
            'Constitución de Empresa' => 'Gestorias',
            'Representante Fiscal' => 'Representante Fiscal',
            'Codigo  Fiscal' => 'Gestorias',
            'Apertura de cuenta' => 'Gestorias',
            'Trimestre contable' => 'Gestorias',
            'Cooperativa 10 años' => 'Vinculación Portugal (Cooperativas 10 Años)',
            'Cooperativa 5 años' => 'Vinculaciones Portugal (Cooperativas 5 Años)',
            'Participaciones sociales' => 'Vinculaciones Portugal',
            'Acumulación de linajes' => 'Investigacion Genealogica',
            'Árbol genealógico de Deslinde' => 'Deslinde Genealógico',
            'Procedimiento de Urgencia' => 'Procedimiento De Urgencia',
            'Certificación de Documentos - Portugal' => 'Jornada Especial De Certificacion De Documentos',

            // Servicios de hermanos (mapean igual que los principales)
            'Española LMD - Hermano' => 'Ley De Memoria Democrática (Supuesto 1)',
            'Italiana - Hermano' => 'Nacionalidad Italiana',
            'Española Sefardi - Hermano' => 'Carta de Naturaleza Sefardí',
            'Portuguesa Sefardi - Hermano' => 'Sefardí Portugal',
            'Portuguesa Sefardi - Subsanación - Hermano' => 'Subsanación de Documentos',
            'Española Sefardi - Subsanación - Hermano' => 'Subsanación de Documentos',
            'Española - Carta de Naturaleza - Hermano' => 'Carta De Naturaleza',
            'Análisis por semana - Hermano' => 'Análisis Por Semana',
            'Recurso de Alzada - Hermano' => 'Recurso de Alzada',
            'Gestión Documental - Hermano' => 'Asistencia Documental',
            'Constitución de Empresa - Hermano' => 'Gestorias',
            'Representante Fiscal - Hermano' => 'Representante Fiscal',
            'Codigo  Fiscal - Hermano' => 'Gestorias',
            'Apertura de cuenta - Hermano' => 'Gestorias',
            'Trimestre contable - Hermano' => 'Gestorias',
            'Cooperativa 10 años - Hermano' => 'Vinculación Portugal (Cooperativas 10 Años)',
            'Cooperativa 5 años - Hermano' => 'Vinculaciones Portugal (Cooperativas 5 Años)',
            'Participaciones sociales - Hermano' => 'Vinculaciones Portugal',
            'Acumulación de linajes - Hermano' => 'Investigacion Genealogica',
            'Árbol genealógico de Deslinde - Hermano' => 'Deslinde Genealógico',
            'Procedimiento de Urgencia - Hermano' => 'Procedimiento De Urgencia',
            'Certificación de Documentos - Portugal - Hermano' => 'Jornada Especial De Certificacion De Documentos',

            // Servicios adicionales
            'Analisis Juridico Genealogico' => 'Plan de Accion Jurídico Sefardi',
            'Diagnóstico Express para Plan de acción de la Nacionalidad Italiana' => 'Nacionalidad Italiana',
            'Formalizacion Anticipada Portuguesa Sefardi' => 'Formalizacion De Expediente De Nacionalidad Portuguesa',
            'Formalizacion Anticipada Ley de Memoria Democrática' => 'Formalización de Expediente españa',
            'Nacionalidad Portuguesa por Conyuge' => 'Nacionalidad Portuguesa por origen Sefardi',
            'Nacionalidad Española por Conyuge' => 'Carta De Naturaleza',
        ];

        // Si existe en el mapa, devolver el valor mapeado
        if (isset($mapa[$servicio])) {
            return $mapa[$servicio];
        }

        // Si no existe en el mapa, intentar una búsqueda parcial
        foreach ($mapa as $key => $value) {
            if (stripos($servicio, $key) !== false) {
                return $value;
            }
        }

        // Si no se encuentra coincidencia, devolver "Registro" como valor por defecto
        \Log::warning('Servicio no mapeado para Monday: ' . $servicio);
        return 'Registro';
    }

    /**
     * Registra la venta en el tablero de Monday.com "Ventas 2026"
     *
     * @param object $customer Cliente de Stripe
     * @param object $paymentIntent Payment Intent de Stripe
     * @param float $monto Total pagado
     * @param array $compras Array de compras realizadas
     * @return bool
     */
    protected function registrarVentaEnMonday($customer, $paymentIntent, $monto, $compras)
    {
        try {
            $token = env('MONDAY_TOKEN');
            $apiUrl = 'https://api.monday.com/v2';
            $headers = [
                'Content-Type: application/json',
                'Authorization: ' . $token
            ];

            $user = auth()->user();

            // Construir la descripción de servicios vendidos CON MAPEO
            $serviciosDescripcion = "";
            $serviciosMapeados = []; // Array para almacenar servicios mapeados

            foreach ($compras as $key => $compra) {
                // Mapear el servicio
                $servicioMapeado = $this->mapearServicioParaMonday($compra->servicio_hs_id);
                $serviciosMapeados[] = $servicioMapeado;

                $serviciosDescripcion .= $servicioMapeado;
                if ($key != count($compras) - 1) {
                    $serviciosDescripcion .= ", ";
                }
            }

            // Nombre del item (título de la venta)
            $itemName = count($compras) > 1 ?
                "Compra Múltiple - " . $user->name :
                $serviciosDescripcion;

            // Preparar la fecha en formato YYYY-MM-DD
            $fechaPago = Carbon::now()->format('Y-m-d');

            // ✅ IMPORTANTE: Para el dropdown, si hay múltiples servicios, usar el primero
            $servicioParaDropdown = $serviciosMapeados[0] ?? 'Registro';

            // Preparar los valores de las columnas
            $columnValues = [
                // Cliente
                'text_mkqswz4p' => $user->name,

                // Número de pasaporte
                'text_mkzaptd3' => $user->passport ?? 'N/A',

                // Fecha de pago
                'date' => $fechaPago,

                // Forma de Pago
                'text_mkrd13sa' => 'Stripe',

                // ✅ Servicio vendidos (dropdown) - USAR VALOR MAPEADO
                'dropdown_mkt4dwyq' => $servicioParaDropdown,

                // Total pagado
                'numeric_mkqsn730' => $monto,

                // Personas por las que se paga (IDs de Stripe + Servicios completos)
                'text_mkza4s9z' => "ID Cliente: " . $customer->id . " | ID Pago: " . $paymentIntent->id . " | Servicios: " . $serviciosDescripcion
            ];

            // Query de GraphQL para crear el item
            $query = 'mutation ($myItemName: String!, $columnVals: JSON!) {
                create_item (
                    board_id: 18393840903,
                    item_name: $myItemName,
                    column_values: $columnVals
                ) {
                    id
                    name
                }
            }';

            // Variables para la mutación
            $vars = [
                'myItemName' => $itemName,
                'columnVals' => json_encode($columnValues)
            ];

            // Preparar el contexto para la petición HTTP
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => json_encode([
                        'query' => $query,
                        'variables' => $vars
                    ]),
                    'ignore_errors' => true
                ]
            ]);

            // Ejecutar la petición
            $response = file_get_contents($apiUrl, false, $context);

            if ($response === false) {
                throw new \Exception("No se pudo conectar con Monday.com");
            }

            $responseData = json_decode($response, true);

            // Verificar si hubo errores
            if (isset($responseData['errors']) && count($responseData['errors']) > 0) {
                $errorMessage = $responseData['errors'][0]['message'] ?? 'Error desconocido';
                throw new \Exception("Error de Monday.com: " . $errorMessage);
            }

            // Log de éxito
            \Log::info('Venta registrada en Monday.com exitosamente', [
                'board_id' => 18393840903,
                'item_name' => $itemName,
                'monday_item_id' => $responseData['data']['create_item']['id'] ?? null,
                'user_id' => $user->id,
                'passport' => $user->passport,
                'monto' => $monto,
                'servicios_originales' => array_column($compras->toArray(), 'servicio_hs_id'),
                'servicios_mapeados' => $serviciosMapeados
            ]);

            return true;

        } catch (\Exception $e) {
            // Log del error con más detalles
            \Log::error('Error registrando venta en Monday.com: ' . $e->getMessage(), [
                'user_id' => auth()->user()->id ?? null,
                'customer_id' => $customer->id ?? null,
                'payment_intent_id' => $paymentIntent->id ?? null,
                'monto' => $monto ?? null,
                'servicios' => $compras ? array_column($compras->toArray(), 'servicio_hs_id') : [],
                'trace' => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    // Función auxiliar para generar string aleatorio
    private function generate_string($input, $strength = 16) {
        $input_length = strlen($input);
        $random_string = '';
        for($i = 0; $i < $strength; $i++) {
            $random_character = $input[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }
        return $random_string;
    }

    // Función auxiliar para traducir códigos de error de Stripe
    private function getStripeErrorMessage($code) {
        $errors = [
            'authentication_required' => 'La tarjeta fue rechazada porque la transacción requiere autenticación.',
            'approve_with_id' => 'No se puede autorizar el pago.',
            'call_issuer' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'card_not_supported' => 'La tarjeta no admite este tipo de compra.',
            'card_velocity_exceeded' => 'El cliente ha superado el saldo o límite de crédito disponible en su tarjeta.',
            'currency_not_supported' => 'La tarjeta no admite la moneda especificada.',
            'do_not_honor' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'do_not_try_again' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'duplicate_transaction' => 'Recientemente se envió una transacción con la misma cantidad e información de la tarjeta de crédito.',
            'expired_card' => 'La tarjeta ha caducado.',
            'fraudulent' => 'El pago fue rechazado porque Stripe sospecha que es fraudulento.',
            'generic_decline' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'incorrect_number' => 'El número de tarjeta es incorrecto.',
            'incorrect_cvc' => 'El número CVC es incorrecto.',
            'incorrect_pin' => 'El PIN ingresado es incorrecto.',
            'incorrect_zip' => 'El código postal es incorrecto.',
            'insufficient_funds' => 'La tarjeta no tiene fondos suficientes para completar la compra.',
            'invalid_account' => 'La tarjeta o la cuenta a la que está conectada la tarjeta no es válida.',
            'invalid_amount' => 'El monto del pago no es válido o excede el monto permitido.',
            'invalid_cvc' => 'El número CVC es incorrecto.',
            'invalid_expiry_month' => 'El mes de vencimiento no es válido.',
            'invalid_expiry_year' => 'El año de caducidad no es válido.',
            'invalid_number' => 'El número de tarjeta es incorrecto.',
            'invalid_pin' => 'El PIN ingresado es incorrecto.',
            'issuer_not_available' => 'No se pudo contactar al emisor de la tarjeta, por lo que no se pudo autorizar el pago.',
            'lost_card' => 'El pago fue rechazado porque la tarjeta se reportó perdida.',
            'merchant_blacklist' => 'El pago fue rechazado porque coincide con un valor en la lista de bloqueo.',
            'new_account_information_available' => 'La tarjeta o la cuenta a la que está conectada la tarjeta no es válida.',
            'no_action_taken' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'not_permitted' => 'El pago no está permitido.',
            'offline_pin_required' => 'La tarjeta fue rechazada porque requiere un PIN.',
            'online_or_offline_pin_required' => 'La tarjeta fue rechazada porque requiere un PIN.',
            'pickup_card' => 'El cliente no puede usar esta tarjeta para realizar este pago.',
            'pin_try_exceeded' => 'Se superó el número permitido de intentos de PIN.',
            'processing_error' => 'Ocurrió un error al procesar la tarjeta.',
            'reenter_transaction' => 'El emisor no pudo procesar el pago por un motivo desconocido.',
            'restricted_card' => 'El cliente no puede usar esta tarjeta para realizar este pago.',
            'revocation_of_all_authorizations' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'revocation_of_authorization' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'security_violation' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'service_not_allowed' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'stolen_card' => 'El pago fue rechazado porque la tarjeta fue reportada como robada.',
            'stop_payment_order' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'testmode_decline' => 'Se utilizó un número de tarjeta de prueba.',
            'transaction_not_allowed' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'try_again_later' => 'La tarjeta fue rechazada por un motivo desconocido.',
            'withdrawal_count_limit_exceeded' => 'El cliente ha superado el saldo o límite de crédito disponible en su tarjeta.',
        ];

        return $errors[$code] ?? 'Error desconocido: ' . $code;
    }

    public function checkRegAlzada(Request $request) {
        $mailpass = json_decode(json_encode(DB::table('users')->where('email', $request->email)->where('passport', $request->numero_de_pasaporte)->get()),true);
        $mail = json_decode(json_encode(DB::table('users')->where('email', $request->email)->get()),true);

        $check = 0;

        //dd(json_decode(json_encode($request->all()), true));

        $cantidad = 0;

        if(isset($request->cantidad_alzada) && $request->cantidad_alzada>=0){
            $cantidad = $cantidad + $request->cantidad_alzada;
        }

        $antepasados = 0;

        if (isset($request->tiene_antepasados_espanoles) && $request->tiene_antepasados_espanoles == "Si"){
            $antepasados = 1;
        }

        if (isset($request->tiene_antepasados_italianos) && $request->tiene_antepasados_italianos == "Si"){
            $antepasados = 2;
        }

        if(isset($request->vinculo_antepasados)){
            $vinculo_antepasados = $request->vinculo_antepasados;
        } else {
            $vinculo_antepasados = '';
        }

        if(isset($request->estado_de_datos_y_documentos_de_los_antepasados)){
            $estado_de_datos_y_documentos_de_los_antepasados = $request->estado_de_datos_y_documentos_de_los_antepasados;
        } else {
            $estado_de_datos_y_documentos_de_los_antepasados = '';
        }

        if (isset($request->nacionalidad_solicitada)){
            $servicio_solicitado = Servicio::where('id_hubspot', "like", $request->nacionalidad_solicitada."%")->first();
        }

        $hss = [];
        $hss[] = json_decode(json_encode($servicio_solicitado),true);

        if (count($mailpass)>0 || count($mail)>0) {
            $preusercheck = json_decode( json_encode( DB::table('users')->where('email', $request->email)->get()),true);

            $comprasexistentes = json_decode( json_encode( DB::table('compras')->where('id_user', $preusercheck[0]['id'])->where('servicio_hs_id', $request->nacionalidad_solicitada)->get()),true);

            if (count($comprasexistentes) > 0){
                $check = 2;
            } else {

                $familiares = 1 + $request->cantidad_alzada;
                DB::table('users')->where('email', $request->email)->update([
                    'pay' => 0,
                    'servicio' => $servicio_solicitado->nombre,
                    'cantidad_alzada' => $cantidad + 1,
                    "antepasados" => $antepasados,
                    'vinculo_antepasados' => $vinculo_antepasados,
                    'estado_de_datos_y_documentos_de_los_antepasados' => $estado_de_datos_y_documentos_de_los_antepasados
                ]);


                if(count($mailpass)>0){
                    $userdata = json_decode(json_encode(DB::table('users')->where('email', $request->email)->where('passport', $request->numero_de_pasaporte)->get()),true);
                } elseif (count($mail)>0){
                    $userdata = json_decode(json_encode(DB::table('users')->where('email', $request->email)->get()),true);
                }

                $compras = Compras::where('id_user', $userdata[0]["id"])->where('pagado', 0)->get();

                $servicio[] = $servicio_solicitado;

                $cps = json_decode(json_encode($compras),true);

                if($userdata[0]["servicio"] == "Recurso de Alzada"){
                    $monto = $hss[0]["precio"] * ($cantidad+1);
                } else {
                    $monto = $hss[0]["precio"];
                }

                if( $userdata[0]["servicio"] == "Española LMD" || $userdata[0]["servicio"] == "Italiana" ) {
                    $desc = "Pago Fase Inicial: Investigación Preliminar y Preparatoria: " . $hss[0]["nombre"];
                    if ($userdata[0]["servicio"] == "Española LMD"){
                        if ($userdata[0]['antepasados']==0){
                            $monto = 299;
                        }
                    }
                    if ($userdata[0]["servicio"] == "Italiana"){
                        if ($userdata[0]['antepasados']==1){
                            $desc = $desc . " + (Consulta Gratuita)";
                        }
                    }
                } elseif ( $userdata[0]["servicio"] == "Gestión Documental" ) {
                    $desc = $hss[0]["nombre"];
                } elseif ($servicio[0]['tipov']==1) {
                    $desc = "Servicios para Vinculaciones: " . $hss[0]["nombre"];
                } else {
                    $desc = "Análisis genealógico: " . $servicio_solicitado->nombre;
                }

                if (isset($request->pay)){
                    if ($request->pay==='1'){
                        $usuariofinal = DB::table('users')->where('email', $request->email)->update([
                            'pay' => 1
                        ]);

                        if (isset($request->monto)){
                            $compra = Compras::create([
                                'id_user' => $userdata[0]["id"],
                                'servicio_hs_id' => $hss[0]["id_hubspot"],
                                'descripcion' => 'Pago desde www.sefaruniversal.com usando Jotform',
                                'pagado' => 0,
                                'monto' =>$request->monto
                            ]);
                        } else {
                            $compra = Compras::create([
                                'id_user' => $userdata[0]["id"],
                                'servicio_hs_id' => $hss[0]["id_hubspot"],
                                'descripcion' => 'Pago desde www.sefaruniversal.com usando Jotform',
                                'pagado' => 0,
                                'monto' => 0
                            ]);
                        }



                        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                        $hash_factura = "sef_".$this->generate_string($permitted_chars, 50);

                        Factura::create([
                            'id_cliente' => $usuariofinal->id,
                            'hash_factura' => $hash_factura,
                            'met' => 'jotform'
                        ]);

                        DB::table('compras')->where('id', $compra->id)->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
                    } else {
                        Compras::create([
                            'id_user' => $userdata[0]["id"],
                            'servicio_hs_id' => $hss[0]["id_hubspot"],
                            'descripcion' => $desc,
                            'pagado' => 0,
                            'monto' => $monto
                        ]);
                    }
                } else {
                    Compras::create([
                        'id_user' => $userdata[0]["id"],
                        'servicio_hs_id' => $hss[0]["id_hubspot"],
                        'descripcion' => $desc,
                        'pagado' => 0,
                        'monto' => $monto
                    ]);
                }

                $check = 1;
            }
        }

        if ($check == 1){
            return redirect()->route('login')->with( ['warning' => 'Ya estabas registrado con el correo ' . $request->email . ' en nuestra plataforma. Por favor, inicia sesión.'] )->with( ['email' => $request->email] );
        } elseif ($check == 2) {
            return redirect()->route('login')->with( ['warning' => 'Ya estabas registrado con el correo ' . $request->email . ' en nuestra plataforma y ya habias solicitado este servicio en el pasado. Por favor, inicia sesión.'] )->with( ['email' => $request->email] );
        } else {
            return redirect()->route( 'register' )->with( ['request' => $request->all()] );
        }
    }

    public function vinculaciones() {
        $servicios = Servicio::where('tipov', 1)->get();
        $compras = DB::table('compras')->where('id_user', auth()->user()->id)->get();
        return view('clientes.vinculaciones', compact('compras', 'servicios'));
    }

    public function regvinculaciones(Request $request) {
        $servicios = Servicio::where('id_hubspot', $request->id)->get();
        $desc = "Servicios para Vinculaciones: " . $servicios[0]["nombre"];
        Compras::create([
            'id_user' => auth()->user()->id,
            'servicio_hs_id' => $servicios[0]["id_hubspot"],
            'descripcion' => $desc,
            'pagado' => 0,
            'monto' => $servicios[0]["precio"]
        ]);
        return redirect()->route('clientes.pay');
    }

    public function fixPayDataHubspot()
    {
        ini_set('max_execution_time', 3000000);
        ini_set('max_input_time', 3000000);

        $hubspot = HubSpot\Factory::createWithAccessToken(env('HUBSPOT_KEY'));
        $query = 'SELECT id, email, pago_cupon, pago_registro, hs_id FROM users where pago_cupon = "NOPAY" or id_pago = "NOPAY"';

        $globalcount = json_decode(json_encode(DB::select($query)),true);

        foreach ($globalcount as $key => $value) {
            $idcontact = "";

            $filter = new \HubSpot\Client\Crm\Contacts\Model\Filter();
            $filter
                ->setOperator('EQ')
                ->setPropertyName('email')
                ->setValue($value["email"]);

            $filterGroup = new \HubSpot\Client\Crm\Contacts\Model\FilterGroup();
            $filterGroup->setFilters([$filter]);

            $searchRequest = new \HubSpot\Client\Crm\Contacts\Model\PublicObjectSearchRequest();
            $searchRequest->setFilterGroups([$filterGroup]);

            //Llamo a todas las propiedades de Hubspot (Si el dia de mañana hay que añadir algo nuevo, la ventaja que tenemos es que ya me estoy trayendo todos los elementos del arreglo)

            $searchRequest->setProperties([
                "registro_pago",
                "registro_cupon",
                "transaction_id"
            ]);

            //Hago la busqueda del cliente
            $contactHS = $hubspot->crm()->contacts()->searchApi()->doSearch($searchRequest);

            if ($contactHS['total'] != 0){
                $valuehscupon = "";
                //sago solo el id del contacto:
                $idcontact = $contactHS['results'][0]['id'];

                DB::table('users')->where('id', $value['id'])->update(['hs_id' => $idcontact]);
                $properties1 = [
                    'registro_pago' => '0',
                    'registro_cupon' => 'NOPAY',
                    'transaction_id' => 'NOPAY'
                ];

                $simplePublicObjectInput = new SimplePublicObjectInput([
                    'properties' => $properties1,
                ]);

                $apiResponse = $hubspot->crm()->contacts()->basicApi()->update($idcontact, $simplePublicObjectInput);
            }

            sleep(2);
        }

        print_r($globalcount);
    }

    public function destroypayelement(Request $request)
    {
        $data = $request->all();
        Compras::where('id', $data["id"])->delete();
        $compras = Compras::where('id_user', auth()->user()->id)->where('pagado', 0)->get();
        if(count($compras)>0){
            echo(1);
        } else {
            echo(0);
        }
    }

    public function checkMondayTest()
    {
        $token = env('MONDAY_TOKEN');
        $apiUrl = 'https://api.monday.com/v2';
        $headers = ['Content-Type: application/json', 'Authorization: ' . $token];

        $query = 'mutation ($myItemName: String!, $columnVals: JSON!) { create_item (board_id: 878831315, group_id: "duplicate_of_en_proceso", item_name:$myItemName, column_values:$columnVals) { id } }';

        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        $hash_factura = "PRUEBA".$this->generate_string($permitted_chars, 10);

        $link = 'https://app.sefaruniversal.com/tree/' . $hash_factura;

        $vars = [
            'myItemName' => 'PRUEBAS PRUEBAS',
            'columnVals' => json_encode([
                'texto' => $hash_factura,
                'link' => $link . " " . $link,
                'estado54' => 'Arbol Incompleto',
                'texto1' => 'PRUEBA',
                'texto4' => $hash_factura
            ])
        ];

        $data = @file_get_contents($apiUrl, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => $headers,
                    'content' => json_encode(['query' => $query, 'variables' => $vars]),
                ]
            ]
        ));
    }

    private function createPDF($dato){
        $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.hash_factura='$dato';";
        $datos_factura = json_decode(json_encode(DB::select($query)),true);

        $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

        $pdf = PDF::loadView('crud.comprobantes.pdf', compact('datos_factura', 'productos'));

        return $pdf->output();
    }

    private function createPDFintel($dato){
        $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.hash_factura='$dato';";
        $datos_factura = json_decode(json_encode(DB::select($query)),true);

        $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

        $pdf = PDF::loadView('crud.comprobantes.pdfintel', compact('datos_factura', 'productos'));

        return $pdf->output();
    }
}

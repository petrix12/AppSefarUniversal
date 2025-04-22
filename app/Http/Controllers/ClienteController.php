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
use App\Models\Hermano;
use App\Models\Alert as Alertas;
use App\Models\GeneralCoupon;
use Illuminate\Http\Request;
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
use Monday;
use Carbon\Carbon;
use App\Services\TeamleaderService;
use App\Services\HubspotService;
use Illuminate\Support\Facades\Schema;
use App\Models\MondayData;
use App\Models\MondayFormBuilder;

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

    public function status(){
        $user = Auth::user();

        $usuariosMondaytemp = $this->getUsersForSelect();

        $usuariosMondaytemp = json_decode(json_encode($usuariosMondaytemp),true);

        $usuariosMonday = $usuariosMondaytemp["original"];

        $facturas = Factura::with("compras")->where("id_cliente", $user->id)->get();

        // Verificar si el usuario ya tiene hs_id
        if (is_null($user->hs_id)) {
            $HScontactByEmail = $this->hubspotService->searchContactByEmail($user->email);
            if ($HScontactByEmail) {
                $user->hs_id = $HScontactByEmail['id'];
                $user->save();
            } else {
                throw new \Exception("El contacto no se encontró en HubSpot.");
            }
        }

        $HScontact = $this->hubspotService->getContactById($user->hs_id);

        $HScontactFiles = $this->hubspotService->getEngagementsByContactId($user->hs_id);

        $urls = $this->hubspotService->getContactFileFields($user->hs_id);

        $deals = $this->hubspotService->getDealsByContactId($user->hs_id);

        if (is_null($user->tl_id)) {
            $TLcontactByEmail = $this->teamleaderService->searchContactByEmail($user->email);

            if (is_null($TLcontactByEmail)) {
                $newContact = $this->teamleaderService->createContact($user);
                $user->tl_id = $newContact['id'];
            } else {
                $user->tl_id = $TLcontactByEmail['id'];
            }

            $user->save();
        }

        // Almacenar las etapas de pipelines cargadas para evitar llamadas repetidas
        $pipelineStages = [];

        // Procesar los negocios y asociar sus etapas (dealstage) y opciones
        $dealsWithStages = array_map(function ($deal) use (&$pipelineStages) {
            $dealstageId = $deal['properties']['dealstage'] ?? null;
            $pipelineId = $deal['properties']['pipeline'] ?? null;

            $dealstageName = null;
            $dealstageOptions = [];

            if ($pipelineId) {
                // Verificar si ya cargamos las etapas del pipeline
                if (!isset($pipelineStages[$pipelineId])) {
                    $pipelineStages[$pipelineId] = $this->hubspotService->getDealStagesByPipeline($pipelineId);
                }

                // Buscar el nombre del dealstage en las etapas del pipeline
                $dealstageName = collect($pipelineStages[$pipelineId])->firstWhere('id', $dealstageId)['name'] ?? null;

                // Asignar las opciones de dealstage (todas las etapas del pipeline)
                $dealstageOptions = $pipelineStages[$pipelineId];
            }

            // Retornar el negocio con el nombre de su etapa actual y las opciones de etapas
            return array_merge($deal, [
                'dealstage_name' => $dealstageName,
                'dealstage_options' => $dealstageOptions,
            ]);
        }, $deals);

        $TLcontact = $this->teamleaderService->getContactById($user->tl_id);
        $TLdeals = $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id);

        $camposDeTeamleader = [
            'n1__enviada_al_cliente' => '4203d8ab-f1de-0145-af52-1bb278951268',
            'documentos' => 'e254d7ed-3c93-097d-b659-852a3b74c5e5',
            'n1__lugar_del_expediente' => '4bbfdc08-686d-0a03-8557-bd1d60d46f57',
            'n1__monto_preestablecido' => '6f7a4408-b146-0e58-a35b-8f02fed60887',
            'n10__fecha_asignacion_de_juez' => '497e7359-8b1a-056a-9e5e-28fa0cf5b2f1',
            'n11__envio_redaccion_abogada' => 'e04af721-8808-0a43-9356-df374565b2fa',
            'n12__notas___no__expediente' => '4b822322-17a1-06ba-9b5b-82db70f46f5b',
            'n13__fecha_recurso_alzada' => '7c06cfed-87f4-00ac-8a5a-946b0b9643b8',
            'n2__firmado_por_el_cliente' => '5f090e48-4a5b-0504-8259-9e945e95126a',
            'n2__antecedentes_penales' => '35c68020-1160-068b-b055-1b5e6fe4ca11',
            'n2__ciudad_formalizacion' => 'ad849a21-82b3-0032-995e-6e9dbcd46f53',
            'n2__enviado_a_redaccion_informe' => 'ed8167e1-00e2-05fb-8a5c-900699b54d88',
            'n2__monto_pagado' => '4bef5482-f2e4-02da-8653-691944760f84',
            'n3__gestionado___entregado' => 'b0421965-2b39-0c4d-9e51-1e567b05126b',
            'n3__contratos_y_permisos' => '39085084-d206-073e-8057-ef23ab046f5a',
            'n3__f__vencimiento_ant__penal' => '578e17da-c01b-0a97-bc5a-7d9255b4c9d5',
            'n3__informe_cargado' => '1c067d8e-1b3b-0b4b-8c5f-436233b4c3f2',
            'n4__certificado_descargado' => '62a2cd97-1898-00bf-885c-029939e4c40f',
            'n4__pago_tasa' => 'a2d11316-e31b-0b2c-bd5e-0c7ad13491d0',
            'n5___f_solicitud_documentos' => 'e0919d4b-322a-0c06-9759-0a6607f4c9db',
            'n5__fecha_de_formalizacion' => '7c87a75b-ce63-01da-9c58-5277f6c40fa9',
            'n5__notas_genealogia' => 'edc41efc-e52f-0c9a-8e5d-41b8fff4c3f3',
            'n6__cil_preaprobado' => '57535be4-4738-00b5-9251-b53739e607c0',
            'n6__fecha_acta_remitida_' => '8091a7fc-3023-0625-8051-de85a4c46f59',
            'n7__enviado_al_dto_juridico' => 'c3feeebf-21a9-0cac-855e-e6f550260ee0',
            'n7__fecha_caducidad_pasaporte' => '6fb8ef4e-6fdb-0241-8354-bda543e4cbff',
            'n7__fecha_de_resolucion' => '3ef52253-5ac1-025a-8c5b-a9d094c468b8',
            'n4__notario___abogado' => '36fa5b9d-bafd-0e61-9058-72b4ed547197',
            'n8__f_rec__solicitud_doc' => 'e255a259-5328-0ee6-ab52-3e4f9604c9de',
            'n9__enviado_a_legales' => '047dc070-6b23-0434-b858-61a1d7e4c9fd',
            'n9__notif__1__int__subsanar_' => '7918f47c-4097-07e1-af57-d6c435660883',
            'n91__recepcion_recaudos_fisico' => '8e8ea98b-5137-047b-8157-c44935a4c3f1',
            'carta_nat_pagado' => '4339375f-ed77-02d9-a157-7da9f9e4bfac',
            'carta_nat_preestab' => 'a42ed217-b570-0973-9052-fab97214c229',
            'cil___fcje_pagado' => 'f23fbe3b-5d13-0a41-a857-e9ab1c63dc42',
            'cil___fcje_preestab' => 'aa1ce4b9-a410-00f2-a953-5f8c2713dc35',
            'codigo_de_proceso' => 'a42f63f5-d527-0544-ab50-9c03857707f2',
            'argumento_de_ventas__new_' => 'c34c71b3-331e-0524-a45a-95a654e51b4c',
            'fase_0_pagado__teamleader_' => 'd90b2e44-2e9b-0f29-945a-71c34bb3def0',
            'fase_1_pagado__teamleader_' => 'a1b50c58-8175-0d13-9856-f661e783dc08',
            'fase_1_preestab' => '73173887-a0e8-0f4f-bb55-b61f33d3c6e9',
            'fase_2_pagado__teamleader_' => 'a5b94ccc-3ea8-06fc-b259-0a487073dc0d',
            'fase_2_preestab' => 'c66a9c15-c965-0812-ad5b-7e48f183c6f9',
            'fase_3_pagado__teamleader_' => '9a1df9b7-c92f-09e5-b156-96af3f83dc0e',
            'fase_3_preestab' => 'e41fdbbb-a25a-005b-af56-9f3ca623c700',
            'fecha_de_aceptacion' => 'fbe8df81-7225-0c01-b051-7f1032054ffe',
            'date_of_birth' => '2ef543c1-e76c-025a-a950-67eec7954d89',
            'numero_de_pasaporte' => '891080d2-eeeb-030f-a256-d0ee6095773d',
            'pais_de_residencia' => 'bd374fc3-39a5-0070-9455-67d94cc6b7f7',
            'servicio_solicitado' => 'fcd48891-20f6-049a-a05f-f78a6f951b4d'
        ];

        if (sizeof($dealsWithStages) > sizeof($TLdeals)) {
            // Obtener los nombres de los tratos en Teamleader
            $teamleaderDealNames = array_map(function ($deal) {
                return $deal['title']; // Asegúrate de que este es el campo que contiene el nombre
            }, $TLdeals);

            // Iterar sobre los tratos de HubSpot
            foreach ($dealsWithStages as $deal) {
                // Validar si el trato ya existe en Teamleader
                if (!in_array($deal['properties']['dealname'], $teamleaderDealNames)) {
                    // Crear el trato si no existe
                    $this->teamleaderService->createProjectFromHubspotDeal($deal, $user->tl_id, $camposDeTeamleader);
                }
            }

            // Actualizar la lista de tratos en Teamleader
            $TLdeals = $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id);
        }

        //get updated deals

        $deals = $this->hubspotService->getDealsByContactId($user->hs_id);
        $TLdeals = $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id);

        $teamleaderDealNames = array_column($TLdeals, 'title', 'id');

        // Obtener los campos de la base de datos excepto los que no se deben llenar
        $columns = Schema::getColumnListing((new Negocio)->getTable());
        $excludedColumns = ['id', 'created_at', 'updated_at', 'hubspot_id', 'teamleader_id', 'user_id'];
        $fillableColumns = array_diff($columns, $excludedColumns);

        // Iterar sobre los negocios de HubSpot y actualizar la base de datos
        foreach ($deals as $deal) {
            $dealId = $deal['id'];
            $dealName = $deal['properties']['dealname'] ?? null;

            //Limpiar

            $data = $deal['properties']["argumento_de_ventas__new_"];

            // Asegurarse de que siempre sea un array, incluso si no hay punto y coma
            $arrayData = $data ? ( strpos($data, ';') !== false ? explode(';', $data) : [$data] ) : null;

            // Convertir el array a JSON
            $jsonData = json_encode($arrayData, JSON_UNESCAPED_UNICODE);

            // Asignar el JSON convertido de nuevo al arreglo
            $deal['properties']["argumento_de_ventas__new_"] = $jsonData;

            $data = $deal['properties']["n2__antecedentes_penales"];

            // Asegurarse de que siempre sea un array, incluso si no hay punto y coma
            $arrayData = $data ? ( strpos($data, ';') !== false ? explode(';', $data) : [$data] ) : null;

            // Convertir el array a JSON
            $jsonData = json_encode($arrayData, JSON_UNESCAPED_UNICODE);

            // Asignar el JSON convertido de nuevo al arreglo
            $deal['properties']["n2__antecedentes_penales"] = $jsonData;

            $data = $deal['properties']["documentos"];

            // Asegurarse de que siempre sea un array, incluso si no hay punto y coma
            $arrayData = $data ? ( strpos($data, ';') !== false ? explode(';', $data) : [$data] ) : null;

            // Convertir el array a JSON
            $jsonData = json_encode($arrayData, JSON_UNESCAPED_UNICODE);

            // Asignar el JSON convertido de nuevo al arreglo
            $deal['properties']["documentos"] = $jsonData;

            // Buscar un trato en Teamleader con el mismo nombre
            $teamleaderId = array_search($dealName, $teamleaderDealNames) ?: null;

            // Buscar si ya existe el negocio en la base de datos
            $existingDeal = Negocio::where('hubspot_id', $dealId)->first();

            // Filtrar solo las propiedades de HubSpot que coincidan con las columnas de la base de datos
            $data = [
                'hubspot_id' => $dealId,
                'teamleader_id' => $teamleaderId,
                'user_id' => $user->id,
            ];

            foreach ($fillableColumns as $column) {
                $data[$column] = $deal['properties'][$column] ?? null;
            }

            if (!$existingDeal) {
                // Si no existe, insertar un nuevo registro
                Negocio::create($data);
            }
        }

        $negocios = Negocio::where("user_id", $user->id)->get();

        $resultUrls = [];
        foreach ($urls as $url) {
            $fileUrl = $this->hubspotService->getFileUrlFromFormIntegrations($url);
            if ($fileUrl !== null) {
                $resultUrls[] = $fileUrl;
            }
        }

        foreach ($resultUrls as $fileUrl) {
            $filename = basename(parse_url($fileUrl, PHP_URL_PATH));

            $s3Path = "public/doc/{$user->passport}/{$filename}";

            $existeEnDB = File::where('file', $filename)
                ->where('IDCliente', $user->passport)
                ->exists();
            if ($existeEnDB) {
                continue;
            }
            // --- Verificación en S3 ---
            $existeEnS3 = Storage::disk('s3')->exists($s3Path);

            if ($existeEnS3) {
                continue;
            }

            $fileContents = file_get_contents($fileUrl);

            Storage::disk('s3')->put($s3Path, $fileContents);

            File::create([
                'file'      => $filename,                       // Nombre del archivo
                'location'  => "public/doc/{$user->passport}/", // Carpeta base
                'IDCliente' => $user->passport,                 // Identificador de cliente
            ]);
        }

        foreach ($HScontactFiles as $fileUrl) {
            $filename = basename(parse_url($fileUrl, PHP_URL_PATH));

            $s3Path = "public/doc/{$user->passport}/{$filename}";

            $existeEnDB = File::where('file', $filename)
                ->where('IDCliente', $user->passport)
                ->exists();
            if ($existeEnDB) {
                continue;
            }

            // --- Verificación en S3 ---
            $existeEnS3 = Storage::disk('s3')->exists($s3Path);

            if ($existeEnS3) {
                continue;
            }

            $fileContents = file_get_contents($fileUrl);

            Storage::disk('s3')->put($s3Path, $fileContents);

            File::create([
                'file'      => $filename,                       // Nombre del archivo
                'location'  => "public/doc/{$user->passport}/", // Carpeta base
                'IDCliente' => $user->passport,                 // Identificador de cliente
            ]);
        }

        $archivos = File::where("IDCliente", $user->passport)->get();

        // Mapear campos de HubSpot con los de la base de datos
        $hubspotFields = [
            'fecha_nac' => 'date_of_birth',
            'firstname' => 'nombres',
            'lastmodifieddate' => 'updated_at',
            'lastname' => 'apellidos',
            'n000__referido_por__clonado_' => 'referido_por',
            'numero_de_pasaporte' => 'passport',
            'servicio_solicitado' => 'servicio',
        ];

        // Recorrer propiedades de HubSpot y añadir las faltantes al arreglo
        foreach ($HScontact['properties'] as $hsField => $value) {
            if (!array_key_exists($hsField, $hubspotFields) && $hsField != "createdate" && $hsField != "hs_object_id") {
                // Agrega automáticamente un nuevo campo con una clave genérica
                $hubspotFields[$hsField] = $hsField; // Usa el mismo nombre como clave en DB
            }
        }

        $updatesToDB = [];
        $updatesToHubSpot = [];

        $hsLastModified = new \DateTime($HScontact['properties']['lastmodifieddate']);
        $dbLastModified = new \DateTime($user->updated_at);

        $utcTimezone = new \DateTimeZone('UTC');
        $hsLastModified->setTimezone($utcTimezone);
        $dbLastModified->setTimezone($utcTimezone);

        foreach ($hubspotFields as $hsField => $dbField) {
            $hubspotValue = $HScontact['properties'][$hsField] ?? null;
            $dbValue = $user->{$dbField};

            if ($hsField != 'lastmodifieddate') {
                // Comparar valores y fechas
                if ($hubspotValue !== $dbValue) {
                    // Ejemplo de comparaciones
                    if ($hubspotValue && (!$dbValue || $hsLastModified > $dbLastModified)) {
                        // HubSpot más reciente
                        if ($hsField!="updated_at"){
                            $user->{$dbField} = $hubspotValue;
                            $updatesToDB[$dbField] = $hubspotValue;
                        }
                    } elseif ($dbValue && (!$hubspotValue || $dbLastModified > $hsLastModified)) {
                        // Base de datos más reciente
                        switch ($hsField) {
                            case 'fecha_nac':
                            case 'date_of_birth':
                                if (!empty($dbValue) && $dbValue !="0000-00-00") {
                                    try {
                                        // Convertir la fecha de la base de datos a timestamp en milisegundos
                                        $onlyDate = (new \DateTime($dbValue))->format('Y-m-d');
                                        $dbDate = new \DateTime($onlyDate, new \DateTimeZone('UTC'));
                                        $dbTimestampMs = $dbDate->getTimestamp() * 1000;

                                        // Convertir la fecha de HubSpot a timestamp en milisegundos (si existe)
                                        $hubspotValue = $HScontact['properties'][$hsField] ?? null;
                                        if ($hubspotValue !== null) {
                                            $hubspotDate = (new \DateTime())->setTimestamp($hubspotValue / 1000);
                                            $hubspotDate->setTimezone(new \DateTimeZone('UTC'));
                                            $hubspotTimestampMs = $hubspotDate->getTimestamp() * 1000;
                                        } else {
                                            $hubspotTimestampMs = null;
                                        }

                                        // Solo actualizar si el valor en HubSpot es diferente
                                        if ($hubspotTimestampMs !== $dbTimestampMs) {
                                            $updatesToHubSpot[$hsField] = $dbTimestampMs;
                                        }
                                    } catch (\Exception $e) {
                                        // Manejar el error de fecha si es necesario
                                    }
                                }
                                break;

                            case 'genero':
                                $cleanValue = trim($dbValue); // Quitar espacios en blanco
                                $mapping = [
                                    'MASCULINO' => 'MASCULINO / MALE',
                                    'FEMENINO'  => 'FEMENINO / FEMALE',
                                    'OTROS'     => 'OTROS / OTHERS',
                                ];

                                if (isset($mapping[$cleanValue])) {
                                    $mappedValue = $mapping[$cleanValue];

                                    // Solo actualizar si el valor en HubSpot es diferente
                                    $hubspotValue = $HScontact['properties'][$hsField] ?? null;
                                    if ($hubspotValue !== $mappedValue) {
                                        $updatesToHubSpot[$hsField] = $mappedValue;
                                    }
                                }
                                break;

                            default:
                                // Comparar valores directamente para otros campos
                                $hubspotValue = $HScontact['properties'][$hsField] ?? null;
                                if ($hsField == "cantidad_alzada"){
                                    if (strval($hubspotValue) !== strval($dbValue)) {
                                        $updatesToHubSpot[$hsField] = $dbValue;
                                    }
                                } else {
                                    if ($hubspotValue !== $dbValue) {
                                        $updatesToHubSpot[$hsField] = $dbValue;
                                    }
                                }
                                break;
                        }
                    }
                }
            }
        }

        $excludedKeys = ['lastmodifieddate', 'referido_por']; // Lista de claves a excluir
        $updatesToHubSpot = array_filter(
            $updatesToHubSpot,
            fn($key) => !in_array($key, $excludedKeys),
            ARRAY_FILTER_USE_KEY
        );

        if (isset($updatesToDB['date_of_birth']) && is_numeric($updatesToDB['date_of_birth'])) {
            if ((int)$updatesToDB['date_of_birth']) {
                unset($updatesToDB['date_of_birth']); // Elimina el campo si es EPOCH
            }
        }

        // Guardar los cambios en la base de datos si hubo actualizaciones
        if (!empty($updatesToDB)) {
            $user->save();
        }

        // Actualizar HubSpot si hubo cambios
        if (!empty($updatesToHubSpot)) {
            try {
                // Aquí llamas a tu servicio HubSpot que hace el PATCH
                $this->hubspotService->updateContact($user->hs_id, $updatesToHubSpot);
            } catch (ClientException $e) {
                // Obtén la respuesta completa en formato string
                $responseBodyAsString = (string) $e->getResponse()->getBody();
            }
        }

        // Obtener tratos asociados desde HubSpot
        $HSdeals = $this->hubspotService->getDealsByContactId($user->hs_id);

        // Monday
        if (!$user->monday_id) {
            $mondayUserDetailsPre = $this->searchUserInMonday($user->passport, $user);
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
                    }
                    text
                }
            }
        ";

        $result = json_decode(json_encode(Monday::customQuery($query)), true);

        $mondayUserDetailsPre = $result['items'][0] ?? null;

        // Guardar datos del usuario en Monday
        if ($mondayUserDetailsPre) {
            $this->storeMondayUserData($user, $mondayUserDetailsPre);
            $boardId = $mondayUserDetailsPre['board']['id'] ?? null;
            $boardName = $mondayUserDetailsPre['board']['name'] ?? null;

            // Guardar las columnas del board en `monday_form_builder`
            if ($boardId) {
                $this->storeMondayBoardColumns($boardId);
            }

            $mondayData = json_decode(MondayData::where('user_id', $user->id)->first(), true);
            $mondayData["data"] = json_decode($mondayData["data"] , true);

            $dataMonday = [];

            foreach($mondayData["data"]["column_values"] as $key => $campo){
                $dataMonday[$campo["id"]] = $campo["text"];
            }

            $mondayFormBuilder = json_decode(MondayFormBuilder::where('board_id', $boardId)->get(), true);

            foreach($mondayFormBuilder as $key=>$campo){
                $mondayFormBuilder[$key]["settings"] = json_decode($campo["settings"], true);
            }

            $mondayUserDetails = [];
            $mondayUserDetails["nombre"] = $mondayUserDetailsPre["name"];
            $mondayUserDetails["id"] = $mondayUserDetailsPre["id"];

            foreach($mondayUserDetailsPre["column_values"] as $key=>$element){
                $mondayUserDetails["propiedades"][$element["id"]] = [$element["column"]["title"], $element["text"]];
            }
        } else {
            $dataMonday = [];
            $mondayData = [];
            $mondayFormBuilder = [];
            $mondayUserDetails = [];
            $boardId = 0;
            $boardName = "";
        }

        // Preparar datos para la vista
        $roles = Role::all();
        $permissions = Permission::all();
        $servicios = Servicio::all();

        $people = json_decode(json_encode(Agcliente::where("IDCliente",trim($user->passport))->get()),true);

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

        $maxGeneraciones = count($generaciones) > 0 ? max($generaciones) : 0;
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

                    if (isset($persona2["idPadreNew"]) && @$persona2["idPadreNew"]==null){

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

                    if (isset($persona2["idMadreNew"]) && @$persona2["idMadreNew"]==null){

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

        foreach ($columnasparatabla as $key => $persona) {
            if ($key==0){
                $columnasparatabla[$key][0]["parentesco"] = "Cliente";
            } else if ($key == 1){
                $columnasparatabla[$key][0]["parentesco"] = "Padre";
                $columnasparatabla[$key][1]["parentesco"] = "Madre";
            } else {
                foreach ($columnasparatabla[$key] as $key2 => $familiar) {
                    $columnasparatabla[$key][$key2]["parentesco"] = $parentescos[$key-2][$key2];
                }
            }
        }

        $flagOtrosProcesos = false;
        $flagMayorInformacion = false;
        $flagNoApto = false;
        $flagArbolIncompleto = true;

        $i = 0;
        foreach ( $columnasparatabla as $generacion => $grupo ){
            foreach ( $grupo as $persona){
                if (isset($persona["showbtn"]) && $persona["showbtn"] == 2){
                    if($persona["parentesco"] == "Tatarabuelo" || $persona["parentesco"] == "Tatarabuelo"){
                        $flagArbolIncompleto = false;
                    }
                }
            }
        }

        /* Calculo de estatus de cliente - Me quiero suicidar... Este codigo es una papa, pero toca hacerlo */

        $clientstatus = 0; // Aqui cubrimos hasta la parte en la que comienza el registro. De resto, dependemos de Monday, o en su defecto Hubspot/teamleader

        if ($user->pay != 0){
            if ($user->pay==1){
                //el cliente pagó pero no ha completado el 001
                $clientstatus = 0.33;
            } else if (!($user->pay==1 || $user->pay==0)){
                if($user->contrato == 0){
                    //no ha firmado contrato
                    $clientstatus = 0.67;
                } else {
                    // Analisis Genealógico
                    $clientstatus = 1;
                }
            }
        } else {
            $clientstatus = 0;
        }

        if ($flagArbolIncompleto == false){
            if ($clientstatus == 1){
                $checktags = "";
                //si está en analisis genealógico, tienes que revisar las etiquetas que tiene el cliente en Monday
                if (isset($dataMonday["men__desplegable"]) && $dataMonday["men__desplegable"] != "") {
                    $checktags = $dataMonday["men__desplegable"];

                    $resultadoIA = $this->analizarEtiquetas($checktags);

                    if ($resultadoIA == 2) {
                        $flagOtrosProcesos = true;
                    } else if ($resultadoIA == 3) {
                        $flagMayorInformacion = true;
                    } else if ($resultadoIA == 4) {
                        $flagArbolIncompleto = true;
                    } else if ($resultadoIA == 5) {
                        $flagNoApto = true;
                    } else {
                        $clientstatus = $clientstatus + $resultadoIA;
                    }
                } else {
                    $clientstatus == 1;
                }
            }
        }

        //aqui empezamos a depender del Hubspot y del Teamleader... Tenemos que pasar toda la data de los negocios.
        // esta es la parte ladilla

        $dealsDataCOS = false;

        if ($clientstatus > 1.69) {
            $dealsDataCOS = true;
        }

        $servicename = Servicio::where("id_hubspot", $user->servicio)->first();

        $html = view('crud.users.edit', compact('servicename', 'flagArbolIncompleto', 'flagNoApto', 'dealsDataCOS', 'flagMayorInformacion', 'flagOtrosProcesos', 'clientstatus', 'negocios', 'usuariosMonday', 'dataMonday', 'mondayData', 'boardId', 'boardName', 'mondayFormBuilder', 'archivos', 'user', 'roles', 'permissions', 'facturas', 'servicios', 'columnasparatabla'))->render();
        return $html;
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

        return view('arboles.tree', compact('IDCliente', 'people', 'columnasparatabla', 'cliente', 'tipoarchivos', 'parentescos', 'htmlGenerado', 'checkBtn', 'generacionBase', 'parentnumber'));
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
            if(Auth::user()->pay==2){
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

    public function procesargetinfo(Request $request){
        /*

            Aqui recibo y organizo el arreglo que viene del Jquery

        */

        if (auth()->user()->pay == 3){
            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]); // no borrar esta linea
            auth()->user()->revokePermissionTo('finish.register');
        } else {
            $inputdata = json_decode(json_encode($request->all()),true);

            $input_u = $inputdata["data"];

            $input = array();

            foreach ($input_u as $key => $value) {
                if($input_u[$key]["name"]!="hs_context") {
                    $input[$input_u[$key]["name"]] = $input_u[$key]["value"];
                }
            }

            DB::table('users')->where('id', auth()->user()->id)->update(['pay' => 2]); // no borrar esta linea
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
                    'texto4' => auth()->user()->hs_id
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
                                'texto_largo8' => $nombres_y_apellidos_del_padre,
                                'texto_largo75' => $nombres_y_apellidos_de_madre,
                                'enlace' => $link . " " . $link,
                                'estado54' => 'Arbol Incompleto',
                                'texto1' => $servicios,
                                'texto6' => auth()->user()->hs_id
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
                        $monto = 99;
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
                $desc = "Inicia tu Proceso: " . $hss[0]["nombre"];
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
            $servicio = Servicio::where('id_hubspot', "like", auth()->user()->servicio." - Hermano")->get();
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
                        $monto = 99;
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
                $desc = "Inicia tu Proceso: " . $hss[0]["nombre"];
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

                    $hash_factura = "sef_".generate_string($permitted_chars, 50);

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
                    $pdfContent = createPDF($hash_factura);

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
                                'texto4' => auth()->user()->hs_id
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

        $hash_factura = "sef_".generate_string($permitted_chars, 50);

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
        $pdfContent = createPDF($hash_factura);

        Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
            $m->to([
                auth()->user()->email
            ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });

        $pdfContent2 = createPDFintel($hash_factura);

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
                    'texto4' => auth()->user()->hs_id
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

        $hash_factura = "sef_".generate_string($permitted_chars, 50);

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

        $pdfContent = createPDF($hash_factura);

        Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
            $m->to([
                auth()->user()->email
            ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
        });

        $pdfContent2 = createPDFintel($hash_factura);

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

                $hash_factura = "sef_".generate_string($permitted_chars, 50);

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
                $pdfContent = createPDF($hash_factura);

                Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                    $m->to([
                        auth()->user()->email
                    ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                $pdfContent2 = createPDFintel($hash_factura);

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
                            'texto4' => auth()->user()->hs_id
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

                $hash_factura = "sef_".generate_string($permitted_chars, 50);

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



                $pdfContent = createPDF($hash_factura);

                Mail::send('mail.comprobante-mail', ['user' => $user], function ($m) use ($pdfContent, $request, $user) {
                    $m->to([
                        auth()->user()->email
                    ])->subject('SEFAR UNIVERSAL - Hemos procesado su pago satisfactoriamente')->attachData($pdfContent, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                $pdfContent2 = createPDFintel($hash_factura);

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
                    ])->subject(strtoupper($user->name) . ' (ID: ' .
                        strtoupper($user->passport) . ') HA REALIZADO UN PAGO EN App Sefar Universal')->attachData($pdfContent2, 'Comprobante.pdf', ['mime' => 'application/pdf']);
                });

                return redirect()->route('gracias')->with("status","exito");
            } else {
                return redirect()->route('clientes.pay')->with("status","error6");
            }
        }
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

                $servicio = Servicio::where('id_hubspot', "like", $servicio_solicitado->nombre."%")->get();

                $cps = json_decode(json_encode($compras),true);

                $hss = json_decode(json_encode($servicio),true);

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
                    $desc = "Inicia tu Proceso: " . $servicio_solicitado;
                }

                if (isset($request->pay)){
                    if ($request->pay==='1'){
                        $usuariofinal = DB::table('users')->where('email', $request->email)->update([
                            'pay' => 1
                        ]);

                        if (isset($request->monto)){
                            $compra = Compras::create([
                                'id_user' => $userdata[0]["id"],
                                'servicio_hs_id' => $userdata[0]["servicio"],
                                'descripcion' => 'Pago desde www.sefaruniversal.com usando Jotform',
                                'pagado' => 0,
                                'monto' =>$request->monto
                            ]);
                        } else {
                            $compra = Compras::create([
                                'id_user' => $userdata[0]["id"],
                                'servicio_hs_id' => $userdata[0]["servicio"],
                                'descripcion' => 'Pago desde www.sefaruniversal.com usando Jotform',
                                'pagado' => 0,
                                'monto' => 0
                            ]);
                        }



                        $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

                        $hash_factura = "sef_".generate_string($permitted_chars, 50);

                        Factura::create([
                            'id_cliente' => $usuariofinal->id,
                            'hash_factura' => $hash_factura,
                            'met' => 'jotform'
                        ]);

                        DB::table('compras')->where('id', $compra->id)->update(['pagado' => 1, 'hash_factura' => $hash_factura]);
                    } else {
                        Compras::create([
                            'id_user' => $userdata[0]["id"],
                            'servicio_hs_id' => $userdata[0]["servicio"],
                            'descripcion' => $desc,
                            'pagado' => 0,
                            'monto' => $monto
                        ]);
                    }
                } else {
                    Compras::create([
                        'id_user' => $userdata[0]["id"],
                        'servicio_hs_id' => $userdata[0]["servicio"],
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

        $hash_factura = "PRUEBA".generate_string($permitted_chars, 10);

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
}

function generate_string($input, $strength = 16) {
    $input_length = strlen($input);
    $random_string = '';
    for($i = 0; $i < $strength; $i++) {
        $random_character = $input[mt_rand(0, $input_length - 1)];
        $random_string .= $random_character;
    }

    return $random_string;
}

function createPDF($dato){
    $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.hash_factura='$dato';";
    $datos_factura = json_decode(json_encode(DB::select($query)),true);

    $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

    $pdf = PDF::loadView('crud.comprobantes.pdf', compact('datos_factura', 'productos'));

    return $pdf->output();
}

function createPDFintel($dato){
    $query = "SELECT a.*, b.name, b.passport, b.email, b.phone, b.created_at as fecha_de_registro FROM facturas as a, users as b WHERE a.id_cliente = b.id AND a.hash_factura='$dato';";
    $datos_factura = json_decode(json_encode(DB::select($query)),true);

    $productos = json_decode(json_encode(Compras::where("hash_factura", $datos_factura[0]["hash_factura"])->get()),true);

    $pdf = PDF::loadView('crud.comprobantes.pdfintel', compact('datos_factura', 'productos'));

    return $pdf->output();
}

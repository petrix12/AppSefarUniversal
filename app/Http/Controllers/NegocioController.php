<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Factura;
use App\Models\Compras;
use App\Services\TeamleaderService;
use App\Services\HubspotService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class NegocioController extends Controller
{
    protected $teamleaderService;
    protected $hubspotService;

    public function __construct(TeamleaderService $teamleaderService, HubspotService $hubspotService)
    {
        $this->teamleaderService = $teamleaderService;
        $this->hubspotService = $hubspotService;
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    private function syncDealIndividual($dealDb, $camposRelacionados, $user)
    {
        $hubspotId = $dealDb->hubspot_id;
        $teamleaderId = $dealDb->teamleader_id;

        // Obtener deal específico de HubSpot
        $hubspotDeal = $this->hubspotService->getDealById($hubspotId);
        if (!$hubspotDeal || !isset($hubspotDeal['properties'])) {
            return dd("❌ No se encontró el trato en HubSpot con ID {$hubspotId}");
        }

        // Obtener deal específico de Teamleader (solo si tiene ID)
        $TLdeal = $teamleaderId ? $this->teamleaderService->getProjectDetails($teamleaderId) : null;

        $hubspotLastMod = new \DateTime($hubspotDeal['properties']['lastmodifieddate'] ?? '1970-01-01');
        $teamleaderLastMod = $TLdeal ? new \DateTime($TLdeal['updated_at'] ?? '1970-01-01') : null;

        $hsProps = $hubspotDeal['properties'];
        $tlFields = $TLdeal['custom_fields'] ?? [];

        $updatesToHubspot = [];
        $updatesToTeamleader = [];
        $updatesToDB = [];

        foreach ($camposRelacionados as $hsField => $tlFieldId) {
            $hsValue = $hsProps[$hsField] ?? null;
            $tlValue = collect($tlFields)->firstWhere('definition.id', $tlFieldId)['value'] ?? null;

            // Determinar valor final por frescura
            if ($hsValue && (!$tlValue || ($teamleaderLastMod && $hubspotLastMod > $teamleaderLastMod))) {
                $finalValue = $hsValue;
            } elseif ($tlValue) {
                $finalValue = $tlValue;
            } else {
                continue;
            }

            // Teamleader solo si existe y requiere actualización
            if ($teamleaderId && $tlValue !== $finalValue) {
                $updatesToTeamleader[] = [
                    'id' => $tlFieldId,
                    'value' => $finalValue
                ];
            }

            // HubSpot solo si TL tiene algo más reciente
            if ($tlValue && (!$hsValue || ($teamleaderLastMod && $teamleaderLastMod > $hubspotLastMod))) {
                $updatesToHubspot[$hsField] = $tlValue;
            }

            // Base de datos
            $updatesToDB[$hsField] = $finalValue;
        }

        // Actualizar HubSpot
        if (!empty($updatesToHubspot)) {
            $this->hubspotService->updateDeals($hubspotId, $updatesToHubspot);
        }

        // Actualizar Teamleader
        if ($teamleaderId && !empty($updatesToTeamleader)) {
            $this->teamleaderService->updateProject($teamleaderId, ['custom_fields' => $updatesToTeamleader]);
        }

        // Actualizar base de datos
        foreach ($updatesToDB as $campo => $valor) {
            if (Schema::hasColumn((new Negocio)->getTable(), $campo)) {
                $dealDb->{$campo} = $valor;
            }
        }
        $dealDb->save();

    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $deal_db = Negocio::find($id);
        $user = User::find($deal_db->user_id);

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

        $this->syncDealIndividual($deal_db, $camposDeTeamleader, $user);

        $TLdeals = $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id);
        return view('crud.negocios.edit', compact('deal_db', 'user', 'TLdeals'));
    }

    public function sincronizarhsytl(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $deal->teamleader_id = $request->teamleader_id;

        if ($deal->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Sincronización completada correctamente.'
            ], 200); // Código HTTP 200: OK
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los cambios.'
            ], 500); // Código HTTP 500: Error interno del servidor
        }
    }

    public function guardarfase1(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $user = User::find($deal->user_id);


        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->fase_1_preestab = $request->fase_1_preestab . " " . $fechaActual;
        $deal->fase_1_enviado = $fechaActual;
        $deal->fase_1_pagado = null;
        $deal->fecha_fase_1_pagado = null;
        $deal->monto_fase_1_pagado = null;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === '73173887-a0e8-0f4f-bb55-b61f33d3c6e9') {
                    $field['value'] = $request->fase_1_preestab . " " . $fechaActual;
                }

                $field['id'] = $field['definition']['id'];

                unset($field['definition']);

                return $field;
            }, $existingFields);

            $campoTeamleader = ['custom_fields' => $updatedFields];

            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
        }

        if($deal->hubspot_id){
            $campoHubspot = [
                'fase_1_preestab' => $request->fase_1_preestab . " " . $fechaActual
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        Compras::create([
            'id_user' => $user["id"],
            'descripcion' => "Pago Fase 1: ". $deal->dealname,
            'pagado' => 0,
            'monto' => $request->fase_1_preestab,
            'deal_id' => $deal->id,
            'phasenum' => 1
        ]);

        $user->pay = $user->pay>12 ? $user->pay : $user->pay+10;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Fase 1: Pago solicitado al cliente correctamente.'
        ], 200);
    }


    public function guardarfase2(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $user = User::find($deal->user_id);


        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->fase_2_preestab = $request->fase_2_preestab . " " . $fechaActual;
        $deal->fase_2_enviado = $fechaActual;
        $deal->fase_2_pagado = null;
        $deal->fecha_fase_2_pagado = null;
        $deal->monto_fase_2_pagado = null;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === 'c66a9c15-c965-0812-ad5b-7e48f183c6f9') {
                    $field['value'] = $request->fase_2_preestab . " " . $fechaActual;
                }
                $field['id'] = $field['definition']['id'];

                unset($field['definition']);

                return $field;
            }, $existingFields);

            $campoTeamleader = ['custom_fields' => $updatedFields];

            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
        }

        if($deal->hubspot_id){
            $campoHubspot = [
                'fase_2_preestab' => $request->fase_2_preestab . " " . $fechaActual
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        Compras::create([
            'id_user' => $user["id"],
            'descripcion' => "Pago Fase 2: ". $deal->dealname,
            'pagado' => 0,
            'monto' => $request->fase_2_preestab,
            'deal_id' => $deal->id,
            'phasenum' => 2
        ]);

        $user->pay = $user->pay>12 ? $user->pay : $user->pay+10;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Fase 2: Pago solicitado al cliente correctamente.'
        ], 200);
    }


    public function guardarfase3(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $user = User::find($deal->user_id);


        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->fase_3_preestab = $request->fase_3_preestab . " " . $fechaActual;
        $deal->fase_3_enviado = $fechaActual;
        $deal->fase_3_pagado = null;
        $deal->fecha_fase_3_pagado = null;
        $deal->monto_fase_3_pagado = null;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === 'e41fdbbb-a25a-005b-af56-9f3ca623c700') {
                    $field['value'] = $request->fase_3_preestab . " " . $fechaActual;
                }
                $field['id'] = $field['definition']['id'];

                unset($field['definition']);

                return $field;
            }, $existingFields);

            $campoTeamleader = ['custom_fields' => $updatedFields];

            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
        }

        if($deal->hubspot_id){
            $campoHubspot = [
                'fase_3_preestab' => $request->fase_3_preestab . " " . $fechaActual
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        Compras::create([
            'id_user' => $user["id"],
            'descripcion' => "Pago Fase 3: ". $deal->dealname,
            'pagado' => 0,
            'monto' => $request->fase_3_preestab,
            'deal_id' => $deal->id,
            'phasenum' => 3
        ]);

        $user->pay = $user->pay>9 ? $user->pay : $user->pay+10;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Fase 3: Pago solicitado al cliente correctamente.'
        ], 200);
    }

    public function guardarcartanat(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $user = User::find($deal->user_id);


        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->carta_nat_preestab = $request->carta_nat_preestab . " " . $fechaActual;
        $deal->carta_nat_enviado = $fechaActual;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === 'a42ed217-b570-0973-9052-fab97214c229') {
                    $field['value'] = $request->carta_nat_preestab . " " . $fechaActual;
                }
                $field['id'] = $field['definition']['id'];

                unset($field['definition']);

                return $field;
            }, $existingFields);

            $campoTeamleader = ['custom_fields' => $updatedFields];

            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
        }

        if($deal->hubspot_id){
            $campoHubspot = [
                'fase_3_preestab' => $request->carta_nat_preestab . " " . $fechaActual
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        Compras::create([
            'id_user' => $user["id"],
            'descripcion' => "Pago Carta de Naturaleza: ". $deal->dealname,
            'pagado' => 0,
            'monto' => $request->carta_nat_preestab,
            'deal_id' => $deal->id,
            'phasenum' => 98
        ]);

        $user->pay = $user->pay>9 ? $user->pay : $user->pay+10;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'carta_nat_preestab: Pago solicitado al cliente correctamente.'
        ], 200);
    }

    public function guardarfcjecil(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $user = User::find($deal->user_id);


        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->cil___fcje_preestab = $request->cil___fcje_preestab . " " . $fechaActual;
        $deal->carta_cilfcje_enviado = $fechaActual;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === 'aa1ce4b9-a410-00f2-a953-5f8c2713dc35') {
                    $field['value'] = $request->cil___fcje_preestab . " " . $fechaActual;
                }
                $field['id'] = $field['definition']['id'];

                unset($field['definition']);

                return $field;
            }, $existingFields);

            $campoTeamleader = ['custom_fields' => $updatedFields];

            $this->teamleaderService->updateProject($deal->teamleader_id, $campoTeamleader);
        }

        if($deal->hubspot_id){
            $campoHubspot = [
                'cil___fcje_preestab' => $request->cil___fcje_preestab . " " . $fechaActual
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        Compras::create([
            'id_user' => $user["id"],
            'descripcion' => "Pago Certificado de Origen Sefardí: ". $deal->dealname,
            'pagado' => 0,
            'monto' => $request->cil___fcje_preestab,
            'deal_id' => $deal->id,
            'phasenum' => 99
        ]);

        $user->pay = $user->pay>9 ? $user->pay : $user->pay+10;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'carta_nat_preestab: Pago solicitado al cliente correctamente.'
        ], 200);
    }


    public function exonerarfase1(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->fase_1_preestab = "EXONERADO " . $fechaActual;
        $deal->fase_1_enviado = $fechaActual;
        $deal->fase_1_pagado = "EXONERADO " . $fechaActual;
        $deal->fecha_fase_1_pagado = $fechaActual;
        $deal->monto_fase_1_pagado = 0;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === '73173887-a0e8-0f4f-bb55-b61f33d3c6e9') {
                    $field['value'] = "EXONERADO " . $fechaActual;
                }

                if ($field['definition']['id'] === "a1b50c58-8175-0d13-9856-f661e783dc08") {
                    $field['value'] = "EXONERADO " . $fechaActual;
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
                'fase_1_preestab' => "EXONERADO " . $fechaActual,
                'fase_1_pagado__teamleader_' => "EXONERADO " . $fechaActual,
                'monto_fase_1_pagado' => 0,
                'fecha_fase_1_pagado' => $timestamp,
                'fase_1_pagado' => "EXONERADO " . $fechaActual
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fase 1: Exonerado'
        ], 200);
    }


    public function exonerarfase2(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->fase_2_preestab = "EXONERADO " . $fechaActual;
        $deal->fase_2_enviado = $fechaActual;
        $deal->fase_2_pagado = "EXONERADO " . $fechaActual;
        $deal->fecha_fase_2_pagado = $fechaActual;
        $deal->monto_fase_2_pagado = 0;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === 'a5b94ccc-3ea8-06fc-b259-0a487073dc0d') {
                    $field['value'] = "EXONERADO " . $fechaActual;
                }

                if ($field['definition']['id'] === "c66a9c15-c965-0812-ad5b-7e48f183c6f9") {
                    $field['value'] = "EXONERADO " . $fechaActual;
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
                'fase_2_preestab' => "EXONERADO " . $fechaActual,
                'fase_2_pagado__teamleader_' => "EXONERADO " . $fechaActual,
                'monto_fase_2_pagado' => 0,
                'fecha_fase_2_pagado' => $timestamp,
                'fase_2_pagado' => "EXONERADO " . $fechaActual
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fase 2: Exonerado'
        ], 200);
    }


    public function exonerarfase3(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->fase_3_preestab = "EXONERADO " . $fechaActual;
        $deal->fase_3_enviado = $fechaActual;
        $deal->fase_3_pagado = "EXONERADO " . $fechaActual;
        $deal->fecha_fase_3_pagado = $fechaActual;
        $deal->monto_fase_3_pagado = 0;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === '9a1df9b7-c92f-09e5-b156-96af3f83dc0e') {
                    $field['value'] = "EXONERADO " . $fechaActual;
                }

                if ($field['definition']['id'] === "e41fdbbb-a25a-005b-af56-9f3ca623c700") {
                    $field['value'] = "EXONERADO " . $fechaActual;
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
                'fase_3_preestab' => "EXONERADO " . $fechaActual,
                'fase_3_pagado__teamleader_' => "EXONERADO " . $fechaActual,
                'monto_fase_3_pagado' => 0,
                'fecha_fase_3_pagado' => $timestamp,
                'fase_3_pagado' => "EXONERADO " . $fechaActual
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fase 3: Exonerado'
        ], 200);
    }


    public function exonerarcartanat(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->carta_nat_pagado = "EXONERADO " . $fechaActual;
        $deal->carta_nat_enviado = $fechaActual;
        $deal->carta_nat_preestab = "EXONERADO " . $fechaActual;
        $deal->carta_nat_fechapagado = $fechaActual;
        $deal->carta_nat_montopagado = 0;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === '4339375f-ed77-02d9-a157-7da9f9e4bfac') {
                    $field['value'] = "EXONERADO " . $fechaActual;
                }

                if ($field['definition']['id'] === "a42ed217-b570-0973-9052-fab97214c229") {
                    $field['value'] = "EXONERADO " . $fechaActual;
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
                'carta_nat_pagado' => "EXONERADO " . $fechaActual,
                'carta_nat_preestab' => "EXONERADO " . $fechaActual,
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        return response()->json([
            'success' => true,
            'message' => 'Carta Nat: Exonerado'
        ], 200);
    }


    public function exonerarcilfcje(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->cil___fcje_pagado = "EXONERADO " . $fechaActual;
        $deal->carta_cilfcje_enviado = $fechaActual;
        $deal->cil___fcje_preestab = "EXONERADO " . $fechaActual;
        $deal->cilfcje_fechapagado = $fechaActual;
        $deal->cilfcje_montopagado = 0;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === 'f23fbe3b-5d13-0a41-a857-e9ab1c63dc42') {
                    $field['value'] = "EXONERADO " . $fechaActual;
                }

                if ($field['definition']['id'] === "aa1ce4b9-a410-00f2-a953-5f8c2713dc35") {
                    $field['value'] = "EXONERADO " . $fechaActual;
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
                'cil___fcje_pagado' => "EXONERADO " . $fechaActual,
                'cil___fcje_preestab' => "EXONERADO " . $fechaActual,
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        return response()->json([
            'success' => true,
            'message' => 'Carta Nat: Exonerado'
        ], 200);
    }

    public function incluidofase1cilfcje(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $fechaActual = Carbon::now()->format('Y/m/d');

        $deal->cil___fcje_pagado = "INCLUIDO EN FASE 1 " . $fechaActual;
        $deal->carta_cilfcje_enviado = $fechaActual;
        $deal->cil___fcje_preestab = "INCLUIDO EN FASE 1 " . $fechaActual;
        $deal->cilfcje_fechapagado = $fechaActual;
        $deal->cilfcje_montopagado = 0;
        $deal->save();

        if ($deal->teamleader_id) {
            $currentProject = $this->teamleaderService->getProjectDetails($deal->teamleader_id);

            // Conservar los campos existentes
            $existingFields = $currentProject['custom_fields'];

            // Actualizar solo el campo necesario sin borrar los demás
            $updatedFields = array_map(function($field) use ($request, $fechaActual) {
                if ($field['definition']['id'] === 'f23fbe3b-5d13-0a41-a857-e9ab1c63dc42') {
                    $field['value'] = "INCLUIDO EN FASE 1 " . $fechaActual;
                }

                if ($field['definition']['id'] === "aa1ce4b9-a410-00f2-a953-5f8c2713dc35") {
                    $field['value'] = "INCLUIDO EN FASE 1 " . $fechaActual;
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
                'cil___fcje_pagado' => "INCLUIDO EN FASE 1 " . $fechaActual,
                'cil___fcje_preestab' => "INCLUIDO EN FASE 1 " . $fechaActual,
            ];

            $this->hubspotService->updateDeals($deal->hubspot_id, $campoHubspot);
        }

        return response()->json([
            'success' => true,
            'message' => 'Carta Nat: Exonerado'
        ], 200);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $deal = Negocio::findOrFail($id);
        $deal->fill($request->all());
        $deal->save();

        $camposRelacionados = [
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
        ]; // o defínelo local si prefieres

        // Actualizar en Teamleader
        if ($deal->teamleader_id) {
            $this->teamleaderService->updateProject($deal->teamleader_id, [
                'custom_fields' => collect($request->all())->map(function ($value, $field) use ($camposRelacionados) {
                    return isset($camposRelacionados[$field]) ? [
                        'id' => $camposRelacionados[$field],
                        'value' => $value
                    ] : null;
                })->filter()->values()->all()
            ]);
        }

        // Actualizar en HubSpot
        if ($deal->hubspot_id) {
            $hsPayload = [];
            foreach ($request->all() as $field => $value) {
                if (array_key_exists($field, $camposRelacionados)) {
                    $hsPayload[$field] = $value;
                }
            }

            $this->hubspotService->updateDeals($deal->hubspot_id, $hsPayload);
        }

        return response()->json(['message' => 'Actualizado y sincronizado.']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
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

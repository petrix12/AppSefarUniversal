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
        //
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

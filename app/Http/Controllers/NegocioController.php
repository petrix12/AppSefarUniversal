<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Factura;
use App\Models\Compras;
use App\Services\TeamleaderService;
use App\Services\HubspotService;
use App\Services\UnificationAiSuggestionService;
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

    private function syncDealIndividual($dealDb, $camposRelacionados)
    {
        $hubspotId = $dealDb->hubspot_id;
        $teamleaderId = $dealDb->teamleader_id;

        if (blank($teamleaderId)) {
            return [];
        }

        $teamleaderDeal = $this->teamleaderService->getProjectDetails((string) $teamleaderId);
        $tlFields = collect($teamleaderDeal['custom_fields'] ?? []);
        $updatesToHubspot = [];
        $comparisons = [];
        $hubspotDeal = blank($hubspotId) ? null : $this->hubspotService->getDealById((string) $hubspotId);

        if (filled($hubspotId) && (! $hubspotDeal || ! isset($hubspotDeal['properties']))) {
            return [];
        }

        // A Teamleader-only record remains Teamleader-sourced and is never
        // created in HubSpot. On linked records, HubSpot is the display source.
        $hsProps = $hubspotDeal['properties'] ?? [];

        foreach ($camposRelacionados as $hsField => $tlFieldId) {
            $hsValue = $hsProps[$hsField] ?? null;
            $tlValue = $tlFields->first(fn ($field) => ($field['definition']['id'] ?? $field['id'] ?? null) === $tlFieldId)['value'] ?? null;

            if (blank($hubspotId)) {
                if (filled($tlValue) && Schema::hasColumn((new Negocio)->getTable(), $hsField)) {
                    $dealDb->{$hsField} = $tlValue;
                }
                continue;
            }

            if (blank($hsValue) && filled($tlValue)) {
                $updatesToHubspot[$hsField] = $tlValue;
                if (Schema::hasColumn((new Negocio)->getTable(), $hsField)) {
                    $dealDb->{$hsField} = $tlValue;
                }
            } elseif (filled($hsValue) && filled($tlValue) && (string) $hsValue !== (string) $tlValue) {
                $comparisons[] = [
                    'field' => $hsField,
                    'hubspot' => $hsValue,
                    'teamleader' => $tlValue,
                ];
            }
        }

        if (filled($hubspotId) && ! empty($updatesToHubspot)) {
            $this->hubspotService->updateDeals($hubspotId, $updatesToHubspot);
        }

        if (Schema::hasColumn((new Negocio)->getTable(), 'teamleader_comparisons')) {
            $dealDb->teamleader_comparisons = $comparisons;
        }
        $dealDb->save();

        return $comparisons;

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
            'pais_de_residencia' => 'bd374fc3-39a5-0070-9455-67d94cc6b7f7',
            'servicio_solicitado' => 'fcd48891-20f6-049a-a05f-f78a6f951b4d'
        ];

        $fieldComparisons = [];
        try {
            $fieldComparisons = $this->syncDealIndividual($deal_db, $camposDeTeamleader);
            $deal_db->refresh();
            $savedComparisons = $deal_db->teamleader_comparisons;
            if (is_string($savedComparisons)) {
                $savedComparisons = json_decode($savedComparisons, true) ?: [];
            }
            $fieldComparisons = collect(array_merge(is_array($savedComparisons) ? $savedComparisons : [], $fieldComparisons))
                ->keyBy('field')->values()->all();
        } catch (\Throwable $exception) {
            \Log::warning('No se pudo completar el enriquecimiento HubSpot desde Teamleader', [
                'negocio_id' => $deal_db->id,
                'hubspot_id' => $deal_db->hubspot_id,
                'teamleader_id' => $deal_db->teamleader_id,
                'error' => $exception->getMessage(),
            ]);
        }

        // Algunos clientes históricos no están vinculados a Teamleader. En ese
        // caso la ficha debe seguir siendo accesible, simplemente sin proyectos
        // disponibles para seleccionar.
        $TLdeals = filled($user->tl_id)
            ? $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id)
            : [];

        return view('crud.negocios.edit', compact('deal_db', 'user', 'TLdeals', 'fieldComparisons'));
    }

    public function sincronizarhsytl(Request $request){
        $deal = Negocio::find($request->id);

        if (!$deal) {
            return response()->json([
                'success' => false,
                'message' => 'Negocio no encontrado.'
            ], 404); // Código HTTP 404: No encontrado
        }

        $request->validate([
            'teamleader_id' => ['nullable', 'uuid'],
        ]);

        $teamleaderId = $request->input('teamleader_id');
        if (filled($teamleaderId)) {
            $alreadyLinked = Negocio::where('teamleader_id', $teamleaderId)
                ->where('id', '!=', $deal->id)
                ->exists();

            if ($alreadyLinked) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ese proyecto de Teamleader ya está asociado a otro negocio.',
                ], 422);
            }

            $user = User::find($deal->user_id);
            $userProjects = $user && filled($user->tl_id)
                ? $this->teamleaderService->getProjectsWithDetailsByCustomerId($user->tl_id)
                : [];

            if (! collect($userProjects)->contains(fn ($project) => ($project['id'] ?? null) === $teamleaderId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'El proyecto seleccionado no pertenece al cliente de este negocio.',
                ], 422);
            }
        }

        // Persist the IDs first. The next edit read enriches HubSpot only.
        $deal->teamleader_id = $teamleaderId;

        if ($deal->save()) {
            return response()->json([
                'success' => true,
                'message' => 'Asociación guardada. Se completarán en HubSpot los campos vacíos y se mostrarán las diferencias para revisión.'
            ], 200); // Código HTTP 200: OK
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar los cambios.'
            ], 500); // Código HTTP 500: Error interno del servidor
        }
    }

    public function sugerirAsociacionesTeamleader($id, UnificationAiSuggestionService $ai)
    {
        $deal = Negocio::findOrFail($id);
        if (blank($deal->hubspot_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Este negocio no tiene un trato de HubSpot para comparar.',
            ], 422);
        }

        if (! $ai->available()) {
            return response()->json([
                'success' => false,
                'message' => 'OpenRouter no está configurado. Define OPENROUTER_API_KEY para solicitar sugerencias.',
            ], 503);
        }

        $user = User::find($deal->user_id);
        if (! $user || blank($user->tl_id)) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene un contacto asociado en Teamleader.',
            ], 422);
        }

        try {
            $hubspotDeal = $this->hubspotService->getDealById((string) $deal->hubspot_id);
            if (! $hubspotDeal || ! isset($hubspotDeal['properties'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo leer el trato de HubSpot.',
                ], 502);
            }

            $hubspotProperties = $hubspotDeal['properties'];
            $sourceName = (string) ($hubspotProperties['dealname'] ?? '');
            $sourceNormalized = $this->normaliseDealTitle($sourceName);
            $projects = $this->teamleaderService->getProjectsWithDetailsByCustomerId((string) $user->tl_id);
            $linkedProjectIds = Negocio::whereNotNull('teamleader_id')
                ->where('id', '!=', $deal->id)
                ->pluck('teamleader_id')
                ->map(fn ($projectId) => (string) $projectId)
                ->all();

            $candidates = collect($projects)
                ->filter(fn ($project) => is_array($project) && filled($project['id'] ?? null))
                ->reject(fn ($project) => in_array((string) $project['id'], $linkedProjectIds, true))
                ->map(function (array $project) use ($sourceNormalized, $hubspotProperties): array {
                    $customFields = collect($project['custom_fields'] ?? []);
                    $fieldValue = fn (string $id) => $customFields->first(
                        fn ($field) => (string) ($field['definition']['id'] ?? $field['id'] ?? '') === $id
                    )['value'] ?? null;

                    $title = (string) ($project['title'] ?? '');
                    $normalizedTitle = $this->normaliseDealTitle($title);
                    similar_text($sourceNormalized, $normalizedTitle, $nameSimilarity);
                    $phaseFields = [];
                    $fieldScore = 0;
                    $normaliseField = fn ($value) => trim(preg_replace('/[^a-z0-9]+/', ' ', \Illuminate\Support\Str::ascii(mb_strtolower((string) $value))) ?: '');
                    $hubspotCode = $normaliseField($hubspotProperties['codigo_de_proceso'] ?? '');
                    $teamleaderCode = $normaliseField($fieldValue('a42f63f5-d527-0544-ab50-9c03857707f2'));
                    if ($hubspotCode !== '' && $hubspotCode === $teamleaderCode) {
                        $fieldScore += 60;
                    }
                    $hubspotService = $normaliseField($hubspotProperties['servicio_solicitado2'] ?? $hubspotProperties['servicio_solicitado'] ?? '');
                    $teamleaderService = $normaliseField($fieldValue('fcd48891-20f6-049a-a05f-f78a6f951b4d'));
                    if ($hubspotService !== '' && $hubspotService === $teamleaderService) {
                        $fieldScore += 25;
                    }
                    foreach (\App\Services\TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS as $phase => $fields) {
                        $phaseFields[$phase] = [
                            'preestab' => $fieldValue($fields['preestab']['id']),
                            'paid' => $fieldValue($fields['paid']['id']),
                        ];
                        $phaseProperties = match ((int) $phase) {
                            1 => ['preestab' => 'fase_1_preestab', 'paid' => 'fase_1_pagado__teamleader_'],
                            2 => ['preestab' => 'fase_2_preestab', 'paid' => 'fase_2_pagado__teamleader_'],
                            3 => ['preestab' => 'fase_3_preestab', 'paid' => 'fase_3_pagado__teamleader_'],
                            98 => ['preestab' => 'carta_nat_preestab', 'paid' => 'carta_nat_pagado'],
                            99 => ['preestab' => 'cil___fcje_preestab', 'paid' => 'cil___fcje_pagado'],
                            default => [],
                        };
                        foreach ($phaseProperties as $kind => $property) {
                            $hsValue = $normaliseField($hubspotProperties[$property] ?? '');
                            $tlValue = $normaliseField($phaseFields[$phase][$kind] ?? '');
                            if ($hsValue !== '' && $hsValue === $tlValue) {
                                $fieldScore += 10;
                                break;
                            }
                        }
                    }

                    return [
                        'id' => (string) $project['id'],
                        'title' => $title,
                        'status' => (string) ($project['status'] ?? ''),
                        'estimated_value' => $project['estimated_value'] ?? [],
                        'service' => (string) ($fieldValue('fcd48891-20f6-049a-a05f-f78a6f951b4d') ?? ''),
                        'process_code' => (string) ($fieldValue('a42f63f5-d527-0544-ab50-9c03857707f2') ?? ''),
                        'phase_fields' => $phaseFields,
                        '_field_score' => $fieldScore,
                        '_name_similarity' => $nameSimilarity ?? 0,
                    ];
                })
                ->sortBy([
                    ['_field_score', 'desc'],
                    ['_name_similarity', 'desc'],
                ])
                ->take(40)
                ->map(fn (array $project) => collect($project)->except(['_field_score', '_name_similarity'])->all())
                ->values()
                ->all();

            $suggestion = $ai->suggestDealAssociations($hubspotDeal['properties'], $candidates);

            return response()->json([
                'success' => true,
                'suggestions' => $suggestion['suggestions'],
                'model' => $suggestion['model'],
            ]);
        } catch (\Throwable $exception) {
            \Log::warning('Falló la sugerencia OpenRouter para asociar negocios', [
                'negocio_id' => $deal->id,
                'hubspot_id' => $deal->hubspot_id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No se pudieron generar sugerencias: '.$exception->getMessage(),
            ], 502);
        }
    }

    private function normaliseDealTitle(string $title): string
    {
        $title = \Illuminate\Support\Str::ascii(mb_strtolower($title));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $title) ?: '');
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
            'pais_de_residencia' => 'bd374fc3-39a5-0070-9455-67d94cc6b7f7',
            'servicio_solicitado' => 'fcd48891-20f6-049a-a05f-f78a6f951b4d'
        ]; // o defínelo local si prefieres

        // Actualizar en Teamleader
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

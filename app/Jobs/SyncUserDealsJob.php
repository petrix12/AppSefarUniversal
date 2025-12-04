<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Negocio;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class SyncUserDealsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $tries = 3;
    public $timeout = 300; // 5 minutos
    public $backoff = [30, 60, 120];

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle(HubspotService $hubspotService, TeamleaderService $teamleaderService)
    {
        try {
            Log::info("Iniciando sincronización de deals", [
                'user_id' => $this->user->id,
                'attempt' => $this->attempts()
            ]);

            // Obtener deals de ambas plataformas
            $deals = $hubspotService->getDealsByContactId($this->user->hs_id);
            $tlDeals = $teamleaderService->getProjectsWithDetailsByCustomerId($this->user->tl_id);

            // Sincronizar con Teamleader
            $this->syncDealsToTeamleader($deals, $tlDeals, $teamleaderService);

            // Sincronizar con base de datos
            $this->syncDealsToDatabase($deals);

            Log::info("Sincronización de deals completada", [
                'user_id' => $this->user->id,
                'deals_synced' => count($deals)
            ]);

        } catch (\Exception $e) {
            Log::error("Error en SyncUserDealsJob", [
                'user_id' => $this->user->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage()
            ]);

            throw $e;
        }
    }

    private function syncDealsToTeamleader($deals, $tlDeals, $teamleaderService): void
    {
        $camposDeTeamleader = $this->getCamposTeamleader();
        $teamleaderDealNames = array_column($tlDeals, 'title');

        foreach ($deals as $deal) {
            $dealName = $deal['properties']['dealname'] ?? '';

            if (!in_array($dealName, $teamleaderDealNames)) {
                try {
                    $teamleaderService->createProjectFromHubspotDeal(
                        $deal,
                        $this->user->tl_id,
                        $camposDeTeamleader
                    );

                    Log::info("Proyecto creado en Teamleader", [
                        'deal_name' => $dealName,
                        'user_id' => $this->user->id
                    ]);
                } catch (\Exception $e) {
                    Log::error("Error creando proyecto en Teamleader", [
                        'deal_name' => $dealName,
                        'user_id' => $this->user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    private function syncDealsToDatabase($deals): void
    {
        $columns = Schema::getColumnListing((new Negocio)->getTable());
        $excludedColumns = ['id', 'created_at', 'updated_at', 'hubspot_id', 'teamleader_id', 'user_id'];
        $fillableColumns = array_diff($columns, $excludedColumns);

        $existingDeals = Negocio::whereIn('hubspot_id', array_column($deals, 'id'))
            ->get()
            ->keyBy('hubspot_id');

        $newDeals = [];
        $dealsToUpdate = [];

        foreach ($deals as $deal) {
            $data = $this->processDealData($deal, $fillableColumns);

            if ($existingDeals->has($deal['id'])) {
                $this->checkAndQueueUpdate($existingDeals->get($deal['id']), $data, $dealsToUpdate);
            } else {
                $newDeals[] = array_merge([
                    'hubspot_id' => $deal['id'],
                    'user_id' => $this->user->id,
                ], $data);
            }
        }

        // Inserción masiva
        if (!empty($newDeals)) {
            Negocio::insert($newDeals);
            Log::info("Nuevos deals insertados", [
                'user_id' => $this->user->id,
                'count' => count($newDeals)
            ]);
        }

        // Actualización masiva
        if (!empty($dealsToUpdate)) {
            foreach ($dealsToUpdate as $dealUpdate) {
                Negocio::where('id', $dealUpdate['id'])->update($dealUpdate['data']);
            }
            Log::info("Deals actualizados", [
                'user_id' => $this->user->id,
                'count' => count($dealsToUpdate)
            ]);
        }
    }

    private function processDealData($deal, $fillableColumns): array
    {
        $processProperty = function($value) {
            if (is_null($value)) return null;
            $arrayData = strpos($value, ';') !== false ? explode(';', $value) : [$value];
            return json_encode($arrayData, JSON_UNESCAPED_UNICODE);
        };

        // Procesar propiedades especiales
        $propsToProcess = ['argumento_de_ventas__new_', 'n2__antecedentes_penales', 'documentos'];
        foreach ($propsToProcess as $prop) {
            if (isset($deal['properties'][$prop])) {
                $deal['properties'][$prop] = $processProperty($deal['properties'][$prop]);
            }
        }

        $data = ['dealname' => $deal['properties']['dealname'] ?? null];

        foreach ($fillableColumns as $column) {
            $data[$column] = $deal['properties'][$column] ?? null;
        }

        return $data;
    }

    private function checkAndQueueUpdate($existingDeal, $data, &$dealsToUpdate): void
    {
        $hasChanges = false;

        foreach ($data as $key => $value) {
            if ($existingDeal->{$key} != $value) {
                $hasChanges = true;
                break;
            }
        }

        if ($hasChanges) {
            $dealsToUpdate[] = [
                'id' => $existingDeal->id,
                'data' => $data
            ];
        }
    }

    private function getCamposTeamleader(): array
    {
        return [
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
            'servicio_solicitado2' => 'fcd48891-20f6-049a-a05f-f78a6f951b4d'
        ];
    }

    public function failed(\Throwable $exception)
    {
        Log::critical("SyncUserDealsJob falló después de todos los intentos", [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}

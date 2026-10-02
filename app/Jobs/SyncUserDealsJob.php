<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Negocio;
use App\Services\HubspotService;
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

    public function handle(
        HubspotService $hubspotService
    )
    {
        try {
            $this->user = $this->user->fresh();

            Log::info("Iniciando sincronización de deals", [
                'user_id' => $this->user->id,
                'attempt' => $this->attempts()
            ]);

            // HubSpot remains the source for COS deals. Teamleader is linked
            // from the business record UI and is never created or updated here.
            $deals = $hubspotService->getDealsByContactId($this->user->hs_id);

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

    public function failed(\Throwable $exception)
    {
        Log::critical("SyncUserDealsJob falló después de todos los intentos", [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}

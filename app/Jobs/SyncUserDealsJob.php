<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\HubspotDealLocalSyncService;
use App\Services\HubspotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
            app(HubspotDealLocalSyncService::class)->sync($this->user, $deals);

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

    public function failed(\Throwable $exception)
    {
        Log::critical("SyncUserDealsJob falló después de todos los intentos", [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage()
        ]);
    }
}

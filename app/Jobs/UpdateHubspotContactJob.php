<?php

namespace App\Jobs;

use App\Services\HubspotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateHubspotContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * ID del contacto en HubSpot
     */
    public $hsId;

    /**
     * Propiedades a actualizar
     */
    public $updates;

    /**
     * Número de intentos permitidos
     */
    public $tries = 3;

    /**
     * Timeout del job en segundos
     */
    public $timeout = 60;

    /**
     * Tiempo de espera antes de reintentar (en segundos)
     */
    public $backoff = [10, 30, 60];

    /**
     * Crear una nueva instancia del job
     *
     * @param string $hsId
     * @param array $updates
     */
    public function __construct(string $hsId, array $updates)
    {
        $this->hsId = $hsId;
        $this->updates = $updates;
    }

    /**
     * Ejecutar el job
     */
    public function handle(HubspotService $hubspotService)
    {
        try {
            Log::info("Iniciando actualización de HubSpot contact", [
                'hs_id' => $this->hsId,
                'fields_to_update' => array_keys($this->updates),
                'attempt' => $this->attempts()
            ]);

            // Actualizar el contacto en HubSpot
            $hubspotService->updateContact($this->hsId, $this->updates);

            Log::info("HubSpot contact actualizado exitosamente", [
                'hs_id' => $this->hsId,
                'updated_fields' => array_keys($this->updates)
            ]);

        } catch (\Exception $e) {
            Log::error("Error actualizando HubSpot contact", [
                'hs_id' => $this->hsId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Si es el último intento, notificar
            if ($this->attempts() >= $this->tries) {
                $this->notifyFailure($e);
            }

            // Re-lanzar la excepción para que se reintente
            throw $e;
        }
    }

    /**
     * Manejar el fallo del job después de todos los intentos
     */
    public function failed(\Throwable $exception)
    {
        Log::critical("Job de actualización HubSpot falló después de todos los intentos", [
            'hs_id' => $this->hsId,
            'updates' => $this->updates,
            'error' => $exception->getMessage()
        ]);

        // Aquí puedes enviar notificaciones por email, Slack, etc.
        $this->notifyFailure($exception);
    }

    /**
     * Notificar fallo (personalizable)
     */
    private function notifyFailure(\Throwable $exception)
    {
        // Opción 1: Enviar email
        try {
            \Mail::raw(
                "Error al actualizar contacto HubSpot\n\n" .
                "HS ID: {$this->hsId}\n" .
                "Error: {$exception->getMessage()}\n" .
                "Campos a actualizar: " . json_encode(array_keys($this->updates)),
                function ($message) {
                    $message->to('sistemasccs@sefarvzla.com')
                            ->subject('Error en sincronización HubSpot');
                }
            );
        } catch (\Exception $e) {
            Log::error("Error enviando email de notificación: " . $e->getMessage());
        }

        // Opción 2: Podrías guardar en una tabla de errores
        // FailedHubspotSync::create([...]);
    }

    /**
     * Determinar el tiempo de espera antes del siguiente intento
     */
    public function backoff()
    {
        // Tiempo de espera incremental: 10s, 30s, 60s
        return $this->backoff[$this->attempts() - 1] ?? 60;
    }
}

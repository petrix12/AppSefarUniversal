<?php

namespace App\Jobs;

use App\Services\TaskWorkflowOwnerDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class RunDailyTaskWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ALERT_EMAIL = 'sistemasccs@gmail.com';

    public int $tries = 1;
    public int $timeout = 300;

    public function __construct(
        private array $options,
        private ?int $adminUserId = null
    ) {
    }

    public function handle(TaskWorkflowOwnerDispatcher $dispatcher): void
    {
        $result = $dispatcher->dispatch($this->options, $this->adminUserId);

        Log::channel('tasks')->info('Despachador legacy de workflow diario ejecuto jobs por owner', [
            'admin_user_id' => $this->adminUserId,
            'options' => $this->options,
            'result' => $result,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $key = 'tasks-owner-dispatch-alert:' . md5(json_encode($this->options) . '|' . $e->getMessage());

        if (! Cache::add($key, true, now()->addDay())) {
            return;
        }

        Log::channel('tasks')->error('Fallo el despachador del workflow diario por HubSpot owner', [
            'admin_user_id' => $this->adminUserId,
            'options' => $this->options,
            'error' => $e->getMessage(),
        ]);

        $body = implode("\n", [
            'Fallo el despachador del workflow diario por HubSpot owner.',
            '',
            'Admin que lo lanzo: ' . ($this->adminUserId ?: 'N/A'),
            'Fecha: ' . now()->toDateTimeString(),
            '',
            'Error:',
            get_class($e) . ': ' . $e->getMessage(),
            '',
            'Opciones:',
            json_encode($this->options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);

        try {
            Mail::raw($body, function ($message) {
                $message->to(self::ALERT_EMAIL)->subject('[App Sefar] Fallo despachador tareas por owner');
            });
        } catch (Throwable $mailError) {
            Log::channel('tasks')->error('No se pudo enviar alerta de fallo del despachador de tareas', [
                'recipient' => self::ALERT_EMAIL,
                'mail_error' => $mailError->getMessage(),
                'original_error' => $e->getMessage(),
            ]);
        }
    }

}

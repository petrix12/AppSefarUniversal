<?php

namespace App\Jobs;

use App\Models\User;
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

    public function handle(): void
    {
        $advisors = $this->eligibleAdvisors();

        if ($advisors->isEmpty()) {
            throw new \RuntimeException('No hay HubSpot owners activos asociados a usuarios habilitados para tareas.');
        }

        $basePerAdvisor = max(1, (int) ($this->options['--per'] ?? 10));
        $forceLimit = max(1, (int) ($this->options['--force-limit'] ?? 200));
        $perOwnerForceLimit = max($basePerAdvisor, (int) ceil($forceLimit / max(1, $advisors->count())));

        foreach ($advisors as $index => $advisor) {
            $ownerOptions = $this->options;
            $ownerOptions['--advisor-id'] = (int) $advisor->id;
            $ownerOptions['--force-limit'] = $perOwnerForceLimit;

            RunDailyTaskWorkflowForOwnerJob::dispatch(
                (int) $advisor->id,
                trim(($advisor->name ?? "Usuario {$advisor->id}") . ' <' . ($advisor->email ?? 'sin correo') . '>'),
                $ownerOptions,
                $this->adminUserId,
                $index === 0
            )
                ->onConnection('database')
                ->onQueue('default');
        }

        Log::channel('tasks')->info('Workflow diario de tareas dividido por HubSpot owner', [
            'admin_user_id' => $this->adminUserId,
            'options' => $this->options,
            'owners_enqueued' => $advisors->count(),
            'per_owner_force_limit' => $perOwnerForceLimit,
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

    private function eligibleAdvisors()
    {
        return User::query()
            ->join('hubspot_owner_user as hou', 'hou.user_id', '=', 'users.id')
            ->join('hubspot_owners as ho', 'ho.id', '=', 'hou.hubspot_owner_id')
            ->where('ho.active', true)
            ->whereNotNull('hou.hubspot_owner_id')
            ->whereRaw("TRIM(hou.hubspot_owner_id) <> ''")
            ->where('users.exclude_from_task_assignment', false)
            ->where(function ($query) {
                $query->whereNull('users.task_assignment_daily_limit')
                    ->orWhere('users.task_assignment_daily_limit', '>', 0);
            })
            ->orderBy('users.name')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'hou.hubspot_owner_id as hs_owner_id',
                'ho.name as hs_owner_name',
            ])
            ->get();
    }
}

<?php

namespace App\Services;

use App\Jobs\RunDailyTaskWorkflowForOwnerJob;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TaskWorkflowOwnerDispatcher
{
    public const QUEUE = 'tasks';

    public function dispatch(array $options, ?int $adminUserId = null): array
    {
        $advisors = $this->eligibleAdvisors();

        if ($advisors->isEmpty()) {
            throw new \RuntimeException('No hay HubSpot owners activos asociados a usuarios habilitados para tareas.');
        }

        $basePerAdvisor = max(1, (int) ($options['--per'] ?? 10));
        $forceLimit = max(1, (int) ($options['--force-limit'] ?? 200));
        $perOwnerForceLimit = max($basePerAdvisor, (int) ceil($forceLimit / max(1, $advisors->count())));

        foreach ($advisors as $index => $advisor) {
            $ownerOptions = $options;
            $ownerOptions['--advisor-id'] = (int) $advisor->id;
            $ownerOptions['--force-limit'] = $perOwnerForceLimit;

            RunDailyTaskWorkflowForOwnerJob::dispatch(
                (int) $advisor->id,
                trim(($advisor->name ?? "Usuario {$advisor->id}") . ' <' . ($advisor->email ?? 'sin correo') . '>'),
                $ownerOptions,
                $adminUserId,
                $index === 0
            )
                ->onConnection('database')
                ->onQueue(self::QUEUE);
        }

        Log::channel('tasks')->info('Workflow diario de tareas encolado por HubSpot owner', [
            'admin_user_id' => $adminUserId,
            'options' => $options,
            'owners_enqueued' => $advisors->count(),
            'per_owner_force_limit' => $perOwnerForceLimit,
            'queue' => self::QUEUE,
        ]);

        return [
            'owners_enqueued' => $advisors->count(),
            'per_owner_force_limit' => $perOwnerForceLimit,
            'queue' => self::QUEUE,
        ];
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

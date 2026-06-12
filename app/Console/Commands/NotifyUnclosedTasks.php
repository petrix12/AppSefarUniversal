<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Models\Setting;
use App\Notifications\UnclosedTasksNotification;
use App\Services\HubspotDealOwnerSyncService;
use App\Services\HubspotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class NotifyUnclosedTasks extends Command
{
    private const TASK_RESPONSE_DAYS = 1;

    private ?bool $taskPoolPolicyColumnsExist = null;
    private ?bool $userReassignmentColumnExists = null;
    private ?bool $reassignmentLockColumnExists = null;

    protected $signature = 'tasks:notify-unclosed
        {--date= : Fecha base opcional YYYY-MM-DD}
        {--source-user-id= : Procesa solo tareas vencidas asignadas a este asesor}
        {--dry-run : Solo muestra cambios, no actualiza nada}';

    protected $description = 'Cancela tareas comerciales abiertas vencidas por 1 dia y reasigna el cliente en BD y HubSpot.';

    public function handle(HubspotService $hubspotService, HubspotDealOwnerSyncService $dealOwnerSync): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : today();

        $dryRun = (bool) $this->option('dry-run');
        $sourceUserId = (int) ($this->option('source-user-id') ?: 0);
        $reassignmentDate = today()->toDateString();

        $limitDate = $date->copy()->subDays(self::TASK_RESPONSE_DAYS)->endOfDay();

        $this->info("🔔 Revisando tareas abiertas con vencimiento hasta: {$limitDate->toDateString()}");

        $tasks = Task::query()
            ->with([
                'assignee:id,name,email',
                'contact:id,name,email,hs_id,owner_id',
            ])
            ->where('status', Task::STATUS_PENDING)
            ->notAssignedToSystems()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $limitDate->toDateString())
            ->whereNotNull('contact_id')
            ->when($sourceUserId > 0, function ($query) use ($sourceUserId) {
                $query->where('user_id', $sourceUserId);
            })
            ->when($this->userReassignmentColumnExists(), function ($query) use ($reassignmentDate) {
                $query->whereNotExists(function ($subQuery) use ($reassignmentDate) {
                    $subQuery->select(DB::raw(1))
                        ->from('users as reassigned_contacts')
                        ->whereColumn('reassigned_contacts.id', 'tasks.contact_id')
                        ->whereDate('reassigned_contacts.last_task_reassigned_at', $reassignmentDate);
                });
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tasks as in_progress_tasks')
                    ->whereColumn('in_progress_tasks.contact_id', 'tasks.contact_id')
                    ->where('in_progress_tasks.status', Task::STATUS_IN_PROGRESS);
            })
            ->get();

        $tasks = $this->withListReassignmentPolicy($tasks);

        if ($tasks->isEmpty()) {
            $this->info('✅ No hay tareas vencidas.');
            return self::SUCCESS;
        }

        $this->info("Tareas vencidas encontradas: {$tasks->count()}");

        $processedTasks = collect();
        $reassignedClients = 0;
        $hubspotUpdated = 0;
        $hubspotDealsUpdated = 0;
        $hubspotNotFound = 0;
        $hubspotFailed = 0;
        $hubspotSkippedByList = 0;
        $retryTasksCreated = 0;

        DB::beginTransaction();

        try {
            foreach ($tasks as $task) {
                $contact = $task->contact;

                if (!$contact) {
                    $this->warn("La tarea {$task->id} no tiene cliente válido.");
                    continue;
                }

                $skipHubspotReassignment = (bool) ($task->skip_hubspot_reassignment ?? false);
                $advisor = $skipHubspotReassignment
                    ? $task->assignee
                    : $this->getNextAdvisorRoundRobin((int) $task->user_id);

                if (! $skipHubspotReassignment && !$advisor) {
                    $this->warn('No hay asesores disponibles con owner real de HubSpot mapeado.');
                    continue;
                }

                $this->line(
                    "Tarea {$task->id} | Cliente {$contact->id} {$contact->name} → Nuevo owner: {$advisor->name}"
                );

                if (!$dryRun) {
                    // 1. Cancelar tarea
                    $skipHubspotReassignment = (bool) ($task->skip_hubspot_reassignment ?? false);
                    $taskUpdate = [
                        'status' => Task::STATUS_CANCELED,
                    ];

                    if ($this->taskPoolPolicyColumnsExist()) {
                        $taskUpdate['task_pool_list_name'] = $task->task_pool_list_name ?: null;
                        $taskUpdate['skip_hubspot_reassignment'] = $skipHubspotReassignment;
                    } else {
                        $task->syncOriginalAttributes([
                            'task_pool_list_name',
                            'skip_hubspot_reassignment',
                        ]);
                    }

                    $task->update($taskUpdate);

                    if ($skipHubspotReassignment) {
                        $hubspotSkippedByList++;
                        $retryTask = $this->createRetryTask($task, $date);

                        if ($retryTask) {
                            $retryTasksCreated++;
                            $this->line("   Reintento creado: tarea {$retryTask->id} para {$date->toDateString()}");
                        } else {
                            $this->line("   Reintento omitido: ya existe una tarea abierta para este contacto y asesor.");
                        }

                        $processedTasks->push($task);
                        continue;
                    }

                    // 2. Actualizar propietario local del cliente
                    $contact->update($this->ownerUpdateAttributes((int) $advisor->id, (string) $advisor->hs_owner_id));

                    $reassignedClients++;

                    // 3. Actualizar propietario en HubSpot
                    try {
                        $hsContactId = $this->resolveHubspotContactId($hubspotService, $contact);

                        if ($hsContactId) {
                            $hubspotService->updateContact($hsContactId, [
                                'hubspot_owner_id' => (string) $advisor->hs_owner_id,
                            ]);

                            $updatedDeals = $dealOwnerSync->syncForContact(
                                $hubspotService,
                                $hsContactId,
                                (string) $advisor->hs_owner_id,
                                (int) $contact->id
                            );
                            $hubspotDealsUpdated += $updatedDeals;

                            $hubspotUpdated++;
                            $this->line("   HubSpot actualizado: contact_id={$contact->id}, hs_id={$hsContactId}, owner={$advisor->hs_owner_id}, deals={$updatedDeals}");
                        } else {
                            $hubspotNotFound++;
                            $this->warn("   HubSpot no encontrado: contact_id={$contact->id}, email={$contact->email}, hs_id={$contact->hs_id}");

                            Log::channel('tasks')->warning('Contacto no encontrado en HubSpot al reasignar owner', [
                                'client_id' => $contact->id,
                                'email' => $contact->email,
                                'hs_id' => $contact->hs_id,
                                'new_owner_user_id' => $advisor->id,
                                'new_hubspot_owner_id' => $advisor->hs_owner_id,
                            ]);
                        }
                    } catch (\Throwable $e) {
                        $hubspotFailed++;
                        $this->warn("   HubSpot falló: contact_id={$contact->id}, owner={$advisor->hs_owner_id}, error={$e->getMessage()}");

                        Log::channel('tasks')->error('Error actualizando owner en HubSpot', [
                            'client_id' => $contact->id,
                            'email' => $contact->email,
                            'hs_id' => $contact->hs_id,
                            'new_owner_user_id' => $advisor->id,
                            'new_hubspot_owner_id' => $advisor->hs_owner_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                $processedTasks->push($task);
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::channel('tasks')->error('Error cancelando tareas vencidas y reasignando clientes', [
                'error' => $e->getMessage(),
            ]);

            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if ($processedTasks->isEmpty()) {
            $this->info('No se procesó ninguna tarea.');
            return self::SUCCESS;
        }

        // Notificar admins
        $admins = User::role('Administrador')->get();

        if ($admins->isEmpty()) {
            $this->warn('No hay administradores para notificar.');
        } else {
            $grouped = $processedTasks->groupBy('user_id');

            foreach ($admins as $admin) {
                if (!$dryRun) {
                    $admin->notify(new UnclosedTasksNotification($grouped, $date));
                }
            }

            $this->info("📧 Notificación enviada a {$admins->count()} admin(s).");
        }

        $this->info($dryRun ? '🧪 Dry-run completado. No se actualizó nada.' : '✅ Proceso completado.');
        $this->info("Clientes reasignados en BD: {$reassignedClients}");
        $this->info("Contactos actualizados en HubSpot: {$hubspotUpdated}");
        $this->info("Negocios actualizados en HubSpot: {$hubspotDealsUpdated}");
        $this->info("HubSpot omitidos por configuracion de lista: {$hubspotSkippedByList}");
        $this->info("Tareas de reintento creadas sin reasignar: {$retryTasksCreated}");
        $this->info("Contactos no encontrados en HubSpot: {$hubspotNotFound}");
        $this->info("Actualizaciones fallidas en HubSpot: {$hubspotFailed}");

        return self::SUCCESS;
    }

    private function createRetryTask(Task $task, Carbon $date): ?Task
    {
        $hasOpenRetry = Task::query()
            ->where('user_id', $task->user_id)
            ->where('contact_id', $task->contact_id)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->notAssignedToSystems()
            ->exists();

        if ($hasOpenRetry) {
            return null;
        }

        return Task::create([
            'user_id' => $task->user_id,
            'contact_id' => $task->contact_id,
            'title' => $task->title,
            'description' => trim(($task->description ?: '') . "\nReintento generado porque la tarea #{$task->id} vencio sin gestion."),
            'due_date' => $date->toDateString(),
            'status' => Task::STATUS_PENDING,
            'created_by_user_id' => null,
            'task_pool_list_name' => $task->task_pool_list_name,
            'skip_hubspot_reassignment' => true,
        ]);
    }

    private function withListReassignmentPolicy($tasks)
    {
        $contactIds = $tasks
            ->pluck('contact_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($contactIds->isEmpty()) {
            return $tasks;
        }

        $metadata = DB::table('list_user as lu')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->whereIn('lu.user_id', $contactIds->all())
            ->where('lu.contacted', 0)
            ->where('l.include_in_task_pool', true)
            ->select('lu.user_id', 'l.name', 'l.disable_hubspot_reassignment')
            ->get()
            ->groupBy('user_id');

        return $tasks
            ->map(function ($task) use ($metadata) {
                $rows = $metadata->get($task->contact_id, collect());
                $listNames = $rows->pluck('name')->filter()->unique()->values();

                $task->task_pool_list_name = $listNames->implode(', ');
                $task->skip_hubspot_reassignment = $rows->contains(
                    fn ($row) => (bool) $row->disable_hubspot_reassignment
                );

                return $task;
            })
            ->sortBy(fn ($task) => (bool) ($task->skip_hubspot_reassignment ?? false) ? 1 : 0)
            ->values();
    }

    private function taskPoolPolicyColumnsExist(): bool
    {
        if ($this->taskPoolPolicyColumnsExist === null) {
            $this->taskPoolPolicyColumnsExist = Schema::hasColumn('tasks', 'task_pool_list_name')
                && Schema::hasColumn('tasks', 'skip_hubspot_reassignment');
        }

        return $this->taskPoolPolicyColumnsExist;
    }

    private function ownerUpdateAttributes(int $advisorId, ?string $hubspotOwnerId = null): array
    {
        $attributes = ['owner_id' => $advisorId];

        if ($this->userReassignmentColumnExists()) {
            $attributes['last_task_reassigned_at'] = now();
        }

        if ($this->reassignmentLockColumnExists()) {
            $attributes['task_reassignment_locked_at'] = now();
        }

        if (Schema::hasColumn('users', 'task_reassignment_locked_owner_id')) {
            $attributes['task_reassignment_locked_owner_id'] = $advisorId;
        }

        if (Schema::hasColumn('users', 'task_reassignment_locked_hubspot_owner_id')) {
            $attributes['task_reassignment_locked_hubspot_owner_id'] = $hubspotOwnerId;
        }

        return $attributes;
    }

    private function userReassignmentColumnExists(): bool
    {
        if ($this->userReassignmentColumnExists === null) {
            $this->userReassignmentColumnExists = Schema::hasColumn('users', 'last_task_reassigned_at');
        }

        return $this->userReassignmentColumnExists;
    }

    private function reassignmentLockColumnExists(): bool
    {
        if ($this->reassignmentLockColumnExists === null) {
            $this->reassignmentLockColumnExists = Schema::hasColumn('users', 'task_reassignment_locked_at');
        }

        return $this->reassignmentLockColumnExists;
    }

    private function resolveHubspotContactId(HubspotService $hubspotService, User $contact): ?string
    {
        if (!empty($contact->hs_id)) {
            return (string) $contact->hs_id;
        }

        if (empty($contact->email)) {
            return null;
        }

        $hsContact = $hubspotService->searchContactOwnerByEmail($contact->email);

        return $hsContact['id'] ?? null;
    }

    private function getNextAdvisorRoundRobin(?int $excludedUserId = null): ?User
    {
        $advisors = User::query()
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
            ->when($excludedUserId, function ($query) use ($excludedUserId) {
                $query->where('users.id', '!=', $excludedUserId);
            })
            ->orderBy('users.id')
            ->select('users.*', 'hou.hubspot_owner_id as hs_owner_id')
            ->get();

        if ($advisors->isEmpty()) {
            return null;
        }

        $lastAdvisorId = (int) Setting::get('tasks.reassignment_round_robin_last_user_id', 0);
        $nextAdvisor = $advisors->firstWhere('id', '>', $lastAdvisorId) ?? $advisors->first();

        Setting::set('tasks.reassignment_round_robin_last_user_id', (string) $nextAdvisor->id);

        return $nextAdvisor;
    }
}

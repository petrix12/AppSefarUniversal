<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\HubspotDealOwnerSyncService;
use App\Services\HubspotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RunDailyTaskWorkflow extends Command
{
    private ?bool $userReassignmentColumnExists = null;
    private ?bool $reassignmentLockColumnExists = null;

    protected $signature = 'tasks:daily-workflow
        {--date= : Fecha base opcional YYYY-MM-DD}
        {--per=10 : Tareas base por asesor}
        {--advisor-id= : Limita el flujo a un asesor/owner interno}
        {--dry-run : Solo muestra cambios, no actualiza nada}
        {--force : Alias de --force-reassign para compatibilidad con el cron anterior}
        {--force-reassign : Fuerza reasignacion de tareas pendientes, canceladas, no efectivas y contactos sin tareas}
        {--skip-notify : Omite la revision de tareas vencidas}
        {--skip-generate : Omite la generacion diaria de tareas}
        {--skip-force : Omite la reasignacion forzada aunque se envie --force-reassign}
        {--force-limit=200 : Maximo de contactos a revisar con --force-reassign}';

    protected $description = 'Ejecuta secuencialmente el cierre/reasignacion de tareas vencidas y la generacion de tareas diarias.';

    public function handle(HubspotService $hubspot, HubspotDealOwnerSyncService $dealOwnerSync): int
    {
        $date = $this->option('date');
        $taskDate = $date ? Carbon::parse($date)->toDateString() : today()->toDateString();
        $reassignmentDate = today()->toDateString();
        $per = (int) ($this->option('per') ?? 10);
        $advisorId = (int) ($this->option('advisor-id') ?: 0);
        $dryRun = (bool) $this->option('dry-run');
        $forceReassign = (bool) $this->option('force-reassign') || (bool) $this->option('force');
        $skipNotify = (bool) $this->option('skip-notify');
        $skipGenerate = (bool) $this->option('skip-generate');
        $skipForce = (bool) $this->option('skip-force');
        $forceLimit = max(1, (int) ($this->option('force-limit') ?? 200));

        $this->info('Iniciando flujo diario de tareas.');
        if ($advisorId > 0) {
            $this->info("Flujo limitado al asesor {$advisorId}.");
        }

        if ($forceReassign && ! $skipForce) {
            $this->line('');
            $this->info('Paso 0/3: forzar reasignacion previa.');
            $this->forceReassignContacts($hubspot, $dealOwnerSync, $dryRun, $forceLimit, $reassignmentDate, $taskDate, $per, $advisorId > 0 ? $advisorId : null);
        }

        $notifyOptions = [];
        if ($date) {
            $notifyOptions['--date'] = $date;
        }
        if ($advisorId > 0) {
            $notifyOptions['--source-user-id'] = $advisorId;
        }
        if ($dryRun) {
            $notifyOptions['--dry-run'] = true;
        }

        if (! $skipNotify) {
            $this->line('');
            $this->info($forceReassign
                ? 'Paso 1/3: revisar tareas vencidas y reasignar clientes.'
                : 'Paso 1/2: revisar tareas vencidas y reasignar clientes.');
            $notifyExitCode = $this->call('tasks:notify-unclosed', $notifyOptions);

            if ($notifyExitCode !== self::SUCCESS) {
                $this->error('El flujo se detuvo porque tasks:notify-unclosed fallo.');
                return $notifyExitCode;
            }
        }

        $generateOptions = [
            '--per' => $per,
        ];
        if ($date) {
            $generateOptions['--date'] = $date;
        }
        if ($advisorId > 0) {
            $generateOptions['--advisor-id'] = $advisorId;
        }
        if ($dryRun) {
            $generateOptions['--dry-run'] = true;
        }

        if (! $skipGenerate) {
            $this->line('');
            $this->info($forceReassign
                ? 'Paso 2/3: generar tareas diarias.'
                : 'Paso 2/2: generar tareas diarias.');
            $generateExitCode = $this->call('tasks:generate-daily', $generateOptions);

            if ($generateExitCode !== self::SUCCESS) {
                $this->error('El flujo termino con error en tasks:generate-daily.');
                return $generateExitCode;
            }
        }

        $this->line('');
        $this->info('Flujo diario de tareas completado.');

        return self::SUCCESS;
    }

    private function forceReassignContacts(
        HubspotService $hubspot,
        HubspotDealOwnerSyncService $dealOwnerSync,
        bool $dryRun,
        int $limit,
        string $workflowDate,
        string $taskDate,
        int $basePerAdvisor,
        ?int $targetAdvisorId = null
    ): void
    {
        $eligibleAdvisorIds = $this->eligibleAdvisorIdsForAutomaticTasks();
        $targetAdvisor = $targetAdvisorId ? $this->advisorById($targetAdvisorId) : null;

        if ($targetAdvisorId && ! $targetAdvisor) {
            throw new \RuntimeException("El asesor {$targetAdvisorId} no tiene owner activo de HubSpot o esta excluido de tareas.");
        }

        if ($targetAdvisor) {
            $availableSlots = $this->availableTaskSlotsForAdvisor($targetAdvisor, $taskDate, $basePerAdvisor);

            if ($availableSlots <= 0) {
                $this->info("El asesor {$targetAdvisor->name} ya tiene el cupo diario lleno para {$taskDate}; no se reasignan contactos en este job.");
                return;
            }

            $limit = min($limit, $availableSlots);
            $this->info("Cupo disponible para {$targetAdvisor->name} en {$taskDate}: {$availableSlots}. Limite de reasignacion aplicado: {$limit}.");
        }

        $contacts = $this->forceReassignCandidates($limit, $eligibleAdvisorIds, $workflowDate, $targetAdvisorId);

        if ($contacts->isEmpty()) {
            $this->info('No hay contactos para reasignacion forzada.');
            return;
        }

        $this->info("Contactos candidatos para reasignacion forzada: {$contacts->count()}");

        $updatedLocal = 0;
        $hubspotUpdated = 0;
        $hubspotDealsUpdated = 0;
        $hubspotNotFound = 0;
        $hubspotFailed = 0;
        $hubspotSkippedByList = 0;
        $openTasksCanceled = 0;

        foreach ($contacts as $contact) {
            $advisor = $targetAdvisor ?: $this->getNextAdvisorRoundRobin();

            if (! $advisor) {
                $this->warn("Sin asesor disponible para contact_id={$contact->id}");
                continue;
            }

            $reason = $contact->force_reason ?? 'sin motivo';
            $listInfo = ! empty($contact->task_pool_list_name) ? " | listas={$contact->task_pool_list_name}" : '';
            $tasksToCancel = $this->countPendingTasksFromIneligibleAssignees((int) $contact->id, $eligibleAdvisorIds);
            $cancelNote = $tasksToCancel > 0 ? " | tareas pendientes a cancelar={$tasksToCancel}" : '';
            $hubspotPolicyNote = (bool) ($contact->disable_hubspot_reassignment ?? false)
                ? ' | HubSpot omitido por lista'
                : '';
            $this->line("   Reasignar contact_id={$contact->id} {$contact->name} | {$reason}{$listInfo} -> {$advisor->name}{$cancelNote}{$hubspotPolicyNote}");

            if ($dryRun) {
                continue;
            }

            if ((bool) ($contact->disable_hubspot_reassignment ?? false)) {
                $hubspotSkippedByList++;
                $this->line("      Sin reasignacion: la lista no permite cambiar HubSpot; se generara tarea local de reintento.");
                continue;
            }

            User::whereKey($contact->id)->update($this->ownerUpdateAttributes((int) $advisor->id, (string) $advisor->hs_owner_id));
            $updatedLocal++;
            $openTasksCanceled += $this->cancelPendingTasksFromIneligibleAssignees((int) $contact->id, $eligibleAdvisorIds);

            try {
                $hsContactId = $this->resolveHubspotContactId($hubspot, $contact);

                if ($hsContactId) {
                    $hubspot->updateContact($hsContactId, [
                        'hubspot_owner_id' => (string) $advisor->hs_owner_id,
                    ]);

                    $updatedDeals = $dealOwnerSync->syncForContact(
                        $hubspot,
                        $hsContactId,
                        (string) $advisor->hs_owner_id,
                        (int) $contact->id
                    );
                    $hubspotDealsUpdated += $updatedDeals;

                    $hubspotUpdated++;
                    $this->line("      HubSpot actualizado: hs_id={$hsContactId}, owner={$advisor->hs_owner_id}, deals={$updatedDeals}");
                } else {
                    $hubspotNotFound++;
                    $this->warn("      HubSpot no encontrado: email={$contact->email}, hs_id={$contact->hs_id}");
                }
            } catch (\Throwable $e) {
                $hubspotFailed++;
                $this->warn("      HubSpot fallo: {$e->getMessage()}");

                Log::channel('tasks')->error('Error en reasignacion forzada de HubSpot', [
                    'client_id' => $contact->id,
                    'email' => $contact->email,
                    'hs_id' => $contact->hs_id,
                    'new_owner_user_id' => $advisor->id,
                    'new_hubspot_owner_id' => $advisor->hs_owner_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($dryRun ? 'Dry-run de reasignacion forzada completado.' : 'Reasignacion forzada completada.');
        $this->info("Reasignados en app: {$updatedLocal}");
        $this->info("Actualizados en HubSpot: {$hubspotUpdated}");
        $this->info("Negocios actualizados en HubSpot: {$hubspotDealsUpdated}");
        $this->info("HubSpot omitidos por configuracion de lista: {$hubspotSkippedByList}");
        $this->info("No encontrados en HubSpot: {$hubspotNotFound}");
        $this->info("Fallidos en HubSpot: {$hubspotFailed}");
        $this->info("Tareas pendientes canceladas de usuarios no elegibles: {$openTasksCanceled}");
    }

    private function forceReassignCandidates(int $limit, array $eligibleAdvisorIds, string $workflowDate, ?int $targetAdvisorId = null)
    {
        $systemsUserIds = Task::systemsUserIds();
        $skipReassignedToday = $this->userReassignmentColumnExists();
        $notReassignedToday = function ($query) use ($skipReassignedToday, $workflowDate) {
            if (! $skipReassignedToday) {
                return;
            }

            $query->where(function ($dateQuery) use ($workflowDate) {
                $dateQuery->whereNull('users.last_task_reassigned_at')
                    ->orWhereDate('users.last_task_reassigned_at', '!=', $workflowDate);
            });
        };
        $taskPoolExists = function ($query) {
            $query->select(DB::raw(1))
                ->from('list_user as lu')
                ->join('lists as l', 'l.id', '=', 'lu.list_id')
                ->whereColumn('lu.user_id', 'users.id')
                ->where('lu.contacted', 0)
                ->where('l.include_in_task_pool', true);
        };

        $completedEffective = function ($query) use ($systemsUserIds) {
            $query->select(DB::raw(1))
                ->from('tasks')
                ->whereColumn('tasks.contact_id', 'users.id')
                ->where('tasks.status', Task::STATUS_COMPLETED)
                ->where(function ($taskQuery) {
                    $taskQuery->where('tasks.call_effective', 1)
                        ->orWhere('tasks.customer_responded', 1)
                        ->orWhereNotNull('tasks.sale_status')
                        ->orWhereNotNull('tasks.reason_no_interest')
                        ->orWhere('tasks.interest_level', 0);
                })
                ->when(! empty($systemsUserIds), function ($taskQuery) use ($systemsUserIds) {
                    $taskQuery->whereNotIn('tasks.user_id', $systemsUserIds);
                });
        };

        $inProgress = function ($query) use ($systemsUserIds) {
            $query->select(DB::raw(1))
                ->from('tasks')
                ->whereColumn('tasks.contact_id', 'users.id')
                ->where('tasks.status', Task::STATUS_IN_PROGRESS)
                ->when(! empty($systemsUserIds), function ($taskQuery) use ($systemsUserIds) {
                    $taskQuery->whereNotIn('tasks.user_id', $systemsUserIds);
                });
        };

        $pendingInEligibleAdvisor = function ($query) use ($systemsUserIds, $eligibleAdvisorIds) {
            $query->select(DB::raw(1))
                ->from('tasks')
                ->whereColumn('tasks.contact_id', 'users.id')
                ->where('tasks.status', Task::STATUS_PENDING)
                ->when(! empty($systemsUserIds), function ($taskQuery) use ($systemsUserIds) {
                    $taskQuery->whereNotIn('tasks.user_id', $systemsUserIds);
                });

            if (! empty($eligibleAdvisorIds)) {
                $query->whereIn('tasks.user_id', $eligibleAdvisorIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        };

        $canceled = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id')
            ->selectRaw("'tarea cancelada' as force_reason")
            ->join('tasks', 'tasks.contact_id', '=', 'users.id')
            ->where('tasks.status', Task::STATUS_CANCELED)
            ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                $query->whereNotIn('tasks.user_id', $systemsUserIds);
            })
            ->whereExists($taskPoolExists)
            ->where($notReassignedToday)
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
            ->whereNotExists($pendingInEligibleAdvisor)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id');

        $ineffective = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id')
            ->selectRaw("'tarea completada no efectiva' as force_reason")
            ->join('tasks', 'tasks.contact_id', '=', 'users.id')
            ->where('tasks.status', Task::STATUS_COMPLETED)
            ->where('tasks.call_effective', 0)
            ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                $query->whereNotIn('tasks.user_id', $systemsUserIds);
            })
            ->whereExists($taskPoolExists)
            ->where($notReassignedToday)
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
            ->whereNotExists($pendingInEligibleAdvisor)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id');

        $withoutTasks = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id')
            ->selectRaw("'en lista sin tareas' as force_reason")
            ->join('list_user as lu', 'lu.user_id', '=', 'users.id')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->where('lu.contacted', 0)
            ->where('l.include_in_task_pool', true)
            ->where($notReassignedToday)
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
            ->whereNotExists(function ($query) use ($systemsUserIds) {
                $query->select(DB::raw(1))
                    ->from('tasks')
                    ->whereColumn('tasks.contact_id', 'users.id')
                    ->when(! empty($systemsUserIds), function ($taskQuery) use ($systemsUserIds) {
                        $taskQuery->whereNotIn('tasks.user_id', $systemsUserIds);
                    });
            })
            ->groupBy('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id');

        $withoutEligibleOwner = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id')
            ->selectRaw("'owner no elegible para tareas' as force_reason")
            ->join('list_user as lu', 'lu.user_id', '=', 'users.id')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->where('lu.contacted', 0)
            ->where('l.include_in_task_pool', true)
            ->where($notReassignedToday)
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
            ->whereNotExists($pendingInEligibleAdvisor)
            ->where(function ($query) use ($eligibleAdvisorIds) {
                $query->whereNull('users.owner_id');

                if (! empty($eligibleAdvisorIds)) {
                    $query->orWhereNotIn('users.owner_id', $eligibleAdvisorIds);
                }
            })
            ->groupBy('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id');

        $openTasksFromIneligible = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id')
            ->selectRaw("'tarea pendiente en usuario no elegible' as force_reason")
            ->join('tasks', 'tasks.contact_id', '=', 'users.id')
            ->where('tasks.status', Task::STATUS_PENDING)
            ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                $query->whereNotIn('tasks.user_id', $systemsUserIds);
            })
            ->whereExists($taskPoolExists)
            ->where($notReassignedToday)
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
            ->whereNotExists($pendingInEligibleAdvisor)
            ->where(function ($query) use ($eligibleAdvisorIds) {
                $query->whereNull('tasks.user_id');

                if (! empty($eligibleAdvisorIds)) {
                    $query->orWhereNotIn('tasks.user_id', $eligibleAdvisorIds);
                }
            })
            ->groupBy('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id');

        $contacts = DB::query()
            ->fromSub(
                $canceled
                    ->union($ineffective)
                    ->union($withoutTasks)
                    ->union($withoutEligibleOwner)
                    ->union($openTasksFromIneligible),
                'candidates'
            )
            ->select('id', 'name', 'email', 'hs_id', 'owner_id')
            ->selectRaw('MIN(force_reason) as force_reason')
            ->groupBy('id', 'name', 'email', 'hs_id', 'owner_id')
            ->orderBy('id')
            ->limit($targetAdvisorId ? $limit * max(2, count($eligibleAdvisorIds) * 2) : $limit * 2)
            ->get();

        $contacts = $this->withTaskPoolMetadata($contacts);

        if ($targetAdvisorId && count($eligibleAdvisorIds) > 1) {
            $contacts = $this->partitionCandidatesForAdvisor($contacts, $eligibleAdvisorIds, $targetAdvisorId);
        }

        return $contacts
            ->sortBy(fn ($contact) => (bool) ($contact->disable_hubspot_reassignment ?? false) ? 1 : 0)
            ->take($limit)
            ->values();
    }

    private function partitionCandidatesForAdvisor($contacts, array $eligibleAdvisorIds, int $targetAdvisorId)
    {
        $advisorSlots = collect($eligibleAdvisorIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values();

        $slot = $advisorSlots->search($targetAdvisorId, true);

        if ($slot === false) {
            return $contacts;
        }

        $slotCount = max(1, $advisorSlots->count());

        return $contacts
            ->filter(fn ($contact) => ((int) $contact->id % $slotCount) === (int) $slot)
            ->values();
    }

    private function withTaskPoolMetadata($contacts)
    {
        $contactIds = $contacts
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($contactIds->isEmpty()) {
            return $contacts;
        }

        $metadata = DB::table('list_user as lu')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->whereIn('lu.user_id', $contactIds->all())
            ->where('lu.contacted', 0)
            ->where('l.include_in_task_pool', true)
            ->select('lu.user_id', 'l.name', 'l.disable_hubspot_reassignment')
            ->get()
            ->groupBy('user_id');

        return $contacts->map(function ($contact) use ($metadata) {
            $rows = $metadata->get($contact->id, collect());
            $listNames = $rows->pluck('name')->filter()->unique()->values();

            $contact->task_pool_list_name = $listNames->implode(', ');
            $contact->disable_hubspot_reassignment = $rows->contains(
                fn ($row) => (bool) $row->disable_hubspot_reassignment
            );

            return $contact;
        });
    }

    private function resolveHubspotContactId(HubspotService $hubspot, object $contact): ?string
    {
        if (!empty($contact->hs_id)) {
            return (string) $contact->hs_id;
        }

        if (empty($contact->email)) {
            return null;
        }

        $hsContact = $hubspot->searchContactOwnerByEmail($contact->email);

        return $hsContact['id'] ?? null;
    }

    private function eligibleAdvisorIdsForAutomaticTasks(): array
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
            ->pluck('users.id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function countPendingTasksFromIneligibleAssignees(int $contactId, array $eligibleAdvisorIds): int
    {
        return (clone $this->pendingTasksFromIneligibleAssigneesQuery($contactId, $eligibleAdvisorIds))->count();
    }

    private function cancelPendingTasksFromIneligibleAssignees(int $contactId, array $eligibleAdvisorIds): int
    {
        $query = $this->pendingTasksFromIneligibleAssigneesQuery($contactId, $eligibleAdvisorIds);
        $count = (clone $query)->count();

        if ($count > 0) {
            $query->update(['status' => Task::STATUS_CANCELED]);
        }

        return $count;
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

    private function pendingTasksFromIneligibleAssigneesQuery(int $contactId, array $eligibleAdvisorIds)
    {
        return Task::query()
            ->where('contact_id', $contactId)
            ->where('status', Task::STATUS_PENDING)
            ->notAssignedToSystems()
            ->where(function ($query) use ($eligibleAdvisorIds) {
                $query->whereNull('user_id');

                if (! empty($eligibleAdvisorIds)) {
                    $query->orWhereNotIn('user_id', $eligibleAdvisorIds);
                }
            });
    }

    private function advisorById(int $advisorId): ?User
    {
        return User::query()
            ->join('hubspot_owner_user as hou', 'hou.user_id', '=', 'users.id')
            ->join('hubspot_owners as ho', 'ho.id', '=', 'hou.hubspot_owner_id')
            ->where('users.id', $advisorId)
            ->where('ho.active', true)
            ->whereNotNull('hou.hubspot_owner_id')
            ->whereRaw("TRIM(hou.hubspot_owner_id) <> ''")
            ->where('users.exclude_from_task_assignment', false)
            ->where(function ($query) {
                $query->whereNull('users.task_assignment_daily_limit')
                    ->orWhere('users.task_assignment_daily_limit', '>', 0);
            })
            ->select('users.*', 'hou.hubspot_owner_id as hs_owner_id')
            ->first();
    }

    private function availableTaskSlotsForAdvisor(User $advisor, string $taskDate, int $basePerAdvisor): int
    {
        $dailyLimit = $advisor->task_assignment_daily_limit;
        $advisorBase = is_null($dailyLimit) ? $basePerAdvisor : max(0, (int) $dailyLimit);

        $assignedTodayCount = Task::query()
            ->where('user_id', $advisor->id)
            ->whereDate('due_date', $taskDate)
            ->where('status', '!=', Task::STATUS_CANCELED)
            ->count();

        return max(0, $advisorBase - $assignedTodayCount);
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

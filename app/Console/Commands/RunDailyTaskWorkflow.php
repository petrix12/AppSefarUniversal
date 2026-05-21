<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\HubspotDealOwnerSyncService;
use App\Services\HubspotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RunDailyTaskWorkflow extends Command
{
    protected $signature = 'tasks:daily-workflow
        {--date= : Fecha base opcional YYYY-MM-DD}
        {--per=10 : Tareas base por asesor}
        {--dry-run : Solo muestra cambios, no actualiza nada}
        {--force : Alias de --force-reassign para compatibilidad con el cron anterior}
        {--force-reassign : Fuerza reasignacion de tareas pendientes, canceladas, no efectivas y contactos sin tareas}
        {--force-limit=200 : Maximo de contactos a revisar con --force-reassign}';

    protected $description = 'Ejecuta secuencialmente el cierre/reasignacion de tareas vencidas y la generacion de tareas diarias.';

    public function handle(HubspotService $hubspot, HubspotDealOwnerSyncService $dealOwnerSync): int
    {
        $date = $this->option('date');
        $per = (int) ($this->option('per') ?? 10);
        $dryRun = (bool) $this->option('dry-run');
        $forceReassign = (bool) $this->option('force-reassign') || (bool) $this->option('force');
        $forceLimit = max(1, (int) ($this->option('force-limit') ?? 200));

        $this->info('Iniciando flujo diario de tareas.');

        if ($forceReassign) {
            $this->line('');
            $this->info('Paso 0/3: forzar reasignacion previa.');
            $this->forceReassignContacts($hubspot, $dealOwnerSync, $dryRun, $forceLimit);
        }

        $notifyOptions = [];
        if ($date) {
            $notifyOptions['--date'] = $date;
        }
        if ($dryRun) {
            $notifyOptions['--dry-run'] = true;
        }

        $this->line('');
        $this->info($forceReassign
            ? 'Paso 1/3: revisar tareas vencidas y reasignar clientes.'
            : 'Paso 1/2: revisar tareas vencidas y reasignar clientes.');
        $notifyExitCode = $this->call('tasks:notify-unclosed', $notifyOptions);

        if ($notifyExitCode !== self::SUCCESS) {
            $this->error('El flujo se detuvo porque tasks:notify-unclosed fallo.');
            return $notifyExitCode;
        }

        $generateOptions = [
            '--per' => $per,
        ];
        if ($date) {
            $generateOptions['--date'] = $date;
        }
        if ($dryRun) {
            $generateOptions['--dry-run'] = true;
        }

        $this->line('');
        $this->info($forceReassign
            ? 'Paso 2/3: generar tareas diarias.'
            : 'Paso 2/2: generar tareas diarias.');
        $generateExitCode = $this->call('tasks:generate-daily', $generateOptions);

        if ($generateExitCode !== self::SUCCESS) {
            $this->error('El flujo termino con error en tasks:generate-daily.');
            return $generateExitCode;
        }

        $this->line('');
        $this->info('Flujo diario de tareas completado.');

        return self::SUCCESS;
    }

    private function forceReassignContacts(HubspotService $hubspot, HubspotDealOwnerSyncService $dealOwnerSync, bool $dryRun, int $limit): void
    {
        $eligibleAdvisorIds = $this->eligibleAdvisorIdsForAutomaticTasks();
        $contacts = $this->forceReassignCandidates($limit, $eligibleAdvisorIds);

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
        $openTasksCanceled = 0;

        foreach ($contacts as $contact) {
            $advisor = $this->getNextAdvisorRoundRobin();

            if (! $advisor) {
                $this->warn("Sin asesor disponible para contact_id={$contact->id}");
                continue;
            }

            $reason = $contact->force_reason ?? 'sin motivo';
            $tasksToCancel = $this->countPendingTasksFromIneligibleAssignees((int) $contact->id, $eligibleAdvisorIds);
            $cancelNote = $tasksToCancel > 0 ? " | tareas pendientes a cancelar={$tasksToCancel}" : '';
            $this->line("   Reasignar contact_id={$contact->id} {$contact->name} | {$reason} -> {$advisor->name}{$cancelNote}");

            if ($dryRun) {
                continue;
            }

            User::whereKey($contact->id)->update(['owner_id' => $advisor->id]);
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

                Log::error('Error en reasignacion forzada de HubSpot', [
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
        $this->info("No encontrados en HubSpot: {$hubspotNotFound}");
        $this->info("Fallidos en HubSpot: {$hubspotFailed}");
        $this->info("Tareas pendientes canceladas de usuarios no elegibles: {$openTasksCanceled}");
    }

    private function forceReassignCandidates(int $limit, array $eligibleAdvisorIds)
    {
        $systemsUserIds = Task::systemsUserIds();

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

        $canceled = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id')
            ->selectRaw("'tarea cancelada' as force_reason")
            ->join('tasks', 'tasks.contact_id', '=', 'users.id')
            ->where('tasks.status', Task::STATUS_CANCELED)
            ->when(! empty($systemsUserIds), function ($query) use ($systemsUserIds) {
                $query->whereNotIn('tasks.user_id', $systemsUserIds);
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('list_user as lu')
                    ->whereColumn('lu.user_id', 'users.id')
                    ->where('lu.contacted', 0);
            })
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
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
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('list_user as lu')
                    ->whereColumn('lu.user_id', 'users.id')
                    ->where('lu.contacted', 0);
            })
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
            ->groupBy('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id');

        $withoutTasks = User::query()
            ->select('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id')
            ->selectRaw("'en lista sin tareas' as force_reason")
            ->join('list_user as lu', 'lu.user_id', '=', 'users.id')
            ->where('lu.contacted', 0)
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
            ->where('lu.contacted', 0)
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
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
            ->whereNotExists($completedEffective)
            ->whereNotExists($inProgress)
            ->where(function ($query) use ($eligibleAdvisorIds) {
                $query->whereNull('tasks.user_id');

                if (! empty($eligibleAdvisorIds)) {
                    $query->orWhereNotIn('tasks.user_id', $eligibleAdvisorIds);
                }
            })
            ->groupBy('users.id', 'users.name', 'users.email', 'users.hs_id', 'users.owner_id');

        return DB::query()
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
            ->limit($limit)
            ->get();
    }

    private function resolveHubspotContactId(HubspotService $hubspot, object $contact): ?string
    {
        if (!empty($contact->hs_id)) {
            return (string) $contact->hs_id;
        }

        if (empty($contact->email)) {
            return null;
        }

        $hsContact = $hubspot->searchContactByEmail($contact->email);

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

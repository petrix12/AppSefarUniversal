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

class NotifyUnclosedTasks extends Command
{
    private const TASK_RESPONSE_DAYS = 1;

    protected $signature = 'tasks:notify-unclosed
        {--date= : Fecha base opcional YYYY-MM-DD}
        {--dry-run : Solo muestra cambios, no actualiza nada}';

    protected $description = 'Cancela tareas comerciales abiertas vencidas por 1 dia y reasigna el cliente en BD y HubSpot.';

    public function handle(HubspotService $hubspotService, HubspotDealOwnerSyncService $dealOwnerSync): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : today();

        $dryRun = (bool) $this->option('dry-run');

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
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('tasks as in_progress_tasks')
                    ->whereColumn('in_progress_tasks.contact_id', 'tasks.contact_id')
                    ->where('in_progress_tasks.status', Task::STATUS_IN_PROGRESS);
            })
            ->get();

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

        DB::beginTransaction();

        try {
            foreach ($tasks as $task) {
                $contact = $task->contact;

                if (!$contact) {
                    $this->warn("La tarea {$task->id} no tiene cliente válido.");
                    continue;
                }

                $advisor = $this->getNextAdvisorRoundRobin((int) $task->user_id);

                if (!$advisor) {
                    $this->warn('No hay asesores disponibles con owner real de HubSpot mapeado.');
                    continue;
                }

                $this->line(
                    "Tarea {$task->id} | Cliente {$contact->id} {$contact->name} → Nuevo owner: {$advisor->name}"
                );

                if (!$dryRun) {
                    // 1. Cancelar tarea
                    $task->update([
                        'status' => 'canceled',
                    ]);

                    // 2. Actualizar propietario local del cliente
                    $contact->update([
                        'owner_id' => $advisor->id,
                    ]);

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

                            Log::warning('Contacto no encontrado en HubSpot al reasignar owner', [
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

                        Log::error('Error actualizando owner en HubSpot', [
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

            Log::error('Error cancelando tareas vencidas y reasignando clientes', [
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
        $this->info("Contactos no encontrados en HubSpot: {$hubspotNotFound}");
        $this->info("Actualizaciones fallidas en HubSpot: {$hubspotFailed}");

        return self::SUCCESS;
    }

    private function resolveHubspotContactId(HubspotService $hubspotService, User $contact): ?string
    {
        if (!empty($contact->hs_id)) {
            return (string) $contact->hs_id;
        }

        if (empty($contact->email)) {
            return null;
        }

        $hsContact = $hubspotService->searchContactByEmail($contact->email);

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

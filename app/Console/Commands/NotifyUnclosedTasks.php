<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Models\Setting;
use App\Notifications\UnclosedTasksNotification;
use App\Services\HubspotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyUnclosedTasks extends Command
{
    protected $signature = 'tasks:notify-unclosed
        {--date= : Fecha base opcional YYYY-MM-DD}
        {--dry-run : Solo muestra cambios, no actualiza nada}';

    protected $description = 'Cancela tareas abiertas con más de 3 días y reasigna aleatoriamente el cliente en BD y HubSpot.';

    public function handle(HubspotService $hubspotService): int
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : today();

        $dryRun = (bool) $this->option('dry-run');

        $limitDate = $date->copy()->subDays(3)->endOfDay();

        $this->info("🔔 Revisando tareas abiertas creadas hasta: {$limitDate->toDateTimeString()}");

        $tasks = Task::query()
            ->with([
                'assignee:id,name,email',
                'contact:id,name,email,hs_id,owner_id',
            ])
            ->whereIn('status', ['pending', 'in_progress'])
            ->where('created_at', '<=', $limitDate)
            ->whereNotNull('contact_id')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('✅ No hay tareas vencidas.');
            return self::SUCCESS;
        }

        $this->info("Tareas vencidas encontradas: {$tasks->count()}");

        $processedTasks = collect();
        $reassignedClients = 0;
        $hubspotUpdated = 0;
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

                $advisor = $this->getNextAdvisorRoundRobin($contact->owner_id);

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

                            $hubspotUpdated++;
                        } else {
                            $hubspotNotFound++;

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

    private function getNextAdvisorRoundRobin(?int $currentOwnerId = null): ?User
    {
        $advisors = User::query()
            ->join('hubspot_owner_user as hou', 'hou.user_id', '=', 'users.id')
            ->whereNotNull('hou.hubspot_owner_id')
            ->whereRaw("TRIM(hou.hubspot_owner_id) <> ''")
            ->when($currentOwnerId, function ($query) use ($currentOwnerId) {
                $query->where('users.id', '!=', $currentOwnerId);
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

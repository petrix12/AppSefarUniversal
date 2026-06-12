<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\HubspotDealOwnerSyncService;
use App\Services\HubspotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class RepairMissingHubspotTaskOwners extends Command
{
    protected $signature = 'tasks:repair-missing-hubspot-owners
        {--dry-run : Solo muestra cambios, no escribe}
        {--limit=200 : Maximo de contactos a revisar}';

    protected $description = 'Asigna owner HubSpot faltante usando el asesor de la tarea abierta solo en listas que permiten HubSpot.';

    public function handle(HubspotService $hubspot, HubspotDealOwnerSyncService $dealOwnerSync): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $tasks = Task::query()
            ->with([
                'assignee:id,name,email',
                'contact:id,name,email,hs_id,owner_id',
            ])
            ->join('list_user as lu', 'lu.user_id', '=', 'tasks.contact_id')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->whereIn('tasks.status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->where('lu.contacted', false)
            ->where('l.include_in_task_pool', true)
            ->where('l.disable_hubspot_reassignment', false)
            ->whereNotNull('tasks.contact_id')
            ->when(! empty(Task::systemsUserIds()), function ($query) {
                $query->whereNotIn('tasks.user_id', Task::systemsUserIds());
            })
            ->orderByDesc('tasks.id')
            ->select('tasks.*')
            ->limit($limit)
            ->get()
            ->unique('contact_id')
            ->values();

        if ($tasks->isEmpty()) {
            $this->info('No hay tareas abiertas en listas con HubSpot permitido para revisar.');
            return self::SUCCESS;
        }

        $ownerMap = DB::table('hubspot_owner_user as hou')
            ->join('hubspot_owners as ho', 'ho.id', '=', 'hou.hubspot_owner_id')
            ->where('ho.active', true)
            ->whereNotNull('hou.hubspot_owner_id')
            ->select('hou.user_id', 'hou.hubspot_owner_id')
            ->get()
            ->keyBy(fn ($row) => (int) $row->user_id);

        $reviewed = 0;
        $updated = 0;
        $dealsUpdated = 0;
        $missingHubspotContact = 0;
        $alreadyOwned = 0;
        $withoutMappedOwner = 0;
        $failed = 0;

        foreach ($tasks as $task) {
            $reviewed++;
            $contact = $task->contact;
            $advisor = $task->assignee;

            if (! $contact || ! $advisor) {
                continue;
            }

            $owner = $ownerMap->get((int) $advisor->id);

            if (! $owner) {
                $withoutMappedOwner++;
                $this->warn("Sin owner HubSpot mapeado para task_id={$task->id}, advisor_id={$advisor->id}");
                continue;
            }

            try {
                $hubspotContact = $this->hubspotContactFor($hubspot, $contact);

                if (! $hubspotContact) {
                    $missingHubspotContact++;
                    $this->warn("HubSpot no encontrado: contact_id={$contact->id}, task_id={$task->id}");
                    continue;
                }

                $currentOwnerId = $hubspotContact['properties']['hubspot_owner_id'] ?? null;

                if (filled($currentOwnerId)) {
                    $alreadyOwned++;
                    continue;
                }

                $this->line("Asignar HubSpot owner faltante: contact_id={$contact->id}, task_id={$task->id}, advisor={$advisor->name}, hs_owner={$owner->hubspot_owner_id}");

                if ($dryRun) {
                    continue;
                }

                $hubspot->updateContact((string) $hubspotContact['id'], [
                    'hubspot_owner_id' => (string) $owner->hubspot_owner_id,
                ]);

                $dealsUpdated += $dealOwnerSync->syncForContact(
                    $hubspot,
                    (string) $hubspotContact['id'],
                    (string) $owner->hubspot_owner_id,
                    (int) $contact->id
                );

                User::whereKey((int) $contact->id)
                    ->update($this->ownerSyncAttributes((int) $advisor->id, (string) $owner->hubspot_owner_id));

                $updated++;
            } catch (\Throwable $e) {
                $failed++;
                $this->warn("Fallo contact_id={$contact->id}, task_id={$task->id}: {$e->getMessage()}");

                Log::channel('tasks')->error('Error reparando owner HubSpot faltante en tarea abierta', [
                    'task_id' => $task->id,
                    'contact_id' => $contact->id,
                    'advisor_id' => $advisor->id,
                    'hubspot_owner_id' => $owner->hubspot_owner_id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info($dryRun ? 'Dry-run completado.' : 'Reparacion completada.');
        $this->info("Contactos revisados: {$reviewed}");
        $this->info("Contactos actualizados en HubSpot: {$updated}");
        $this->info("Negocios actualizados: {$dealsUpdated}");
        $this->info("Ya tenian owner HubSpot: {$alreadyOwned}");
        $this->info("No encontrados en HubSpot: {$missingHubspotContact}");
        $this->info("Asesores sin owner mapeado: {$withoutMappedOwner}");
        $this->info("Fallidos: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function hubspotContactFor(HubspotService $hubspot, object $contact): ?array
    {
        if (! empty($contact->hs_id)) {
            return $hubspot->getContactOwnerById((string) $contact->hs_id);
        }

        if (blank($contact->email)) {
            return null;
        }

        return $hubspot->searchContactOwnerByEmail((string) $contact->email);
    }

    private function ownerSyncAttributes(int $advisorId, ?string $hubspotOwnerId = null): array
    {
        $attributes = ['owner_id' => $advisorId];

        if (Schema::hasColumn('users', 'task_reassignment_locked_owner_id')) {
            $attributes['task_reassignment_locked_owner_id'] = $advisorId;
        }

        if (Schema::hasColumn('users', 'task_reassignment_locked_hubspot_owner_id')) {
            $attributes['task_reassignment_locked_hubspot_owner_id'] = $hubspotOwnerId;
        }

        return $attributes;
    }
}

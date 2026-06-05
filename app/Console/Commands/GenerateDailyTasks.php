<?php
// app/Console/Commands/GenerateDailyTasks.php

namespace App\Console\Commands;

use App\Mail\DailyTasksAssignedMail;
use App\Models\Task;
use App\Models\User;
use App\Services\HubspotDealOwnerSyncService;
use App\Services\HubspotService;
use App\Services\MarkContactedService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class GenerateDailyTasks extends Command
{
    private ?bool $userReassignmentColumnExists = null;
    private ?bool $reassignmentLockColumnExists = null;
    private array $hubspotOwnerCache = [];

    protected $signature = 'tasks:generate-daily
        {--date=      : Fecha Y-m-d (defecto: hoy)}
        {--per=10     : Tareas base por asesor}
        {--advisor-id= : Genera tareas solo para este asesor}
        {--dry-run    : Solo muestra, no escribe}
        {--skip-list-cleanup : No marca contactos para salir de listas}
        {--no-email   : No envia correo a los vendedores}
    ';

    protected $description = 'Genera tareas diarias por asesor respetando cupos, evitando repetidos semanales y omitiendo desinteresados.';

    public function handle(
        HubspotService $hubspot,
        HubspotDealOwnerSyncService $dealOwnerSync,
        MarkContactedService $markContacted
    ): int
    {
        $date   = $this->option('date') ? Carbon::parse($this->option('date')) : today();
        $base   = (int) ($this->option('per') ?? 10);
        $dryRun = (bool) $this->option('dry-run');
        $advisorId = (int) ($this->option('advisor-id') ?: 0);
        $skipListCleanup = (bool) $this->option('skip-list-cleanup');
        $reassignmentDate = today();
        $sendEmails = ! $dryRun && ! (bool) $this->option('no-email');

        $this->info("📅 Fecha: {$date->toDateString()} | Base: {$base} tareas | " . ($dryRun ? 'DRY-RUN' : 'REAL'));

        // 1) Asesores activos: solo usuarios vinculados a un Owner real de HubSpot.
        $advisors = User::query()
            ->join('hubspot_owner_user as hou', 'hou.user_id', '=', 'users.id')
            ->join('hubspot_owners as ho', 'ho.id', '=', 'hou.hubspot_owner_id')
            ->where('ho.active', true)
            ->whereNotNull('hou.hubspot_owner_id')
            ->whereRaw("TRIM(hou.hubspot_owner_id) <> ''")
            ->where('users.exclude_from_task_assignment', false)
            ->when($advisorId > 0, function ($query) use ($advisorId) {
                $query->where('users.id', $advisorId);
            })
            ->orderBy('users.name')
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.task_assignment_daily_limit',
                'hou.hubspot_owner_id as hs_owner_id',
                'ho.name as hs_owner_name',
            ])
            ->get();

        if ($advisors->isEmpty()) {
            $this->error($advisorId > 0
                ? "El asesor {$advisorId} no tiene owner activo de HubSpot o esta excluido de tareas."
                : 'No hay usuarios asociados a HubSpot Owners activos y habilitados para tareas.'
            );
            return self::FAILURE;
        }

        $advisorsByHubspotOwnerId = $advisors->keyBy(fn ($advisor) => (string) $advisor->hs_owner_id);

        if (! $skipListCleanup) {
            $this->markContactsThatShouldLeaveReassignmentLists($markContacted, $dryRun);
        }

        // 2) Contactos disponibles en listas habilitadas para el pool.
        $contactColumns = [
            'u.id as contact_id',
            'u.name as contact_name',
            'u.email as contact_email',
            'u.hs_id as contact_hs_id',
            'u.owner_id',
            'l.id as list_id',
            'l.name as list_name',
            'l.disable_hubspot_reassignment',
        ];

        if ($this->userReassignmentColumnExists()) {
            $contactColumns[] = 'u.last_task_reassigned_at';
        }

        if ($this->reassignmentLockColumnExists()) {
            $contactColumns[] = 'u.task_reassignment_locked_at';
            $contactColumns[] = 'u.task_reassignment_locked_owner_id';
            $contactColumns[] = 'u.task_reassignment_locked_hubspot_owner_id';
        }

        $contacts = DB::table('list_user as lu')
            ->join('users as u', 'u.id', '=', 'lu.user_id')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->select($contactColumns)
            ->where('lu.contacted', 0)
            ->where('l.include_in_task_pool', true)
            ->get()
            ->groupBy('contact_id')
            ->map(function ($rows) {
                $contact = clone $rows->first();
                $listNames = $rows->pluck('list_name')->filter()->unique()->values();

                $contact->list_name = $listNames->implode(', ');
                $contact->disable_hubspot_reassignment = $rows->contains(
                    fn ($row) => (bool) $row->disable_hubspot_reassignment
                );

                return $contact;
            })
            ->values();

        if ($contacts->isEmpty()) {
            $this->warn('No hay contactos disponibles en listas habilitadas para el pool de tareas.');
            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalReassignedLocal = 0;
        $totalSkippedByHubspot = 0;
        $totalSkippedHubspotByList = 0;
        $assignedContactIdsThisRun = [];
        $nonReassignableContacts = Task::query()
            ->where(function ($q) {
                $q->whereIn('status', ['desinteres', 'no_interest', 'not_interested'])
                    ->orWhere(function ($completed) {
                        $completed->where('status', Task::STATUS_COMPLETED)
                            ->where(function ($effective) {
                                $effective->where('call_effective', 1)
                                    ->orWhere('customer_responded', 1)
                                    ->orWhereNotNull('sale_status')
                                    ->orWhereNotNull('reason_no_interest')
                                    ->orWhere('interest_level', 0);
                            });
                    });
            })
            ->pluck('contact_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $inProgressContacts = Task::query()
            ->where('status', Task::STATUS_IN_PROGRESS)
            ->pluck('contact_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $pendingContacts = Task::query()
            ->where('status', Task::STATUS_PENDING)
            ->notAssignedToSystems()
            ->pluck('contact_id')
            ->filter()
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->toArray();

        foreach ($advisors as $advisor) {
            $createdTaskIds = [];
            $dailyLimit = $advisor->task_assignment_daily_limit;
            $advisorBase = is_null($dailyLimit) ? $base : max(0, (int) $dailyLimit);

            // 3) Tareas ya asignadas para ese dia
            $assignedTodayCount = Task::query()
                ->where('user_id', $advisor->id)
                ->whereDate('due_date', $date)
                ->where('status', '!=', Task::STATUS_CANCELED)
                ->count();

            // 4) Follow-ups ya programados para ese dia
            $followUpCount = Task::query()
                ->where('user_id', $advisor->id)
                ->whereDate('due_date', $date)
                ->whereNotNull('follow_up_date')
                ->count();

            $toCreate = max(0, $advisorBase - $assignedTodayCount);

            $this->line("👤 {$advisor->name}: cupo={$advisorBase} / asignadas={$assignedTodayCount} / follow-ups={$followUpCount} → a crear={$toCreate}");

            if ($toCreate === 0) {
                $this->warn("   ↳ Cupo lleno para este asesor.");
                continue;
            }

            // 5) Contactos que ya tienen tarea HOY en cualquier asesor
            $alreadyTaskedToday = Task::query()
                ->whereDate('due_date', $date)
                ->where('status', '!=', Task::STATUS_CANCELED)
                ->pluck('contact_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            // 6) Contactos que ya tuvieron tarea en los ÚLTIMOS 7 DÍAS para este asesor
            $weekStart = $date->copy()->subDays(6)->startOfDay(); // hoy incluido = ventana de 7 días
            $weekEnd   = $date->copy()->endOfDay();

            $recentlyTasked = Task::query()
                ->where('user_id', $advisor->id)
                ->whereBetween('due_date', [$weekStart, $weekEnd])
                ->pluck('contact_id')
                ->filter()
                ->unique()
                ->map(fn ($id) => (int) $id)
                ->toArray();

            // 7) Contactos con desinterés: excluirlos COMPLETAMENTE
            // Ajusta este filtro según cómo guardes el desinterés:
            // a) status = 'no_interest' / 'desinterest'
            // b) interest_level = 0
            // c) reason_no_interest no null
            // Ademas se usa como pool para completar cupo cuando el asesor no tiene contactos propios.
            $candidates = $contacts
                ->reject(function ($contact) use ($advisor, $reassignmentDate, $alreadyTaskedToday, $recentlyTasked, $nonReassignableContacts, $inProgressContacts, $pendingContacts, &$assignedContactIdsThisRun) {
                    $contactId = (int) $contact->contact_id;

                    return in_array($contactId, $assignedContactIdsThisRun, true)
                        || in_array($contactId, $alreadyTaskedToday, true)
                        || in_array($contactId, $recentlyTasked, true)
                        || in_array($contactId, $pendingContacts, true)
                        || in_array($contactId, $inProgressContacts, true)
                        || $this->wasReassignedTodayToDifferentAdvisor($contact, $advisor, $reassignmentDate)
                        || in_array($contactId, $nonReassignableContacts, true);
                })
                ->unique('contact_id')
                ->values();

            [$hubspotOwnerCandidates, $localOnlyCandidates] = $candidates
                ->partition(fn ($contact) => ! (bool) ($contact->disable_hubspot_reassignment ?? false));

            $candidates = $hubspotOwnerCandidates
                ->shuffle()
                ->concat($localOnlyCandidates->shuffle())
                ->values()
                ->take(max($toCreate * 3, $toCreate));

            if ($candidates->isEmpty()) {
                $this->warn("   ↳ Sin candidatos disponibles.");
                continue;
            }

            foreach ($candidates as $contact) {
                if (count($createdTaskIds) >= $toCreate) {
                    break;
                }

                if (! $this->contactBelongsToAdvisorInHubspot(
                    $hubspot,
                    $contact,
                    $advisor,
                    $advisorsByHubspotOwnerId,
                    $dryRun
                )) {
                    continue;
                }

                $previousOwner = $contact->owner_id ?? 'NULL';
                $ownerChanged = (int) ($contact->owner_id ?? 0) !== (int) $advisor->id;
                $skipHubspotByList = (bool) ($contact->disable_hubspot_reassignment ?? false);
                $ownerNote = $ownerChanged ? " | owner previo={$previousOwner}" : '';
                $hubspotNote = $ownerChanged
                    ? ' | owner local actualizado desde HubSpot'
                    : '';
                $this->line("   + Tarea -> {$contact->contact_name} | Lista: {$contact->list_name} (contact_id={$contact->contact_id}){$ownerNote}{$hubspotNote}");

                if (! $dryRun) {
                    if ($ownerChanged) {
                        User::whereKey((int) $contact->contact_id)
                            ->update($this->ownerUpdateAttributes((int) $advisor->id, (string) $advisor->hs_owner_id));
                        $contact->owner_id = (int) $advisor->id;
                        $totalReassignedLocal++;
                    }

                    $task = Task::create([
                        'user_id'            => $advisor->id,
                        'contact_id'         => $contact->contact_id,
                        'title'              => "Comunicarse con el cliente {$contact->contact_name} [Lista: {$contact->list_name}]",
                        'description' => $this->taskDescriptionForGeneratedContact(
                            $contact,
                            $ownerChanged,
                            $previousOwner,
                            $skipHubspotByList
                        ),
                        'due_date'           => $date->toDateString(),
                        'status'             => Task::STATUS_PENDING,
                        'created_by_user_id' => null,
                    ]);

                    $createdTaskIds[] = $task->id;
                }

                $assignedContactIdsThisRun[] = (int) $contact->contact_id;
                $totalCreated++;
            }

            if ($sendEmails && ! empty($createdTaskIds)) {
                $this->sendAssignedTasksEmail($advisor, $createdTaskIds, $date);
            }
        }

        $this->info("✅ Tareas " . ($dryRun ? 'simuladas' : 'creadas') . ": {$totalCreated}");
        $this->info("Contactos reasignados localmente por cupo: {$totalReassignedLocal}");
        $this->info("Reasignaciones HubSpot omitidas por configuracion de lista: {$totalSkippedHubspotByList}");
        $this->info("Contactos omitidos por no poder sincronizar HubSpot: {$totalSkippedByHubspot}");
        return self::SUCCESS;
    }

    private function contactBelongsToAdvisorInHubspot(
        HubspotService $hubspot,
        object $contact,
        object $advisor,
        $advisorsByHubspotOwnerId,
        bool $dryRun
    ): bool {
        $hubspotOwnerId = $this->hubspotOwnerIdForContact($hubspot, $contact);

        if (! $hubspotOwnerId) {
            if ($this->contactLockedToAnotherAdvisor($contact, (int) $advisor->id)) {
                $this->line("   - Omitido: contact_id={$contact->contact_id} ya esta bloqueado para owner local {$contact->task_reassignment_locked_owner_id}.");
                return false;
            }

            return true;
        }

        $hubspotAdvisor = $advisorsByHubspotOwnerId->get((string) $hubspotOwnerId);

        if (! $hubspotAdvisor) {
            $this->line("   - Omitido: contact_id={$contact->contact_id} tiene owner HubSpot {$hubspotOwnerId} sin asesor interno mapeado.");
            return false;
        }

        if ((int) $hubspotAdvisor->id !== (int) $advisor->id) {
            if (! $dryRun) {
                User::whereKey((int) $contact->contact_id)
                    ->update($this->ownerUpdateAttributes((int) $hubspotAdvisor->id, (string) $hubspotOwnerId));
            }

            $contact->owner_id = (int) $hubspotAdvisor->id;
            $contact->task_reassignment_locked_owner_id = (int) $hubspotAdvisor->id;
            $contact->task_reassignment_locked_hubspot_owner_id = (string) $hubspotOwnerId;

            $this->line("   - Omitido: HubSpot dice que contact_id={$contact->contact_id} pertenece a {$hubspotAdvisor->name}, no a {$advisor->name}.");
            return false;
        }

        if ((int) ($contact->owner_id ?? 0) !== (int) $advisor->id) {
            if (! $dryRun) {
                User::whereKey((int) $contact->contact_id)
                    ->update($this->ownerUpdateAttributes((int) $advisor->id, (string) $hubspotOwnerId));
            }

            $contact->owner_id = (int) $advisor->id;
            $contact->task_reassignment_locked_owner_id = (int) $advisor->id;
            $contact->task_reassignment_locked_hubspot_owner_id = (string) $hubspotOwnerId;
        }

        return true;
    }

    private function hubspotOwnerIdForContact(HubspotService $hubspot, object $contact): ?string
    {
        $cacheKey = (string) ($contact->contact_hs_id ?: $contact->contact_email ?: $contact->contact_id);

        if (array_key_exists($cacheKey, $this->hubspotOwnerCache)) {
            return $this->hubspotOwnerCache[$cacheKey];
        }

        try {
            $hsContactId = $this->resolveHubspotContactId($hubspot, $contact);

            if (! $hsContactId) {
                return $this->hubspotOwnerCache[$cacheKey] = null;
            }

            $data = $hubspot->getContactById($hsContactId);
            $ownerId = $data['properties']['hubspot_owner_id'] ?? null;

            return $this->hubspotOwnerCache[$cacheKey] = filled($ownerId) ? (string) $ownerId : null;
        } catch (\Throwable $e) {
            $this->warn("   - No se pudo leer owner HubSpot para contact_id={$contact->contact_id}: {$e->getMessage()}");

            Log::channel('tasks')->warning('No se pudo leer owner HubSpot antes de generar tarea', [
                'client_id' => $contact->contact_id,
                'email' => $contact->contact_email,
                'hs_id' => $contact->contact_hs_id,
                'error' => $e->getMessage(),
            ]);

            return $this->hubspotOwnerCache[$cacheKey] = null;
        }
    }

    private function contactLockedToAnotherAdvisor(object $contact, int $advisorId): bool
    {
        return $this->reassignmentLockColumnExists()
            && ! empty($contact->task_reassignment_locked_owner_id)
            && (int) $contact->task_reassignment_locked_owner_id !== $advisorId;
    }

    private function taskDescriptionForGeneratedContact(
        object $contact,
        bool $ownerChanged,
        mixed $previousOwner,
        bool $skipHubspotByList
    ): string {
        $description = "Lista origen: {$contact->list_name}";

        if (! $ownerChanged) {
            return $description;
        }

        $description .= ". Reasignado automaticamente desde owner {$previousOwner}.";

        if ($skipHubspotByList) {
            $description .= ' HubSpot no se actualizo por configuracion de la lista.';
        }

        return $description;
    }

    private function wasReassignedTodayToDifferentAdvisor(object $contact, object $advisor, Carbon $date): bool
    {
        if (! $this->userReassignmentColumnExists()) {
            return false;
        }

        if (empty($contact->last_task_reassigned_at)) {
            return false;
        }

        return Carbon::parse($contact->last_task_reassigned_at)->isSameDay($date)
            && (int) ($contact->owner_id ?? 0) !== (int) $advisor->id;
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

    private function markContactsThatShouldLeaveReassignmentLists(MarkContactedService $markContacted, bool $dryRun): void
    {
        $tasks = Task::query()
            ->whereNotNull('contact_id')
            ->where('status', Task::STATUS_COMPLETED)
            ->where(function ($query) {
                $query->where('call_effective', 1)
                    ->orWhere('customer_responded', 1)
                    ->orWhereNotNull('sale_status')
                    ->orWhereNotNull('reason_no_interest')
                    ->orWhere('interest_level', 0);
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('list_user as lu')
                    ->whereColumn('lu.user_id', 'tasks.contact_id')
                    ->where('lu.contacted', 0);
            })
            ->orderByDesc('id')
            ->get()
            ->unique('contact_id')
            ->values();

        if ($tasks->isEmpty()) {
            return;
        }

        $this->warn("Contactos para sacar de listas de reasignacion: {$tasks->count()}");

        if ($dryRun) {
            return;
        }

        foreach ($tasks as $task) {
            $markContacted->markFromTask($task);
        }
    }

    private function sendAssignedTasksEmail(User $advisor, array $taskIds, Carbon $date): void
    {
        if (blank($advisor->email)) {
            $this->warn("   ↳ No se envio correo a {$advisor->name}: no tiene email.");
            return;
        }

        $tasks = Task::query()
            ->with('contact')
            ->whereKey($taskIds)
            ->orderBy('id')
            ->get();

        if ($tasks->isEmpty()) {
            return;
        }

        try {
            Mail::to($advisor->email)->send(new DailyTasksAssignedMail(
                $advisor,
                $tasks,
                $date,
                route('tasks.index')
            ));

            $this->line("   ↳ Correo enviado a {$advisor->email} ({$tasks->count()} tarea(s)).");
        } catch (\Throwable $e) {
            $this->warn("   ↳ No se pudo enviar correo a {$advisor->email}: {$e->getMessage()}");

            Log::channel('tasks')->error('Error enviando correo de tareas asignadas', [
                'advisor_id' => $advisor->id,
                'advisor_email' => $advisor->email,
                'task_ids' => $taskIds,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncContactOwnerForTaskGeneration(
        HubspotService $hubspot,
        HubspotDealOwnerSyncService $dealOwnerSync,
        object $contact,
        object $advisor
    ): bool {
        try {
            $hsContactId = $this->resolveHubspotContactId($hubspot, $contact);

            if (! $hsContactId) {
                $this->warn("      HubSpot no encontrado: contact_id={$contact->contact_id}, email={$contact->contact_email}, hs_id={$contact->contact_hs_id}");

                Log::channel('tasks')->warning('Contacto no encontrado en HubSpot al reasignar durante generacion de tareas', [
                    'client_id' => $contact->contact_id,
                    'email' => $contact->contact_email,
                    'hs_id' => $contact->contact_hs_id,
                    'new_owner_user_id' => $advisor->id,
                    'new_hubspot_owner_id' => $advisor->hs_owner_id,
                ]);

                return false;
            }

            $hubspot->updateContact($hsContactId, [
                'hubspot_owner_id' => (string) $advisor->hs_owner_id,
            ]);

            $updatedDeals = $dealOwnerSync->syncForContact(
                $hubspot,
                $hsContactId,
                (string) $advisor->hs_owner_id,
                (int) $contact->contact_id
            );

            User::whereKey((int) $contact->contact_id)
                ->update($this->ownerUpdateAttributes((int) $advisor->id));

            $contact->owner_id = (int) $advisor->id;

            $this->line("      HubSpot actualizado: hs_id={$hsContactId}, owner={$advisor->hs_owner_id}, deals={$updatedDeals}");

            return true;
        } catch (\Throwable $e) {
            $this->warn("      HubSpot fallo: contact_id={$contact->contact_id}, owner={$advisor->hs_owner_id}, error={$e->getMessage()}");

            Log::channel('tasks')->error('Error reasignando contacto en HubSpot durante generacion de tareas', [
                'client_id' => $contact->contact_id,
                'email' => $contact->contact_email,
                'hs_id' => $contact->contact_hs_id,
                'new_owner_user_id' => $advisor->id,
                'new_hubspot_owner_id' => $advisor->hs_owner_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function resolveHubspotContactId(HubspotService $hubspot, object $contact): ?string
    {
        if (! empty($contact->contact_hs_id)) {
            return (string) $contact->contact_hs_id;
        }

        if (empty($contact->contact_email)) {
            return null;
        }

        $hsContact = $hubspot->searchContactByEmail($contact->contact_email);

        return $hsContact['id'] ?? null;
    }
}

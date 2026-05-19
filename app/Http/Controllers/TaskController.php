<?php
// app/Http/Controllers/TaskController.php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Setting;
use App\Services\HubspotService;
use App\Services\HubspotDealOwnerSyncService;
use App\Services\MarkContactedService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    public function __construct(
        private readonly MarkContactedService $markContacted
    ) {}

    // ── Mis tareas ───────────────────────────────────────────
    public function table(Request $request)
    {
        $status = $request->input('status', 'open');
        $allowedStatuses = ['open', 'pending', 'in_progress', 'completed', 'canceled', 'all'];

        if (! in_array($status, $allowedStatuses, true)) {
            $status = 'open';
        }

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : null;

        $search = trim((string) $request->input('q', ''));

        $baseQuery = Task::query()
            ->with('contact')
            ->where('user_id', auth()->id());

        if ($date) {
            $baseQuery->whereDate('due_date', $date);
        }

        if ($search !== '') {
            $baseQuery->where(function ($query) use ($search) {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($contactQuery) use ($search) {
                        $contactQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('passport', 'like', "%{$search}%");
                    });
            });
        }

        $tasksQuery = (clone $baseQuery);

        match ($status) {
            'open' => $tasksQuery->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS]),
            'all' => null,
            default => $tasksQuery->where('status', $status),
        };

        $tasks = $tasksQuery
            ->orderByRaw("FIELD(status, 'pending','in_progress','completed','canceled')")
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        $stats = [
            'total'       => $tasks->count(),
            'open'        => $tasks->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])->count(),
            'pending'     => $tasks->where('status', Task::STATUS_PENDING)->count(),
            'in_progress' => $tasks->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'completed'   => $tasks->where('status', Task::STATUS_COMPLETED)->count(),
            'canceled'    => $tasks->where('status', Task::STATUS_CANCELED)->count(),
        ];

        $filters = [
            'status' => $status,
            'date' => $date?->toDateString(),
            'q' => $search,
        ];

        return view('tasks.index', compact('tasks', 'stats', 'date', 'filters'));
    }

    // ── Ver detalle / flujo ──────────────────────────────────
    public function show(Task $task)
    {
        $this->authorizeAccess($task);

        return view('tasks.show', compact('task'));
    }

    public function updateSalesTracking(Request $request, Task $task, HubspotService $hubspot)
    {
        $this->authorizeAccess($task);

        if ($task->isAssignedToSystems()) {
            return $this->completeInternalTask($task);
        }

        $data = $request->validate([
            'contact_methods' => 'required|array|min:1',
            'contact_methods.*' => 'in:' . implode(',', array_keys(Task::contactMethodOptions())),
            'customer_responded' => 'required|in:0,1',
            'sale_status' => 'nullable|in:' . implode(',', array_keys(Task::saleStatusOptions())),
            'sales_tags' => 'nullable|array',
            'sales_tags.*' => 'in:' . implode(',', array_keys(Task::salesTagOptions())),
            'interest_level' => 'nullable|in:0,1',
            'reason_no_interest' => 'nullable|string|max:255',
            'reason_no_effective' => 'nullable|string|max:255',
            'product_of_interest' => 'nullable|string|max:255',
            'follow_up_date' => 'nullable|date|after:today',
        ]);

        $oldFollowUpDate = optional($task->follow_up_date)->toDateString();
        $methods = array_values(array_unique($data['contact_methods']));
        $customerResponded = $data['customer_responded'] === '1';
        $onlyUnansweredCall = ! $customerResponded
            && count($methods) === 1
            && in_array(Task::CONTACT_METHOD_CALL, $methods, true);

        $interest = array_key_exists('interest_level', $data) && $data['interest_level'] !== null
            ? $data['interest_level'] === '1'
            : null;

        $task->contact_methods = $methods;
        $task->customer_responded = $customerResponded;
        $task->call_effective = ! $onlyUnansweredCall;
        $task->reason_no_effective = $onlyUnansweredCall
            ? ($request->filled('reason_no_effective') ? $data['reason_no_effective'] : 'Solo llamada sin respuesta')
            : ($data['reason_no_effective'] ?? null);
        $task->interest_level = $interest;
        $task->reason_no_interest = $interest === false
            ? ($data['reason_no_interest'] ?? null)
            : null;
        $task->sale_status = $data['sale_status'] ?? null;
        $task->sales_tags = array_values($data['sales_tags'] ?? []);
        $task->product_of_interest = $data['product_of_interest'] ?? null;
        $task->follow_up_date = $data['follow_up_date'] ?? null;
        $task->status = Task::STATUS_COMPLETED;
        $task->save();

        if ($task->follow_up_date && $task->follow_up_date->toDateString() !== $oldFollowUpDate) {
            $this->createFollowUp($task);
        }

        $message = 'Gestion comercial guardada. El cliente queda en tu seguimiento.';

        if ($onlyUnansweredCall) {
            $message = 'Gestion guardada como llamada sin respuesta. El contacto no fue reasignado desde este cierre.';
        } else {
            $this->markContacted->markFromTask($task);
        }

        return redirect()->route('tasks.show', $task)->with('success', $message);
    }

    // ── Flujo de llamada (multi-paso) ────────────────────────
    public function submitFlow(Request $request, Task $task, HubspotService $hubspot, HubspotDealOwnerSyncService $dealOwnerSync)
    {
        $this->authorizeAccess($task);

        if ($task->isAssignedToSystems()) {
            return back()->with('error', 'Esta es una tarea interna de Sistemas. Se completa desde su gestion interna, sin reasignar clientes.');
        }

        if ($task->isClosed()) {
            return back()->with('error', 'Esta tarea ya fue cerrada.');
        }

        $step             = $request->input('step');
        $taskWasCompleted = false;
        $shouldMarkContacted = false;
        $shouldReassignIneffectiveContact = false;

        DB::transaction(function () use ($request, $task, $step, &$taskWasCompleted, &$shouldMarkContacted, &$shouldReassignIneffectiveContact) {

            // ── PASO 1: ¿Llamada efectiva? ───────────────────
            if ($step === 'call_result') {
                $request->validate([
                    'call_effective'      => 'required|in:0,1',
                    'reason_no_effective' => 'required_if:call_effective,0|nullable|string|max:255',
                ]);

                $effective            = (bool) $request->call_effective;
                $task->status         = 'in_progress';
                $task->call_effective = $effective;

                if (! $effective) {
                    $task->reason_no_effective = $request->reason_no_effective;
                    $task->status              = 'completed';
                    $taskWasCompleted          = true;
                    $shouldReassignIneffectiveContact = false;
                }

                $task->save();
                return;
            }

            // ── PASO 2: ¿Hubo interés? ───────────────────────
            if ($step === 'interest') {
                $request->validate([
                    'interest_level'     => 'required|in:0,1',
                    'reason_no_interest' => 'required_if:interest_level,0|nullable|string|max:255',
                ]);

                $interest             = (bool) $request->interest_level;
                $task->interest_level = $interest;

                if (! $interest) {
                    $task->reason_no_interest = $request->reason_no_interest;
                    $task->status             = 'completed';
                    $taskWasCompleted         = true;
                    $shouldMarkContacted      = true;
                }

                $task->save();
                return;
            }

            // ── PASO 3: Producto + seguimiento ───────────────
            if ($step === 'product_followup') {
                $request->validate([
                    'product_of_interest' => 'required|string|max:255',
                    'follow_up_date'      => 'nullable|date|after:today',
                ]);

                $task->product_of_interest = $request->product_of_interest;
                $task->follow_up_date      = $request->follow_up_date;
                $task->status              = 'completed';
                $taskWasCompleted          = true;
                $shouldMarkContacted       = true;
                $task->save();

                if ($request->filled('follow_up_date')) {
                    $this->createFollowUp($task);
                }
            }
        });

        $message = 'Progreso guardado correctamente.';

        if ($shouldReassignIneffectiveContact) {
            $message = $this->reassignIneffectiveContact($task, $hubspot, $dealOwnerSync);
        }

        // Marcar contactado solo si hubo comunicacion efectiva.
        if ($taskWasCompleted && $shouldMarkContacted) {
            $this->markContacted->markFromTask($task);
        }

        return redirect()->route('tasks.show', $task)
                         ->with('success', $message);
    }

    // ── Sincronizar con HubSpot ──────────────────────────────
    public function syncContact(Task $task, \App\Services\HubspotService $hubspot)
    {
        if (! $task->contact) {
            return back()->with('error', 'Esta tarea no tiene contacto asociado.');
        }

        try {
            $data  = null;
            $props = [];

            if ($task->contact->hs_id) {
                try {
                    $data = $hubspot->getContactById($task->contact->hs_id);
                } catch (\Exception $e) {
                    $data = null;
                }
            }

            if (! $data && $task->contact->email) {
                $data = $hubspot->searchContactByEmail($task->contact->email);
            }

            if (! $data) {
                return back()->with('error', 'No se encontró el contacto en HubSpot ni por ID ni por email.');
            }

            $props  = $data['properties'] ?? [];
            $update = [];

            if (! empty($props['email']))       $update['email'] = $props['email'];
            if (! empty($props['phone']))       $update['phone'] = $props['phone'];
            if (! empty($props['mobilephone'])) $update['phone'] = $props['mobilephone'];

            if (! empty($update)) {
                $task->contact->update($update);
            }

            $tieneEmail    = ! empty($update['email']);
            $tieneTelefono = ! empty($update['phone']);

            if ($tieneEmail && $tieneTelefono) {
                return back()->with('success', '✅ Sincronizado correctamente. Se encontró email y teléfono.');
            }

            $encontrados = [];
            $faltantes   = [];

            $tieneEmail    ? $encontrados[] = 'email'    : $faltantes[] = 'email';
            $tieneTelefono ? $encontrados[] = 'teléfono' : $faltantes[] = 'teléfono';

            $parteExito = ! empty($encontrados)
                ? 'Se encontró: ' . implode(' y ', $encontrados) . '. '
                : '';

            $parteAviso = 'HubSpot no tiene registrado: ' . implode(' y ', $faltantes) . '.';

            return back()->with('error', $parteExito . $parteAviso);

        } catch (\Exception $e) {
            return back()->with('error', 'No se pudo sincronizar con HubSpot: ' . $e->getMessage());
        }
    }

    public function completeInternal(Task $task)
    {
        $this->authorizeAccess($task);

        if (! $task->isAssignedToSystems()) {
            abort(404);
        }

        return $this->completeInternalTask($task);
    }

    // ── Crear tarea de seguimiento ────────────────────────────
    private function createFollowUp(Task $original): void
    {
        if ($original->isAssignedToSystems()) {
            return;
        }

        $followDate    = Carbon::parse($original->follow_up_date);
        $existingCount = Task::query()
            ->where('user_id', $original->user_id)
            ->forDate($followDate)
            ->count();

        if (max(0, 10 - $existingCount) <= 0) {
            return;
        }

        $name = $original->contact?->name ?? 'Lead / General';

        Task::create([
            'user_id'            => $original->user_id,
            'contact_id'         => $original->contact_id ?? null,
            'title'              => "Seguimiento: {$name}",
            'description'        => "Seguimiento generado desde tarea #{$original->id}",
            'due_date'           => $followDate->toDateString(),
            'status'             => 'pending',
            'contact_methods'    => $original->contact_methods,
            'customer_responded' => $original->customer_responded,
            'sale_status'        => $original->sale_status,
            'sales_tags'         => $original->sales_tags,
            'created_by_user_id' => auth()->id(),
        ]);
    }

    private function reassignIneffectiveContact(Task $task, HubspotService $hubspot, HubspotDealOwnerSyncService $dealOwnerSync): string
    {
        if ($task->isAssignedToSystems()) {
            return 'Tarea interna de Sistemas completada. No se reasigno ningun cliente.';
        }

        $task->loadMissing('contact:id,name,email,hs_id,owner_id');
        $contact = $task->contact;

        if (! $contact) {
            return 'Progreso guardado. No se pudo reasignar porque la tarea no tiene contacto asociado.';
        }

        $advisor = $this->getNextAdvisorRoundRobin($contact->owner_id);

        if (! $advisor) {
            Log::warning('No hay asesor disponible para reasignar contacto con comunicacion no efectiva.', [
                'task_id' => $task->id,
                'client_id' => $contact->id,
                'current_owner_id' => $contact->owner_id,
            ]);

            return 'Progreso guardado. No se reasigno el contacto porque no hay asesores disponibles.';
        }

        $contact->update([
            'owner_id' => $advisor->id,
        ]);

        try {
            $hsContactId = $this->resolveHubspotContactId($hubspot, $contact);
            $updatedDeals = 0;

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
            } else {
                Log::warning('Contacto no encontrado en HubSpot al reasignar por llamada no efectiva.', [
                    'task_id' => $task->id,
                    'client_id' => $contact->id,
                    'email' => $contact->email,
                    'hs_id' => $contact->hs_id,
                    'new_owner_user_id' => $advisor->id,
                    'new_hubspot_owner_id' => $advisor->hs_owner_id,
                ]);

                return "Progreso guardado. Contacto reasignado a {$advisor->name} en la app, pero no se encontro en HubSpot.";
            }
        } catch (\Throwable $e) {
            Log::error('Error actualizando owner en HubSpot por llamada no efectiva.', [
                'task_id' => $task->id,
                'client_id' => $contact->id,
                'email' => $contact->email,
                'hs_id' => $contact->hs_id,
                'new_owner_user_id' => $advisor->id,
                'new_hubspot_owner_id' => $advisor->hs_owner_id,
                'error' => $e->getMessage(),
            ]);

            return "Progreso guardado. Contacto reasignado a {$advisor->name} en la app, pero HubSpot no se pudo actualizar.";
        }

        return "Progreso guardado. Contacto reasignado a {$advisor->name}. Negocios asociados actualizados: {$updatedDeals}.";
    }

    private function resolveHubspotContactId(HubspotService $hubspot, User $contact): ?string
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

    private function completeInternalTask(Task $task)
    {
        if ($task->isClosed()) {
            return back()->with('error', 'Esta tarea ya fue cerrada.');
        }

        $task->update([
            'status' => Task::STATUS_COMPLETED,
        ]);

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Tarea interna de Sistemas completada. No se reasigno ningun cliente.');
    }

    private function getNextAdvisorRoundRobin(?int $currentOwnerId = null): ?User
    {
        $advisors = User::query()
            ->join('hubspot_owner_user as hou', 'hou.user_id', '=', 'users.id')
            ->whereNotNull('hou.hubspot_owner_id')
            ->whereRaw("TRIM(hou.hubspot_owner_id) <> ''")
            ->where('users.exclude_from_task_assignment', false)
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

    // ── Guard de acceso ───────────────────────────────────────
    private function authorizeAccess(Task $task): void
    {
        if (! $task->isOwnedBy(auth()->id()) && ! auth()->user()->can('admin-tasks')) {
            abort(403, 'No tienes acceso a esta tarea.');
        }
    }
}

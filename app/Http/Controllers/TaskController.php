<?php
// app/Http/Controllers/TaskController.php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Services\HubspotService;
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
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : today();

        $tasks = Task::query()
            ->with('contact')
            ->where('user_id', auth()->id())
            ->forDate($date)
            ->orderByRaw("FIELD(status, 'pending','in_progress','completed','canceled')")
            ->get();

        $stats = [
            'total'       => $tasks->count(),
            'pending'     => $tasks->where('status', 'pending')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed'   => $tasks->where('status', 'completed')->count(),
            'canceled'    => $tasks->where('status', 'canceled')->count(),
        ];

        return view('tasks.index', compact('tasks', 'stats', 'date'));
    }

    // ── Ver detalle / flujo ──────────────────────────────────
    public function show(Task $task)
    {
        $this->authorizeAccess($task);

        return view('tasks.show', compact('task'));
    }

    // ── Flujo de llamada (multi-paso) ────────────────────────
    public function submitFlow(Request $request, Task $task, HubspotService $hubspot)
    {
        $this->authorizeAccess($task);

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
                    $shouldReassignIneffectiveContact = true;
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
            $message = $this->reassignIneffectiveContact($task, $hubspot);
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

    // ── Crear tarea de seguimiento ────────────────────────────
    private function createFollowUp(Task $original): void
    {
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
            'created_by_user_id' => auth()->id(),
        ]);
    }

    private function reassignIneffectiveContact(Task $task, HubspotService $hubspot): string
    {
        $task->loadMissing('contact:id,name,email,hs_id,owner_id');
        $contact = $task->contact;

        if (! $contact) {
            return 'Progreso guardado. No se pudo reasignar porque la tarea no tiene contacto asociado.';
        }

        $advisor = $this->getRandomAdvisorExcluding($contact->owner_id);

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

            if ($hsContactId) {
                $hubspot->updateContact($hsContactId, [
                    'hubspot_owner_id' => (string) $advisor->hs_owner_id,
                ]);
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

        return "Progreso guardado. Contacto reasignado a {$advisor->name}.";
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

    private function getRandomAdvisorExcluding(?int $currentOwnerId = null): ?User
    {
        return User::query()
            ->join('hubspot_owner_user as hou', 'hou.user_id', '=', 'users.id')
            ->whereNotNull('hou.hubspot_owner_id')
            ->whereRaw("TRIM(hou.hubspot_owner_id) <> ''")
            ->when($currentOwnerId, function ($query) use ($currentOwnerId) {
                $query->where('users.id', '!=', $currentOwnerId);
            })
            ->inRandomOrder()
            ->select('users.*', 'hou.hubspot_owner_id as hs_owner_id')
            ->first();
    }

    // ── Guard de acceso ───────────────────────────────────────
    private function authorizeAccess(Task $task): void
    {
        if (! $task->isOwnedBy(auth()->id()) && ! auth()->user()->can('admin-tasks')) {
            abort(403, 'No tienes acceso a esta tarea.');
        }
    }
}

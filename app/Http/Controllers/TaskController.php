<?php
// app/Http/Controllers/TaskController.php

namespace App\Http\Controllers;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
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
    public function submitFlow(Request $request, Task $task)
    {
        $this->authorizeAccess($task);

        if ($task->isClosed()) {
            return back()->with('error', 'Esta tarea ya fue cerrada.');
        }

        $step = $request->input('step');

        DB::transaction(function () use ($request, $task, $step) {

            // ── PASO 1: ¿Llamada efectiva? ───────────────────
            if ($step === 'call_result') {
                $request->validate([
                    'call_effective'       => 'required|in:0,1',
                    'reason_no_effective'  => 'required_if:call_effective,0|nullable|string|max:255',
                ]);

                $effective = (bool) $request->call_effective;

                $task->status         = 'in_progress';
                $task->call_effective = $effective;

                if (! $effective) {
                    $task->reason_no_effective = $request->reason_no_effective;
                    $task->status              = 'completed'; // cerrada sin interés
                }

                $task->save();
                return;
            }

            // ── PASO 2: ¿Hubo interés? ───────────────────────
            if ($step === 'interest') {
                $request->validate([
                    'interest_level'    => 'required|in:0,1',
                    'reason_no_interest'=> 'required_if:interest_level,0|nullable|string|max:255',
                ]);

                $interest = (bool) $request->interest_level;

                $task->interest_level = $interest;

                if (! $interest) {
                    $task->reason_no_interest = $request->reason_no_interest;
                    $task->status             = 'completed';
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
                $task->save();

                // Crear tarea de seguimiento si se indicó fecha
                if ($request->filled('follow_up_date')) {
                    $this->createFollowUp($task);
                }
            }
        });

        return redirect()->route('tasks.show', $task)
                         ->with('success', 'Progreso guardado correctamente.');
    }

    // ── Crear tarea de seguimiento ────────────────────────────
    private function createFollowUp(Task $original): void
    {
        $followDate = Carbon::parse($original->follow_up_date);

        // Cuántas tareas ya tiene ese día
        $existingCount = Task::query()
            ->where('user_id', $original->user_id)
            ->forDate($followDate)
            ->count();

        $base     = 10;
        $canAdd   = max(0, $base - $existingCount);

        if ($canAdd <= 0) {
            return; // Ya tiene 10 o más tareas ese día
        }

        Task::create([
            'user_id'            => $original->user_id,
            'contact_id'         => $original->contact_id,
            'title'              => "Seguimiento: {$original->contact->name}",
            'description'        => "Seguimiento generado desde tarea #{$original->id}",
            'due_date'           => $followDate->toDateString(),
            'status'             => 'pending',
            'created_by_user_id' => auth()->id(),
        ]);
    }

    // ── Guard de acceso ───────────────────────────────────────
    private function authorizeAccess(Task $task): void
    {
        if (! $task->isOwnedBy(auth()->id()) && ! auth()->user()->can('admin-tasks')) {
            abort(403, 'No tienes acceso a esta tarea.');
        }
    }
}

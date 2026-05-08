<?php
// app/Http/Controllers/AdminTaskController.php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class AdminTaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:administrador');
    }

    // ── Listado admin ────────────────────────────────────────
    public function table(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : today();

        $query = Task::query()
            ->with(['assignee', 'contact'])
            ->forDate($date);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tasks    = $query->orderBy('user_id')->paginate(30)->withQueryString();
        $advisors = User::whereDoesntHave('roles', function ($q) {
        $q->where('name', 'Cliente');
    })
    ->pluck('name', 'id');
        $stats    = $this->getDayStats($date);
        $chartData = $this->buildChartData($date);

        return view('tasks.admin.index', compact('tasks', 'date', 'advisors', 'stats', 'chartData'));
    }

    // ── Crear manual ─────────────────────────────────────────
    public function create()
    {
        $advisors = User::whereDoesntHave('roles', function ($q) {
        $q->where('name', 'Cliente');
    })
    ->pluck('name', 'id');
        $contacts = User::role('Cliente')->pluck('name', 'id');

        return view('tasks.admin.create', compact('advisors', 'contacts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id'    => 'required|exists:users,id',
            'contact_id' => 'nullable|exists:users,id',
            'title'      => 'required|string|max:255',
            'description'=> 'nullable|string',
            'due_date'   => 'required|date',
            'status'     => 'sometimes|in:pending,in_progress,completed,canceled',
            'contact_methods' => 'nullable|array',
            'contact_methods.*' => 'in:' . implode(',', array_keys(Task::contactMethodOptions())),
            'customer_responded' => 'nullable|in:0,1',
            'sale_status' => 'nullable|in:' . implode(',', array_keys(Task::saleStatusOptions())),
            'sales_tags' => 'nullable|array',
            'sales_tags.*' => 'in:' . implode(',', array_keys(Task::salesTagOptions())),
        ]);

        $data['created_by_user_id'] = auth()->id();
        $data['status']             = $data['status'] ?? 'pending';
        $data['contact_methods']    = array_values($data['contact_methods'] ?? []);
        $data['customer_responded'] = isset($data['customer_responded']) ? $data['customer_responded'] === '1' : null;
        $data['sales_tags']         = array_values($data['sales_tags'] ?? []);

        Task::create($data);

        return redirect()->route('tasks.admin.index')
                         ->with('success', 'Tarea creada correctamente.');
    }

    // ── Editar ────────────────────────────────────────────────
    public function edit(Task $task)
    {
        $advisors = User::whereDoesntHave('roles', function ($q) {
        $q->where('name', 'Cliente');
    })
    ->pluck('name', 'id');
        $contacts = User::role('Cliente')->pluck('name', 'id');

        return view('tasks.admin.edit', compact('task', 'advisors', 'contacts'));
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'user_id'    => 'sometimes|exists:users,id',
            'contact_id' => 'nullable|exists:users,id',
            'title'      => 'sometimes|string|max:255',
            'description'=> 'nullable|string',
            'due_date'   => 'sometimes|date',
            'status'     => 'sometimes|in:pending,in_progress,completed,canceled',
            'contact_methods' => 'nullable|array',
            'contact_methods.*' => 'in:' . implode(',', array_keys(Task::contactMethodOptions())),
            'customer_responded' => 'nullable|in:0,1',
            'sale_status' => 'nullable|in:' . implode(',', array_keys(Task::saleStatusOptions())),
            'sales_tags' => 'nullable|array',
            'sales_tags.*' => 'in:' . implode(',', array_keys(Task::salesTagOptions())),
        ]);

        $data['contact_methods'] = array_values($data['contact_methods'] ?? []);
        $data['customer_responded'] = isset($data['customer_responded']) ? $data['customer_responded'] === '1' : null;
        $data['sales_tags'] = array_values($data['sales_tags'] ?? []);

        $task->update($data);

        return redirect()->route('tasks.admin.index')
                         ->with('success', 'Tarea actualizada.');
    }

    // ── Eliminar ──────────────────────────────────────────────
    public function destroy(Task $task)
    {
        $task->delete();

        return back()->with('success', 'Tarea eliminada.');
    }

    // ── Resumen por asesor ────────────────────────────────────
    public function summary(Request $request)
    {
        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'))
            : today();

        $rows = DB::table('tasks')
            ->select('user_id', 'status', DB::raw('COUNT(*) as total'))
            ->whereDate('due_date', $date)
            ->groupBy('user_id', 'status')
            ->get()
            ->groupBy('user_id');

        $advisors = User::whereDoesntHave('roles', function ($q) {
        $q->where('name', 'Cliente');
    })
    ->pluck('name', 'id');
        $chartData = $this->buildChartData($date);

        return view('tasks.admin.summary', compact('rows', 'advisors', 'date', 'chartData'));
    }

    // ── Disparar generación manual desde UI ───────────────────
    public function generateDaily(Request $request)
    {
        $date = $request->input('date', today()->toDateString());

        Artisan::call('tasks:generate-daily', ['--date' => $date]);

        $output = Artisan::output();

        return back()->with('success', "<pre class='mb-0'>{$output}</pre>");
    }

    // ── Datos para el gráfico (sin librerías) ─────────────────
    private function buildChartData(Carbon $date): array
    {
        $rows = DB::table('tasks')
            ->select('user_id', 'status', DB::raw('COUNT(*) as total'))
            ->whereDate('due_date', $date)
            ->groupBy('user_id', 'status')
            ->get()
            ->groupBy('user_id');

        $advisors = User::whereDoesntHave('roles', function ($q) {
        $q->where('name', 'Cliente');
    })
    ->pluck('name', 'id');

        $labels   = [];
        $pending  = [];
        $progress = [];
        $done     = [];
        $canceled = [];

        foreach ($rows as $userId => $statuses) {
            $labels[]   = $advisors[$userId] ?? "Asesor #{$userId}";
            $byStatus   = collect($statuses)->pluck('total', 'status');
            $pending[]  = (int) ($byStatus['pending']     ?? 0);
            $progress[] = (int) ($byStatus['in_progress'] ?? 0);
            $done[]     = (int) ($byStatus['completed']   ?? 0);
            $canceled[] = (int) ($byStatus['canceled']    ?? 0);
        }

        return compact('labels', 'pending', 'progress', 'done', 'canceled');
    }

    private function getDayStats(Carbon $date): array
    {
        return DB::table('tasks')
            ->select('status', DB::raw('COUNT(*) as total'))
            ->whereDate('due_date', $date)
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}

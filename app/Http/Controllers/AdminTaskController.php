<?php
// app/Http/Controllers/AdminTaskController.php

namespace App\Http\Controllers;

use App\Exports\TaskStatusReportExport;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

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

        $filteredTaskCount = (clone $query)->count();
        $tasks    = $query->orderBy('user_id')->paginate(30)->withQueryString();
        $advisors = User::whereDoesntHave('roles', function ($q) {
        $q->where('name', 'Cliente');
    })
    ->pluck('name', 'id');
        $stats    = $this->getDayStats($date);
        $chartData = $this->buildChartData($date);

        return view('tasks.admin.index', compact('tasks', 'date', 'advisors', 'stats', 'chartData', 'filteredTaskCount'));
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

    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(array_keys($this->taskStatusLabels()))],
        ]);

        $deleted = Task::query()
            ->whereKey($data['task_ids'])
            ->delete();

        return redirect()
            ->route('tasks.admin.index', $this->taskIndexFilters($data))
            ->with('success', "{$deleted} tarea(s) eliminada(s).");
    }

    public function bulkDestroyFiltered(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(array_keys($this->taskStatusLabels()))],
        ]);

        $date = Carbon::parse($data['date']);
        $query = Task::query()->forDate($date);

        if (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $deleted = $query->delete();

        return redirect()
            ->route('tasks.admin.index', $this->taskIndexFilters($data))
            ->with('success', "{$deleted} tarea(s) eliminada(s) segun los filtros.");
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

    public function forceDailyWorkflow(Request $request)
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'per' => ['nullable', 'integer', 'min:1', 'max:100'],
            'force_limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }

        $lock = Cache::lock('tasks-daily-workflow-admin', 1800);

        if (! $lock->get()) {
            return back()->with('error', 'El workflow diario ya esta en ejecucion.');
        }

        try {
            $options = [
                '--force-reassign' => true,
                '--per' => (int) ($data['per'] ?? 10),
                '--force-limit' => (int) ($data['force_limit'] ?? 200),
            ];

            if (! empty($data['date'])) {
                $options['--date'] = $data['date'];
            }

            if ($request->boolean('dry_run')) {
                $options['--dry-run'] = true;
            }

            $exitCode = Artisan::call('tasks:daily-workflow', $options);
            $output = Artisan::output();
            $message = "<pre class='mb-0'>" . e($output) . '</pre>';

            Log::info('Workflow diario forzado desde panel admin', [
                'admin_user_id' => auth()->id(),
                'exit_code' => $exitCode,
                'options' => $options,
            ]);

            return back()->with($exitCode === 0 ? 'success' : 'error', $message);
        } finally {
            optional($lock)->release();
        }
    }

    public function reports(Request $request)
    {
        $filters = $this->normalizeTaskReportFilters($request);
        [$startDate, $endDate, $periodLabel] = $this->resolveTaskReportPeriod($filters);

        $advisors = $this->advisorOptions();
        $statusLabels = $this->taskStatusLabels();
        $stats = $this->buildTaskReportStats($startDate, $endDate, $filters);

        return view('tasks.admin.reports', compact(
            'advisors',
            'endDate',
            'filters',
            'periodLabel',
            'startDate',
            'stats',
            'statusLabels'
        ));
    }

    public function exportReport(Request $request)
    {
        $filters = $this->validateTaskReportFilters($request);
        [$startDate, $endDate, $periodLabel] = $this->resolveTaskReportPeriod($filters);

        $filename = sprintf(
            'reporte_tareas_%s_%s_%s.xlsx',
            $filters['period'],
            $startDate->format('Ymd'),
            now()->format('His')
        );

        return Excel::download(
            new TaskStatusReportExport($startDate, $endDate, $filters, $periodLabel),
            $filename
        );
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

    private function advisorOptions()
    {
        return User::whereDoesntHave('roles', function ($q) {
            $q->where('name', 'Cliente');
        })
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    private function taskStatusLabels(): array
    {
        return [
            Task::STATUS_PENDING => 'Pendiente',
            Task::STATUS_IN_PROGRESS => 'En curso',
            Task::STATUS_COMPLETED => 'Completada',
            Task::STATUS_CANCELED => 'Cancelada',
        ];
    }

    private function normalizeTaskReportFilters(Request $request): array
    {
        $period = $request->input('period', 'daily');

        if (! in_array($period, ['daily', 'monthly', 'annual'], true)) {
            $period = 'daily';
        }

        return [
            'period' => $period,
            'date' => $request->input('date', today()->toDateString()),
            'month' => $request->input('month', today()->format('Y-m')),
            'year' => $request->input('year', today()->format('Y')),
            'user_id' => $request->input('user_id'),
            'status' => $request->input('status'),
        ];
    }

    private function validateTaskReportFilters(Request $request): array
    {
        $data = $request->validate([
            'period' => ['required', Rule::in(['daily', 'monthly', 'annual'])],
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'regex:/^\d{4}-\d{2}$/'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:' . ((int) date('Y') + 1)],
            'user_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', Rule::in(array_keys($this->taskStatusLabels()))],
        ]);

        $data['date'] = $data['date'] ?? today()->toDateString();
        $data['month'] = $data['month'] ?? today()->format('Y-m');
        $data['year'] = $data['year'] ?? today()->format('Y');
        $data['user_id'] = $data['user_id'] ?? null;
        $data['status'] = $data['status'] ?? null;

        return $data;
    }

    private function resolveTaskReportPeriod(array $filters): array
    {
        if ($filters['period'] === 'monthly') {
            $date = Carbon::createFromFormat('Y-m-d', $filters['month'] . '-01')->startOfMonth();

            return [
                $date->copy(),
                $date->copy()->endOfMonth(),
                'Mensual - ' . $date->translatedFormat('F Y'),
            ];
        }

        if ($filters['period'] === 'annual') {
            $date = Carbon::createFromDate((int) $filters['year'], 1, 1)->startOfYear();

            return [
                $date->copy(),
                $date->copy()->endOfYear(),
                'Anual - ' . $date->format('Y'),
            ];
        }

        $date = Carbon::parse($filters['date'])->startOfDay();

        return [
            $date->copy(),
            $date->copy()->endOfDay(),
            'Diario - ' . $date->format('d/m/Y'),
        ];
    }

    private function buildTaskReportStats(Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $query = DB::table('tasks')
            ->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $overdue = (clone $query)
            ->whereDate('due_date', '<', today())
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->count();

        return [
            'total' => array_sum($byStatus),
            'pending' => (int) ($byStatus[Task::STATUS_PENDING] ?? 0),
            'in_progress' => (int) ($byStatus[Task::STATUS_IN_PROGRESS] ?? 0),
            'completed' => (int) ($byStatus[Task::STATUS_COMPLETED] ?? 0),
            'canceled' => (int) ($byStatus[Task::STATUS_CANCELED] ?? 0),
            'overdue' => $overdue,
        ];
    }

    private function taskIndexFilters(array $data): array
    {
        return array_filter([
            'date' => $data['date'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'status' => $data['status'] ?? null,
        ], fn ($value) => filled($value));
    }
}

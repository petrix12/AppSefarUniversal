<?php

namespace App\Exports;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TaskStatusReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Carbon $startDate,
        private readonly Carbon $endDate,
        private readonly array $filters,
        private readonly string $periodLabel
    ) {
    }

    public function sheets(): array
    {
        return [
            new TaskReportSummarySheet($this->startDate, $this->endDate, $this->filters, $this->periodLabel),
            new TaskReportAdvisorSheet($this->startDate, $this->endDate, $this->filters),
            new TaskReportDateSheet($this->startDate, $this->endDate, $this->filters),
            new TaskReportDetailSheet($this->startDate, $this->endDate, $this->filters),
        ];
    }
}

class TaskReportSummarySheet implements FromArray, WithTitle, ShouldAutoSize, WithStyles
{
    use TaskReportHelpers;

    public function __construct(
        private readonly Carbon $startDate,
        private readonly Carbon $endDate,
        private readonly array $filters,
        private readonly string $periodLabel
    ) {
    }

    public function title(): string
    {
        return 'Resumen';
    }

    public function array(): array
    {
        $query = $this->baseTableQuery($this->startDate, $this->endDate, $this->filters);
        $byStatus = (clone $query)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $open = (int) ($byStatus[Task::STATUS_PENDING] ?? 0)
            + (int) ($byStatus[Task::STATUS_IN_PROGRESS] ?? 0);
        $closed = (int) ($byStatus[Task::STATUS_COMPLETED] ?? 0)
            + (int) ($byStatus[Task::STATUS_CANCELED] ?? 0);
        $overdue = (clone $query)
            ->whereDate('due_date', '<', today())
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS])
            ->count();

        return [
            ['Reporte de tareas'],
            ['Periodo', $this->periodLabel],
            ['Rango', $this->startDate->format('d/m/Y') . ' - ' . $this->endDate->format('d/m/Y')],
            ['Generado', now()->format('d/m/Y H:i:s')],
            ['Asesor', $this->filterLabel('user_id', 'Todos')],
            ['Estado', $this->statusLabel($this->filters['status'] ?? null) ?? 'Todos'],
            [],
            ['Indicador', 'Total'],
            ['Total de tareas', array_sum($byStatus)],
            ['Abiertas', $open],
            ['Cerradas', $closed],
            ['Vencidas abiertas', $overdue],
            ['Pendientes', (int) ($byStatus[Task::STATUS_PENDING] ?? 0)],
            ['En curso', (int) ($byStatus[Task::STATUS_IN_PROGRESS] ?? 0)],
            ['Completadas', (int) ($byStatus[Task::STATUS_COMPLETED] ?? 0)],
            ['Canceladas', (int) ($byStatus[Task::STATUS_CANCELED] ?? 0)],
        ];
    }
}

class TaskReportAdvisorSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    use TaskReportHelpers;

    public function __construct(
        private readonly Carbon $startDate,
        private readonly Carbon $endDate,
        private readonly array $filters
    ) {
    }

    public function title(): string
    {
        return 'Por asesor';
    }

    public function headings(): array
    {
        return [
            'Asesor ID',
            'Asesor',
            'Total',
            'Pendientes',
            'En curso',
            'Completadas',
            'Canceladas',
            'Vencidas abiertas',
        ];
    }

    public function array(): array
    {
        $query = $this->baseTableQuery($this->startDate, $this->endDate, $this->filters);

        $rows = (clone $query)
            ->leftJoin('users as advisors', 'advisors.id', '=', 'tasks.user_id')
            ->select(
                'tasks.user_id',
                DB::raw("COALESCE(advisors.name, CONCAT('Asesor #', tasks.user_id)) as advisor_name"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tasks.status = 'pending' THEN 1 ELSE 0 END) as pending_total"),
                DB::raw("SUM(CASE WHEN tasks.status = 'in_progress' THEN 1 ELSE 0 END) as progress_total"),
                DB::raw("SUM(CASE WHEN tasks.status = 'completed' THEN 1 ELSE 0 END) as completed_total"),
                DB::raw("SUM(CASE WHEN tasks.status = 'canceled' THEN 1 ELSE 0 END) as canceled_total"),
                DB::raw("SUM(CASE WHEN tasks.due_date < CURDATE() AND tasks.status IN ('pending','in_progress') THEN 1 ELSE 0 END) as overdue_total")
            )
            ->groupBy('tasks.user_id', 'advisors.name')
            ->orderBy('advisor_name')
            ->get();

        return $rows->map(fn ($row) => [
            $row->user_id,
            $row->advisor_name,
            (int) $row->total,
            (int) $row->pending_total,
            (int) $row->progress_total,
            (int) $row->completed_total,
            (int) $row->canceled_total,
            (int) $row->overdue_total,
        ])->all();
    }
}

class TaskReportDateSheet implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithStyles
{
    use TaskReportHelpers;

    public function __construct(
        private readonly Carbon $startDate,
        private readonly Carbon $endDate,
        private readonly array $filters
    ) {
    }

    public function title(): string
    {
        return 'Por fecha';
    }

    public function headings(): array
    {
        return [
            'Fecha',
            'Total',
            'Pendientes',
            'En curso',
            'Completadas',
            'Canceladas',
            'Vencidas abiertas',
        ];
    }

    public function array(): array
    {
        $query = $this->baseTableQuery($this->startDate, $this->endDate, $this->filters);

        $rows = (clone $query)
            ->select(
                'tasks.due_date',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN tasks.status = 'pending' THEN 1 ELSE 0 END) as pending_total"),
                DB::raw("SUM(CASE WHEN tasks.status = 'in_progress' THEN 1 ELSE 0 END) as progress_total"),
                DB::raw("SUM(CASE WHEN tasks.status = 'completed' THEN 1 ELSE 0 END) as completed_total"),
                DB::raw("SUM(CASE WHEN tasks.status = 'canceled' THEN 1 ELSE 0 END) as canceled_total"),
                DB::raw("SUM(CASE WHEN tasks.due_date < CURDATE() AND tasks.status IN ('pending','in_progress') THEN 1 ELSE 0 END) as overdue_total")
            )
            ->groupBy('tasks.due_date')
            ->orderBy('tasks.due_date')
            ->get();

        return $rows->map(fn ($row) => [
            Carbon::parse($row->due_date)->format('d/m/Y'),
            (int) $row->total,
            (int) $row->pending_total,
            (int) $row->progress_total,
            (int) $row->completed_total,
            (int) $row->canceled_total,
            (int) $row->overdue_total,
        ])->all();
    }
}

class TaskReportDetailSheet implements FromQuery, WithHeadings, WithMapping, WithTitle, ShouldAutoSize, WithStyles
{
    use TaskReportHelpers;

    public function __construct(
        private readonly Carbon $startDate,
        private readonly Carbon $endDate,
        private readonly array $filters
    ) {
    }

    public function title(): string
    {
        return 'Detalle';
    }

    public function query(): Builder
    {
        $query = Task::query()
            ->with([
                'assignee:id,name,email',
                'contact:id,name,email,phone,passport',
                'creator:id,name,email',
            ])
            ->whereBetween('due_date', [$this->startDate->toDateString(), $this->endDate->toDateString()]);

        if (! empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
        }

        if (! empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        return $query
            ->orderBy('due_date')
            ->orderBy('user_id')
            ->orderBy('id');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha limite',
            'Estado',
            'Vencida',
            'Dias vencida',
            'Asesor',
            'Email asesor',
            'Cliente',
            'Email cliente',
            'Telefono cliente',
            'Pasaporte cliente',
            'Titulo',
            'Descripcion',
            'Vias de contacto',
            'Cliente respondio',
            'Gestion efectiva',
            'Motivo no efectivo',
            'Mostro interes',
            'Motivo sin interes',
            'Producto de interes',
            'Estatus de venta',
            'Etiquetas de venta',
            'Fecha seguimiento',
            'Creada por',
            'Creada',
            'Actualizada',
        ];
    }

    public function map($task): array
    {
        $isOverdue = $task->due_date
            && $task->due_date->lt(today())
            && in_array($task->status, [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS], true);

        return [
            $task->id,
            $this->dateLabel($task->due_date),
            $this->statusLabel($task->status) ?? $task->status,
            $isOverdue ? 'Si' : 'No',
            $isOverdue ? $task->due_date->diffInDays(today()) : 0,
            $task->assignee?->name,
            $task->assignee?->email,
            $task->contact?->name,
            $task->contact?->email,
            $task->contact?->phone,
            $task->contact?->passport,
            $task->title,
            $task->description,
            implode(', ', $task->contactMethodLabels()),
            $this->boolLabel($task->customer_responded, 'Si', 'No', 'Sin registrar'),
            $this->boolLabel($task->call_effective, 'Si', 'No', 'Sin registrar'),
            $task->reason_no_effective,
            $this->boolLabel($task->interest_level, 'Si', 'No', 'Sin registrar'),
            $task->reason_no_interest,
            $task->product_of_interest,
            $task->saleStatusLabel(),
            $this->salesTagsLabel($task->sales_tags ?? []),
            $this->dateLabel($task->follow_up_date),
            $task->creator?->name,
            $this->dateTimeLabel($task->created_at),
            $this->dateTimeLabel($task->updated_at),
        ];
    }
}

trait TaskReportHelpers
{
    protected function baseTableQuery(Carbon $startDate, Carbon $endDate, array $filters)
    {
        $query = DB::table('tasks')
            ->whereBetween('tasks.due_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if (! empty($filters['user_id'])) {
            $query->where('tasks.user_id', $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('tasks.status', $filters['status']);
        }

        return $query;
    }

    protected function statusLabel(?string $status): ?string
    {
        return [
            Task::STATUS_PENDING => 'Pendiente',
            Task::STATUS_IN_PROGRESS => 'En curso',
            Task::STATUS_COMPLETED => 'Completada',
            Task::STATUS_CANCELED => 'Cancelada',
        ][$status] ?? null;
    }

    protected function filterLabel(string $field, string $fallback): string
    {
        if ($field !== 'user_id' || empty($this->filters['user_id'])) {
            return $fallback;
        }

        return DB::table('users')
            ->where('id', $this->filters['user_id'])
            ->value('name') ?? $fallback;
    }

    protected function boolLabel($value, string $true, string $false, string $empty): string
    {
        if ($value === null) {
            return $empty;
        }

        return (bool) $value ? $true : $false;
    }

    protected function salesTagsLabel(array $tags): string
    {
        $options = Task::salesTagOptions();

        return collect($tags)
            ->map(fn ($tag) => $options[$tag]['label'] ?? $tag)
            ->filter()
            ->implode(', ');
    }

    protected function dateLabel($date): ?string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y') : null;
    }

    protected function dateTimeLabel($date): ?string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y H:i') : null;
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

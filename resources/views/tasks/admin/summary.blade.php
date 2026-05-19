{{-- resources/views/tasks/admin/summary.blade.php --}}
@extends('adminlte::page')

@section('title', 'Resumen de Tareas')

@section('content_header')
    <h1>
        <i class="fas fa-chart-bar mr-2 text-primary"></i>
        Resumen de Tareas
        <small class="text-muted">{{ $date->format('d/m/Y') }}</small>
    </h1>
@stop

@section('content')

    {{-- Selector de fecha --}}
    <div class="card card-outline card-secondary mb-3">
        <div class="card-body py-2">
            <form method="GET" class="form-inline">
                <label class="mr-2 font-weight-bold">Fecha:</label>
                <input type="date" name="date" class="form-control form-control-sm mr-2"
                       value="{{ $date->toDateString() }}">
                <button class="btn btn-sm btn-primary">
                    <i class="fas fa-search mr-1"></i>Ver
                </button>
                <a href="{{ route('tasks.admin.index') }}"
                   class="btn btn-sm btn-outline-secondary ml-2">
                    <i class="fas fa-list mr-1"></i>Ver tareas
                </a>
            </form>
        </div>
    </div>

    {{-- Gráfico --}}
    <div class="card card-outline card-dark mb-4">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-chart-bar mr-1"></i>
                Distribución por asesor
            </h3>
        </div>
        <div class="card-body">
            @if(empty($chartData['labels']))
                <p class="text-muted text-center py-3">
                    Sin datos para esta fecha.
                </p>
            @else
                <div class="tasks-chart-wrap">
                    <canvas id="summaryChart"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Tabla de resumen por asesor --}}
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-users mr-1"></i>
                Detalle por asesor
            </h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-bordered table-hover mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>Asesor</th>
                        <th class="text-center text-warning">Pendientes</th>
                        <th class="text-center text-primary">En curso</th>
                        <th class="text-center text-success">Completadas</th>
                        <th class="text-center text-danger">Canceladas</th>
                        <th class="text-center">Total</th>
                        <th class="text-center">% Cierre</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $advisorId => $statuses)
                        @php
                            $byStatus  = collect($statuses)->pluck('total', 'status');
                            $pending   = (int)($byStatus['pending']     ?? 0);
                            $inProg    = (int)($byStatus['in_progress'] ?? 0);
                            $completed = (int)($byStatus['completed']   ?? 0);
                            $canceled  = (int)($byStatus['canceled']    ?? 0);
                            $total     = $pending + $inProg + $completed + $canceled;
                            $pct       = $total > 0 ? round(($completed / $total) * 100) : 0;
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $advisors[$advisorId] ?? "Asesor #{$advisorId}" }}</strong>
                            </td>
                            <td class="text-center">
                                @if($pending > 0)
                                    <span class="badge badge-warning">{{ $pending }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($inProg > 0)
                                    <span class="badge badge-primary">{{ $inProg }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($completed > 0)
                                    <span class="badge badge-success">{{ $completed }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($canceled > 0)
                                    <span class="badge badge-danger">{{ $canceled }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-center font-weight-bold">{{ $total }}</td>
                            <td class="text-center">
                                <div class="progress" style="height: 18px;">
                                    <div class="progress-bar bg-{{ $pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger') }}"
                                         style="width: {{ $pct }}%">
                                        {{ $pct }}%
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                Sin datos para esta fecha.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                    @php
                        $totals = $rows->flatMap(fn($s) => $s)->groupBy('status')
                            ->map(fn($g) => $g->sum('total'));
                        $grandTotal = $totals->sum();
                        $grandPct   = $grandTotal > 0
                            ? round((($totals['completed'] ?? 0) / $grandTotal) * 100)
                            : 0;
                    @endphp
                    <tfoot class="thead-light">
                        <tr class="font-weight-bold">
                            <td>TOTAL</td>
                            <td class="text-center">{{ $totals['pending']     ?? 0 }}</td>
                            <td class="text-center">{{ $totals['in_progress'] ?? 0 }}</td>
                            <td class="text-center">{{ $totals['completed']   ?? 0 }}</td>
                            <td class="text-center">{{ $totals['canceled']    ?? 0 }}</td>
                            <td class="text-center">{{ $grandTotal }}</td>
                            <td class="text-center">{{ $grandPct }}%</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .tasks-chart-wrap {
            position: relative;
            width: 100%;
            height: 320px;
            min-height: 320px;
            max-height: 320px;
            overflow: hidden;
        }

        .tasks-chart-wrap canvas {
            display: block;
            width: 100% !important;
            height: 100% !important;
        }

        @media (max-width: 767.98px) {
            .tasks-chart-wrap {
                height: 280px;
                min-height: 280px;
                max-height: 280px;
            }
        }
    </style>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
@if(!empty($chartData['labels']))
    new Chart(document.getElementById('summaryChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: @json($chartData['labels']),
            datasets: [
                {
                    label: 'Pendientes',
                    data: @json($chartData['pending']),
                    backgroundColor: 'rgba(255, 193, 7, 0.85)',
                    borderColor: 'rgba(255, 193, 7, 1)',
                    borderWidth: 1,
                },
                {
                    label: 'En curso',
                    data: @json($chartData['progress']),
                    backgroundColor: 'rgba(0, 123, 255, 0.85)',
                    borderColor: 'rgba(0, 123, 255, 1)',
                    borderWidth: 1,
                },
                {
                    label: 'Completadas',
                    data: @json($chartData['done']),
                    backgroundColor: 'rgba(40, 167, 69, 0.85)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1,
                },
                {
                    label: 'Canceladas',
                    data: @json($chartData['canceled']),
                    backgroundColor: 'rgba(220, 53, 69, 0.85)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 200,
            plugins: {
                legend: { position: 'top' },
                tooltip: {
                    callbacks: {
                        footer: (items) => {
                            const total = items.reduce((s, i) => s + i.parsed.y, 0);
                            return `Total: ${total}`;
                        }
                    }
                }
            },
            scales: {
                x: { stacked: true },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                },
            },
        },
    });
@endif
</script>
@stop

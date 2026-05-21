@extends('adminlte::page')

@section('title', 'Panel Estadistico')
@section('plugins.Chartjs', true)

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="m-0">
                <i class="fas fa-chart-line mr-2 text-primary"></i>
                Panel Estadistico
            </h1>
            <small class="text-muted">Registros, pagos, tareas y ventas separados por seccion</small>
        </div>

        <a href="{{ route('diarioindex') }}" class="btn btn-sm btn-outline-secondary mt-3 mt-md-0">
            <i class="fas fa-file mr-1"></i>Reporte diario
        </a>
    </div>
@stop

@section('content')
    <div class="dashboard-page">
        <section class="dashboard-section">
            <div class="card card-outline card-primary">
                <div class="card-header dashboard-section-header">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="fas fa-users mr-1"></i>Registros y pagos
                        </h3>
                        <small class="text-muted">{{ $start->format('d/m/Y') }} - {{ $end->format('d/m/Y') }}</small>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('reportes.dashboard') }}" class="dashboard-filter">
                        <input type="hidden" name="task_start" value="{{ $taskFilters['start'] }}">
                        <input type="hidden" name="task_end" value="{{ $taskFilters['end'] }}">
                        <input type="hidden" name="task_user_id" value="{{ $taskFilters['user_id'] }}">
                        <input type="hidden" name="task_status" value="{{ $taskFilters['status'] }}">
                        <input type="hidden" name="pipeline_month" value="{{ $pipelineMonth }}">
                        <input type="hidden" name="pipeline_user_id" value="{{ $pipelineUserId }}">

                        <label class="dashboard-field">
                            <span>Mes</span>
                            <input type="month" name="registration_month" value="{{ $month }}" class="form-control">
                        </label>

                        <button class="btn btn-primary">
                            <i class="fas fa-filter mr-1"></i>Filtrar registros
                        </button>
                    </form>
                </div>
            </div>

            <div class="row">
                @php
                    $kpiCards = [
                        ['label' => 'Registros del mes', 'value' => number_format($kpis['registered_month']), 'color' => 'info', 'icon' => 'user-plus'],
                        ['label' => 'Pagaron registro', 'value' => number_format($kpis['paid_registration_month']), 'color' => 'success', 'icon' => 'credit-card'],
                        ['label' => 'Conversion x10', 'value' => number_format($kpis['conversion_per_ten'], 2), 'color' => 'primary', 'icon' => 'percentage'],
                        ['label' => 'Monto registro', 'value' => number_format($kpis['registration_payment_amount'], 2) . ' EUR', 'color' => 'teal', 'icon' => 'euro-sign'],
                    ];
                @endphp

                @foreach($kpiCards as $card)
                    <div class="col-12 col-sm-6 col-xl-3">
                        <div class="small-box bg-{{ $card['color'] }}">
                            <div class="inner">
                                <h3>{{ $card['value'] }}</h3>
                                <p>{{ $card['label'] }}</p>
                            </div>
                            <div class="icon"><i class="fas fa-{{ $card['icon'] }}"></i></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card card-outline card-primary analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Registros vs pagos de registro</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="registrationsPaymentsChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-outline card-success analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Conversion diaria x10</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="conversionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card card-outline card-info analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Acumulado del mes</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="cumulativeChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-outline card-secondary analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Ingresos por servicio</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="serviceRevenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="card card-outline card-warning">
                <div class="card-header dashboard-section-header">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="fas fa-tasks mr-1"></i>Tareas
                        </h3>
                        <small class="text-muted">{{ $taskStart->format('d/m/Y') }} - {{ $taskEnd->format('d/m/Y') }}</small>
                    </div>
                    <a href="{{ route('tasks.admin.reports', [
                        'period' => 'daily',
                        'date' => $taskStart->toDateString(),
                        'user_id' => $taskFilters['user_id'],
                        'status' => $taskFilters['status'],
                    ]) }}" class="btn btn-sm btn-outline-success">
                        <i class="fas fa-file-excel mr-1"></i>Reporte detallado
                    </a>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('reportes.dashboard') }}" class="dashboard-filter dashboard-filter-wide">
                        <input type="hidden" name="registration_month" value="{{ $month }}">
                        <input type="hidden" name="pipeline_month" value="{{ $pipelineMonth }}">
                        <input type="hidden" name="pipeline_user_id" value="{{ $pipelineUserId }}">

                        <label class="dashboard-field">
                            <span>Desde</span>
                            <input type="date" name="task_start" value="{{ $taskFilters['start'] }}" class="form-control">
                        </label>

                        <label class="dashboard-field">
                            <span>Hasta</span>
                            <input type="date" name="task_end" value="{{ $taskFilters['end'] }}" class="form-control">
                        </label>

                        <label class="dashboard-field">
                            <span>Asesor</span>
                            <select name="task_user_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach($advisors as $id => $name)
                                    <option value="{{ $id }}" {{ (string) $taskFilters['user_id'] === (string) $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="dashboard-field">
                            <span>Estatus</span>
                            <select name="task_status" class="form-control">
                                <option value="">Todos</option>
                                @foreach($taskStatusLabels as $status => $label)
                                    <option value="{{ $status }}" {{ $taskFilters['status'] === $status ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <button class="btn btn-warning">
                            <i class="fas fa-filter mr-1"></i>Filtrar tareas
                        </button>
                    </form>
                </div>
            </div>

            <div class="row">
                @php
                    $taskCards = [
                        ['label' => 'Total', 'value' => $taskMetrics['total'], 'color' => 'dark', 'icon' => 'clipboard-list'],
                        ['label' => 'Pendientes', 'value' => $taskMetrics['pending'], 'color' => 'warning', 'icon' => 'clock'],
                        ['label' => 'En curso', 'value' => $taskMetrics['in_progress'], 'color' => 'primary', 'icon' => 'spinner'],
                        ['label' => 'Completadas', 'value' => $taskMetrics['completed'], 'color' => 'success', 'icon' => 'check'],
                        ['label' => 'Vencidas abiertas', 'value' => $taskMetrics['overdue'], 'color' => 'danger', 'icon' => 'exclamation-circle'],
                        ['label' => 'Respondieron', 'value' => $taskMetrics['responded'], 'color' => 'info', 'icon' => 'comment-dots'],
                    ];
                @endphp

                @foreach($taskCards as $card)
                    <div class="col-12 col-sm-6 col-xl-2">
                        <div class="info-box">
                            <span class="info-box-icon bg-{{ $card['color'] }}"><i class="fas fa-{{ $card['icon'] }}"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ $card['label'] }}</span>
                                <span class="info-box-number">{{ number_format($card['value']) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card card-outline card-warning analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Tareas por fecha</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="tasksByDateChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card card-outline card-info analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Tareas por estatus</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="taskStatusChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card card-outline card-secondary analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Tareas por asesor</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="taskAdvisorChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">Ultimas tareas del filtro</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Asesor</th>
                                    <th>Cliente</th>
                                    <th>Titulo</th>
                                    <th>Estatus</th>
                                    <th style="width:120px">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $taskColorMap = [
                                        'pending' => 'warning',
                                        'in_progress' => 'primary',
                                        'completed' => 'success',
                                        'canceled' => 'danger',
                                    ];
                                @endphp
                                @forelse($recentTasks as $task)
                                    <tr>
                                        <td>{{ $task->id }}</td>
                                        <td>{{ $task->due_date?->format('d/m/Y') ?? '-' }}</td>
                                        <td>{{ $task->assignee?->name ?? '-' }}</td>
                                        <td>
                                            {{ $task->contact?->name ?? '-' }}
                                            @if($task->contact?->passport)
                                                <br><small class="text-muted">{{ $task->contact->passport }}</small>
                                            @endif
                                        </td>
                                        <td>{{ \Illuminate\Support\Str::limit($task->title, 55) }}</td>
                                        <td>
                                            <span class="badge badge-{{ $taskColorMap[$task->status] ?? 'secondary' }}">
                                                {{ $taskStatusLabels[$task->status] ?? $task->status }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('tasks.admin.edit', $task) }}" class="btn btn-sm btn-outline-primary">
                                                Ver
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No hay tareas para este filtro.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="card card-outline card-success">
                <div class="card-header dashboard-section-header">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="fas fa-funnel-dollar mr-1"></i>Pipeline comercial
                        </h3>
                        <small class="text-muted">{{ $pipelineStart->format('d/m/Y') }} - {{ $pipelineEnd->format('d/m/Y') }}</small>
                    </div>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('reportes.dashboard') }}" class="dashboard-filter">
                        <input type="hidden" name="registration_month" value="{{ $month }}">
                        <input type="hidden" name="task_start" value="{{ $taskFilters['start'] }}">
                        <input type="hidden" name="task_end" value="{{ $taskFilters['end'] }}">
                        <input type="hidden" name="task_user_id" value="{{ $taskFilters['user_id'] }}">
                        <input type="hidden" name="task_status" value="{{ $taskFilters['status'] }}">

                        <label class="dashboard-field">
                            <span>Mes</span>
                            <input type="month" name="pipeline_month" value="{{ $pipelineMonth }}" class="form-control">
                        </label>

                        <label class="dashboard-field">
                            <span>Asesor</span>
                            <select name="pipeline_user_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach($advisors as $id => $name)
                                    <option value="{{ $id }}" {{ (string) $pipelineUserId === (string) $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <button class="btn btn-success">
                            <i class="fas fa-filter mr-1"></i>Filtrar pipeline
                        </button>
                    </form>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-7">
                    <div class="card card-outline card-success analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Pipeline comercial</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="salesPipelineChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="card card-outline card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Resumen del pipeline</h3>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th>Estatus</th>
                                            <th class="text-right">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($salesPipeline as $row)
                                            <tr>
                                                <td>{{ $row['label'] }}</td>
                                                <td class="text-right">{{ number_format($row['total']) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .dashboard-page {
            max-width: 1320px;
            margin: 0 auto;
        }

        .dashboard-section {
            margin-bottom: 1.35rem;
        }

        .dashboard-section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .75rem;
        }

        .dashboard-filter {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            align-items: end;
        }

        .dashboard-filter-wide {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }

        .dashboard-field {
            display: grid;
            gap: .3rem;
            margin: 0;
            font-weight: 700;
        }

        .analytics-card .card-body {
            height: 320px;
        }

        .analytics-card canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .small-box,
        .info-box,
        .card {
            border-radius: 8px;
        }

        @media (max-width: 991.98px) {
            .dashboard-filter,
            .dashboard-filter-wide {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .dashboard-section-header,
            .dashboard-filter,
            .dashboard-filter-wide {
                grid-template-columns: 1fr;
                align-items: stretch;
                flex-direction: column;
            }

            .dashboard-filter .btn {
                width: 100%;
            }

            .analytics-card .card-body {
                height: 280px;
            }
        }
    </style>
@stop

@section('js')
    <script>
        const chartData = @json($charts);
        const gridColor = 'rgba(15, 23, 42, 0.08)';
        const fontColor = '#475569';

        Chart.defaults.global.defaultFontColor = fontColor;
        Chart.defaults.global.defaultFontFamily = 'Arial, sans-serif';

        function chartOptions(extra = {}) {
            return Object.assign({
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' },
                scales: {
                    xAxes: [{ gridLines: { color: gridColor } }],
                    yAxes: [{ ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: gridColor } }]
                }
            }, extra);
        }

        function makeChart(id, config) {
            const element = document.getElementById(id);

            if (!element) {
                return null;
            }

            return new Chart(element, config);
        }

        makeChart('registrationsPaymentsChart', {
            type: 'bar',
            data: {
                labels: chartData.registrations.labels,
                datasets: [
                    {
                        label: 'Registros',
                        data: chartData.registrations.registrations,
                        backgroundColor: 'rgba(14, 165, 233, 0.55)',
                        borderColor: '#0284C7',
                        borderWidth: 1
                    },
                    {
                        label: 'Pagaron registro',
                        data: chartData.registrations.paid,
                        type: 'line',
                        fill: false,
                        borderColor: '#16A34A',
                        backgroundColor: '#16A34A',
                        lineTension: 0.25
                    }
                ]
            },
            options: chartOptions()
        });

        makeChart('conversionChart', {
            type: 'line',
            data: {
                labels: chartData.registrations.labels,
                datasets: [{
                    label: 'Pagos por cada 10 registros',
                    data: chartData.registrations.conversion_per_ten,
                    borderColor: '#2563EB',
                    backgroundColor: 'rgba(37, 99, 235, 0.12)',
                    pointBackgroundColor: '#2563EB',
                    lineTension: 0.25
                }]
            },
            options: chartOptions({
                scales: {
                    xAxes: [{ gridLines: { color: gridColor } }],
                    yAxes: [{ ticks: { beginAtZero: true, suggestedMax: 10 }, gridLines: { color: gridColor } }]
                }
            })
        });

        makeChart('cumulativeChart', {
            type: 'line',
            data: {
                labels: chartData.registrations.labels,
                datasets: [
                    {
                        label: 'Registros acumulados',
                        data: chartData.registrations.cumulative_registrations,
                        borderColor: '#0891B2',
                        backgroundColor: 'rgba(8, 145, 178, 0.10)',
                        lineTension: 0.2
                    },
                    {
                        label: 'Pagos acumulados',
                        data: chartData.registrations.cumulative_paid,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.10)',
                        lineTension: 0.2
                    }
                ]
            },
            options: chartOptions()
        });

        makeChart('serviceRevenueChart', {
            type: 'horizontalBar',
            data: {
                labels: chartData.service_revenue.labels,
                datasets: [{
                    label: 'EUR',
                    data: chartData.service_revenue.data,
                    backgroundColor: '#0F766E'
                }]
            },
            options: chartOptions()
        });

        makeChart('tasksByDateChart', {
            type: 'bar',
            data: {
                labels: chartData.tasks_by_date.labels,
                datasets: [
                    { label: 'Pendientes', data: chartData.tasks_by_date.pending, backgroundColor: '#F59E0B' },
                    { label: 'En curso', data: chartData.tasks_by_date.in_progress, backgroundColor: '#2563EB' },
                    { label: 'Completadas', data: chartData.tasks_by_date.completed, backgroundColor: '#16A34A' },
                    { label: 'Canceladas', data: chartData.tasks_by_date.canceled, backgroundColor: '#DC2626' },
                ]
            },
            options: chartOptions({
                scales: {
                    xAxes: [{ stacked: true, gridLines: { color: gridColor } }],
                    yAxes: [{ stacked: true, ticks: { beginAtZero: true, precision: 0 }, gridLines: { color: gridColor } }]
                }
            })
        });

        makeChart('taskStatusChart', {
            type: 'doughnut',
            data: {
                labels: chartData.task_status.labels,
                datasets: [{
                    data: chartData.task_status.data,
                    backgroundColor: ['#F59E0B', '#2563EB', '#16A34A', '#DC2626']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' }
            }
        });

        makeChart('taskAdvisorChart', {
            type: 'horizontalBar',
            data: {
                labels: chartData.task_advisors.labels,
                datasets: [{
                    label: 'Tareas',
                    data: chartData.task_advisors.data,
                    backgroundColor: '#64748B'
                }]
            },
            options: chartOptions()
        });

        makeChart('salesPipelineChart', {
            type: 'bar',
            data: {
                labels: chartData.sales_pipeline.labels,
                datasets: [{
                    label: 'Clientes',
                    data: chartData.sales_pipeline.data,
                    backgroundColor: ['#0EA5E9', '#6366F1', '#F59E0B', '#14B8A6', '#22C55E']
                }]
            },
            options: chartOptions()
        });
    </script>
@stop

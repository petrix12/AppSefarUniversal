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
            <small class="text-muted">Reportes, pagos, tareas y COS</small>
        </div>

        <form method="GET" class="form-inline mt-3 mt-md-0">
            <label class="mr-2 font-weight-bold">Mes</label>
            <input type="month" name="month" value="{{ $month }}" class="form-control form-control-sm mr-2">
            <button class="btn btn-sm btn-primary">
                <i class="fas fa-filter mr-1"></i>Filtrar
            </button>
            <a href="{{ route('diarioindex') }}" class="btn btn-sm btn-outline-secondary ml-2">
                <i class="fas fa-file mr-1"></i>Reporte diario
            </a>
        </form>
    </div>
@stop

@section('content')
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
                    <div class="icon">
                        <i class="fas fa-{{ $card['icon'] }}"></i>
                    </div>
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

    <div class="row">
        @php
            $opsCards = [
                ['label' => 'Tareas abiertas', 'value' => number_format($taskMetrics['open']), 'color' => 'warning', 'icon' => 'tasks'],
                ['label' => 'Tareas vencidas', 'value' => number_format($taskMetrics['overdue']), 'color' => 'danger', 'icon' => 'exclamation-circle'],
                ['label' => 'Completadas mes', 'value' => number_format($taskMetrics['completed_month']), 'color' => 'success', 'icon' => 'check-circle'],
                ['label' => 'Respondieron mes', 'value' => number_format($taskMetrics['responded_month']), 'color' => 'primary', 'icon' => 'comment-dots'],
            ];
        @endphp

        @foreach($opsCards as $card)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="info-box">
                    <span class="info-box-icon bg-{{ $card['color'] }}"><i class="fas fa-{{ $card['icon'] }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $card['label'] }}</span>
                        <span class="info-box-number">{{ $card['value'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-outline card-warning analytics-card">
                <div class="card-header">
                    <h3 class="card-title">Tareas abiertas por vendedor</h3>
                </div>
                <div class="card-body">
                    <canvas id="sellerTasksChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card card-outline card-primary analytics-card">
                <div class="card-header">
                    <h3 class="card-title">Pipeline comercial</h3>
                </div>
                <div class="card-body">
                    <canvas id="salesPipelineChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        @php
            $cosCards = [
                ['label' => 'Clientes con COS', 'value' => number_format($cosMetrics['with_data']), 'color' => 'info', 'icon' => 'sitemap'],
                ['label' => 'COS listo', 'value' => number_format($cosMetrics['ready']), 'color' => 'success', 'icon' => 'check'],
                ['label' => 'COS vigente', 'value' => number_format($cosMetrics['fresh']), 'color' => 'primary', 'icon' => 'sync'],
                ['label' => 'COS vencido', 'value' => number_format($cosMetrics['expired']), 'color' => 'danger', 'icon' => 'clock'],
            ];
        @endphp

        @foreach($cosCards as $card)
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="info-box">
                    <span class="info-box-icon bg-{{ $card['color'] }}"><i class="fas fa-{{ $card['icon'] }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $card['label'] }}</span>
                        <span class="info-box-number">{{ $card['value'] }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-outline card-info analytics-card">
                <div class="card-header">
                    <h3 class="card-title">Etapas COS mas frecuentes</h3>
                </div>
                <div class="card-body">
                    <canvas id="cosStagesChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card card-outline card-success analytics-card">
                <div class="card-header">
                    <h3 class="card-title">Servicios con COS calculado</h3>
                </div>
                <div class="card-body">
                    <canvas id="cosServicesChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header">
            <h3 class="card-title">Clientes recientes con COS guardado</h3>
            <div class="card-tools text-muted small">{{ number_format($cosMetrics['warnings']) }} advertencia(s) detectadas en la muestra</div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Servicio</th>
                            <th>Etapa</th>
                            <th>Cache COS</th>
                            <th style="width:120px">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($cosMetrics['recent'] as $client)
                            <tr>
                                <td>
                                    <strong>{{ $client['name'] }}</strong>
                                    <br><small class="text-muted">{{ $client['email'] }}</small>
                                </td>
                                <td>{{ $client['service'] }}</td>
                                <td>{{ $client['stage'] }}</td>
                                <td>
                                    @if($client['expires_at'])
                                        <span class="badge badge-{{ $client['expires_at']->isFuture() ? 'success' : 'danger' }}">
                                            {{ $client['expires_at']->format('d/m/Y H:i') }}
                                        </span>
                                    @else
                                        <span class="badge badge-secondary">Sin fecha</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('crud.users.edit', $client['user_id']) }}" class="btn btn-sm btn-outline-primary">
                                        Ver COS
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay clientes con COS guardado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .analytics-card .card-body {
            height: 320px;
        }

        .analytics-card canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .small-box, .info-box, .card {
            border-radius: 8px;
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

        new Chart(document.getElementById('registrationsPaymentsChart'), {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Registros',
                        data: chartData.registrations,
                        backgroundColor: 'rgba(14, 165, 233, 0.55)',
                        borderColor: '#0284C7',
                        borderWidth: 1
                    },
                    {
                        label: 'Pagaron registro',
                        data: chartData.paid,
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

        new Chart(document.getElementById('conversionChart'), {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: 'Pagos por cada 10 registros',
                    data: chartData.conversion_per_ten,
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

        new Chart(document.getElementById('cumulativeChart'), {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'Registros acumulados',
                        data: chartData.cumulative_registrations,
                        borderColor: '#0891B2',
                        backgroundColor: 'rgba(8, 145, 178, 0.10)',
                        lineTension: 0.2
                    },
                    {
                        label: 'Pagos acumulados',
                        data: chartData.cumulative_paid,
                        borderColor: '#059669',
                        backgroundColor: 'rgba(5, 150, 105, 0.10)',
                        lineTension: 0.2
                    }
                ]
            },
            options: chartOptions()
        });

        new Chart(document.getElementById('serviceRevenueChart'), {
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

        new Chart(document.getElementById('sellerTasksChart'), {
            type: 'horizontalBar',
            data: {
                labels: chartData.seller_open_tasks.labels,
                datasets: [{
                    label: 'Tareas abiertas',
                    data: chartData.seller_open_tasks.data,
                    backgroundColor: '#F59E0B'
                }]
            },
            options: chartOptions()
        });

        new Chart(document.getElementById('salesPipelineChart'), {
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

        new Chart(document.getElementById('cosStagesChart'), {
            type: 'horizontalBar',
            data: {
                labels: chartData.cos_stages.labels,
                datasets: [{
                    label: 'Procesos',
                    data: chartData.cos_stages.data,
                    backgroundColor: '#2563EB'
                }]
            },
            options: chartOptions()
        });

        new Chart(document.getElementById('cosServicesChart'), {
            type: 'doughnut',
            data: {
                labels: chartData.cos_services.labels,
                datasets: [{
                    data: chartData.cos_services.data,
                    backgroundColor: ['#0F766E', '#2563EB', '#F59E0B', '#DC2626', '#7C3AED', '#0891B2', '#65A30D', '#DB2777']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                legend: { position: 'bottom' }
            }
        });
    </script>
@stop

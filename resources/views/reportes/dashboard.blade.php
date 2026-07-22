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
            <small class="text-muted">Registros, pagos, Banca Online, COS, tareas y ventas separados por seccion</small>
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
                        <input type="hidden" name="banca_start" value="{{ $bancaOnlineFilters['start'] }}">
                        <input type="hidden" name="banca_end" value="{{ $bancaOnlineFilters['end'] }}">
                        <input type="hidden" name="banca_country" value="{{ $bancaOnlineFilters['country'] }}">
                        <input type="hidden" name="banca_plan" value="{{ $bancaOnlineFilters['plan'] }}">
                        <input type="hidden" name="banca_case_status" value="{{ $bancaOnlineFilters['case_status'] }}">

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
            <div class="card card-outline card-info">
                <div class="card-header dashboard-section-header">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="fas fa-university mr-1"></i>Banca Online
                        </h3>
                        <small class="text-muted">{{ $bancaOnlineStart->format('d/m/Y') }} - {{ $bancaOnlineEnd->format('d/m/Y') }}</small>
                    </div>
                    <a href="{{ route('admin.banca-online.index') }}" class="btn btn-sm btn-outline-info">
                        <i class="fas fa-sliders-h mr-1"></i>Administrar Banca Online
                    </a>
                </div>
                <div class="card-body">
                    <form method="GET" action="{{ route('reportes.dashboard') }}" class="dashboard-filter dashboard-filter-banca">
                        <input type="hidden" name="registration_month" value="{{ $month }}">
                        <input type="hidden" name="task_start" value="{{ $taskFilters['start'] }}">
                        <input type="hidden" name="task_end" value="{{ $taskFilters['end'] }}">
                        <input type="hidden" name="task_user_id" value="{{ $taskFilters['user_id'] }}">
                        <input type="hidden" name="task_status" value="{{ $taskFilters['status'] }}">
                        <input type="hidden" name="pipeline_month" value="{{ $pipelineMonth }}">
                        <input type="hidden" name="pipeline_user_id" value="{{ $pipelineUserId }}">

                        <label class="dashboard-field">
                            <span>Desde</span>
                            <input type="date" name="banca_start" value="{{ $bancaOnlineFilters['start'] }}" class="form-control">
                        </label>

                        <label class="dashboard-field">
                            <span>Hasta</span>
                            <input type="date" name="banca_end" value="{{ $bancaOnlineFilters['end'] }}" class="form-control">
                        </label>

                        <label class="dashboard-field">
                            <span>Pais</span>
                            <select name="banca_country" class="form-control">
                                <option value="">Todos</option>
                                @foreach($bancaOnlineDashboard['countries'] as $country)
                                    <option value="{{ $country['slug'] }}" {{ $bancaOnlineFilters['country'] === $country['slug'] ? 'selected' : '' }}>
                                        {{ $country['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="dashboard-field">
                            <span>Plan</span>
                            <select name="banca_plan" class="form-control">
                                <option value="">Todos</option>
                                @foreach($bancaOnlineDashboard['plans'] as $plan)
                                    <option value="{{ $plan['slug'] }}" {{ $bancaOnlineFilters['plan'] === $plan['slug'] ? 'selected' : '' }}>
                                        {{ $plan['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="dashboard-field">
                            <span>Situacion</span>
                            <select name="banca_case_status" class="form-control">
                                <option value="">Todas</option>
                                @foreach($bancaOnlineDashboard['case_statuses'] as $status)
                                    <option value="{{ $status['key'] }}" {{ $bancaOnlineFilters['case_status'] === $status['key'] ? 'selected' : '' }}>
                                        {{ $status['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <button class="btn btn-info">
                            <i class="fas fa-filter mr-1"></i>Filtrar Banca
                        </button>
                    </form>

                    @unless($bancaOnlineDashboard['has_event_tracking'])
                        <div class="alert alert-warning dashboard-note mt-3 mb-0">
                            La tabla de eventos aun no existe en este ambiente. El panel muestra compras de Banca Online, pero el embudo queda completo al migrar <code>banca_online_events</code>.
                        </div>
                    @endunless
                    @unless($bancaOnlineDashboard['has_sales_tracking'])
                        <div class="alert alert-warning dashboard-note mt-3 mb-0">
                            Faltan columnas de ventas de Banca Online en <code>compras</code>. Ejecuta las migraciones de Banca Online antes de validar ingresos y activaciones.
                        </div>
                    @endunless
                </div>
            </div>

            <div class="row">
                @php
                    $bancaCards = [
                        ['label' => 'Activaciones', 'value' => number_format($bancaOnlineDashboard['created_count']), 'color' => 'info', 'icon' => 'rocket'],
                        ['label' => 'Pagos completados', 'value' => number_format($bancaOnlineDashboard['paid_count']), 'color' => 'success', 'icon' => 'check-circle'],
                        ['label' => 'Pendientes', 'value' => number_format($bancaOnlineDashboard['pending_count']), 'color' => 'warning', 'icon' => 'clock'],
                        ['label' => 'Ingresos Banca', 'value' => number_format($bancaOnlineDashboard['revenue'], 2) . ' EUR', 'color' => 'teal', 'icon' => 'euro-sign'],
                        ['label' => 'Llegan a pago', 'value' => number_format($bancaOnlineDashboard['payment_started_count']), 'color' => 'primary', 'icon' => 'credit-card'],
                        ['label' => 'Conversion pago', 'value' => number_format($bancaOnlineDashboard['payment_conversion'], 1) . '%', 'color' => 'dark', 'icon' => 'percentage'],
                    ];
                @endphp

                @foreach($bancaCards as $card)
                    <div class="col-12 col-sm-6 col-xl-2">
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
                <div class="col-lg-5">
                    <div class="card card-outline card-info analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Embudo Banca Online</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="bancaOnlineFunnelChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-outline card-success">
                        <div class="card-header">
                            <h3 class="card-title">Ingresos por plan</h3>
                        </div>
                        <div class="card-body p-0 dashboard-scroll-panel">
                            <table class="table table-sm mb-0 dashboard-mini-table">
                                <thead>
                                    <tr>
                                        <th>Plan</th>
                                        <th class="text-right">Pagos</th>
                                        <th class="text-right">EUR</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($bancaOnlineDashboard['plan_revenue'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td class="text-right">{{ number_format($row['total']) }}</td>
                                            <td class="text-right">{{ number_format($row['revenue'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">Sin pagos en este filtro.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card card-outline card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Situaciones consultadas</h3>
                        </div>
                        <div class="card-body p-0 dashboard-scroll-panel">
                            <table class="table table-sm mb-0 dashboard-mini-table">
                                <tbody>
                                    @forelse($bancaOnlineDashboard['case_status_breakdown'] as $row)
                                        <tr>
                                            <td>{{ $row['label'] }}</td>
                                            <td class="text-right font-weight-bold">{{ number_format($row['total']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted py-4">Sin eventos de situacion.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-6">
                    <div class="card card-outline card-info">
                        <div class="card-header">
                            <h3 class="card-title">Activaciones recientes</h3>
                        </div>
                        <div class="card-body p-0 dashboard-scroll-panel dashboard-scroll-panel-lg">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Alcance</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($bancaOnlineDashboard['recent_activations'] as $activation)
                                        @php
                                            $metadata = $activation->metadata ?? [];
                                            $planLabel = $metadata['plan_title'] ?? $metadata['plan_slug'] ?? 'Ruta estrategica';
                                            $packageLabel = $metadata['package_title'] ?? $activation->servicio?->nombre ?? 'Alcance';
                                            $activationDate = $activation->paid_at ?: $activation->updated_at ?: $activation->created_at;
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong>{{ $activation->user?->email ?? 'Sin correo' }}</strong>
                                                @if($activation->user?->name)
                                                    <br><small class="text-muted">{{ $activation->user->name }}</small>
                                                @endif
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::limit($planLabel . ' - ' . $packageLabel, 55) }}</td>
                                            <td>
                                                <span class="badge badge-{{ (int) $activation->pagado === 1 ? 'success' : 'warning' }}">
                                                    {{ (int) $activation->pagado === 1 ? 'Pagado' : 'Pendiente' }}
                                                </span>
                                            </td>
                                            <td>{{ optional($activationDate)->format('d/m H:i') ?? '-' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Sin activaciones en este filtro.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card card-outline card-primary">
                        <div class="card-header">
                            <h3 class="card-title">Eventos recientes</h3>
                        </div>
                        <div class="card-body p-0 dashboard-scroll-panel dashboard-scroll-panel-lg">
                            <table class="table table-hover mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Evento</th>
                                        <th>Contacto</th>
                                        <th>Contexto</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(!$bancaOnlineDashboard['has_event_tracking'])
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">Migracion de eventos pendiente.</td>
                                        </tr>
                                    @else
                                        @forelse($bancaOnlineDashboard['recent_events'] as $event)
                                            @php
                                                $context = collect([$event->country_slug, $event->plan_slug, $event->case_status])->filter()->implode(' - ');
                                            @endphp
                                            <tr>
                                                <td>{{ $bancaOnlineDashboard['event_labels'][$event->event] ?? $event->event }}</td>
                                                <td>{{ $event->email ?? $event->user?->email ?? 'Visitante' }}</td>
                                                <td>{{ $context ?: '-' }}</td>
                                                <td>{{ optional($event->occurred_at)->format('d/m H:i') ?? '-' }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">Sin eventos en este filtro.</td>
                                            </tr>
                                        @endforelse
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="card card-outline card-teal">
                <div class="card-header dashboard-section-header">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="fas fa-folder-open mr-1"></i>Documentos del expediente
                        </h3>
                        <small class="text-muted">Solicitudes pendientes, documentos no disponibles y bloqueos frecuentes</small>
                    </div>
                </div>
                @unless($documentRequestMetrics['available'])
                    <div class="card-body">
                        <div class="alert alert-warning mb-0">
                            La tabla <code>document_requests</code> no existe en este ambiente.
                        </div>
                    </div>
                @endunless
            </div>

            <div class="row">
                @php
                    $documentCards = [
                        ['label' => 'Pendientes cliente', 'value' => $documentRequestMetrics['pending'], 'color' => 'warning', 'icon' => 'file-upload'],
                        ['label' => 'No disponibles', 'value' => $documentRequestMetrics['missing'], 'color' => 'danger', 'icon' => 'search'],
                        ['label' => 'Recibidos/aprobados', 'value' => $documentRequestMetrics['received'], 'color' => 'success', 'icon' => 'check-circle'],
                        ['label' => 'Clientes bloqueados', 'value' => $documentRequestMetrics['blocked_clients'], 'color' => 'teal', 'icon' => 'user-clock'],
                    ];
                @endphp

                @foreach($documentCards as $card)
                    <div class="col-12 col-sm-6 col-xl-3">
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
                <div class="col-lg-5">
                    <div class="card card-outline card-teal analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Tipos de documentos pendientes</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="documentRequestTypesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-outline card-warning">
                        <div class="card-header">
                            <h3 class="card-title">Faltantes frecuentes</h3>
                        </div>
                        <div class="card-body p-0 dashboard-scroll-panel">
                            <table class="table table-sm mb-0 dashboard-mini-table">
                                <tbody>
                                    @forelse($documentRequestMetrics['frequent_missing'] as $row)
                                        <tr>
                                            <td>{{ \Illuminate\Support\Str::limit($row['document'], 54) }}</td>
                                            <td class="text-right font-weight-bold">{{ number_format($row['total']) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted py-4">Sin documentos faltantes.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3">
                    <div class="card card-outline card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Casos recientes</h3>
                        </div>
                        <div class="card-body p-0 dashboard-scroll-panel">
                            <table class="table table-sm mb-0 dashboard-mini-table">
                                <tbody>
                                    @forelse($documentRequestMetrics['recent'] as $row)
                                        <tr>
                                            <td>
                                                <strong>{{ \Illuminate\Support\Str::limit($row['document'], 34) }}</strong>
                                                <br>
                                                <small class="text-muted">{{ $row['client'] }} · {{ $row['status_label'] }}</small>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td class="text-center text-muted py-4">Sin casos recientes.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="dashboard-section">
            <div class="card card-outline card-dark">
                <div class="card-header dashboard-section-header">
                    <div>
                        <h3 class="card-title mb-0">
                            <i class="fas fa-search-location mr-1"></i>COS
                        </h3>
                        <small class="text-muted">Canal de consulta y visibilidad interna de procesos</small>
                    </div>
                </div>
            </div>

            <div class="row">
                @php
                    $cosCards = [
                        ['label' => 'Clientes con COS', 'value' => $cosMetrics['with_data'], 'color' => 'dark', 'icon' => 'database'],
                        ['label' => 'Listos para cliente', 'value' => $cosMetrics['ready'], 'color' => 'success', 'icon' => 'eye'],
                        ['label' => 'COS vigentes', 'value' => $cosMetrics['fresh'], 'color' => 'info', 'icon' => 'sync-alt'],
                        ['label' => 'COS vencidos', 'value' => $cosMetrics['expired'], 'color' => 'warning', 'icon' => 'hourglass-end'],
                        ['label' => 'Alertas internas', 'value' => $cosMetrics['warnings'], 'color' => 'danger', 'icon' => 'exclamation-triangle'],
                    ];
                @endphp

                @foreach($cosCards as $card)
                    <div class="col-12 col-sm-6 col-xl">
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
                <div class="col-lg-4">
                    <div class="card card-outline card-dark analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Procesos por etapa</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="cosStagesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-outline card-secondary analytics-card">
                        <div class="card-header">
                            <h3 class="card-title">Procesos por servicio</h3>
                        </div>
                        <div class="card-body">
                            <canvas id="cosServicesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card card-outline card-secondary">
                        <div class="card-header">
                            <h3 class="card-title">Procesos recientes</h3>
                        </div>
                        <div class="card-body p-0 dashboard-scroll-panel dashboard-scroll-panel-lg">
                            <table class="table table-sm table-hover mb-0 dashboard-mini-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Cliente</th>
                                        <th>Etapa</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cosMetrics['recent'] as $row)
                                        <tr>
                                            <td>
                                                <strong>{{ $row['email'] }}</strong>
                                                <br><small class="text-muted">{{ $row['service'] }}</small>
                                            </td>
                                            <td>{{ \Illuminate\Support\Str::limit($row['stage'], 42) }}</td>
                                            <td>
                                                <span class="badge badge-{{ $row['ready'] ? 'success' : 'secondary' }}">
                                                    {{ $row['ready'] ? 'Visible' : 'Interno' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="text-center text-muted py-4">Sin datos COS sincronizados.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
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
                        <input type="hidden" name="banca_start" value="{{ $bancaOnlineFilters['start'] }}">
                        <input type="hidden" name="banca_end" value="{{ $bancaOnlineFilters['end'] }}">
                        <input type="hidden" name="banca_country" value="{{ $bancaOnlineFilters['country'] }}">
                        <input type="hidden" name="banca_plan" value="{{ $bancaOnlineFilters['plan'] }}">
                        <input type="hidden" name="banca_case_status" value="{{ $bancaOnlineFilters['case_status'] }}">

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
                        <input type="hidden" name="banca_start" value="{{ $bancaOnlineFilters['start'] }}">
                        <input type="hidden" name="banca_end" value="{{ $bancaOnlineFilters['end'] }}">
                        <input type="hidden" name="banca_country" value="{{ $bancaOnlineFilters['country'] }}">
                        <input type="hidden" name="banca_plan" value="{{ $bancaOnlineFilters['plan'] }}">
                        <input type="hidden" name="banca_case_status" value="{{ $bancaOnlineFilters['case_status'] }}">

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

        .dashboard-filter-banca {
            grid-template-columns: repeat(6, minmax(0, 1fr));
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

        .dashboard-note {
            font-size: .9rem;
        }

        .dashboard-scroll-panel {
            max-height: 280px;
            overflow-y: auto;
            overscroll-behavior: contain;
        }

        .dashboard-scroll-panel-lg {
            max-height: 360px;
        }

        .dashboard-mini-table td,
        .dashboard-mini-table th {
            vertical-align: middle;
        }

        .info-box-number {
            white-space: normal;
        }

        .small-box,
        .info-box,
        .card {
            border-radius: 8px;
        }

        @media (max-width: 991.98px) {
            .dashboard-filter,
            .dashboard-filter-wide,
            .dashboard-filter-banca {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .dashboard-section-header,
            .dashboard-filter,
            .dashboard-filter-wide,
            .dashboard-filter-banca {
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

        makeChart('bancaOnlineFunnelChart', {
            type: 'horizontalBar',
            data: {
                labels: chartData.banca_online_funnel.labels,
                datasets: [{
                    label: 'Eventos',
                    data: chartData.banca_online_funnel.data,
                    backgroundColor: ['#0EA5E9', '#6366F1', '#F59E0B', '#14B8A6', '#16A34A']
                }]
            },
            options: chartOptions()
        });

        makeChart('cosStagesChart', {
            type: 'horizontalBar',
            data: {
                labels: chartData.cos_stages.labels,
                datasets: [{
                    label: 'Procesos',
                    data: chartData.cos_stages.data,
                    backgroundColor: '#334155'
                }]
            },
            options: chartOptions()
        });

        makeChart('cosServicesChart', {
            type: 'horizontalBar',
            data: {
                labels: chartData.cos_services.labels,
                datasets: [{
                    label: 'Procesos',
                    data: chartData.cos_services.data,
                    backgroundColor: '#64748B'
                }]
            },
            options: chartOptions()
        });

        makeChart('documentRequestTypesChart', {
            type: 'doughnut',
            data: {
                labels: chartData.document_request_types.labels,
                datasets: [{
                    data: chartData.document_request_types.data,
                    backgroundColor: ['#0F766E', '#DBBA72', '#334155']
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

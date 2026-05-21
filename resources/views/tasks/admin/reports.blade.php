@extends('adminlte::page')

@section('title', 'Reportes de tareas')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="mb-0">
                <i class="fas fa-file-excel mr-2 text-success"></i>Reportes de tareas
            </h1>
            <small class="text-muted">{{ $periodLabel }}</small>
        </div>
        <a href="{{ route('tasks.admin.index') }}" class="btn btn-sm btn-outline-secondary mt-2 mt-md-0">
            <i class="fas fa-arrow-left mr-1"></i>Panel de tareas
        </a>
    </div>
@stop

@section('content')
    <div class="task-report-page">
        <div class="card card-outline card-success">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter mr-1"></i>Generar reporte
                </h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('tasks.admin.reports') }}" class="task-report-form">
                    <div class="task-report-grid">
                        <label class="task-report-field">
                            <span>Tipo</span>
                            <select name="period" id="task-report-period" class="form-control">
                                <option value="daily" {{ $filters['period'] === 'daily' ? 'selected' : '' }}>Diario</option>
                                <option value="monthly" {{ $filters['period'] === 'monthly' ? 'selected' : '' }}>Mensual</option>
                                <option value="annual" {{ $filters['period'] === 'annual' ? 'selected' : '' }}>Anual</option>
                            </select>
                        </label>

                        <label class="task-report-field task-report-period-field" data-period-field="daily">
                            <span>Dia</span>
                            <input type="date" name="date" class="form-control" value="{{ $filters['date'] }}">
                        </label>

                        <label class="task-report-field task-report-period-field" data-period-field="monthly">
                            <span>Mes</span>
                            <input type="month" name="month" class="form-control" value="{{ $filters['month'] }}">
                        </label>

                        <label class="task-report-field task-report-period-field" data-period-field="annual">
                            <span>Anio</span>
                            <input type="number" name="year" min="2000" max="{{ now()->year + 1 }}" class="form-control" value="{{ $filters['year'] }}">
                        </label>

                        <label class="task-report-field">
                            <span>Asesor</span>
                            <select name="user_id" class="form-control">
                                <option value="">Todos</option>
                                @foreach($advisors as $id => $name)
                                    <option value="{{ $id }}" {{ (string) $filters['user_id'] === (string) $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="task-report-field">
                            <span>Estado</span>
                            <select name="status" class="form-control">
                                <option value="">Todos</option>
                                @foreach($statusLabels as $key => $label)
                                    <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="task-report-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i>Previsualizar
                        </button>
                        <button type="submit" formaction="{{ route('tasks.admin.reports.export') }}" class="btn btn-success">
                            <i class="fas fa-download mr-1"></i>Exportar Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            @php
                $cards = [
                    ['label' => 'Total', 'value' => $stats['total'], 'color' => 'dark', 'icon' => 'clipboard-list'],
                    ['label' => 'Pendientes', 'value' => $stats['pending'], 'color' => 'warning', 'icon' => 'clock'],
                    ['label' => 'En curso', 'value' => $stats['in_progress'], 'color' => 'primary', 'icon' => 'spinner'],
                    ['label' => 'Completadas', 'value' => $stats['completed'], 'color' => 'success', 'icon' => 'check'],
                    ['label' => 'Canceladas', 'value' => $stats['canceled'], 'color' => 'danger', 'icon' => 'times'],
                    ['label' => 'Vencidas abiertas', 'value' => $stats['overdue'], 'color' => 'secondary', 'icon' => 'exclamation-triangle'],
                ];
            @endphp
            @foreach($cards as $card)
                <div class="col-sm-6 col-lg-2">
                    <div class="small-box bg-{{ $card['color'] }}">
                        <div class="inner">
                            <h3>{{ number_format($card['value']) }}</h3>
                            <p>{{ $card['label'] }}</p>
                        </div>
                        <div class="icon"><i class="fas fa-{{ $card['icon'] }}"></i></div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-body">
                <div class="task-report-note">
                    <div>
                        <strong>Excel generado</strong>
                        <span>Incluye hojas de resumen, tareas por asesor, tareas por fecha y detalle completo de cada tarea.</span>
                    </div>
                    <div>
                        <strong>Rango activo</strong>
                        <span>{{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .task-report-page {
            max-width: 1180px;
            margin: 0 auto;
        }

        .task-report-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .85rem;
        }

        .task-report-field {
            display: grid;
            gap: .35rem;
            margin: 0;
            font-weight: 700;
        }

        .task-report-actions {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: .55rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(9, 49, 67, .14);
        }

        .task-report-note {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .85rem;
        }

        .task-report-note > div {
            padding: .9rem;
            border: 1px solid rgba(9, 49, 67, .14);
            border-radius: 8px;
            background: #f4f7f9;
        }

        .task-report-note strong,
        .task-report-note span {
            display: block;
        }

        .task-report-note span {
            color: #607783;
        }

        @media (max-width: 991.98px) {
            .task-report-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .task-report-grid,
            .task-report-note {
                grid-template-columns: 1fr;
            }

            .task-report-actions .btn {
                width: 100%;
            }
        }
    </style>
@stop

@section('js')
    <script>
        (function () {
            const periodSelect = document.getElementById('task-report-period');
            const fields = document.querySelectorAll('[data-period-field]');

            function syncFields() {
                const period = periodSelect.value;

                fields.forEach((field) => {
                    field.style.display = field.dataset.periodField === period ? 'grid' : 'none';
                });
            }

            periodSelect.addEventListener('change', syncFields);
            syncFields();
        })();
    </script>
@stop

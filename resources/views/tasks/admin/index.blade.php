@extends('adminlte::page')

@section('title', 'Admin — Tareas')

@section('content_header')
    <h1>
        <i class="fas fa-tasks mr-2 text-primary"></i>Panel de Tareas
        <small class="text-muted">{{ $date->format('d/m/Y') }}</small>
    </h1>
@stop

@section('content')
    @php
        $salesTagOptions = \App\Models\Task::salesTagOptions();
    @endphp

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {!! session('success') !!}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    {{-- Stats --}}
    <div class="row">
        @php
            $statusConfig = [
                'pending'     => ['label'=>'Pendientes',  'color'=>'warning', 'icon'=>'clock'],
                'in_progress' => ['label'=>'En curso',    'color'=>'primary', 'icon'=>'spinner'],
                'completed'   => ['label'=>'Completadas', 'color'=>'success', 'icon'=>'check'],
                'canceled'    => ['label'=>'Canceladas',  'color'=>'danger',  'icon'=>'times'],
            ];
        @endphp
        @foreach($statusConfig as $key => $cfg)
            <div class="col-sm-3">
                <div class="small-box bg-{{ $cfg['color'] }}">
                    <div class="inner">
                        <h3>{{ $stats[$key] ?? 0 }}</h3>
                        <p>{{ $cfg['label'] }}</p>
                    </div>
                    <div class="icon"><i class="fas fa-{{ $cfg['icon'] }}"></i></div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Gráfico (Chart.js inline, sin instalar nada extra) --}}
    <div class="card card-outline card-dark mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-bar mr-1"></i>Tareas por asesor</h3>
        </div>
        <div class="card-body">
            <div class="tasks-chart-wrap">
                <canvas id="taskChart"></canvas>
            </div>
        </div>
    </div>

    {{-- Filtros + Acciones --}}
    <div class="card card-outline card-secondary mb-3">
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">

            {{-- ✅ FORM GET: filtros de fecha, asesor, estado --}}
            <form method="GET" class="form-inline flex-wrap gap-2 mr-2">
                <input type="date" name="date" class="form-control form-control-sm mr-2"
                    value="{{ $date->toDateString() }}">

                <select name="user_id" class="form-control form-control-sm mr-2">
                    <option value="">— Todos los asesores —</option>
                    @foreach($advisors as $id => $name)
                        <option value="{{ $id }}" {{ request('user_id') == $id ? 'selected' : '' }}>
                            {{ $name }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="form-control form-control-sm mr-2">
                    <option value="">— Todos los estados —</option>
                    <option value="pending"     {{ request('status') === 'pending'     ? 'selected' : '' }}>Pendiente</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>En curso</option>
                    <option value="completed"   {{ request('status') === 'completed'   ? 'selected' : '' }}>Completada</option>
                    <option value="canceled"    {{ request('status') === 'canceled'    ? 'selected' : '' }}>Cancelada</option>
                </select>

                <button type="submit" class="btn btn-sm btn-primary mr-2">
                    <i class="fas fa-search mr-1"></i>Filtrar
                </button>
            </form>
            {{-- ✅ FORM GET cierra ANTES del siguiente form --}}

            <a href="{{ route('tasks.admin.create') }}" class="btn btn-sm btn-success mr-2">
                <i class="fas fa-plus mr-1"></i>Nueva tarea
            </a>

            <a href="{{ route('tasks.admin.reports', ['period' => 'daily', 'date' => $date->toDateString()]) }}" class="btn btn-sm btn-outline-success mr-2">
                <i class="fas fa-file-excel mr-1"></i>Reportes
            </a>

            {{-- ✅ FORM POST: completamente independiente, fuera del GET --}}
            <form method="POST" action="{{ route('tasks.admin.generate-daily') }}" class="d-inline">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button type="submit" class="btn btn-sm btn-warning"
                        onclick="return confirm('¿Generar tareas para {{ $date->toDateString() }}?')">
                    <i class="fas fa-magic mr-1"></i>Generar tareas diarias
                </button>
            </form>

        </div>
    </div>

    {{-- Tabla --}}
    <div class="card card-outline card-primary">
        <div class="card-body p-0">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>#</th>
                        <th>Asesor</th>
                        <th>Contacto</th>
                        <th>Título</th>
                        <th>Estado</th>
                        <th>Via</th>
                        <th>Venta</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php
                            $isSystemsTask = $task->isAssignedToSystems();
                            $colorMap = [
                                'pending'=>'warning','in_progress'=>'primary',
                                'completed'=>'success','canceled'=>'danger'
                            ];
                            $labelMap = [
                                'pending'=>'Pendiente','in_progress'=>'En curso',
                                'completed'=>'Completada','canceled'=>'Cancelada'
                            ];
                        @endphp
                        <tr>
                            <td>{{ $task->id }}</td>
                            <td>{{ $task->assignee?->name ?? '—' }}</td>
                            <td>{{ $task->contact?->name ?? '—' }}</td>
                            <td>
                                {{ Str::limit($task->title, 45) }}
                                @if($isSystemsTask)
                                    <span class="badge badge-dark ml-1">Sistemas</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{ $colorMap[$task->status] ?? 'secondary' }}">
                                    {{ $labelMap[$task->status] ?? $task->status }}
                                </span>
                            </td>
                            <td>{{ implode(', ', $task->contactMethodLabels()) ?: '-' }}</td>
                            <td>{{ $task->saleStatusLabel() ?? '-' }}</td>
                            <td>{{ $task->due_date->format('d/m/Y') }}</td>
                            <td>
                                <button type="button"
                                        class="btn btn-xs btn-secondary"
                                        data-toggle="modal"
                                        data-target="#task-detail-{{ $task->id }}"
                                        title="Ver datos de la tarea">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="{{ route('tasks.admin.edit', $task) }}"
                                   class="btn btn-xs btn-info">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form action="{{ route('tasks.admin.destroy', $task) }}"
                                      method="POST" class="d-inline"
                                      onsubmit="return confirm('¿Eliminar tarea #{{ $task->id }}?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-xs btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                Sin tareas para los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @foreach($tasks as $task)
                @php
                    $detailColorMap = [
                        'pending' => 'warning',
                        'in_progress' => 'primary',
                        'completed' => 'success',
                        'canceled' => 'danger',
                    ];
                    $detailLabelMap = [
                        'pending' => 'Pendiente',
                        'in_progress' => 'En curso',
                        'completed' => 'Completada',
                        'canceled' => 'Cancelada',
                    ];
                @endphp
                <div class="modal fade" id="task-detail-{{ $task->id }}" tabindex="-1" role="dialog" aria-labelledby="task-detail-title-{{ $task->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-dark">
                                <div>
                                    <h5 class="modal-title" id="task-detail-title-{{ $task->id }}">
                                        <i class="fas fa-clipboard-list mr-1"></i>
                                        Tarea #{{ $task->id }} - {{ $task->title }}
                                    </h5>
                                    <small class="text-light">
                                        {{ $task->assignee?->name ?? 'Sin asesor' }} · {{ $task->due_date->format('d/m/Y') }}
                                    </small>
                                </div>
                                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="card card-outline card-primary mb-3">
                                    <div class="card-header py-2">
                                        <h3 class="card-title">
                                            <i class="fas fa-user-check mr-1"></i>
                                            {{ $task->isAssignedToSystems() ? 'Gestion interna' : 'Registro del vendedor' }}
                                        </h3>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <span class="text-muted small d-block">Vias de contacto</span>
                                                <span>{{ implode(', ', $task->contactMethodLabels()) ?: '-' }}</span>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <span class="text-muted small d-block">Respondio</span>
                                                @if(is_null($task->customer_responded))
                                                    <span class="badge badge-secondary">Sin registrar</span>
                                                @else
                                                    <span class="badge badge-{{ $task->customer_responded ? 'success' : 'warning' }}">
                                                        {{ $task->customer_responded ? 'Si' : 'Esperando respuesta' }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <span class="text-muted small d-block">Gestion efectiva</span>
                                                @if(is_null($task->call_effective))
                                                    <span class="badge badge-secondary">Sin registrar</span>
                                                @else
                                                    <span class="badge badge-{{ $task->call_effective ? 'success' : 'danger' }}">
                                                        {{ $task->call_effective ? 'Efectiva' : 'No efectiva' }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="col-md-8 mb-3">
                                                <span class="text-muted small d-block">Observacion / motivo sin respuesta</span>
                                                <span>{{ $task->reason_no_effective ?: '-' }}</span>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <span class="text-muted small d-block">Mostro interes</span>
                                                @if(is_null($task->interest_level))
                                                    <span class="badge badge-secondary">Sin registrar</span>
                                                @else
                                                    <span class="badge badge-{{ $task->interest_level ? 'success' : 'danger' }}">
                                                        {{ $task->interest_level ? 'Si' : 'No' }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="col-md-8 mb-3">
                                                <span class="text-muted small d-block">Motivo sin interes</span>
                                                <span>{{ $task->reason_no_interest ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <span class="text-muted small d-block">Estatus de venta</span>
                                                <span class="font-weight-bold">{{ $task->saleStatusLabel() ?? 'Sin estatus' }}</span>
                                            </div>
                                            <div class="col-md-8 mb-3">
                                                <span class="text-muted small d-block">Etiquetas</span>
                                                @forelse($task->sales_tags ?? [] as $tag)
                                                    @if(isset($salesTagOptions[$tag]))
                                                        <span class="badge badge-{{ $salesTagOptions[$tag]['class'] }} mr-1">
                                                            {{ $salesTagOptions[$tag]['label'] }}
                                                        </span>
                                                    @endif
                                                @empty
                                                    <span>—</span>
                                                @endforelse
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <span class="text-muted small d-block">Producto de interes</span>
                                                <span>{{ $task->product_of_interest ?: '—' }}</span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <span class="text-muted small d-block">Seguimiento programado</span>
                                                <span>{{ $task->follow_up_date ? $task->follow_up_date->format('d/m/Y') : '—' }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card card-outline card-secondary h-100">
                                            <div class="card-header py-2">
                                                <h3 class="card-title">
                                                    <i class="fas fa-info-circle mr-1"></i>Datos de la tarea
                                                </h3>
                                            </div>
                                            <div class="card-body">
                                                <dl class="row mb-0">
                                                    <dt class="col-sm-4">Estado</dt>
                                                    <dd class="col-sm-8">
                                                        <span class="badge badge-{{ $detailColorMap[$task->status] ?? 'secondary' }}">
                                                            {{ $detailLabelMap[$task->status] ?? $task->status }}
                                                        </span>
                                                    </dd>
                                                    <dt class="col-sm-4">Titulo</dt>
                                                    <dd class="col-sm-8">{{ $task->title }}</dd>
                                                    <dt class="col-sm-4">Descripcion</dt>
                                                    <dd class="col-sm-8">{{ $task->description ?: '—' }}</dd>
                                                    <dt class="col-sm-4">Fecha limite</dt>
                                                    <dd class="col-sm-8">{{ $task->due_date->format('d/m/Y') }}</dd>
                                                    <dt class="col-sm-4">Creada</dt>
                                                    <dd class="col-sm-8">{{ $task->created_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                                                    <dt class="col-sm-4">Actualizada</dt>
                                                    <dd class="col-sm-8">{{ $task->updated_at?->format('d/m/Y H:i') ?? '—' }}</dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="card card-outline card-secondary h-100">
                                            <div class="card-header py-2">
                                                <h3 class="card-title">
                                                    <i class="fas fa-address-book mr-1"></i>Contacto y asignacion
                                                </h3>
                                            </div>
                                            <div class="card-body">
                                                <dl class="row mb-0">
                                                    <dt class="col-sm-4">Asesor</dt>
                                                    <dd class="col-sm-8">{{ $task->assignee?->name ?? '—' }}</dd>
                                                    <dt class="col-sm-4">Contacto</dt>
                                                    <dd class="col-sm-8">{{ $task->contact?->name ?? '—' }}</dd>
                                                    <dt class="col-sm-4">Email</dt>
                                                    <dd class="col-sm-8">
                                                        @if($task->contact?->email)
                                                            <a href="mailto:{{ $task->contact->email }}">{{ $task->contact->email }}</a>
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>
                                                    <dt class="col-sm-4">Telefono</dt>
                                                    <dd class="col-sm-8">
                                                        @if($task->contact?->phone)
                                                            <a href="tel:{{ $task->contact->phone }}">{{ $task->contact->phone }}</a>
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>
                                                    <dt class="col-sm-4">Pasaporte</dt>
                                                    <dd class="col-sm-8">{{ $task->contact?->passport ?? '—' }}</dd>
                                                    <dt class="col-sm-4">COS</dt>
                                                    <dd class="col-sm-8">
                                                        @if($task->contact)
                                                            <a href="{{ url('/users/' . $task->contact->id . '/edit') }}" target="_blank">
                                                                Abrir ficha del cliente
                                                            </a>
                                                        @else
                                                            —
                                                        @endif
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="{{ route('tasks.admin.edit', $task) }}" class="btn btn-info">
                                    <i class="fas fa-edit mr-1"></i>Editar tarea
                                </a>
                                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="card-footer">
            {{ $tasks->links() }}
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
{{-- Chart.js desde CDN (sin instalación) --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const ctx = document.getElementById('taskChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: @json($chartData['labels']),
            datasets: [
                {
                    label: 'Pendientes',
                    data: @json($chartData['pending']),
                    backgroundColor: 'rgba(255, 193, 7, 0.8)',
                },
                {
                    label: 'En curso',
                    data: @json($chartData['progress']),
                    backgroundColor: 'rgba(0, 123, 255, 0.8)',
                },
                {
                    label: 'Completadas',
                    data: @json($chartData['done']),
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                },
                {
                    label: 'Canceladas',
                    data: @json($chartData['canceled']),
                    backgroundColor: 'rgba(220, 53, 69, 0.8)',
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            resizeDelay: 200,
            plugins: { legend: { position: 'top' } },
            scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } },
            },
        },
    });
</script>
@stop

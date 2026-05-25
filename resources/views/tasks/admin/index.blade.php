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
        $currentTaskFilters = [
            'date' => $date->toDateString(),
            'user_id' => request('user_id'),
            'status' => request('status'),
        ];
    @endphp

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {!! session('success') !!}
            <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            {!! session('error') !!}
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
        <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2 task-admin-actions">

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

            <form id="bulk-delete-form" method="POST" action="{{ route('tasks.admin.bulk-destroy') }}" class="d-inline mr-2">
                @csrf
                @method('DELETE')
                @foreach($currentTaskFilters as $filterName => $filterValue)
                    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                @endforeach
                <button type="submit" class="btn btn-sm btn-outline-danger" id="bulkDeleteButton" disabled>
                    <i class="fas fa-trash-alt mr-1"></i>Borrar seleccionadas
                    <span class="badge badge-danger ml-1" id="bulkDeleteCount">0</span>
                </button>
            </form>

            <form method="POST" action="{{ route('tasks.admin.bulk-destroy-filtered') }}" class="d-inline mr-2">
                @csrf
                @method('DELETE')
                @foreach($currentTaskFilters as $filterName => $filterValue)
                    <input type="hidden" name="{{ $filterName }}" value="{{ $filterValue }}">
                @endforeach
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        {{ $filteredTaskCount < 1 ? 'disabled' : '' }}
                        onclick="return confirm('Se eliminaran {{ $filteredTaskCount }} tarea(s) segun los filtros actuales. ¿Continuar?')">
                    <i class="fas fa-filter mr-1"></i>Borrar filtradas ({{ $filteredTaskCount }})
                </button>
            </form>

            {{-- ✅ FORM POST: completamente independiente, fuera del GET --}}
            <form method="POST" action="{{ route('tasks.admin.generate-daily') }}" class="d-inline">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <button type="submit" class="btn btn-sm btn-warning"
                        onclick="return confirm('¿Generar tareas para {{ $date->toDateString() }}?')">
                    <i class="fas fa-magic mr-1"></i>Generar tareas diarias
                </button>
            </form>

            <form method="POST" action="{{ route('tasks.admin.daily-workflow.force') }}" class="d-inline-flex align-items-center task-workflow-force-form">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <input type="number"
                       name="per"
                       class="form-control form-control-sm task-admin-number"
                       min="1"
                       max="100"
                       value="10"
                       title="Tareas base por asesor">
                <input type="number"
                       name="force_limit"
                       class="form-control form-control-sm task-admin-number"
                       min="1"
                       max="2000"
                       value="200"
                       title="Limite de contactos a revisar">
                <button type="submit" class="btn btn-sm btn-danger"
                        onclick="return confirm('Esto encolara reasignacion forzada y generacion de tareas para {{ $date->toDateString() }}. ¿Continuar?')">
                    <i class="fas fa-bolt mr-1"></i>Encolar workflow forzado
                </button>
            </form>

        </div>
    </div>

    <div class="card card-outline card-warning mb-3">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-random mr-1"></i>Reasignacion masiva de contactos</h3>
        </div>
        <form method="POST" action="{{ route('tasks.admin.bulk-reassign-contacts') }}">
            @csrf
            <div class="card-body">
                <div class="row">
                    <div class="col-md-5 mb-3">
                        <label class="form-label text-muted small mb-1">Asesor destino</label>
                        <select name="advisor_user_id" class="form-control" required>
                            <option value="">Selecciona asesor con owner activo</option>
                            @foreach($reassignmentAdvisors as $advisorId => $advisorLabel)
                                <option value="{{ $advisorId }}">{{ $advisorLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-7 mb-3">
                        <label class="form-label text-muted small mb-1">IDs o correos de contactos</label>
                        <textarea
                            name="identifiers"
                            class="form-control task-bulk-reassign-textarea"
                            rows="3"
                            placeholder="Ej: 13797, cliente@correo.com, 14002"
                            required
                        ></textarea>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center task-bulk-reassign-options">
                    <div class="custom-control custom-checkbox mr-3">
                        <input type="hidden" name="update_hubspot" value="0">
                        <input type="checkbox" class="custom-control-input" id="bulk_update_hubspot" name="update_hubspot" value="1" checked>
                        <label class="custom-control-label" for="bulk_update_hubspot">Actualizar HubSpot</label>
                    </div>
                    <div class="custom-control custom-checkbox mr-3">
                        <input type="hidden" name="update_deals" value="0">
                        <input type="checkbox" class="custom-control-input" id="bulk_update_deals" name="update_deals" value="1" checked>
                        <label class="custom-control-label" for="bulk_update_deals">Actualizar negocios</label>
                    </div>
                    <div class="custom-control custom-checkbox mr-3">
                        <input type="hidden" name="cancel_pending_tasks" value="0">
                        <input type="checkbox" class="custom-control-input" id="bulk_cancel_pending" name="cancel_pending_tasks" value="1" checked>
                        <label class="custom-control-label" for="bulk_cancel_pending">Cancelar pendientes de otros asesores</label>
                    </div>
                    <div class="custom-control custom-checkbox mr-3">
                        <input type="hidden" name="respect_no_hubspot_lists" value="0">
                        <input type="checkbox" class="custom-control-input" id="bulk_respect_lists" name="respect_no_hubspot_lists" value="1" checked>
                        <label class="custom-control-label" for="bulk_respect_lists">Respetar listas sin HubSpot</label>
                    </div>
                    <div class="custom-control custom-checkbox mr-3">
                        <input type="hidden" name="dry_run" value="0">
                        <input type="checkbox" class="custom-control-input" id="bulk_dry_run" name="dry_run" value="1">
                        <label class="custom-control-label" for="bulk_dry_run">Solo simular</label>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-content-end">
                <button type="submit" class="btn btn-warning"
                        onclick="return confirm('Se encolara la reasignacion masiva en segundo plano. ¿Continuar?')">
                    <i class="fas fa-paper-plane mr-1"></i>Encolar reasignacion
                </button>
            </div>
        </form>
    </div>

    {{-- Tabla --}}
    <div class="card card-outline card-primary">
        <div class="card-body p-0">
            <div class="table-responsive task-table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th class="task-select-col">
                            <input type="checkbox" id="selectAllTasks" title="Seleccionar visibles">
                        </th>
                        <th>#</th>
                        <th>Asesor</th>
                        <th>Contacto</th>
                        <th>Título</th>
                        <th>Estado</th>
                        <th>Via</th>
                        <th>Venta</th>
                        <th>Resultado</th>
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

                            $resultBadge = ['class' => 'secondary', 'label' => 'Sin resultado'];

                            if ($task->call_effective === true) {
                                $resultBadge = ['class' => 'success', 'label' => 'Gestion efectiva'];
                            } elseif ($task->call_effective === false) {
                                $resultBadge = ['class' => 'danger', 'label' => 'No efectiva'];
                            } elseif ($task->customer_responded === true) {
                                $resultBadge = ['class' => 'info', 'label' => 'Respondio'];
                            } elseif ($task->customer_responded === false) {
                                $resultBadge = ['class' => 'warning', 'label' => 'Esperando respuesta'];
                            } elseif ($task->status === \App\Models\Task::STATUS_COMPLETED) {
                                $resultBadge = ['class' => 'secondary', 'label' => 'Completada sin detalle'];
                            } elseif ($task->status === \App\Models\Task::STATUS_CANCELED) {
                                $resultBadge = ['class' => 'danger', 'label' => 'Cancelada'];
                            }

                            $resultNotes = collect();

                            if ($task->saleStatusLabel()) {
                                $resultNotes->push($task->saleStatusLabel());
                            }

                            foreach (($task->sales_tags ?? []) as $tag) {
                                if (isset($salesTagOptions[$tag])) {
                                    $resultNotes->push($salesTagOptions[$tag]['label']);
                                }
                            }

                            if ($task->reason_no_effective) {
                                $resultNotes->push($task->reason_no_effective);
                            } elseif ($task->reason_no_interest) {
                                $resultNotes->push($task->reason_no_interest);
                            }

                            if ($task->follow_up_date) {
                                $resultNotes->push('Seguimiento ' . $task->follow_up_date->format('d/m/Y'));
                            }

                            if ($task->product_of_interest) {
                                $resultNotes->push($task->product_of_interest);
                            }
                        @endphp
                        <tr>
                            <td class="task-select-col">
                                <input type="checkbox"
                                       class="task-bulk-checkbox"
                                       name="task_ids[]"
                                       value="{{ $task->id }}"
                                       form="bulk-delete-form"
                                       aria-label="Seleccionar tarea #{{ $task->id }}">
                            </td>
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
                            <td class="task-result-cell">
                                <span class="badge badge-{{ $resultBadge['class'] }}">
                                    {{ $resultBadge['label'] }}
                                </span>
                                @if($resultNotes->isNotEmpty())
                                    <div class="task-result-note" title="{{ $resultNotes->implode(' / ') }}">
                                        {{ Str::limit($resultNotes->take(3)->implode(' / '), 90) }}
                                    </div>
                                @endif
                            </td>
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
                            <td colspan="11" class="text-center py-4 text-muted">
                                Sin tareas para los filtros aplicados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>

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

        .task-admin-actions {
            gap: .5rem;
        }

        .task-workflow-force-form {
            gap: .35rem;
        }

        .task-admin-number {
            width: 74px;
        }

        .task-bulk-reassign-textarea {
            min-height: 92px;
            resize: vertical;
        }

        .task-bulk-reassign-options {
            gap: .35rem .75rem;
        }

        .task-select-col {
            width: 38px;
            text-align: center;
            vertical-align: middle !important;
        }

        .task-table-responsive {
            min-height: 160px;
        }

        .task-result-cell {
            min-width: 210px;
            max-width: 320px;
        }

        .task-result-note {
            color: #6c757d;
            font-size: .78rem;
            line-height: 1.25;
            margin-top: .25rem;
            overflow-wrap: anywhere;
        }

        @media (max-width: 767.98px) {
            .tasks-chart-wrap {
                height: 280px;
                min-height: 280px;
                max-height: 280px;
            }

            .task-admin-actions > form,
            .task-admin-actions > a {
                width: 100%;
                margin-right: 0 !important;
            }

            .task-admin-actions .btn,
            .task-admin-actions .form-control {
                width: 100%;
            }
        }
    </style>
@stop

@section('js')
{{-- Chart.js desde CDN (sin instalación) --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    const chartCanvas = document.getElementById('taskChart');

    if (window.Chart && chartCanvas) {
        const ctx = chartCanvas.getContext('2d');
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
    }

    const selectAllTasks = document.getElementById('selectAllTasks');
    const taskCheckboxes = Array.from(document.querySelectorAll('.task-bulk-checkbox'));
    const bulkDeleteForm = document.getElementById('bulk-delete-form');
    const bulkDeleteButton = document.getElementById('bulkDeleteButton');
    const bulkDeleteCount = document.getElementById('bulkDeleteCount');

    function updateBulkDeleteState() {
        const selected = taskCheckboxes.filter((checkbox) => checkbox.checked).length;

        if (bulkDeleteButton) {
            bulkDeleteButton.disabled = selected < 1;
        }

        if (bulkDeleteCount) {
            bulkDeleteCount.textContent = selected;
        }

        if (selectAllTasks) {
            selectAllTasks.checked = selected > 0 && selected === taskCheckboxes.length;
            selectAllTasks.indeterminate = selected > 0 && selected < taskCheckboxes.length;
        }
    }

    if (selectAllTasks) {
        selectAllTasks.addEventListener('change', () => {
            taskCheckboxes.forEach((checkbox) => {
                checkbox.checked = selectAllTasks.checked;
            });
            updateBulkDeleteState();
        });
    }

    taskCheckboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', updateBulkDeleteState);
    });

    if (bulkDeleteForm) {
        bulkDeleteForm.addEventListener('submit', (event) => {
            const selected = taskCheckboxes.filter((checkbox) => checkbox.checked).length;

            if (selected < 1 || !confirm(`Se eliminaran ${selected} tarea(s) seleccionada(s). ¿Continuar?`)) {
                event.preventDefault();
            }
        });
    }

    updateBulkDeleteState();
</script>
@stop

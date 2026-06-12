@extends('adminlte::page')

@section('title', 'Mis Tareas')

@section('content_header')
    <h1 class="m-0">
        <i class="fas fa-tasks mr-2 text-primary"></i>
        Mis Tareas
        <small class="text-muted fs-6 ml-2">Tareas abiertas y seguimiento</small>
    </h1>
@stop

@section('content')

    {{-- Alerts --}}
    @foreach(['success','error','warning'] as $msg)
        @if(session($msg))
            <div class="alert alert-{{ $msg === 'error' ? 'danger' : $msg }} alert-dismissible fade show">
                {!! session($msg) !!}
                <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
            </div>
        @endif
    @endforeach

    {{-- Filtros --}}
    <div class="card card-outline card-secondary mb-3">
        <div class="card-body">
            <form method="GET" class="form-row align-items-end">
                <div class="col-12 col-md-4 mb-2">
                    <label class="mb-1 font-weight-bold">Buscar</label>
                    <input type="text" name="q" class="form-control form-control-sm"
                           value="{{ $filters['q'] ?? '' }}"
                           placeholder="Cliente, correo, pasaporte o titulo">
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <label class="mb-1 font-weight-bold">Estado</label>
                    <select name="status" class="form-control form-control-sm">
                        <option value="open" {{ ($filters['status'] ?? 'open') === 'open' ? 'selected' : '' }}>Abiertas</option>
                        <option value="pending" {{ ($filters['status'] ?? '') === 'pending' ? 'selected' : '' }}>Pendientes</option>
                        <option value="in_progress" {{ ($filters['status'] ?? '') === 'in_progress' ? 'selected' : '' }}>En curso</option>
                        <option value="completed" {{ ($filters['status'] ?? '') === 'completed' ? 'selected' : '' }}>Completadas</option>
                        <option value="canceled" {{ ($filters['status'] ?? '') === 'canceled' ? 'selected' : '' }}>Canceladas</option>
                        <option value="all" {{ ($filters['status'] ?? '') === 'all' ? 'selected' : '' }}>Todas</option>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-2">
                    <label class="mb-1 font-weight-bold">Vencimiento</label>
                    <input type="date" name="date" class="form-control form-control-sm"
                           value="{{ $filters['date'] ?? '' }}">
                </div>
                <div class="col-12 col-md-2 mb-2 d-flex">
                    <button class="btn btn-sm btn-primary mr-2 flex-fill">
                        <i class="fas fa-search mr-1"></i>Filtrar
                    </button>
                    <a href="{{ route('tasks.index') }}" class="btn btn-sm btn-outline-secondary" title="Limpiar filtros">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
            <div class="text-muted small mt-1">
                Por defecto se muestran todas tus tareas abiertas, sin limitar por fecha.
            </div>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row">
        @php
            $cards = [
                ['label'=>'Abiertas',    'value'=>$stats['open'],        'color'=>'info',    'icon'=>'list'],
                ['label'=>'Pendientes',  'value'=>$stats['pending'],     'color'=>'warning', 'icon'=>'clock'],
                ['label'=>'En curso',    'value'=>$stats['in_progress'], 'color'=>'primary', 'icon'=>'spinner'],
                ['label'=>'Completadas', 'value'=>$stats['completed'],   'color'=>'success', 'icon'=>'check-circle'],
                ['label'=>'Canceladas',  'value'=>$stats['canceled'],    'color'=>'danger',  'icon'=>'times-circle'],
            ];
        @endphp
        @foreach($cards as $card)
            <div class="col-6 col-md">
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

    {{-- Tabla --}}
    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-phone-alt mr-1"></i>
                {{ ($filters['status'] ?? 'open') === 'open' ? 'Tareas abiertas' : 'Tareas filtradas' }}
            </h3>
            <div class="card-tools text-muted small">
                {{ $tasks->count() }} resultado(s)
            </div>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Contacto</th>
                        <th>Titulo</th>
                        <th>Descripcion</th>
                        <th style="width:120px">Vence</th>
                        <th>Estado</th>
                        <th>Via</th>
                        <th>Venta</th>
                        <th>Etiquetas</th>
                        <th style="width:120px">Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php
                            $isSystemsTask = $task->isAssignedToSystems();
                            $badgeMap = [
                                'pending'     => 'warning',
                                'in_progress' => 'primary',
                                'completed'   => 'success',
                                'canceled'    => 'danger',
                            ];
                            $labelMap = [
                                'pending'     => 'Pendiente',
                                'in_progress' => 'En curso',
                                'completed'   => 'Completada',
                                'canceled'    => 'Cancelada',
                            ];
                            $waitingFollowUp = $task->isWaitingForFollowUp();
                        @endphp
                        <tr class="{{ $task->isClosed() ? 'text-muted' : '' }}">
                            <td>{{ $task->id }}</td>
                            <td>
                                <strong>{{ $task->contact?->name ?? '-' }}</strong>
                                @if($task->contact?->passport ?? false)
                                    <br><small class="text-muted">{{ $task->contact?->passport }}</small>
                                @endif
                            </td>
                            <td>{{ $task->title }}</td>
                            <td>{{ Str::limit($task->description, 50) }}</td>
                            <td>
                                @if($task->due_date)
                                    @php
                                        $isOverdue = ! $task->isClosed() && $task->due_date->lt(today());
                                        $isToday = $task->due_date->isToday();
                                    @endphp
                                    <span class="badge badge-{{ $isOverdue ? 'danger' : ($isToday ? 'info' : 'light') }}">
                                        {{ $task->due_date->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-{{ $waitingFollowUp ? 'info' : ($badgeMap[$task->status] ?? 'secondary') }}">
                                    {{ $waitingFollowUp ? 'En espera de seguimiento' : ($labelMap[$task->status] ?? $task->status) }}
                                </span>
                            </td>
                            <td>{{ implode(', ', $task->contactMethodLabels()) ?: '-' }}</td>
                            <td>{{ $waitingFollowUp ? 'Esperando respuesta' : ($task->saleStatusLabel() ?? '-') }}</td>
                            <td>
                                @foreach($task->sales_tags ?? [] as $tag)
                                    @php($tagMeta = \App\Models\Task::salesTagOptions()[$tag] ?? null)
                                    @if($tagMeta)
                                        <span class="badge badge-{{ $tagMeta['class'] }}">{{ $tagMeta['label'] }}</span>
                                    @endif
                                @endforeach
                            </td>
                            <td>
                                <a href="{{ route('tasks.show', $task) }}"
                                   class="btn btn-sm btn-{{ $task->isClosed() ? 'outline-secondary' : 'primary' }}">
                                    <i class="fas fa-{{ $task->isClosed() ? 'eye' : ($isSystemsTask ? 'check' : 'phone') }} mr-1"></i>
                                    {{ $task->isClosed() ? 'Ver' : ($isSystemsTask ? 'Resolver' : 'Cumplir') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                No hay tareas con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

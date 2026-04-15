@extends('adminlte::page')

@section('title', 'Mis Tareas')

@section('content_header')
    <h1 class="m-0">
        <i class="fas fa-tasks mr-2 text-primary"></i>
        Mis Tareas
        <small class="text-muted fs-6 ml-2">{{ $date->format('d/m/Y') }}</small>
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

    {{-- Filtro de fecha --}}
    <div class="card card-outline card-secondary mb-3">
        <div class="card-body py-2">
            <form method="GET" class="form-inline">
                <label class="mr-2 font-weight-bold">Ver fecha:</label>
                <input type="date" name="date" class="form-control form-control-sm mr-2"
                       value="{{ $date->toDateString() }}">
                <button class="btn btn-sm btn-primary">
                    <i class="fas fa-search mr-1"></i>Buscar
                </button>
            </form>
        </div>
    </div>

    {{-- Stats --}}
    <div class="row">
        @php
            $cards = [
                ['label'=>'Total',       'value'=>$stats['total'],       'color'=>'info',    'icon'=>'list'],
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
                <i class="fas fa-phone-alt mr-1"></i>Tareas del día
            </h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover table-striped mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th style="width:60px">#</th>
                        <th>Contacto</th>
                        <th>Título</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th style="width:120px">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tasks as $task)
                        @php
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
                        @endphp
                        <tr class="{{ $task->isClosed() ? 'text-muted' : '' }}">
                            <td>{{ $task->id }}</td>
                            <td>
                                <strong>{{ $task->contact?->name ?? '—' }}</strong>
                                @if($task->contact?->passport ?? false)
                                    <br><small class="text-muted">{{ $task->contact?->passport }}</small>
                                @endif
                            </td>
                            <td>{{ $task->title }}</td>
                            <td>{{ Str::limit($task->description, 50) }}</td>
                            <td>
                                <span class="badge badge-{{ $badgeMap[$task->status] ?? 'secondary' }}">
                                    {{ $labelMap[$task->status] ?? $task->status }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('tasks.show', $task) }}"
                                   class="btn btn-sm btn-{{ $task->isClosed() ? 'outline-secondary' : 'primary' }}">
                                    <i class="fas fa-{{ $task->isClosed() ? 'eye' : 'phone' }} mr-1"></i>
                                    {{ $task->isClosed() ? 'Ver' : 'Cumplir' }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                No hay tareas asignadas para esta fecha.
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

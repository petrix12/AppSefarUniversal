@extends('adminlte::page')

@section('title', 'Monday · Actividad')

@section('content_header')
    <div><h1 class="m-0"><i class="fas fa-exchange-alt text-primary mr-2"></i>Actividad de Monday</h1><small class="text-muted">Webhooks, sincronizaciones y movimientos detectados</small></div>
@stop

@section('content')
    @include('admin.monday.partials.nav')

    <div class="card card-outline card-primary">
        <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-bolt mr-2"></i>Eventos recibidos</h3></div>
        <div class="card-body border-bottom py-2">
            <form class="form-inline" method="GET">
                <select name="event_status" class="form-control form-control-sm mr-2"><option value="">Todos los estados</option>@foreach(['pending','processing','processed','failed'] as $status)<option value="{{ $status }}" @selected($eventStatus === $status)>{{ $status }}</option>@endforeach</select>
                <select name="event_type" class="form-control form-control-sm mr-2"><option value="">Todos los eventos</option>@foreach($eventTypes as $type)<option value="{{ $type }}" @selected($eventType === $type)>{{ $type }}</option>@endforeach</select>
                <button class="btn btn-primary btn-sm"><i class="fas fa-filter mr-1"></i>Filtrar</button>
                <a href="{{ route('admin.monday.activity') }}" class="btn btn-default btn-sm ml-1"><i class="fas fa-times"></i></a>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead><tr><th>Recibido</th><th>Evento</th><th>Estado</th><th>Tablero</th><th>Elemento</th><th>Detalle</th></tr></thead>
                <tbody>
                @forelse($events as $event)
                    <tr>
                        <td class="text-nowrap">{{ $event->created_at?->format('d/m/Y H:i:s') }}</td>
                        <td><code>{{ $event->event_type ?: '—' }}</code></td>
                        <td><span class="badge badge-{{ $event->status === 'processed' ? 'success' : ($event->status === 'failed' ? 'danger' : 'warning') }}">{{ $event->status }}</span></td>
                        <td>{{ $boardsById[$event->board_id] ?? $event->board_id ?: '—' }}</td>
                        <td>@if($event->item_id)<a href="{{ route('admin.monday.items.show', $event->item_id) }}">#{{ $event->item_id }}</a>@else—@endif</td>
                        <td>@if($event->error_message)<span class="text-danger" title="{{ $event->error_message }}">{{ \Illuminate\Support\Str::limit($event->error_message, 90) }}</span>@else<span class="text-muted">{{ $event->processed_at ? 'Procesado '.$event->processed_at->format('H:i:s') : 'Esperando procesamiento' }}</span>@endif</td>
                    </tr>
                @empty<tr><td colspan="6" class="text-center text-muted py-3">No hay eventos para estos filtros.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer d-flex justify-content-center">{{ $events->links() }}</div>
    </div>

    <div class="card card-outline card-info">
        <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-sync-alt mr-2"></i>Sincronizaciones</h3></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Inicio</th><th>Alcance</th><th>Estado</th><th>Tableros</th><th>Items</th><th>Subitems</th><th>Valores</th><th>API</th><th>Error</th></tr></thead><tbody>
        @forelse($runs as $run)<tr><td class="text-nowrap">{{ $run->started_at?->format('d/m/Y H:i:s') ?: '—' }}</td><td>{{ $run->scope }}{{ $run->board_id ? ' · '.$run->board_id : '' }}</td><td><span class="badge badge-{{ $run->status === 'completed' ? 'success' : ($run->status === 'failed' ? 'danger' : 'warning') }}">{{ $run->status }}</span></td><td>{{ $run->boards }}</td><td>{{ $run->items }}</td><td>{{ $run->subitems }}</td><td>{{ $run->values }}</td><td>{{ $run->requests }}</td><td class="text-danger" title="{{ $run->error_message }}">{{ \Illuminate\Support\Str::limit($run->error_message, 70) }}</td></tr>
        @empty<tr><td colspan="9" class="text-center text-muted py-3">Sin ejecuciones.</td></tr>@endforelse
        </tbody></table></div><div class="card-footer d-flex justify-content-center">{{ $runs->links() }}</div>
    </div>

    <div class="card card-outline card-warning">
        <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-route mr-2"></i>Movimientos de elementos</h3></div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr><th>Fecha</th><th>Elemento</th><th>Origen</th><th>Destino</th><th>Grupo origen</th><th>Grupo destino</th><th>Detectado por</th></tr></thead><tbody>
        @forelse($movements as $movement)<tr><td class="text-nowrap">{{ $movement->moved_at?->format('d/m/Y H:i:s') }}</td><td><a href="{{ route('admin.monday.items.show', $movement->item_id) }}">{{ $movement->item?->name ?: '#'.$movement->item_id }}</a></td><td>{{ $boardsById[$movement->from_board_id] ?? $movement->from_board_id ?: '—' }}</td><td>{{ $boardsById[$movement->to_board_id] ?? $movement->to_board_id ?: '—' }}</td><td><code>{{ $movement->from_group_id ?: '—' }}</code></td><td><code>{{ $movement->to_group_id ?: '—' }}</code></td><td><span class="badge badge-light border">{{ $movement->source }}</span></td></tr>
        @empty<tr><td colspan="7" class="text-center text-muted py-3">Todavía no se han detectado movimientos.</td></tr>@endforelse
        </tbody></table></div><div class="card-footer d-flex justify-content-center">{{ $movements->links() }}</div>
    </div>
@stop

@section('css')
<style>.monday-admin-nav{gap:.35rem}.monday-admin-nav .nav-link{color:#495057;background:#fff;border:1px solid #dee2e6}.monday-admin-nav .nav-link.active{color:#fff;background:#0073ea;border-color:#0073ea}.table td,.table th{vertical-align:middle}</style>
@stop


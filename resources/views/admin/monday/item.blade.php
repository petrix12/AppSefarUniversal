@extends('adminlte::page')

@section('title', 'Monday · '.$item->name)

@section('content_header')
    <div>
        <a href="{{ route('admin.monday.boards.show', $item->board_id) }}" class="small"><i class="fas fa-arrow-left mr-1"></i>{{ $item->board?->name ?: 'Volver al tablero' }}</a>
        <h1 class="m-0 mt-1">{{ $item->name ?: 'Elemento '.$item->id }}</h1>
        <small class="text-muted">Elemento #{{ $item->id }} {{ $item->parent_item_id ? '· subelemento de #'.$item->parent_item_id : '' }}</small>
    </div>
@stop

@section('content')
    @include('admin.monday.partials.nav')
    <div class="row">
        <div class="col-lg-8">
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-columns mr-2"></i>Valores</h3></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead><tr><th>Columna</th><th>Tipo</th><th>Valor</th><th>ID técnico</th></tr></thead>
                        <tbody>
                        @foreach($item->values->sortBy(fn($value) => $columns->get($value->monday_column_id)?->id ?? PHP_INT_MAX) as $value)
                            @php
                                $column = $columns->get($value->monday_column_id);
                            @endphp
                            <tr>
                                <td class="font-weight-bold">{{ $column?->title ?: $value->monday_column_id }}</td>
                                <td><span class="badge badge-light border">{{ $column?->type ?: $value->type ?: '—' }}</span></td>
                                <td class="text-break">{{ $value->text !== null && $value->text !== '' ? $value->text : '—' }}</td>
                                <td><code>{{ $value->monday_column_id }}</code></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if($subitems->total() > 0)
                <div class="card card-outline card-info">
                    <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-level-down-alt mr-2"></i>Subelementos</h3></div>
                    <div class="list-group list-group-flush">
                        @foreach($subitems as $subitem)
                            <a href="{{ route('admin.monday.items.show', $subitem->id) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <span><i class="far fa-square text-muted mr-2"></i>{{ $subitem->name }}</span>
                                <span class="badge badge-light">{{ $subitem->populated_values_count }} valores</span>
                            </a>
                        @endforeach
                    </div>
                    <div class="card-footer">
                        <div class="small text-muted mb-2">Mostrando {{ number_format($subitems->firstItem()) }}–{{ number_format($subitems->lastItem()) }} de {{ number_format($subitems->total()) }} subelementos</div>
                        {{ $subitems->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            @endif

            <div class="card card-outline card-secondary collapsed-card">
                <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-code mr-2"></i>Datos técnicos</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fas fa-plus"></i></button></div></div>
                <div class="card-body"><pre class="mb-0 small">{{ json_encode($item->raw_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title font-weight-bold">Información</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Estado</dt><dd class="col-7"><span class="badge badge-{{ $item->deleted_at ? 'danger' : 'success' }}">{{ $item->state ?: 'active' }}</span></dd>
                        <dt class="col-5">Tablero</dt><dd class="col-7"><a href="{{ route('admin.monday.boards.show', $item->board_id) }}">{{ $item->board?->name ?: $item->board_id }}</a></dd>
                        <dt class="col-5">Grupo</dt><dd class="col-7"><code>{{ $item->group_id ?: '—' }}</code></dd>
                        <dt class="col-5">Creado</dt><dd class="col-7">{{ $item->monday_created_at?->format('d/m/Y H:i') ?: '—' }}</dd>
                        <dt class="col-5">Actualizado</dt><dd class="col-7">{{ $item->monday_updated_at?->format('d/m/Y H:i') ?: '—' }}</dd>
                        <dt class="col-5">Replicado</dt><dd class="col-7">{{ $item->last_synced_at?->format('d/m/Y H:i:s') ?: '—' }}</dd>
                    </dl>
                </div>
            </div>
            <div class="card card-outline card-warning">
                <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-route mr-2"></i>Movimientos</h3></div>
                <div class="card-body p-0">
                    <div class="timeline timeline-inverse p-3 mb-0">
                        @forelse($item->movements->sortByDesc('moved_at') as $movement)
                            <div><i class="fas fa-exchange-alt bg-warning"></i><div class="timeline-item"><span class="time"><i class="far fa-clock"></i> {{ $movement->moved_at?->format('d/m/Y H:i') }}</span><h3 class="timeline-header">{{ $movement->source }}</h3><div class="timeline-body small">{{ $boardsById[$movement->from_board_id] ?? $movement->from_board_id ?: '—' }} <i class="fas fa-arrow-right mx-1"></i> {{ $boardsById[$movement->to_board_id] ?? $movement->to_board_id ?: '—' }}<br><span class="text-muted">Grupo: {{ $movement->from_group_id ?: '—' }} → {{ $movement->to_group_id ?: '—' }}</span></div></div></div>
                        @empty
                            <div class="text-muted text-center py-3">Sin movimientos registrados.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
<style>
    .monday-admin-nav { gap:.35rem; }.monday-admin-nav .nav-link{color:#495057;background:#fff;border:1px solid #dee2e6}.monday-admin-nav .nav-link.active{color:#fff;background:#0073ea;border-color:#0073ea}
    pre { max-height:500px; overflow:auto; background:#17212b; color:#d9e2ec; border-radius:6px; padding:1rem; }
</style>
@stop


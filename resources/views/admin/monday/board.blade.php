@extends('adminlte::page')

@section('title', 'Monday · '.$board->name)

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <a href="{{ route('admin.monday.index') }}" class="small"><i class="fas fa-arrow-left mr-1"></i>Todos los tableros</a>
            <h1 class="m-0 mt-1">{{ $board->name ?: 'Tablero '.$board->id }}</h1>
            <small class="text-muted">{{ $board->workspace_name ?: 'Sin workspace' }} · ID {{ $board->id }} · réplica local</small>
        </div>
        <div class="mt-2 mt-md-0 text-md-right">
            <span class="badge badge-success px-3 py-2"><i class="fas fa-database mr-1"></i> Local</span>
            <div class="small text-muted mt-1">{{ $board->last_synced_at ? 'Actualizado '.$board->last_synced_at->diffForHumans() : 'Sin sincronizar' }}</div>
        </div>
    </div>
@stop

@section('content')
    @include('admin.monday.partials.nav')

    <div class="card card-outline card-primary mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row align-items-end">
                <div class="col-md-4 mb-2">
                    <label class="small mb-1">Buscar dentro del tablero</label>
                    <input type="search" class="form-control form-control-sm" name="q" value="{{ $search }}" placeholder="Elemento, ID o valor de columna">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="small mb-1">Grupo</label>
                    <select name="group" class="form-control form-control-sm">
                        <option value="">Todos los grupos</option>
                        @foreach($groups as $group)
                            <option value="{{ $group->monday_id }}" @selected($groupId === $group->monday_id)>{{ $group->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">Estado</label>
                    <select name="state" class="form-control form-control-sm">
                        <option value="active" @selected($state === 'active')>Activos</option>
                        <option value="archived" @selected($state === 'archived')>Archivados</option>
                        <option value="deleted" @selected($state === 'deleted')>Eliminados</option>
                        <option value="all" @selected($state === 'all')>Todos</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2 d-flex">
                    <button class="btn btn-primary btn-sm flex-grow-1"><i class="fas fa-filter mr-1"></i>Aplicar</button>
                    <a href="{{ route('admin.monday.boards.show', $board) }}" class="btn btn-default btn-sm ml-1" title="Limpiar"><i class="fas fa-times"></i></a>
                </div>
            </form>
        </div>
    </div>

    @php
        $itemsByGroup = $items->groupBy(fn ($row) => (string) ($row->group_id ?? ''));
        $visibleGroups = $groupId !== '' ? $groups->where('monday_id', $groupId) : $groups;
    @endphp

    <div class="monday-board-shell">
        @foreach($visibleGroups as $group)
            @php
                $groupItems = $itemsByGroup->get((string) $group->monday_id, collect());
            @endphp
            <section class="monday-group mb-4">
                <div class="monday-group-title" style="--group-color: {{ $group->color ?: '#579bfc' }}">
                    <span class="monday-group-dot"></span>
                    <strong>{{ $group->title ?: 'Grupo sin nombre' }}</strong>
                    <span class="badge badge-light ml-2">{{ number_format((int) ($groupCounts[$group->monday_id] ?? 0)) }}</span>
                </div>
                <div class="table-responsive monday-grid-wrap">
                    <table class="table table-bordered table-sm monday-grid mb-0">
                        <thead><tr><th class="item-column sticky-col">Elemento</th>@foreach($columns as $column)<th title="{{ $column->type }} · {{ $column->monday_id }}">{{ $column->title ?: $column->monday_id }}</th>@endforeach<th>Actualizado</th></tr></thead>
                        <tbody>
                        @forelse($groupItems as $row)
                            @include('admin.monday.partials.item-row', ['row' => $row, 'columns' => $columns, 'subitem' => false])
                            @foreach($row->subitems as $subitem)
                                @include('admin.monday.partials.item-row', ['row' => $subitem, 'columns' => $columns, 'subitem' => true])
                            @endforeach
                        @empty
                            <tr><td colspan="{{ $columns->count() + 2 }}" class="text-muted text-center py-3">Este grupo no tiene elementos para los filtros seleccionados.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        @php
            $ungrouped = $itemsByGroup->get('', collect());
        @endphp
        @if($ungrouped->isNotEmpty())
            <section class="monday-group mb-4">
                <div class="monday-group-title" style="--group-color:#6c757d"><span class="monday-group-dot"></span><strong>Sin grupo</strong></div>
                <div class="table-responsive monday-grid-wrap"><table class="table table-bordered table-sm monday-grid mb-0"><thead><tr><th class="item-column sticky-col">Elemento</th>@foreach($columns as $column)<th>{{ $column->title ?: $column->monday_id }}</th>@endforeach<th>Actualizado</th></tr></thead><tbody>
                @foreach($ungrouped as $row)
                    @include('admin.monday.partials.item-row', ['row' => $row, 'columns' => $columns, 'subitem' => false])
                    @foreach($row->subitems as $subitem)
                        @include('admin.monday.partials.item-row', ['row' => $subitem, 'columns' => $columns, 'subitem' => true])
                    @endforeach
                @endforeach
                </tbody></table></div>
            </section>
        @endif
    </div>

    <div class="text-muted small mb-4">Mostrando {{ number_format($items->count()) }} elementos principales</div>
@stop

@section('css')
<style>
    .monday-admin-nav { gap:.35rem; }
    .monday-admin-nav .nav-link { color:#495057; background:#fff; border:1px solid #dee2e6; }
    .monday-admin-nav .nav-link.active { color:#fff; background:#0073ea; border-color:#0073ea; }
    .monday-board-shell { min-height:240px; }
    .monday-group-title { display:flex; align-items:center; color:var(--group-color); font-size:1.05rem; margin:0 0 .45rem .15rem; }
    .monday-group-dot { width:9px; height:28px; border-radius:4px; background:var(--group-color); margin-right:.55rem; }
    .monday-grid-wrap { background:#fff; border-radius:7px; box-shadow:0 1px 4px rgba(0,0,0,.06); }
    .monday-grid { width:max-content; min-width:100%; font-size:.8rem; }
    .monday-grid thead th { background:#f6f7f9; color:#4b4f56; font-weight:600; min-width:145px; max-width:220px; height:38px; vertical-align:middle; white-space:nowrap; }
    .monday-grid td { min-width:145px; max-width:220px; height:38px; vertical-align:middle; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .monday-grid .item-column { min-width:260px; max-width:320px; }
    .monday-grid .sticky-col { position:sticky; left:0; z-index:2; background:#fff; box-shadow:2px 0 3px rgba(0,0,0,.05); }
    .monday-grid thead .sticky-col { z-index:3; background:#f6f7f9; }
    .monday-grid tr:hover td, .monday-grid tr:hover .sticky-col { background:#f4f9ff; }
    .monday-grid .subitem-row td { background:#fbfbfc; }
    .monday-grid .subitem-row .sticky-col { padding-left:2rem; background:#fbfbfc; }
    .monday-item-link { color:#20252a; font-weight:500; }
    .monday-item-link:hover { color:#0073ea; }
    .monday-cell-text { display:block; overflow:hidden; text-overflow:ellipsis; }
    .monday-status { display:block; margin:-.3rem; padding:.35rem .5rem; color:#fff; text-align:center; border-radius:3px; }
</style>
@stop


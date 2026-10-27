@extends('adminlte::page')

@section('title', 'Monday · Tableros')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <div>
            <h1 class="m-0"><i class="fas fa-table text-primary mr-2"></i>Réplica local de Monday</h1>
            <small class="text-muted">Vista operativa servida desde la base de datos de la aplicación</small>
        </div>
        <span class="badge badge-success px-3 py-2"><i class="fas fa-database mr-1"></i> Base local</span>
    </div>
@stop

@section('content')
    @include('admin.monday.partials.nav')

    @if (! $installed)
        <div class="alert alert-warning">
            <h5><i class="icon fas fa-exclamation-triangle"></i> La réplica aún no está instalada</h5>
            Ejecuta la migración de Monday antes de utilizar esta pantalla.
        </div>
    @else
        <div class="row">
            <div class="col-6 col-lg-2">
                <div class="small-box bg-info monday-metric"><div class="inner"><h3>{{ number_format($metrics['boards']) }}</h3><p>Tableros</p></div><div class="icon"><i class="fas fa-table"></i></div></div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="small-box bg-primary monday-metric"><div class="inner"><h3>{{ number_format($metrics['items']) }}</h3><p>Elementos</p></div><div class="icon"><i class="fas fa-list"></i></div></div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="small-box bg-indigo monday-metric"><div class="inner"><h3>{{ number_format($metrics['subitems']) }}</h3><p>Subelementos</p></div><div class="icon"><i class="fas fa-level-down-alt"></i></div></div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="small-box bg-teal monday-metric"><div class="inner"><h3>{{ number_format($metrics['values']) }}</h3><p>Valores</p></div><div class="icon"><i class="fas fa-th"></i></div></div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="small-box {{ $metrics['webhook_pending'] ? 'bg-warning' : 'bg-success' }} monday-metric">
                    <div class="inner"><h3>{{ number_format($metrics['webhook_pending']) }}</h3><p>Eventos pendientes o fallidos</p></div>
                    <div class="icon"><i class="fas fa-bolt"></i></div>
                    <a href="{{ route('admin.monday.activity') }}" class="small-box-footer">Revisar actividad <i class="fas fa-arrow-circle-right"></i></a>
                </div>
            </div>
        </div>

        <div class="card card-outline card-primary">
            <div class="card-header border-0">
                <div class="d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title font-weight-bold"><i class="fas fa-layer-group mr-2"></i>Tableros disponibles</h3>
                    <form method="GET" class="form-inline mt-2 mt-md-0">
                        <div class="input-group input-group-sm">
                            <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="Nombre, ID o workspace">
                            <div class="input-group-append">
                                <button class="btn btn-primary" title="Buscar"><i class="fas fa-search"></i></button>
                                @if(request('q'))
                                    <a href="{{ route('admin.monday.index') }}" class="btn btn-default" title="Limpiar"><i class="fas fa-times"></i></a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body pt-2">
                <div class="row">
                    @forelse($boards as $board)
                        <div class="col-md-6 col-xl-4 d-flex">
                            <a href="{{ route('admin.monday.boards.show', $board) }}" class="monday-board-card w-100">
                                <div class="monday-board-accent"></div>
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="pr-3">
                                            <h5 class="font-weight-bold text-dark mb-1">{{ $board->name ?: 'Tablero sin nombre' }}</h5>
                                            <div class="text-muted small">ID {{ $board->id }}</div>
                                        </div>
                                        <span class="badge badge-light border">{{ $board->kind ?: 'board' }}</span>
                                    </div>
                                    <div class="small text-muted mt-2 text-truncate">
                                        <i class="far fa-folder mr-1"></i>{{ $board->workspace_name ?: 'Sin workspace' }}
                                    </div>
                                    <div class="d-flex monday-board-counts mt-3">
                                        <span><strong>{{ number_format($board->items_total) }}</strong><small>elementos</small></span>
                                        <span><strong>{{ number_format($board->subitems_total) }}</strong><small>subitems</small></span>
                                        <span><strong>{{ number_format($board->groups_count) }}</strong><small>grupos</small></span>
                                        <span><strong>{{ number_format($board->columns_count) }}</strong><small>columnas</small></span>
                                    </div>
                                    <div class="mt-3 small {{ $board->last_synced_at ? 'text-success' : 'text-warning' }}">
                                        <i class="fas fa-sync-alt mr-1"></i>
                                        {{ $board->last_synced_at ? 'Actualizado '.$board->last_synced_at->diffForHumans() : 'Pendiente de sincronización' }}
                                    </div>
                                </div>
                            </a>
                        </div>
                    @empty
                        <div class="col-12"><div class="empty-state"><i class="fas fa-search"></i><p>No se encontraron tableros.</p></div></div>
                    @endforelse
                </div>
                <div class="d-flex justify-content-center mt-3">{{ $boards->links() }}</div>
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title font-weight-bold"><i class="fas fa-history mr-2"></i>Sincronizaciones recientes</h3></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>Inicio</th><th>Alcance</th><th>Estado</th><th>Tableros</th><th>Elementos</th><th>Subitems</th><th>Solicitudes</th><th>Duración</th></tr></thead>
                    <tbody>
                    @forelse($recentRuns as $run)
                        <tr>
                            <td class="text-nowrap">{{ optional($run->started_at)->format('d/m/Y H:i:s') ?: '—' }}</td>
                            <td>{{ $run->scope }}{{ $run->board_id ? ' · '.$run->board_id : '' }}</td>
                            <td><span class="badge badge-{{ $run->status === 'completed' ? 'success' : ($run->status === 'failed' ? 'danger' : 'warning') }}">{{ $run->status }}</span></td>
                            <td>{{ number_format($run->boards) }}</td><td>{{ number_format($run->items) }}</td><td>{{ number_format($run->subitems) }}</td><td>{{ number_format($run->requests) }}</td>
                            <td>{{ $run->started_at && $run->finished_at ? $run->started_at->diffForHumans($run->finished_at, true) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">Todavía no hay ejecuciones registradas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@stop

@section('css')
<style>
    .monday-admin-nav { gap: .35rem; }
    .monday-admin-nav .nav-link { color:#495057; background:#fff; border:1px solid #dee2e6; }
    .monday-admin-nav .nav-link.active { color:#fff; background:#0073ea; border-color:#0073ea; }
    .monday-metric { min-height:120px; border-radius:10px; overflow:hidden; }
    .monday-metric .inner h3 { font-size:1.75rem; }
    .monday-board-card { position:relative; display:block; overflow:hidden; margin-bottom:1rem; background:#fff; border:1px solid #e3e7eb; border-radius:10px; box-shadow:0 2px 6px rgba(0,0,0,.04); transition:.16s ease; }
    .monday-board-card:hover { text-decoration:none; transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.09); border-color:#b8d9ff; }
    .monday-board-accent { height:5px; background:linear-gradient(90deg,#0073ea,#00c875,#a25ddc); }
    .monday-board-counts { gap:1.2rem; }
    .monday-board-counts span { display:flex; flex-direction:column; color:#343a40; }
    .monday-board-counts small { color:#7c858d; font-size:.7rem; }
    .empty-state { text-align:center; padding:3rem; color:#8b949e; }
    .empty-state i { font-size:2rem; margin-bottom:.75rem; }
</style>
@stop


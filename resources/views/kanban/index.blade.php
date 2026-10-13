@extends('adminlte::page')

@section('title', 'Tableros Kanban')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between flex-wrap">
        <div>
            <h1 class="m-0"><i class="fas fa-columns text-primary mr-2"></i>Tableros</h1>
            <p class="text-muted mb-0 mt-1">Organiza el trabajo del equipo en columnas y tarjetas.</p>
        </div>
        <button class="btn btn-primary mt-2 mt-md-0" data-toggle="modal" data-target="#newBoardModal">
            <i class="fas fa-plus mr-1"></i>Nuevo tablero
        </button>
        @can('administrador')
            <a class="btn btn-outline-secondary mt-2 mt-md-0 ml-md-2" href="{{ route('kanban.org-chart') }}"><i class="fas fa-sitemap mr-1"></i>Organigrama</a>
        @endcan
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="close" data-dismiss="alert"><span>&times;</span></button></div>
    @endif

    @if($boards->isEmpty())
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body text-center py-5">
                <i class="fas fa-columns fa-3x text-muted mb-3"></i>
                <h4>Tu primer tablero empieza aquí</h4>
                <p class="text-muted">Crea un tablero y organiza el trabajo del equipo con tarjetas que puedes mover entre columnas.</p>
                <button class="btn btn-primary" data-toggle="modal" data-target="#newBoardModal">Crear tablero</button>
            </div>
        </div>
    @else
        <div class="row mt-3">
            @foreach($boards as $board)
                <div class="col-12 col-md-6 col-xl-4">
                    <a href="{{ route('kanban.show', $board) }}" class="kanban-board-link">
                        <div class="card kanban-board-tile shadow-sm">
                            <div class="card-body">
                                <div class="d-flex align-items-start justify-content-between">
                                    <div>
                                        <h4 class="mb-1">{{ $board->name }}</h4>
                                        <p class="text-muted mb-3">{{ $board->description ?: 'Tablero del equipo' }}</p>
                                    </div>
                                    <i class="fas fa-arrow-right text-muted"></i>
                                </div>
                                <div class="small text-muted">
                                    <span class="mr-3"><i class="fas fa-columns mr-1"></i>{{ $board->columns_count }} columnas</span>
                                    <span><i class="far fa-sticky-note mr-1"></i>{{ $board->columns->sum(fn ($column) => $column->cards->count()) }} tarjetas</span>
                                </div>
                                <div class="small text-muted mt-2">Creado por {{ $board->creator?->name ?? 'Equipo' }}</div>
                            </div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <div class="modal fade" id="newBoardModal" tabindex="-1" role="dialog" aria-labelledby="newBoardLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('kanban.store') }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="newBoardLabel">Crear tablero</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <div class="form-group"><label for="boardName">Nombre</label><input class="form-control" id="boardName" name="name" maxlength="120" required placeholder="Ej. Lanzamiento de producto"></div>
                    <div class="form-group mb-0"><label for="boardDescription">Descripción <span class="text-muted font-weight-normal">(opcional)</span></label><textarea class="form-control" id="boardDescription" name="description" rows="3" maxlength="2000" placeholder="¿Qué va a organizar este tablero?"></textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button class="btn btn-primary">Crear tablero</button></div>
            </form>
        </div>
    </div>
@stop

@section('css')
<style>
    .kanban-board-link { color: inherit; text-decoration: none !important; }
    .kanban-board-tile { border-top: 3px solid #176b87; transition: transform .15s ease, box-shadow .15s ease; }
    .kanban-board-link:hover .kanban-board-tile { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.12) !important; }
</style>
@stop

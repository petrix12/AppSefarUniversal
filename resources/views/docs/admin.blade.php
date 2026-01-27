@extends('adminlte::page')

@section('title', 'Administrar Documentos')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Administrar Documentos</h1>
    </div>
@stop

@section('content')

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @can('docs.upload')
        <div class="card mb-3">
            <div class="card-header">
                <b>Subir documento</b>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('docs.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label>Título</label>
                            <input type="text" name="title" class="form-control" required value="{{ old('title') }}">
                        </div>

                        <div class="col-md-6 mb-2">
                            <label>Categoría (carpeta)</label>
                            <input type="text" name="category" class="form-control" placeholder="guias, manuales, scripts..." value="{{ old('category') }}">
                        </div>

                        <div class="col-md-12 mb-2">
                            <label>Descripción (opcional)</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
                        </div>

                        <div class="col-md-6 mb-2">
                            <label>Visibilidad</label>
                            <select name="visibility" class="form-control" required>
                                <option value="coordventas" @selected(old('visibility')=='coordventas')>Coord. Ventas</option>
                                <option value="todos" @selected(old('visibility')=='todos')>Todos</option>
                                <option value="admins" @selected(old('visibility')=='admins')>Solo Admin</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-2">
                            <label>Archivo</label>
                            <input type="file" name="file" class="form-control" required>
                            <small class="text-muted">Máx 50MB</small>
                        </div>

                        <div class="col-md-12 mt-2">
                            <button class="btn btn-primary">
                                <i class="fas fa-upload"></i> Subir
                            </button>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    @endcan

    {{-- Filtros --}}
    <div class="card p-4">
        <form method="GET" action="{{ route('docs.admin') }}" class="mb-3">
            <div class="row">
                <div class="col-md-6 mb-2">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Buscar..." value="{{ request('q') }}">
                        <div class="input-group-append">
                            <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                    </div>
                </div>

                <div class="col-md-4 mb-2">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">Todas las categorías</option>
                        @foreach($categories as $c)
                            <option value="{{ $c }}" @selected(request('category')==$c)>{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 mb-2">
                    <a href="{{ route('docs.admin') }}" class="btn btn-outline-secondary btn-block">Limpiar</a>
                </div>
            </div>
        </form>
    </div>

    {{-- Tabla --}}
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Categoría</th>
                        <th>Visibilidad</th>
                        <th>Subido</th>
                        <th class="text-right"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($docs as $doc)
                    <tr>
                        <td>
                            <b>{{ $doc->title }}</b><br>
                            <small class="text-muted">{{ $doc->original_name }}</small>
                        </td>
                        <td>{{ ucfirst($doc->category ?? 'general') }}</td>
                        <td><span class="badge badge-secondary">{{ $doc->visibility }}</span></td>
                        <td>{{ optional($doc->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="text-right">
                            <a href="{{ route('docs.download', $doc->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>

                            @can('docs.delete')
                                <form method="POST" action="{{ route('docs.destroy', $doc->id) }}" style="display:inline-block"
                                      onsubmit="return confirm('¿Eliminar este documento?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted p-4">No hay documentos</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if($docs->hasPages())
            <div class="card-footer">
                {{ $docs->links() }}
            </div>
        @endif
    </div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop

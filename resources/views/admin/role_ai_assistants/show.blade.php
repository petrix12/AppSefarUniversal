@extends('adminlte::page')

@section('title', $assistant->name)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-0">{{ $assistant->name }}</h1>
            <small class="text-muted">Rol: {{ $assistant->role?->name ?? 'Sin rol' }}</small>
        </div>
        <a href="{{ route('admin.role-ai-assistants.index') }}" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <strong>Configuracion del bot</strong>
                </div>
                <form action="{{ route('admin.role-ai-assistants.update', $assistant) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="card-body">
                        <div class="form-group">
                            <label for="assistant-name">Nombre</label>
                            <input id="assistant-name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $assistant->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="assistant-model">Modelo OpenRouter</label>
                            <input id="assistant-model" name="model" class="form-control @error('model') is-invalid @enderror" value="{{ old('model', $assistant->model) }}" placeholder="{{ config('services.openrouter.model') }}">
                            @error('model')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="assistant-instructions">Instrucciones base</label>
                            <textarea id="assistant-instructions" name="instructions" rows="9" class="form-control @error('instructions') is-invalid @enderror">{{ old('instructions', $assistant->instructions) }}</textarea>
                            @error('instructions')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <input type="hidden" name="is_active" value="0">
                        <div class="custom-control custom-switch mb-2">
                            <input type="checkbox" class="custom-control-input" id="assistant-active" name="is_active" value="1" @checked(old('is_active', $assistant->is_active))>
                            <label class="custom-control-label" for="assistant-active">Activo</label>
                        </div>

                        <input type="hidden" name="training_enabled" value="0">
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="assistant-training" name="training_enabled" value="1" @checked(old('training_enabled', $assistant->training_enabled))>
                            <label class="custom-control-label" for="assistant-training">Permitir entrenamiento por usuarios del rol</label>
                        </div>
                    </div>
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar cambios
                        </button>
                    </div>
                </form>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong>Personas con acceso</strong>
                    <span class="badge badge-secondary ml-1">{{ $accessUsers->count() }}</span>
                </div>
                <div class="card-body table-responsive p-0" style="max-height: 360px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($accessUsers as $user)
                                <tr>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted">No hay usuarios internos con este rol.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Contexto entrenado</strong>
                    <span class="text-muted">{{ $knowledgeEntries->total() }} entrada(s)</span>
                </div>
                <div class="card-body table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Titulo</th>
                                <th>Contenido</th>
                                <th>Usuario</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($knowledgeEntries as $entry)
                                <tr>
                                    <td>{{ $entry->title ?: 'Sin titulo' }}</td>
                                    <td style="min-width: 280px;">
                                        <div>{{ $entry->excerpt(220) }}</div>
                                        <details class="mt-1">
                                            <summary class="text-primary">Ver completo</summary>
                                            <pre class="role-ai-context-pre">{{ $entry->content }}</pre>
                                        </details>
                                    </td>
                                    <td>{{ $entry->createdBy?->name ?? 'Sistema' }}</td>
                                    <td>
                                        @if($entry->status === 'active')
                                            <span class="badge badge-success">Activo</span>
                                        @else
                                            <span class="badge badge-secondary">Archivado</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($entry->created_at)->format('d/m/Y H:i') }}</td>
                                    <td class="text-right">
                                        @if($entry->status === 'active')
                                            <form action="{{ route('admin.role-ai-assistants.knowledge.archive', [$assistant, $entry]) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-warning">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('admin.role-ai-assistants.knowledge.restore', [$assistant, $entry]) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">Este bot aun no tiene contexto entrenado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    {{ $knowledgeEntries->links() }}
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .role-ai-context-pre {
            background: #f8fafb;
            border: 1px solid #e1e7eb;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 8px;
            max-height: 280px;
            overflow: auto;
            padding: 10px;
            white-space: pre-wrap;
        }
    </style>
@stop

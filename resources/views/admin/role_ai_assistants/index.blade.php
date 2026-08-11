@extends('adminlte::page')

@section('title', 'Asistentes IA por rol')

@section('content_header')
    <h1>Asistentes IA por rol</h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="alert alert-info">
        Cada rol interno tiene un asistente propio. El rol Cliente queda excluido.
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Asistente</th>
                        <th>Rol</th>
                        <th>Modelo</th>
                        <th>Estado</th>
                        <th>Entrenamiento</th>
                        <th>Contextos activos</th>
                        <th>Usuarios con acceso</th>
                        <th>Sesiones</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assistants as $assistant)
                        <tr>
                            <td>{{ $assistant->name }}</td>
                            <td>{{ $assistant->role?->name ?? 'Sin rol' }}</td>
                            <td><code>{{ $assistant->model ?: config('services.openrouter.model') }}</code></td>
                            <td>
                                @if($assistant->is_active)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-secondary">Inactivo</span>
                                @endif
                            </td>
                            <td>
                                @if($assistant->training_enabled)
                                    <span class="badge badge-primary">Disponible</span>
                                @else
                                    <span class="badge badge-warning">Cerrado</span>
                                @endif
                            </td>
                            <td>{{ $assistant->active_knowledge_entries_count }}</td>
                            <td>{{ $assistant->access_count }}</td>
                            <td>{{ $assistant->chat_sessions_count }}</td>
                            <td class="text-right">
                                <a href="{{ route('admin.role-ai-assistants.show', $assistant) }}" class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i> Revisar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No hay asistentes configurados.</td>
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

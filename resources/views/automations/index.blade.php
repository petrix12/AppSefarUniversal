@extends('adminlte::page')

@section('title', 'Automatizaciones')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-bolt mr-2 text-warning"></i>Automatizaciones</h1>
        <div>
            <form action="{{ route('automations.run-due') }}" method="POST" class="d-inline">
                @csrf
                <button class="btn btn-outline-primary"><i class="fas fa-play mr-1"></i>Procesar pendientes</button>
            </form>
            <a href="{{ route('automations.create') }}" class="btn btn-primary ml-1"><i class="fas fa-plus mr-1"></i>Nueva automatización</a>
        </div>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card card-outline card-primary">
        <div class="card-header"><h3 class="card-title">Reglas activas y pausadas</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Regla</th><th>Disparador</th><th>Acciones</th><th>Ejecuciones</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                <tbody>
                @forelse($rules as $rule)
                    <tr>
                        <td><strong>{{ $rule->name }}</strong><br><small class="text-muted">{{ $rule->description }}</small></td>
                        <td>
                            @if($rule->trigger_type === 'event')
                                <span class="badge badge-info">Evento</span> {{ $rule->trigger_event }}
                            @elseif($rule->trigger_type === 'schedule')
                                <span class="badge badge-primary">Cron</span> <code>{{ $rule->cron_expression }}</code>
                            @else
                                <span class="badge badge-warning">Fecha</span> {{ data_get($rule->trigger_config, 'field_key') }}
                            @endif
                        </td>
                        <td>{{ $rule->actions_count }}</td>
                        <td>{{ $rule->runs_count }}</td>
                        <td><span class="badge badge-{{ $rule->is_active ? 'success' : 'secondary' }}">{{ $rule->is_active ? 'Activa' : 'Pausada' }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('automations.edit', $rule) }}" class="btn btn-sm btn-outline-primary">Editar</a>
                            <form action="{{ route('automations.toggle', $rule) }}" method="POST" class="d-inline">@csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-secondary">{{ $rule->is_active ? 'Pausar' : 'Activar' }}</button>
                            </form>
                            <form action="{{ route('automations.destroy', $rule) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta automatización y su historial?')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Aún no hay automatizaciones configuradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($rules->hasPages())<div class="card-footer">{{ $rules->links() }}</div>@endif
    </div>

    <div class="card card-outline card-secondary">
        <div class="card-header"><h3 class="card-title">Últimas ejecuciones</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-sm mb-0"><thead><tr><th>Fecha</th><th>Regla</th><th>Acción</th><th>Cliente</th><th>Estado</th><th>Detalle</th></tr></thead><tbody>
                @forelse($recentRuns as $run)
                    <tr><td>{{ optional($run->created_at)->format('d/m/Y H:i') }}</td><td>{{ $run->rule?->name }}</td><td>{{ $run->action?->action_type }}</td><td>{{ $run->entity_id ?: '—' }}</td><td>{{ $run->status }}</td><td><small>{{ $run->error_message ?: data_get($run->result, 'reason', '') }}</small></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">Sin ejecuciones todavía.</td></tr>
                @endforelse
            </tbody></table>
        </div>
    </div>
@stop

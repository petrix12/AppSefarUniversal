@extends('adminlte::page')

@section('title', 'Actividades CRM')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center"><h1 class="mb-0"><i class="fas fa-history mr-2 text-primary"></i>Actividades CRM</h1><a href="{{ route('crm.activities.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Registrar actividad</a></div>
@stop

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="card card-outline card-primary">
        <div class="card-header"><form class="form-inline" method="GET"><label for="type" class="mr-2">Tipo</label><select id="type" name="type" class="form-control mr-2"><option value="">Todos</option>@foreach($types as $key => $label)<option value="{{ $key }}" @selected($type === $key)>{{ $label }}</option>@endforeach</select><button class="btn btn-outline-primary mr-2">Filtrar</button><a href="{{ route('crm.activities.index') }}" class="btn btn-outline-secondary">Limpiar</a></form></div>
        <div class="card-body p-0 table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Fecha</th><th>Contacto</th><th>Actividad</th><th>Responsable</th><th>Seguimiento</th><th></th></tr></thead><tbody>
            @forelse($activities as $activity)
                <tr><td>{{ $activity->occurred_at?->format('d/m/Y H:i') }}</td><td>{{ $activity->lead?->nombre ?: $activity->client?->name ?: 'Contacto eliminado' }}</td><td><span class="badge badge-info">{{ $types[$activity->type] ?? $activity->type }}</span><br><strong>{{ $activity->subject }}</strong>@if($activity->details)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($activity->details, 140) }}</small>@endif</td><td>{{ $activity->createdBy?->name ?: '—' }}</td><td>{{ $activity->follow_up_at?->format('d/m/Y H:i') ?: '—' }}</td><td class="text-right"><a href="{{ route('crm.activities.edit', $activity) }}" class="btn btn-sm btn-outline-primary">Editar</a></td></tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">Todavía no hay actividades registradas.</td></tr>@endforelse
        </tbody></table></div>
        @if($activities->hasPages())<div class="card-footer">{{ $activities->links() }}</div>@endif
    </div>
@stop

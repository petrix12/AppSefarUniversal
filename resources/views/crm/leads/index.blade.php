@extends('adminlte::page')

@section('title', 'Leads CRM')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-user-plus mr-2 text-primary"></i>Leads CRM</h1>
        <a href="{{ route('crm.leads.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Nuevo lead</a>
    </div>
@stop

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="card card-outline card-primary">
        <div class="card-header">
            <form class="form-row align-items-end" method="GET">
                <div class="col-md-6 mb-2"><label class="small mb-1">Buscar</label><input name="q" value="{{ $search }}" class="form-control" placeholder="Nombre, correo, teléfono o pasaporte"></div>
                <div class="col-md-2 mb-2"><label class="small mb-1">Etapa</label><select name="etapa" class="form-control"><option value="">Todas</option>@foreach($stages as $key => $label)<option value="{{ $key }}" @selected($etapa === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-2 mb-2"><label class="small mb-1">Estado</label><select name="estado" class="form-control"><option value="">Todos</option><option value="abierto" @selected($estado === 'abierto')>Abierto</option><option value="convertido" @selected($estado === 'convertido')>Convertido</option><option value="perdido" @selected($estado === 'perdido')>Perdido</option></select></div>
                <div class="col-md-2 mb-2"><button class="btn btn-outline-primary mr-1">Filtrar</button><a href="{{ route('crm.leads.index') }}" class="btn btn-outline-secondary">Limpiar</a></div>
            </form>
        </div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Contacto</th><th>Origen</th><th>Etapa</th><th>Responsable</th><th>Estado</th><th class="text-right">Acción</th></tr></thead>
                <tbody>
                @forelse($leads as $lead)
                    <tr>
                        <td><strong>{{ $lead->nombre }}</strong><br><small class="text-muted">{{ $lead->email ?: 'Sin correo' }} · {{ $lead->telefono ?: 'Sin teléfono' }}</small></td>
                        <td>{{ $lead->origen ?: '—' }}</td>
                        <td>{{ $stages[$lead->etapa] ?? $lead->etapa }}</td>
                        <td>{{ $lead->assignee?->name ?: 'Sin asignar' }}</td>
                        <td><span class="badge badge-{{ $lead->estado === 'abierto' ? 'success' : ($lead->estado === 'convertido' ? 'primary' : 'secondary') }}">{{ ucfirst($lead->estado) }}</span></td>
                        <td class="text-right text-nowrap"><a href="{{ route('crm.activities.create', ['lead_id' => $lead->id]) }}" class="btn btn-sm btn-outline-info" title="Registrar actividad"><i class="fas fa-history"></i></a> <a href="{{ route('crm.quotes.create', ['lead_id' => $lead->id]) }}" class="btn btn-sm btn-outline-success" title="Crear presupuesto"><i class="fas fa-file-invoice-dollar"></i></a> <a href="{{ route('crm.leads.edit', $lead) }}" class="btn btn-sm btn-outline-primary">Editar</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay leads para estos filtros.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($leads->hasPages())<div class="card-footer">{{ $leads->links() }}</div>@endif
    </div>
@stop

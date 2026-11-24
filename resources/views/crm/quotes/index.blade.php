@extends('adminlte::page')

@section('title', 'Presupuestos CRM')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center"><h1 class="mb-0"><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>Presupuestos CRM</h1><a href="{{ route('crm.quotes.create') }}" class="btn btn-primary"><i class="fas fa-plus mr-1"></i>Nuevo presupuesto</a></div>
@stop

@section('content')
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    <div class="alert alert-info">Estos presupuestos son registros comerciales del CRM. No crean facturas ni cobran pagos automáticamente.</div>
    <div class="card card-outline card-primary">
        <div class="card-header"><form class="form-row align-items-end" method="GET"><div class="col-md-5 mb-2"><label>Buscar</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Número o concepto"></div><div class="col-md-3 mb-2"><label>Estado</label><select name="status" class="form-control"><option value="">Todos</option>@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div><div class="col-md-4 mb-2"><button class="btn btn-outline-primary mr-1">Filtrar</button><a href="{{ route('crm.quotes.index') }}" class="btn btn-outline-secondary">Limpiar</a></div></form></div>
        <div class="card-body p-0 table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Número</th><th>Contacto</th><th>Presupuesto</th><th>Estado</th><th>Vigencia</th><th class="text-right">Total</th><th></th></tr></thead><tbody>
            @forelse($quotes as $quote)
                <tr><td><strong>{{ $quote->quote_number }}</strong></td><td>{{ $quote->lead?->nombre ?: $quote->client?->name ?: 'Contacto eliminado' }}</td><td>{{ $quote->title }}<br><small class="text-muted">{{ $quote->items_count }} concepto(s)</small></td><td><span class="badge badge-secondary">{{ $statuses[$quote->status] ?? $quote->status }}</span></td><td>{{ $quote->valid_until?->format('d/m/Y') ?: '—' }}</td><td class="text-right">{{ number_format((float) $quote->total, 2, ',', '.') }} {{ $quote->currency }}</td><td class="text-right"><a href="{{ route('crm.quotes.edit', $quote) }}" class="btn btn-sm btn-outline-primary">Editar</a></td></tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay presupuestos en el CRM.</td></tr>@endforelse
        </tbody></table></div>
        @if($quotes->hasPages())<div class="card-footer">{{ $quotes->links() }}</div>@endif
    </div>
@stop

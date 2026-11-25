@extends('platforms.layout')
@section('platform_title', 'Auditoría de pagos')
@section('platform_content')
<div class="card"><div class="card-body">
    <div class="platform-toolbar justify-content-between mb-3">
        <div><h2 class="h5 mb-1">Pagos, fallos y reembolsos</h2><p class="text-muted mb-0">Consulta en vivo a la API de Stripe. La App no guarda una copia de estos eventos.</p></div>
        <a class="btn btn-outline-primary" href="{{ route('admin.platforms.audit', [$account, 'category'=>$category]) }}">Actualizar</a>
    </div>
    <div class="alert alert-info" role="status">Stripe permite consultar eventos de los últimos 30 días. Esta auditoría muestra hasta 100 resultados por página, en orden cronológico inverso.</div>
    <form method="GET" class="platform-toolbar align-items-end mb-3">
        <div><label for="audit-category">Tipo de actividad</label><select class="form-control" id="audit-category" name="category">
            <option value="failures" @selected($category==='failures')>Pagos fallidos</option>
            <option value="payments" @selected($category==='payments')>Pagos confirmados y cancelados</option>
            <option value="refunds" @selected($category==='refunds')>Reembolsos</option>
            <option value="disputes" @selected($category==='disputes')>Contracargos</option>
            <option value="all" @selected($category==='all')>Toda la actividad de pagos</option>
        </select></div>
        <button class="btn btn-primary" type="submit">Consultar Stripe</button>
    </form>

    @if($auditError)
        <div class="alert alert-warning" role="alert">
            No se pudieron consultar los eventos de Stripe ({{ $auditError }}). Comprueba que la clave de esta cuenta permita leer eventos y vuelve a intentar.
        </div>
    @endif

    <div class="table-responsive" tabindex="0" aria-label="Auditoría de pagos de Stripe">
        <table class="table table-striped">
            <thead><tr><th>Fecha (UTC)</th><th>Evento</th><th>Pago o registro</th><th>Estado</th><th>Importe</th><th>Diagnóstico de Stripe</th></tr></thead>
            <tbody>
            @forelse($events as $event)
                <tr>
                    <td class="text-nowrap">{{ $event['created'] ? \Carbon\Carbon::createFromTimestampUTC($event['created'])->format('d/m/Y H:i:s') : '—' }}</td>
                    <td>{{ $event['label'] }}<small class="d-block text-muted platform-value">{{ $event['type'] }} · {{ $event['event_id'] }}</small></td>
                    <td class="platform-value">{{ $event['resource_id'] ?: '—' }}<small class="d-block text-muted">{{ $event['resource'] ?: '—' }}@if($event['customer_id']) · {{ $event['customer_id'] }}@endif</small></td>
                    <td>{{ $event['status'] ? \App\Services\PlatformPresentation::status($event['status']) : '—' }}</td>
                    <td class="text-nowrap">{{ $event['amount'] }}</td>
                    <td class="platform-value">
                        @if($event['error_code'])<strong>{{ $event['error_code'] }}</strong><br>@endif
                        {{ $event['reason'] }}
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">{{ $auditError ? 'No hay resultados porque falló la consulta.' : 'Stripe no devolvió eventos para este filtro en el periodo disponible.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($hasMore && count($events))
        <div class="d-flex justify-content-end"><a class="btn btn-outline-primary" href="{{ route('admin.platforms.audit', [$account, 'category'=>$category, 'starting_after'=>end($events)['event_id']]) }}">Siguiente página</a></div>
    @endif
</div></div>
@endsection

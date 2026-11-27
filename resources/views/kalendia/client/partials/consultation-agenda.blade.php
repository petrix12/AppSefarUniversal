<h3 class="h5">Elige la fecha y la hora de tu consulta</h3>
<p class="text-muted">{{ $purchase->servicio_hs_id }}</p>
<div class="alert alert-success">
    <strong>Pago confirmado</strong> · {{ format_money($purchase->monto, 2) }} EUR
    <a class="d-block mt-1" href="{{ route('viewcomprobantecliente', $purchase->factura->id) }}" target="_blank" rel="noopener">Ver comprobante #{{ $purchase->factura->id }}</a>
</div>
@if($booking && in_array($booking->status, ['processing','uncertain'], true))
    <p>La reserva anterior está pendiente de comprobación. Puedes continuar esta misma solicitud.</p>
    <form method="POST" action="{{ route('clientes.meeting.book', $purchase->id) }}" data-consultation-booking>@csrf
        <button class="btn btn-primary" type="submit" @disabled(!$bookingReady)>Comprobar esta reserva</button>
    </form>
@elseif(!$remoteService || $agendaUnavailable || !$bookingReady)
    <div class="alert alert-info">Tu pago está confirmado. La agenda no está disponible ahora; puedes volver a esta misma consulta para elegir el horario.</div>
    <button type="button" class="btn btn-outline-primary" data-consultation-agenda-retry>Volver a consultar fechas y horarios</button>
    <a href="{{ route('clientes.meetings') }}" class="btn btn-link">Ver Mi agenda</a>
@else
    @if(($queuedRequest?->status ?? null) === 'needs_slot')
        <div class="alert alert-warning">El horario anterior dejó de estar disponible. Elige otra fecha y hora.</div>
    @endif
    @include('clientes.partials.meeting-form')
@endif

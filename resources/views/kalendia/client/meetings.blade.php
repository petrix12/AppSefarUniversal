@extends('adminlte::page')
@section('title', 'Mis reuniones')
@section('content_header')<h1>Mis reuniones</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    @if(session('info'))<div class="alert alert-info" role="status">{{ session('info') }}</div>@endif
    <div class="card"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="h5 mb-2">{{ $summary['status'] }}</h2><a class="btn btn-primary" href="{{ route('clientes.consultations') }}">Contratar una consulta</a></div>
        <p class="text-muted">Consulta tus próximas reuniones, su estado y el enlace de acceso en tu hora local.</p>
        @include('kalendia.partials.timezone')
        @php($initialRequest = $bookingRequests->first(fn ($item) => $item->purchase && $item->purchase->source !== \App\Models\KalendiaConsultationOrder::SOURCE))
        @if($initialRequest)
            <div class="alert {{ $initialRequest->status === 'needs_slot' ? 'alert-warning' : 'alert-info' }}" role="status">
                @if($initialRequest->status === 'needs_slot')
                    No encontramos disponibilidad para tu reunión inicial. <a href="{{ route('clientes.meeting', ['purchase' => $initialRequest->compra_id]) }}">Elegir otra fecha y hora</a>
                @elseif($initialRequest->status === 'review')
                    Tu solicitud de reunión inicial necesita revisión del equipo. <a href="{{ route('clientes.support-ticket.create') }}">Contactar con soporte</a>
                @else
                    Tu reunión inicial se está agendando en segundo plano. Consulta Mis reuniones más tarde para ver el resultado.
                @endif
            </div>
        @else
            @can('registration.meeting')<div class="alert alert-info">Tienes una reunión inicial incluida pendiente de agendar. <a href="{{ route('clientes.meeting') }}">Elegir fecha y hora</a></div>@endcan
        @endif
        @include('kalendia.partials.appointments', ['appointments' => $summary['appointments']])
        @if(method_exists($summary['appointments'], 'links')){{ $summary['appointments']->links() }}@endif
    </div></div>
    @if($bookingRequests->isNotEmpty())
        <div class="card"><div class="card-header"><h2 class="card-title">Solicitudes de reunión</h2></div><div class="card-body table-responsive"><table class="table mb-0"><thead><tr><th>Servicio</th><th>Fecha solicitada</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
            @foreach($bookingRequests as $bookingRequest)
                @php($isConsultation = $bookingRequest->purchase?->source === \App\Models\KalendiaConsultationOrder::SOURCE)
                <tr>
                    <td>{{ $bookingRequest->purchase?->servicio?->nombre ?: ($isConsultation ? 'Consulta contratada' : 'Reunión inicial incluida') }}</td>
                    <td>{{ $bookingRequest->requested_date?->format('d/m/Y') ?? '—' }}</td>
                    <td>{{ $bookingRequest->status === 'needs_slot' ? 'Necesita otra fecha' : ($bookingRequest->status === 'review' ? 'En revisión' : 'Agendando en segundo plano') }}</td>
                    <td>
                        @if($bookingRequest->status === 'review')
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('clientes.support-ticket.create') }}">Contactar con soporte</a>
                        @elseif($bookingRequest->status === 'needs_slot')
                            <a class="btn btn-sm btn-outline-primary" href="{{ $isConsultation ? route('clientes.consultations.reserve', $bookingRequest->compra_id) : route('clientes.meeting', ['purchase' => $bookingRequest->compra_id]) }}">Elegir fecha y hora</a>
                        @else<span class="text-muted">Puedes salir de esta página; el estado seguirá disponible aquí.</span>@endif
                    </td>
                </tr>
            @endforeach
        </tbody></table></div></div>
    @endif
    @if($orders->isNotEmpty())
        <div class="card"><div class="card-header"><h2 class="card-title">Consultas contratadas y pagos pendientes</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>Consulta</th><th>Importe</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
        @foreach($orders as $order)<tr><td>{{ $order->name }}<br><small class="text-muted">{{ $order->type }}</small></td><td>{{ format_money($order->amount, 2) }} EUR</td><td>{{ $order->status === 'paid' ? 'Pagada' : 'Pago pendiente' }}</td><td>
            @if($order->status !== 'paid')<a class="btn btn-sm btn-outline-primary" href="{{ route('clientes.consultations.checkout', $order) }}">Continuar esta compra</a>
            @elseif(!$order->purchase?->kalendiaBooking?->remote_id)
                @php($bookingRequest = $bookingRequests->firstWhere('compra_id', $order->compra_id))
                @if($bookingRequest?->status === 'needs_slot')<a class="btn btn-sm btn-warning" href="{{ route('clientes.consultations.reserve', $order->compra_id) }}">Elegir otro horario</a>
                @elseif(in_array($bookingRequest?->status, ['queued', 'processing', 'review'], true))<span class="text-info">{{ $bookingRequest->status === 'review' ? 'Requiere revisión del equipo' : 'Agendando en segundo plano' }}</span>
                @else<a class="btn btn-sm btn-primary" href="{{ route('clientes.consultations.reserve', $order->compra_id) }}">Agendar consulta</a>@endif
            @else<span class="text-muted">Reserva registrada</span>@endif
        </td></tr>@endforeach
        </tbody></table>@if(method_exists($orders, 'links')){{ $orders->links() }}@endif</div></div>
    @endif
@stop

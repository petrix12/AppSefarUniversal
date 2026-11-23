@extends('adminlte::page')
@section('title', 'Mis reuniones')
@section('content_header')<h1>Mis reuniones</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    @if(session('warning'))
        <div class="alert alert-warning" role="status">{{ session('warning') }}</div>
    @endif
    <div class="card"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3"><h2 class="h5 mb-2">{{ $summary['status'] }}</h2><a class="btn btn-primary" href="{{ route('clientes.consultations') }}">Contratar una consulta</a></div>
        <p class="text-muted">Consulta tus próximas reuniones, su estado y el enlace de acceso en tu hora local.</p>
        @include('kalendia.partials.timezone')
        @can('registration.meeting')<div class="alert alert-info">Tienes una reunión inicial incluida pendiente de agendar. <a href="{{ route('clientes.meeting') }}">Elegir fecha y hora</a></div>@endcan
        @include('kalendia.partials.appointments', ['appointments' => $summary['appointments']])
        @if(method_exists($summary['appointments'], 'links')){{ $summary['appointments']->links() }}@endif
    </div></div>
    @if($orders->isNotEmpty())
        <div class="card"><div class="card-header"><h2 class="card-title">Consultas contratadas y pagos pendientes</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>Consulta</th><th>Importe</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
        @foreach($orders as $order)<tr><td>{{ $order->name }}<br><small class="text-muted">{{ $order->type }}</small></td><td>{{ format_money($order->amount, 2) }} EUR</td><td>{{ $order->status === 'paid' ? 'Pagada' : 'Pago pendiente' }}</td><td>
            @if($order->status !== 'paid')<a class="btn btn-sm btn-outline-primary" href="{{ route('clientes.consultations.checkout', $order) }}">Continuar esta compra</a>
            @elseif(!$order->purchase?->kalendiaBooking?->remote_id)<a class="btn btn-sm btn-primary" href="{{ route('clientes.consultations.reserve', $order->compra_id) }}">Agendar consulta</a>
            @else<span class="text-muted">Reserva registrada</span>@endif
        </td></tr>@endforeach
        </tbody></table>@if(method_exists($orders, 'links')){{ $orders->links() }}@endif</div></div>
    @endif
@stop

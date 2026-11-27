@extends('adminlte::page')
@php($isConsultation = $purchase->source === \App\Models\KalendiaConsultationOrder::SOURCE)
@section('title', $isConsultation ? 'Agendar consulta' : 'Mi reunión')
@section('content_header')
    <h1>{{ $isConsultation ? 'Agendar consulta' : 'Mi reunión' }}</h1>
@stop
@section('content')
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert">@foreach($errors->all() as $message)<p class="mb-0">{{ $message }}</p>@endforeach</div>
    @endif
    <div class="card card-outline card-primary">
        <div class="card-header"><h2 class="card-title">{{ $isConsultation ? 'Elige la fecha y la hora de tu consulta' : 'Agenda la reunión incluida en tu servicio' }}</h2></div>
        <div class="card-body">
            <p>{{ $isConsultation ? 'Tu consulta ya está pagada. Selecciona una fecha y un horario disponible para reunirte con nuestro equipo.' : 'Ya completaste el pago, el formulario 001 y el contrato. Selecciona un horario disponible para reunirte con nuestro equipo.' }}</p>
            @include('kalendia.partials.timezone')
            @if($purchases->count() > 1)
                <form method="GET" class="mb-3">
                    <label for="meeting-purchase">Servicio contratado</label>
                    <select id="meeting-purchase" name="purchase" class="form-control" onchange="this.form.submit()">
                        @foreach($purchases as $item)<option value="{{ $item->id }}" @selected($item->id === $purchase->id)>{{ $item->servicio?->nombre ?: $item->servicio_hs_id }} · #{{ $item->id }}</option>@endforeach
                    </select>
                </form>
            @else
                <h3 class="h5">{{ $purchase->servicio?->nombre ?: $purchase->servicio_hs_id }}</h3>
            @endif
            <div class="p-3 mb-4 rounded border bg-light">
                <i class="fas fa-check-circle text-success mr-1" aria-hidden="true"></i><strong>Pago verificado en la App</strong>
                <span class="d-block small text-muted mt-1">Comprobante #{{ $purchase->factura->id }} · {{ format_money($purchase->monto, 2) }} {{ data_get($purchase->metadata, 'display_currency', 'EUR') }}</span>
                <span class="d-block small mt-1">{{ $isConsultation ? 'Consulta pagada. No tienes que realizar otro pago.' : 'Esta reunión está incluida. No tienes que realizar otro pago.' }}</span>
                @if($isConsultation)<a class="d-block small mt-1" href="{{ route('viewcomprobantecliente', $purchase->factura->id) }}" target="_blank" rel="noopener">Ver comprobante</a>@endif
            </div>

            @if($booking?->remote_id)
                <div class="border rounded p-3">
                    <h3 class="h5">{{ $booking->remote_status === 'confirmed' ? 'Reunión confirmada' : 'Reserva recibida' }}</h3>
                    @php($bookingInstant = \Carbon\CarbonImmutable::parse($booking->date.' '.$booking->time, 'America/Bogota'))
                    <p class="mb-1"><strong data-kalendia-instant="{{ $bookingInstant->toIso8601String() }}">{{ $bookingInstant->setTimezone(\App\Services\KalendiaTimezone::preferred())->format('d/m/Y H:i') }}</strong></p>
                    <p class="small text-muted">Referencia de la reserva: {{ $booking->remote_id }}</p>
                    @if($booking->meeting_url)
                        <a class="btn btn-primary" href="{{ $booking->meeting_url }}" target="_blank" rel="noopener noreferrer">Abrir enlace de la reunión <i class="fas fa-external-link-alt ml-1" aria-hidden="true"></i></a>
                    @else
                        <p class="mb-0 text-muted">Consulta los detalles de acceso en la confirmación de tu reunión o contacta con nuestro equipo.</p>
                    @endif
                </div>
            @elseif($booking && in_array($booking->status, ['processing', 'uncertain'], true))
                <div class="alert alert-info">La reserva anterior sigue pendiente de comprobación. Puedes solicitar una comprobación en segundo plano; su resultado aparecerá en Mis reuniones.</div>
                <form method="POST" action="{{ route('clientes.meeting.book', $purchase->id) }}">@csrf
                    <button class="btn btn-primary" type="submit" @disabled(!$bookingReady)>Solicitar comprobación</button>
                </form>
            @elseif(!$remoteService || $agendaUnavailable)
                <div class="alert alert-info mb-0">La agenda de este servicio todavía no está disponible. Tu pago se conserva. Puedes continuar con tu expediente y consultar tus reservas desde «Mis reuniones».</div>
            @else
                @if(($queuedRequest?->status ?? null) === 'needs_slot')
                    <div class="alert alert-warning" role="status">No encontramos disponibilidad para el horario anterior. Elige una nueva fecha y hora para volver a enviar la solicitud.</div>
                @endif
                @unless($bookingReady)
                    <div class="alert alert-info">Estamos habilitando las reservas con el pago que ya realizaste. Puedes consultar los horarios disponibles; no se solicitará un nuevo pago.</div>
                @endunless
                @include('clientes.partials.meeting-form')
            @endif
        </div>
        <div class="card-footer text-right"><a class="btn btn-outline-secondary" href="{{ $continueUrl }}">{{ $continueLabel ?? 'Consultar mi expediente' }}</a></div>
    </div>
@stop
@section('css')
    <link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">
    <style>
        .flatpickr-calendar { font-family: var(--sefar-font-family, 'Lato', sans-serif); }
        .flatpickr-day.selected, .flatpickr-day.selected:hover { background: var(--sefar-primary, #093143); border-color: var(--sefar-primary, #093143); }
    </style>
@stop
@section('js')
    <script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('vendor/flatpickr/es.js') }}"></script>
    <script src="{{ asset('js/registration-meeting.js') }}?v={{ filemtime(public_path('js/registration-meeting.js')) }}"></script>
@stop

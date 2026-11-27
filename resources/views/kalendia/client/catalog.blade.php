@extends('adminlte::page')
@section('title', 'Consultas')
@section('content_header')<h1>Consultas disponibles</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    <p>Elige una consulta y realiza el pago. Después elegirás la fecha y la hora disponibles en la misma ventana.</p>
    @if($apiUnavailable ?? false)<div class="alert alert-warning">No pudimos confirmar los precios y horarios de las consultas. Inténtalo de nuevo en unos minutos.</div>@endif
    @unless($ready)<div class="alert alert-info">La contratación se habilitará cuando completes tu registro y estén configurados los pagos.</div>@endunless
    <div class="row">
    @forelse($offers as $offer)<div class="col-md-6 col-xl-4 mb-3"><div class="card h-100"><div class="card-body d-flex flex-column">
        <span class="text-muted small mb-2">{{ $offer->type }}</span><h2 class="h5">{{ $offer->name }}</h2><p class="flex-grow-1">{{ $offer->description }}</p>
        <strong class="mb-3">{{ $offer->hasPrice() ? format_money($offer->price, 2).' EUR' : 'Precio pendiente' }}</strong>
        <form method="POST" action="{{ route('clientes.consultations.purchase', $offer) }}" data-consultation-offer>@csrf<input type="hidden" name="checkout_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <button class="btn btn-primary btn-block" @disabled(!($offerReady[$offer->id] ?? false))>{{ ($offerReady[$offer->id] ?? false) ? 'Contratar consulta' : 'Próximamente' }}</button>
        </form>
    </div></div></div>@empty<div class="col-12"><div class="alert alert-info">Las consultas estarán disponibles cuando se publiquen sus tipos y precios.</div></div>@endforelse
    </div>
    <a href="{{ route('clientes.meetings') }}" class="btn btn-outline-secondary">Mi agenda</a>
    <div class="modal fade" id="consultation-payment-modal" tabindex="-1" role="dialog" aria-labelledby="consultation-payment-title" aria-describedby="consultation-payment-steps" aria-hidden="true" data-backdrop="static" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document"><div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="consultation-payment-title">Contratar consulta</h2>
                <button type="button" class="close" data-consultation-close aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <ol id="consultation-payment-steps" class="list-unstyled d-flex flex-wrap border-bottom pb-3 mb-3" style="gap:.5rem 1.5rem">
                    <li data-consultation-step="1" aria-current="step"><strong>1. Pagar consulta</strong></li>
                    <li data-consultation-step="2" class="text-muted">2. Elegir fecha y hora</li>
                    <li data-consultation-step="3" class="text-muted">3. Solicitud enviada</li>
                </ol>
                <p class="alert alert-info" data-consultation-feedback role="status" aria-live="polite" hidden></p>
                <div data-consultation-timezone hidden>@include('kalendia.partials.timezone')</div>
                <div data-consultation-content tabindex="-1"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-consultation-close>Cerrar</button></div>
        </div></div>
    </div>
@stop
@section('css')<link rel="stylesheet" href="{{ asset('vendor/flatpickr/flatpickr.min.css') }}">@stop
@section('js')
    <script src="https://js.stripe.com/v3/"></script>
    <script src="{{ asset('vendor/flatpickr/flatpickr.min.js') }}"></script>
    <script src="{{ asset('vendor/flatpickr/es.js') }}"></script>
    <script src="{{ asset('js/registration-meeting.js') }}?v={{ filemtime(public_path('js/registration-meeting.js')) }}"></script>
    <script src="{{ asset('js/kalendia-consultation-payment.js') }}?v={{ filemtime(public_path('js/kalendia-consultation-payment.js')) }}"></script>
    <script src="{{ asset('js/kalendia-consultation-modal.js') }}?v={{ filemtime(public_path('js/kalendia-consultation-modal.js')) }}"></script>
@stop

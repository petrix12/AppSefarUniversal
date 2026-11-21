@extends('adminlte::page')
@section('title', 'Pagar consulta')
@section('content_header')<h1>Pagar consulta</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    <div class="row"><div class="col-lg-7"><div class="card"><div class="card-body">
        <h2 class="h5">{{ $order->name }}</h2><p>{{ $order->type }}</p><p class="h4">{{ format_money($order->amount, 2) }} EUR</p>
        <p>Después de confirmar este pago podrás elegir la fecha y hora. Esta compra conserva su importe y referencia al reintentar.</p>
        <form data-consultation-payment data-intent-url="{{ route('clientes.consultations.intent', $order) }}" data-return-url="{{ route('clientes.consultations.complete', $order) }}" data-stripe-key="{{ config('services.stripe.key') }}">
            @csrf<div data-payment-element></div><p data-payment-message role="status" aria-live="polite" class="mt-3"></p>
            <button type="submit" class="btn btn-primary" data-payment-submit disabled>Pagar {{ format_money($order->amount, 2) }} EUR</button>
        </form>
        <a class="btn btn-link mt-3" href="{{ route('clientes.consultations.complete', $order) }}">Comprobar el estado de este pago</a>
    </div></div></div></div>
@stop
@section('js')<script src="https://js.stripe.com/v3/"></script><script src="{{ asset('js/kalendia-consultation-payment.js') }}"></script>@stop

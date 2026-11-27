@extends('adminlte::page')
@section('title', 'Pagar consulta')
@section('content_header')<h1>Pagar consulta</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    <div class="row"><div class="col-lg-7"><div class="card"><div class="card-body">
        @include('kalendia.client.partials.payment-form')
    </div></div></div></div>
@stop
@section('js')<script src="https://js.stripe.com/v3/"></script><script src="{{ asset('js/kalendia-consultation-payment.js') }}?v={{ filemtime(public_path('js/kalendia-consultation-payment.js')) }}"></script>@stop

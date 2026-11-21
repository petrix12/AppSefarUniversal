@extends('adminlte::page')
@section('title', 'Consultas')
@section('content_header')<h1>Consultas disponibles</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    <p>Elige el tipo de consulta, realiza el pago en la App y después selecciona un horario disponible.</p>
    @unless($ready)<div class="alert alert-info">La contratación se habilitará cuando completes tu registro y estén configurados los pagos.</div>@endunless
    <div class="row">
    @forelse($offers as $offer)<div class="col-md-6 col-xl-4 mb-3"><div class="card h-100"><div class="card-body d-flex flex-column">
        <span class="text-muted small mb-2">{{ $offer->type }}</span><h2 class="h5">{{ $offer->name }}</h2><p class="flex-grow-1">{{ $offer->description }}</p>
        <strong class="mb-3">{{ $offer->hasPrice() ? format_money($offer->price, 2).' EUR' : 'Precio pendiente' }}</strong>
        <form method="POST" action="{{ route('clientes.consultations.purchase', $offer) }}">@csrf<input type="hidden" name="checkout_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <button class="btn btn-primary btn-block" @disabled(!($offerReady[$offer->id] ?? false))>{{ ($offerReady[$offer->id] ?? false) ? 'Contratar consulta' : 'Próximamente' }}</button>
        </form>
    </div></div></div>@empty<div class="col-12"><div class="alert alert-info">Las consultas estarán disponibles cuando se publiquen sus tipos y precios.</div></div>@endforelse
    </div>
    <a href="{{ route('clientes.meetings') }}" class="btn btn-outline-secondary">Mis reuniones</a>
@stop

@extends('adminlte::page')
@section('title', 'Consultas y precios')
@section('content_header')<h1>Kalendia · Consultas y precios</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    @if($apiUnavailable)<div class="alert alert-warning">No se pudo consultar el catálogo de Kalendia. Se conservan las consultas guardadas.</div>@endif
    <p>Crea un tipo de consulta o una modalidad de precio. Puedes asociar varias modalidades al mismo servicio de Kalendia. El importe se cobra en la App; una consulta sin precio positivo permanece deshabilitada.</p>
    <div class="alert alert-info">El servicio asociado debe estar a cero en Kalendia o disponer de un método confirmado para pagos externos. Así se evita pedir un segundo pago.</div>
    @php($forms = $offers->concat([null]))
    <div class="row">@foreach($forms as $offer)<div class="col-lg-6"><div class="card"><div class="card-header"><h2 class="card-title">{{ $offer ? $offer->name : 'Nueva consulta o modalidad' }}</h2></div><div class="card-body">
        <form method="POST" action="{{ $offer ? route('admin.kalendia.offers.update', $offer) : route('admin.kalendia.offers.store') }}">@csrf @if($offer)@method('PUT')@endif
            <div class="form-group"><label for="offer-name-{{ $offer?->id ?? 'new' }}">Nombre</label><input id="offer-name-{{ $offer?->id ?? 'new' }}" class="form-control" name="name" value="{{ $offer?->name }}" maxlength="255" required></div>
            <div class="row"><div class="col-md-6 form-group"><label for="offer-type-{{ $offer?->id ?? 'new' }}">Tipo de consulta</label><input id="offer-type-{{ $offer?->id ?? 'new' }}" class="form-control" name="type" value="{{ $offer?->type }}" placeholder="Jurídica, genealógica…" maxlength="100" required></div>
            <div class="col-md-6 form-group"><label for="offer-price-{{ $offer?->id ?? 'new' }}">Precio en la App · EUR</label><input id="offer-price-{{ $offer?->id ?? 'new' }}" class="form-control" name="price" type="number" min="0" max="99999999.99" step="0.01" value="{{ $offer?->price }}"><small class="text-muted">Vacío o cero: contratación deshabilitada.</small></div></div>
            <div class="form-group"><label for="offer-service-{{ $offer?->id ?? 'new' }}">Servicio de Kalendia</label><select id="offer-service-{{ $offer?->id ?? 'new' }}" class="form-control" name="remote_service_id" required><option value="">Selecciona un servicio</option>
                @if($offer && !collect($services)->contains('id', $offer->remote_service_id))<option value="{{ $offer->remote_service_id }}" selected>Servicio guardado · {{ $offer->remote_service_id }}</option>@endif
                @foreach($services as $service)<option value="{{ $service['id'] }}" @selected($offer?->remote_service_id === $service['id'])>{{ $service['name'] }} · Kalendia: {{ is_numeric($service['price']) ? format_money($service['price'],2).' EUR' : 'precio sin confirmar' }}</option>@endforeach
            </select></div>
            <div class="form-group"><label for="offer-description-{{ $offer?->id ?? 'new' }}">Descripción para el cliente</label><textarea id="offer-description-{{ $offer?->id ?? 'new' }}" name="description" class="form-control" maxlength="3000">{{ $offer?->description }}</textarea></div>
            <div class="form-check mb-3"><input type="hidden" name="active" value="0"><input type="checkbox" id="offer-active-{{ $offer?->id ?? 'new' }}" name="active" value="1" class="form-check-input" @checked($offer?->active ?? true)><label class="form-check-label" for="offer-active-{{ $offer?->id ?? 'new' }}">Visible en el catálogo de consultas</label></div>
            <button class="btn btn-primary" type="submit">Guardar consulta</button>
            @if($offer)<span class="ml-2 text-muted">{{ $offer->hasPrice() ? 'Precio configurado' : 'Sin contratación habilitada' }}</span>@endif
        </form>
    </div></div></div>@endforeach</div>
@stop

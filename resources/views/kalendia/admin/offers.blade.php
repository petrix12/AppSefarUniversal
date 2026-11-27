@extends('adminlte::page')
@section('title', 'Consultas y precios')
@section('content_header')<h1>Kalendia · Consultas y precios</h1>@stop
@section('content')
    @include('kalendia.partials.messages')
    @if($apiUnavailable)<div class="alert alert-warning">No se pudo consultar el catálogo de Kalendia. Se conservan las consultas guardadas.</div>@endif
    <p>Los servicios y precios se importan automáticamente desde Kalendia. El cliente paga con Stripe en App Sefar y después elige un horario para su consulta.</p>
    <form method="POST" action="{{ route('admin.kalendia.offers.sync') }}" class="mb-3">@csrf<button class="btn btn-primary" type="submit"><i class="fas fa-sync-alt" aria-hidden="true"></i> Actualizar servicios y precios de Kalendia</button></form>
    <div class="alert alert-info">Se publican servicios activos con precio positivo en EUR. Las reuniones gratuitas mantienen su flujo incluido. Los nombres y precios importados se actualizan desde Kalendia; puedes decidir su visibilidad.</div>
    @php($forms = $offers->concat([null]))
    <div class="row">@foreach($forms as $offer)<div class="col-lg-6"><div class="card"><div class="card-header"><h2 class="card-title">{{ $offer ? $offer->name : 'Nueva consulta o modalidad' }}</h2></div><div class="card-body">
        @if($offer?->catalog_service_id)
            <p class="small text-muted">Sincronizado desde Kalendia · {{ $offer->catalog_synced_at?->format('d/m/Y H:i') }}
                @unless($offer->catalog_available)
                    · No disponible para contratar
                @endunless
            </p>
        @endif
        <form method="POST" action="{{ $offer ? route('admin.kalendia.offers.update', $offer) : route('admin.kalendia.offers.store') }}">@csrf @if($offer)@method('PUT')@endif
            <div class="form-group"><label for="offer-name-{{ $offer?->id ?? 'new' }}">Nombre</label><input id="offer-name-{{ $offer?->id ?? 'new' }}" class="form-control" name="name" value="{{ $offer?->name }}" maxlength="255" required @readonly($offer?->catalog_service_id)></div>
            <div class="row"><div class="col-md-6 form-group"><label for="offer-type-{{ $offer?->id ?? 'new' }}">Tipo de consulta</label><input id="offer-type-{{ $offer?->id ?? 'new' }}" class="form-control" name="type" value="{{ $offer?->type }}" placeholder="Jurídica, genealógica…" maxlength="100" required></div>
            <div class="col-md-6 form-group"><label for="offer-price-{{ $offer?->id ?? 'new' }}">Precio · {{ $offer?->currency ?? 'EUR' }}</label><input id="offer-price-{{ $offer?->id ?? 'new' }}" class="form-control" name="price" type="number" min="0" max="99999999.99" step="0.01" value="{{ $offer?->price }}" @readonly($offer?->catalog_service_id)><small class="text-muted">{{ $offer?->catalog_service_id ? 'Precio del catálogo de Kalendia.' : 'Vacío o cero: contratación deshabilitada.' }}</small></div></div>
            <div class="form-group"><label for="offer-service-{{ $offer?->id ?? 'new' }}">Servicio de Kalendia</label><select id="offer-service-{{ $offer?->id ?? 'new' }}" class="form-control" name="remote_service_id" required @disabled($offer?->catalog_service_id)><option value="">Selecciona un servicio</option>
                @if($offer && !collect($services)->contains('id', $offer->remote_service_id))<option value="{{ $offer->remote_service_id }}" selected>Servicio guardado · {{ $offer->remote_service_id }}</option>@endif
                @foreach($services as $service)<option value="{{ $service['id'] }}" @selected($offer?->remote_service_id === $service['id'])>{{ $service['name'] }} · Kalendia: {{ is_numeric($service['price']) ? format_money($service['price'],2).' '.($service['currency'] ?? 'EUR') : 'precio sin confirmar' }}</option>@endforeach
            </select></div>
            <div class="form-group"><label for="offer-description-{{ $offer?->id ?? 'new' }}">Descripción para el cliente</label><textarea id="offer-description-{{ $offer?->id ?? 'new' }}" name="description" class="form-control" maxlength="3000" @readonly($offer?->catalog_service_id)>{{ $offer?->description }}</textarea></div>
            <div class="form-check mb-3"><input type="hidden" name="active" value="0"><input type="checkbox" id="offer-active-{{ $offer?->id ?? 'new' }}" name="active" value="1" class="form-check-input" @checked($offer?->active ?? true)><label class="form-check-label" for="offer-active-{{ $offer?->id ?? 'new' }}">Visible en el catálogo de consultas</label></div>
            <button class="btn btn-primary" type="submit">Guardar consulta</button>
            @if($offer)<span class="ml-2 text-muted">{{ $offer->hasPrice() ? 'Precio configurado' : 'Sin contratación habilitada' }}</span>@endif
        </form>
    </div></div></div>@endforeach</div>
@stop

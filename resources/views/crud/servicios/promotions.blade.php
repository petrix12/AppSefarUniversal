@extends('adminlte::page')
@section('title', 'Precios temporales')
@section('content_header')<h1>Precios temporales de registro</h1>@stop
@section('content')
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p>Selecciona los servicios y guarda el precio con su fecha de inicio y fin. Al terminar, cada servicio vuelve automáticamente a su precio habitual.</p>
    <form method="POST" action="{{ route('crud.servicios.promotions.store') }}">
        @csrf
        <div class="card"><div class="card-body">
            <div class="row">
                <div class="col-md-4 mb-3"><label for="promo-price">Precio temporal en EUR</label><input id="promo-price" class="form-control" name="precio_temporal" type="number" min="1" step="1" value="{{ old('precio_temporal', 49) }}" required></div>
                <div class="col-md-4 mb-3"><label for="promo-start">Desde</label><input id="promo-start" class="form-control" name="precio_temporal_desde" type="date" value="{{ old('precio_temporal_desde', now(\App\Models\Servicio::PRICE_TIMEZONE)->format('Y-m-d')) }}" required></div>
                <div class="col-md-4 mb-3"><label for="promo-end">Último día incluido</label><input id="promo-end" class="form-control" name="precio_temporal_hasta" type="date" value="{{ old('precio_temporal_hasta') }}" required></div>
            </div>
            <p class="text-muted mb-0">Días completos en hora de Caracas. Los registros nuevos conservan el precio al crearse, aunque paguen más tarde. Las compras anteriores mantienen su importe.</p>
        </div></div>
        <div class="card"><div class="card-body">
            <div class="mb-3 d-flex flex-wrap" style="gap:.5rem">
                <button type="button" class="btn btn-outline-primary" data-select-price="299">Seleccionar servicios de 299 EUR</button>
                <button type="button" class="btn btn-outline-secondary" data-clear-services>Quitar selección</button>
            </div>
            <div class="table-responsive"><table class="table"><thead><tr><th>Aplicar</th><th>Servicio</th><th>Precio habitual</th><th>Precio temporal guardado</th></tr></thead><tbody>
            @forelse($servicios as $servicio)<tr>
                <td><input id="promo-service-{{ $servicio->id }}" name="servicios[]" value="{{ $servicio->id }}" type="checkbox" data-service-price="{{ $servicio->precio }}" @checked(in_array($servicio->id, old('servicios', [])))></td>
                <td><label for="promo-service-{{ $servicio->id }}" class="font-weight-normal mb-0">{{ $servicio->nombre }}</label><small class="d-block text-muted">{{ $servicio->id_hubspot }}</small></td>
                <td>{{ format_money($servicio->precio, 2) }} EUR</td>
                <td>@if($servicio->precio_temporal !== null){{ format_money($servicio->precio_temporal, 2) }} EUR · {{ $servicio->precio_temporal_desde?->format('d/m/Y') }}–{{ $servicio->precio_temporal_hasta?->format('d/m/Y') }}@else — @endif</td>
            </tr>@empty<tr><td colspan="4">No hay servicios de registro activos en EUR.</td></tr>@endforelse
            </tbody></table></div>
            <p class="text-muted">Guardar sustituye la promoción de los servicios seleccionados. Puedes modificar o quitar una promoción desde «Editar servicio».</p>
            <button class="btn btn-primary" type="submit">Guardar precios temporales</button>
            <a href="{{ route('crud.servicios.index') }}" class="btn btn-link">Volver a servicios</a>
        </div></div>
    </form>
@stop
@section('js')
<script>
    document.querySelector('[data-select-price]').addEventListener('click', function () {
        document.querySelectorAll('[data-service-price]').forEach(input => { input.checked = input.dataset.servicePrice === this.dataset.selectPrice; });
    });
    document.querySelector('[data-clear-services]').addEventListener('click', function () {
        document.querySelectorAll('[data-service-price]').forEach(input => { input.checked = false; });
    });
</script>
@stop

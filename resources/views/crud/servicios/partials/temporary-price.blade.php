@php($priceService = $servicio ?? null)
<fieldset class="border rounded p-3 mb-3">
    <legend class="h5 w-auto px-2">Precio temporal del registro</legend>
    <p class="text-muted">El precio habitual se conserva. Estas fechas incluyen el día completo en hora de Caracas. Las compras ya creadas conservan su importe.</p>
    <div class="row">
        <div class="col-md-4 mb-3">
            <label for="precio-temporal">Precio temporal en EUR</label>
            <input id="precio-temporal" name="precio_temporal" type="number" min="1" step="1" class="form-control" value="{{ old('precio_temporal', $priceService?->precio_temporal) }}">
            @error('precio_temporal')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col-md-4 mb-3">
            <label for="precio-temporal-desde">Desde</label>
            <input id="precio-temporal-desde" name="precio_temporal_desde" type="date" class="form-control" value="{{ old('precio_temporal_desde', $priceService?->precio_temporal_desde?->format('Y-m-d')) }}">
            @error('precio_temporal_desde')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
        <div class="col-md-4 mb-3">
            <label for="precio-temporal-hasta">Último día incluido</label>
            <input id="precio-temporal-hasta" name="precio_temporal_hasta" type="date" class="form-control" value="{{ old('precio_temporal_hasta', $priceService?->precio_temporal_hasta?->format('Y-m-d')) }}">
            @error('precio_temporal_hasta')<small class="text-danger">{{ $message }}</small>@enderror
        </div>
    </div>
    <small class="text-muted">Al terminar vuelve automáticamente al precio habitual. Para quitar esta promoción, deja vacíos los tres campos. Disponible para servicios de registro en EUR.</small>
</fieldset>

@if($servicio->hasTemporaryPrice())<del class="text-muted mr-1">{{ format_money($servicio->precio, 2) }} {{ $servicio->moneda ?? 'EUR' }}</del>@endif
<strong>{{ format_money($servicio->currentPrice(), 2) }} {{ $servicio->moneda ?? 'EUR' }}</strong>
@if($servicio->hasTemporaryPrice())<small class="d-block text-success">Precio temporal hasta el {{ $servicio->precio_temporal_hasta->format('d/m/Y') }} inclusive.</small>@endif

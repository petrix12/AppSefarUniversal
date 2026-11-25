<h3 class="h5">{{ $row['label'] }}</h3>
@if(!empty($row['description']))<p class="platform-value">{{ $row['description'] }}</p>@endif
<dl class="platform-data mb-3">
    <div><dt>Registro</dt><dd class="platform-value">{{ $row['resource_id'] ?: '—' }}</dd></div>
    <div><dt>Estado</dt><dd>{{ \App\Services\PlatformPresentation::status($row['status']) }}</dd></div>
    <div><dt>Importe</dt><dd>{{ $row['amount'] }}</dd></div>
    <div><dt>Titular</dt><dd class="platform-value">{{ $row['customer_id'] ?: '—' }}</dd></div>
    @if($row['created'])
        <div><dt>Fecha (UTC)</dt><dd>{{ \Carbon\Carbon::createFromTimestampUTC($row['created'])->format('d/m/Y H:i:s') }}</dd></div>
    @endif
    @if($row['event_id'])
        <div><dt>Evento</dt><dd class="platform-value">{{ $row['event_id'] }}<br>{{ $row['type'] }}</dd></div>
    @endif
    @if($row['error_code'])
        <div><dt>Código de Stripe</dt><dd class="platform-value">{{ $row['error_code'] }}</dd></div>
    @endif
    @if(!empty($row['decline_code']))
        <div><dt>Código de rechazo</dt><dd class="platform-value">{{ $row['decline_code'] }}</dd></div>
    @endif
    @if(!empty($row['network_status']))
        <div><dt>Respuesta de la red</dt><dd class="platform-value">{{ $row['network_status'] }}</dd></div>
    @endif
    @if(!empty($row['outcome_type']))
        <div><dt>Resultado del cobro</dt><dd class="platform-value">{{ $row['outcome_type'] }}</dd></div>
    @endif
</dl>
@if($row['reason'] !== '—')
    <p class="platform-value"><strong>Diagnóstico de Stripe</strong><br>{{ $row['reason'] }}</p>
@endif
<div class="platform-toolbar">
    @php
        $related = [
            'Consultar registro actual' => $row['event_id'] ? $row['resource_id'] : null,
            'Ver cobro asociado' => $row['charge_id'] ?? null,
            'Ver intento asociado' => $row['payment_intent_id'] ?? null,
        ];
        $shown = [$row['event_id'] ?: $row['resource_id']];
    @endphp
    @foreach($related as $label => $id)
        @if(is_string($id) && preg_match('/^(ch|pi|in|re|du|dp)_[A-Za-z0-9_-]{1,180}$/D', $id) && !in_array($id, $shown, true))
            @php($shown[] = $id)
            <button type="button" class="btn btn-outline-primary" data-platform-record="{{ route('admin.stripe.detail', [$account, $id]) }}">{{ $label }}</button>
        @endif
    @endforeach

    @if($row['lookup_resource'] === 'payment_intents' && preg_match('/^pi_[A-Za-z0-9_-]{1,180}$/D', $row['resource_id']))
        <a class="btn btn-outline-primary" href="{{ route('admin.stripe.workspace', ['account' => $account, 'dataset' => 'charges', 'q' => $row['resource_id']]) }}">Ver cobros de este intento</a>
    @endif

    @if($canOperate && in_array($row['lookup_resource'], ['charges', 'payment_intents'], true) && !$row['event_id'])
        <form method="POST" action="{{ route('admin.platforms.lookup', ['stripe', $account, $row['lookup_resource']]) }}" data-platform-lookup>
            @csrf
            <input type="hidden" name="remote_id" value="{{ $row['resource_id'] }}">
            <button type="submit" class="btn btn-outline-primary">{{ match ($row['operation']) {
                'refund' => 'Gestionar reembolso',
                'cancel' => 'Gestionar cancelación',
                default => 'Revisar operaciones',
            } }}</button>
        </form>
    @endif
</div>

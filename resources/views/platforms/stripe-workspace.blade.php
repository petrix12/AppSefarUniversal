@extends('platforms.layout')
@section('platform_title', 'Pagos y actividad')
@section('platform_content')
@php
    $sections = [
        'charges' => 'Pagos',
        'events' => 'Actividad y fallos',
        'payment_intents' => 'Intentos de pago',
        'invoices' => 'Facturas',
        'refunds' => 'Reembolsos',
        'disputes' => 'Contracargos',
    ];
    $scope = array_intersect_key($filters, array_flip(['account', 'date_from', 'date_to']));
@endphp
<div class="card"><div class="card-body">
    <nav class="platform-toolbar mb-4" aria-label="Información de Stripe">
        @foreach($sections as $key => $label)
            <a class="btn {{ $key === $dataset ? 'btn-primary' : 'btn-outline-secondary' }}"
                href="{{ route('admin.stripe.workspace', $scope + ['dataset' => $key]) }}"
                @if($key === $dataset) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.stripe.workspace') }}" class="mb-4">
        <input type="hidden" name="dataset" value="{{ $dataset }}">
        <div class="platform-toolbar align-items-end mb-3">
            <div>
                <label for="stripe-account">Cuenta</label>
                <select class="form-control" id="stripe-account" name="account">
                    <option value="main" @selected($account === 'main')>Stripe principal</option>
                    <option value="spain_legacy" @selected($readOnly)>España · solo consulta</option>
                </select>
            </div>
            @if($dataset === 'events')
                <div>
                    <label for="stripe-category">Actividad</label>
                    <select class="form-control" id="stripe-category" name="category">
                        <option value="failures" @selected($category === 'failures')>Fallos y rechazos</option>
                        <option value="payments" @selected($category === 'payments')>Pagos confirmados y cancelados</option>
                        <option value="refunds" @selected($category === 'refunds')>Reembolsos</option>
                        <option value="disputes" @selected($category === 'disputes')>Contracargos</option>
                        <option value="all" @selected($category === 'all')>Toda la actividad de pagos</option>
                    </select>
                </div>
            @else
                <div>
                    <label for="stripe-status">{{ $dataset === 'invoices' ? 'Estado' : 'Estado en la página' }}</label>
                    <select class="form-control" id="stripe-status" name="status">
                        <option value="">Todos</option>
                        @foreach($statuses as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div class="platform-filter">
                <label for="stripe-search">ID de Stripe</label>
                <input class="form-control" id="stripe-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="184"
                    placeholder="{{ $dataset === 'events' ? 'evt_…' : 'ch_…, pi_…, cus_…' }}">
            </div>
        </div>
        <div class="platform-toolbar align-items-end">
            <div><label for="stripe-from">Desde (UTC)</label><input class="form-control" type="date" id="stripe-from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div><label for="stripe-to">Hasta (UTC)</label><input class="form-control" type="date" id="stripe-to" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-search mr-1" aria-hidden="true"></i>Consultar Stripe</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.stripe.workspace', ['account' => $account, 'dataset' => $dataset]) }}">Limpiar filtros</a>
        </div>
    </form>

    @if($apiError)
        <div class="alert alert-warning" role="alert">{{ match ($apiError) {
            'not_configured' => 'Falta configurar la clave de Stripe para esta cuenta.',
            'http_401', 'http_403' => 'La clave de esta cuenta no pudo acceder a esta información en Stripe.',
            'resource_missing' => 'Stripe no encontró ese identificador en la cuenta seleccionada.',
            'http_429', 'rate_limit' => 'Stripe limitó temporalmente las consultas. Vuelve a intentarlo en unos segundos.',
            default => 'Stripe no pudo completar la consulta ('.$apiError.').',
        } }}</div>
    @endif
    @if($dataset === 'events')
        <p class="small text-muted">Stripe conserva los eventos consultables durante 30 días. Un pago puede generar varios eventos.</p>
    @endif

    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <h2 class="h5 mb-2">{{ $listLabel }}</h2>
        <span class="small text-muted">
            @if($liveMode !== null){{ $liveMode ? 'Producción' : 'Pruebas' }} · @endif{{ count($rows) }} resultados en esta página
        </span>
    </div>
    <div class="table-responsive" tabindex="0" aria-label="Resultados de Stripe">
        <table class="table table-striped">
            <thead><tr><th>Fecha (UTC)</th><th>Registro</th><th>Estado</th><th>Importe</th><th>Titular</th><th>Diagnóstico de Stripe</th><th>Detalle</th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                @php($id = $row['event_id'] ?: $row['resource_id'])
                <tr>
                    <td class="text-nowrap">{{ $row['created'] ? \Carbon\Carbon::createFromTimestampUTC($row['created'])->format('d/m/Y H:i') : '—' }}</td>
                    <td class="platform-value">
                        <strong>{{ $row['label'] }}</strong>
                        <small class="d-block text-muted">{{ $row['resource_id'] ?: $id }}</small>
                        @if($row['event_id'] && $row['resource_id'])<small class="d-block text-muted">{{ $row['event_id'] }}</small>@endif
                    </td>
                    <td>{{ $statuses[$row['status']] ?? \App\Services\PlatformPresentation::status($row['status']) }}</td>
                    <td class="text-nowrap">{{ $row['amount'] }}</td>
                    <td class="platform-value">{{ $row['customer_id'] ?: '—' }}</td>
                    <td class="platform-value">
                        @if($code = ($row['decline_code'] ?? null) ?: $row['error_code'])<strong>{{ $code }}</strong><br>@endif
                        {{ $row['reason'] }}
                    </td>
                    <td>
                        @if($id)
                            <button type="button" class="btn btn-outline-primary" data-platform-record="{{ route('admin.stripe.detail', [$account, $id]) }}">Ver detalle</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted">{{ $apiError ? 'No se pudieron cargar los resultados.' : ($nextCursor ? 'No hay coincidencias en esta página. Puedes seguir consultando.' : 'No hay coincidencias en esta página.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if(!empty($filters['starting_after']) || $nextCursor)
        <div class="platform-toolbar justify-content-end">
            @if(!empty($filters['starting_after']))
                <a class="btn btn-outline-secondary" href="{{ route('admin.stripe.workspace', \Illuminate\Support\Arr::except($filters, 'starting_after')) }}">Primera página</a>
            @endif
            @if($nextCursor)
                <a class="btn btn-outline-primary" href="{{ route('admin.stripe.workspace', array_replace($filters, ['starting_after' => $nextCursor])) }}">Siguiente página</a>
            @endif
        </div>
    @endif
</div></div>
@endsection

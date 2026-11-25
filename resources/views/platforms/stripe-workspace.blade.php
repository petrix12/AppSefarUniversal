@extends('platforms.layout')
@section('platform_css')
    <link rel="stylesheet" href="{{ asset('css/stripe-workspace.css') }}?v={{ filemtime(public_path('css/stripe-workspace.css')) }}">
@endsection

@section('platform_content')
@php
    $sections = [
        'charges' => ['Pagos', 'fa-credit-card'],
        'events' => ['Actividad y fallos', 'fa-stream'],
        'payment_intents' => ['Intentos de pago', 'fa-sync-alt'],
        'invoices' => ['Facturas', 'fa-file-invoice'],
        'refunds' => ['Reembolsos', 'fa-undo-alt'],
        'disputes' => ['Contracargos', 'fa-shield-alt'],
    ];
    $scope = \Illuminate\Support\Arr::only($filters, ['account', 'date_from', 'date_to']);
    $accountScope = \Illuminate\Support\Arr::only($filters, ['dataset', 'category', 'date_from', 'date_to']);
    $hasDates = filled($filters['date_from'] ?? null) || filled($filters['date_to'] ?? null);
    $hasFilters = $hasDates || filled($filters['q'] ?? null) || filled($filters['status'] ?? null)
        || ($dataset === 'events' && $category !== 'failures');
    $clearUrl = route('admin.stripe.workspace', ['account' => $account, 'dataset' => $dataset]);
    $error = match ($apiError) {
        'not_configured' => 'Falta configurar la clave de Stripe para esta cuenta.',
        'http_401', 'http_403' => 'La clave de esta cuenta no pudo acceder a esta información en Stripe.',
        'resource_missing' => 'Stripe no encontró ese identificador en la cuenta seleccionada.',
        'http_429', 'rate_limit' => 'Stripe limitó temporalmente las consultas. Vuelve a intentarlo en unos segundos.',
        default => 'Stripe no pudo completar la consulta. Vuelve a intentarlo.',
    };
@endphp

<section class="stripe-desk" aria-labelledby="stripe-heading">
    <header class="stripe-masthead">
        <div class="stripe-masthead-main">
            <span class="stripe-mark" aria-hidden="true"><i class="fab fa-stripe-s"></i></span>
            <div>
                <h1 id="stripe-heading">Operaciones de Stripe</h1>
                <p>{{ $readOnly ? 'España · solo consulta' : 'Cuenta principal' }}</p>
            </div>
        </div>
        @if($liveMode !== null)
            <span class="stripe-mode"><i class="fas {{ $liveMode ? 'fa-circle' : 'fa-flask' }}" aria-hidden="true"></i>{{ $liveMode ? 'Producción' : 'Pruebas' }}</span>
        @endif
    </header>

    <div class="stripe-workspace-grid">
        <aside class="stripe-rail">
            <nav class="stripe-account-switch" aria-label="Cuenta de Stripe">
                <p class="stripe-rail-label">Cuenta</p>
                @foreach(['main' => ['Principal', 'fa-building'], 'spain_legacy' => ['España', 'fa-history']] as $key => [$label, $icon])
                    <a href="{{ route('admin.stripe.workspace', ['account' => $key] + $accountScope) }}"
                        @if($account === $key) aria-current="true" @endif>
                        <i class="fas {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                        @if($key === 'spain_legacy')<span class="sr-only"> · solo consulta</span>@endif
                    </a>
                @endforeach
            </nav>
            <nav class="stripe-sections" aria-label="Secciones de Stripe">
                <p class="stripe-rail-label">Movimientos</p>
                @foreach($sections as $key => [$label, $icon])
                    <a href="{{ route('admin.stripe.workspace', $scope + ['dataset' => $key]) }}"
                        @if($key === $dataset) aria-current="page" @endif>
                        <i class="fas {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                    </a>
                @endforeach
            </nav>
        </aside>

        <section class="stripe-panel" aria-labelledby="stripe-results-heading">
            <header class="stripe-panel-header">
                <div>
                    <h2 id="stripe-results-heading">{{ $dataset === 'events' ? 'Actividad y fallos' : $listLabel }}</h2>
                    @if($dataset === 'events')<p>Stripe muestra los eventos de los últimos 30 días.</p>@endif
                </div>
                <a class="btn btn-outline-primary stripe-refresh" href="{{ route('admin.stripe.workspace', $filters) }}">
                    <i class="fas fa-sync-alt" aria-hidden="true"></i> Actualizar
                </a>
            </header>

            <form method="GET" action="{{ route('admin.stripe.workspace') }}" class="stripe-filters">
                <input type="hidden" name="account" value="{{ $account }}">
                <input type="hidden" name="dataset" value="{{ $dataset }}">

                <div class="stripe-filter-row">
                    <div>
                        <label for="stripe-search">Buscar por ID de Stripe</label>
                        <div class="stripe-search-field">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <input class="form-control" id="stripe-search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="184"
                                placeholder="{{ $dataset === 'events' ? 'evt_…' : 'ch_…, pi_…, cus_…' }}">
                        </div>
                    </div>
                    @if($dataset === 'events')
                        <div>
                            <label for="stripe-category">Tipo de actividad</label>
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
                    <button class="btn btn-primary stripe-apply" type="submit">Aplicar filtros</button>
                </div>

                <details class="stripe-dates" @if($hasDates) open @endif>
                    <summary><i class="far fa-calendar-alt" aria-hidden="true"></i> Fechas <span>(UTC)</span></summary>
                    <div class="stripe-date-fields">
                        <div><label for="stripe-from">Desde</label><input class="form-control" type="date" id="stripe-from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                        <div><label for="stripe-to">Hasta</label><input class="form-control" type="date" id="stripe-to" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
                    </div>
                </details>
            </form>

            @if($apiError)
                <div class="stripe-api-error" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><span>{{ $error }}</span></div>
            @endif

            <div class="stripe-result-bar">
                @if(!$apiError)
                    <span>{{ count($rows) === 1 ? '1 resultado' : count($rows).' resultados' }} en esta página</span>
                @endif
                @if($hasFilters)
                    <a href="{{ $clearUrl }}" class="stripe-clear"><i class="fas fa-times" aria-hidden="true"></i> Limpiar filtros</a>
                @endif
            </div>

            @if($rows)
                <div class="stripe-table-scroll" role="region" tabindex="0" aria-label="Resultados de Stripe">
                    <table class="stripe-table">
                        <thead><tr><th scope="col">Registro</th><th scope="col">Estado</th><th scope="col">Importe</th><th scope="col">Fecha (UTC)</th><th scope="col">Diagnóstico</th><th scope="col"><span class="sr-only">Detalle</span></th></tr></thead>
                        <tbody>
                        @foreach($rows as $row)
                            @php
                                $id = $row['event_id'] ?: $row['resource_id'];
                                $instant = $row['created'] ? \Carbon\Carbon::createFromTimestampUTC($row['created']) : null;
                                $code = ($row['decline_code'] ?? null) ?: $row['error_code'];
                                $reason = $row['reason'];
                            @endphp
                            <tr>
                                <td class="stripe-cell-record">
                                    <strong>{{ \Illuminate\Support\Str::limit(($row['description'] ?? '') ?: $row['label'], 72) }}</strong>
                                    <span class="stripe-reference">{{ $row['resource_id'] ?: $id }}</span>
                                    @if($row['customer_id'])<span class="stripe-reference">{{ $row['customer_id'] }}</span>@endif
                                </td>
                                <td class="stripe-cell-status">@include('platforms.partials.stripe-status', ['payment' => $row, 'statusLabels' => $statuses])</td>
                                <td class="stripe-cell-amount"><span class="stripe-mobile-label" aria-hidden="true">Importe</span>{{ $row['amount'] }}</td>
                                <td class="stripe-cell-date">
                                    <span class="stripe-mobile-label" aria-hidden="true">Fecha (UTC)</span>
                                    @if($instant)
                                        <time datetime="{{ $instant->toIso8601String() }}">{{ $instant->format('d/m/Y') }} <span>{{ $instant->format('H:i') }}</span></time>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="stripe-cell-reason">
                                    <span class="stripe-mobile-label" aria-hidden="true">Diagnóstico</span>
                                    @if($code)<span class="stripe-error-code">{{ $code }}</span>@endif
                                    @if($reason !== '—')
                                        <span class="stripe-reason-text">{{ \Illuminate\Support\Str::limit($reason, 150) }}</span>
                                    @elseif(!$code)
                                        <span class="stripe-reference">—</span>
                                    @endif
                                </td>
                                <td class="stripe-cell-action">
                                    @if($id)
                                        <button type="button" class="stripe-open" data-platform-record="{{ route('admin.stripe.detail', [$account, $id]) }}"
                                            aria-label="Ver detalle de {{ $row['resource_id'] ?: $id }}">Ver detalle <i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @elseif(!$apiError)
                <div class="stripe-empty">
                    <span class="stripe-empty-icon"><i class="fas fa-search" aria-hidden="true"></i></span>
                    <h3>{{ $hasFilters ? 'Sin coincidencias en esta página' : 'No hay movimientos para mostrar' }}</h3>
                    @if($nextCursor)
                        <p>Puedes continuar a la página siguiente.</p>
                    @elseif($hasDates)
                        <p>Amplía el intervalo o limpia los filtros.</p>
                    @elseif($hasFilters)
                        <p>Revisa el identificador o prueba con otros filtros.</p>
                    @endif
                </div>
            @endif

            @if(!empty($filters['starting_after']) || $nextCursor)
                <nav class="stripe-pagination" aria-label="Paginación de Stripe">
                    @if(!empty($filters['starting_after']))
                        <a class="btn btn-outline-secondary" href="{{ route('admin.stripe.workspace', \Illuminate\Support\Arr::except($filters, 'starting_after')) }}"><i class="fas fa-angle-double-left" aria-hidden="true"></i> Primera página</a>
                    @endif
                    @if($nextCursor)
                        <a class="btn btn-outline-primary" href="{{ route('admin.stripe.workspace', array_replace($filters, ['starting_after' => $nextCursor])) }}">Siguiente página <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    @endif
                </nav>
            @endif
        </section>
    </div>
</section>
@endsection

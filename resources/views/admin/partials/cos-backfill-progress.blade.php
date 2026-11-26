@php
    $calculated = (int) ($cosProgress['calculated'] ?? 0);
    $forReview = (int) ($cosProgress['hubspot_review'] ?? 0);
    $processed = $calculated + $forReview;
    $total = $cosProgress['total'] ?? null;
    $done = $cosProgress['status'] === 'completed';
    $running = $cosProgress['status'] === 'running';
    $retryAt = $cosProgress['retry_after'] ?? 0;
    $error = $cosProgress['error'] ?? null;
    $waiting = $retryAt > now()->timestamp;
    $percent = $done ? 100 : ($total > 0 ? min(100, 100 * $processed / $total) : 0);
    $status = match (true) {
        $done => ['Completado', 'success'],
        $waiting => ['Pausado por límite de HubSpot', 'warning'],
        $error !== null => ['Reintento pendiente', 'warning'],
        $running => ['En curso', 'info'],
        default => ['Pendiente de iniciar', 'secondary'],
    };
@endphp

<div class="d-flex flex-wrap justify-content-between align-items-baseline mb-2">
    <p class="mb-1 mr-3">
        <span class="h5 mb-0">{{ number_format($processed, 0, ',', '.') }}</span>
        @if($total !== null)
            de {{ number_format($total, 0, ',', '.') }}
        @endif
        representados procesados
    </p>
    <span class="badge badge-{{ $status[1] }} mb-1">{{ $status[0] }}</span>
</div>

@if($total !== null || $done)
    <div class="progress mb-3">
        <div class="progress-bar bg-info" role="progressbar" aria-label="Progreso de la carga inicial del COS"
             aria-valuenow="{{ round($percent, 1) }}" aria-valuemin="0" aria-valuemax="100" style="width: {{ $percent }}%"></div>
    </div>
@endif

@if($running || $done)
    <dl class="row mb-1">
        @foreach([
            'Calculados' => $calculated,
            'Sincronizados con HubSpot' => $cosProgress['hubspot_synced'] ?? 0,
            'Calculados con datos locales' => $cosProgress['without_hubspot_link'] ?? 0,
            'Sin estatus aplicable' => $cosProgress['without_status'] ?? 0,
            'Apartados para revisión' => $forReview,
        ] as $label => $count)
            <div class="col-6 col-md mb-2">
                <dt class="small text-muted">{{ $label }}</dt>
                <dd class="h5 mb-0">{{ number_format($count, 0, ',', '.') }}</dd>
            </div>
        @endforeach
    </dl>
@endif

@if($waiting)
    <p class="small mb-2">El cron retomará la carga a partir del {{ \Illuminate\Support\Carbon::createFromTimestamp($retryAt)->setTimezone(config('app.timezone'))->format('d/m/Y H:i T') }}.</p>
@elseif($error !== null)
    <div class="alert alert-warning py-2 mb-2" role="status">
        El cron reintentará el representado {{ $error['user_id'] }}.
        @if(isset($error['http_status'])) HubSpot devolvió HTTP {{ $error['http_status'] }}. @endif
        @if(isset($error['sqlstate'])) Código de base de datos {{ $error['sqlstate'] }}. @endif
        @if(isset($error['attempts'])) Intentos con este error {{ $error['attempts'] }}. @endif
    </div>
@elseif(! $running && ! $done)
    <p class="text-muted small mb-2">El avance aparecerá aquí cuando inicie el primer lote.</p>
@endif

@foreach(['last_scheduled_at' => 'Último lote programado', 'last_progress_at' => 'Último avance'] as $key => $label)
    @if(isset($cosProgress[$key]))
        <p class="small text-muted mb-1">{{ $label }}
            <time datetime="{{ $cosProgress[$key] }}">{{ \Illuminate\Support\Carbon::parse($cosProgress[$key])->setTimezone(config('app.timezone'))->format('d/m/Y H:i T') }}</time>
        </p>
    @endif
@endforeach

<p class="small mb-0">Webhooks de contactos y negocios en HubSpot
    <strong>{{ $cosProgress['hubspot_scoped_processing'] ? 'Activos' : ($done ? 'Pendientes de configuración' : 'En espera de la carga') }}</strong>
</p>

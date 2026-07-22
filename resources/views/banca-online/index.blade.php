@php
    $serviceName = $country['service_name'] ?? 'Servicio Sefar';
    $selectedStatus = $caseStatusOptions[$selectedCaseStatus] ?? null;
    $recommendedPlanSlug = $recommendation['plan_slug'] ?? null;
    $hasHiddenHighlights = collect($plans)->contains(fn ($plan) => count($plan['highlights'] ?? []) > 2);
    $boCssPath = public_path('css/banca-online-2026.css');
    $boJsPath = public_path('js/banca-online-2026.js');
    $boCssVersion = file_exists($boCssPath) ? filemtime($boCssPath) : time();
    $boJsVersion = file_exists($boJsPath) ? filemtime($boJsPath) : time();
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Banca Online 2026 | Sefar Universal</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/banca-online-2026.css') }}?v={{ $boCssVersion }}">
</head>
<body class="bo-page">
    <header class="bo-topbar">
        <div class="bo-topbar-inner">
            <a class="bo-brand" href="{{ route('banca-online.country', $countrySlug) }}">
                <img src="{{ asset('img/logo2.png') }}" alt="Sefar Universal">
                <span>Banca Online 2026</span>
            </a>
            <nav class="bo-country-tabs" aria-label="Servicio solicitado">
                @foreach($countries as $slug => $item)
                    <a class="{{ $countrySlug === $slug ? 'active' : '' }}" href="{{ route('banca-online.country', $slug) }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="bo-container">
        <section class="bo-intro" aria-label="Banca Online 2026">
            <div class="bo-intro-main">
                <span class="bo-eyebrow"><i class="fas fa-route"></i> Banca Online 2026</span>
                <h1>Elige el siguiente paso de tu expediente</h1>
                <p>Te orientamos segun tu situacion actual para que actives el alcance profesional correcto, sin vueltas.</p>
                <div class="bo-intro-actions">
                    <a class="bo-button bo-button-primary bo-intro-action" href="#case-status-heading">
                        Empezar diagnostico <i class="fas fa-arrow-down" aria-hidden="true"></i>
                    </a>
                </div>
                <div class="bo-help-steps" aria-label="Resumen del proceso">
                    <span><i class="fas fa-map-marker-alt" aria-hidden="true"></i> Situacion actual</span>
                    <span><i class="fas fa-compass" aria-hidden="true"></i> Ruta sugerida</span>
                    <span><i class="fas fa-lock" aria-hidden="true"></i> Activacion segura</span>
                </div>
            </div>

            <aside class="bo-switch-panel">
                <div class="bo-motion-stage" data-bo-motion-stage aria-hidden="true">
                    <div class="bo-motion-sweep"></div>
                    <div class="bo-motion-rings">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="bo-motion-fallback">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                    <div class="bo-motion-core">
                        <span>Ruta activa</span>
                        <strong>2026</strong>
                        <small>Diagnostico, estrategia y activacion</small>
                    </div>
                    <div class="bo-motion-node bo-motion-node-a">
                        <i class="fas fa-user-check" aria-hidden="true"></i>
                        <span>Contexto</span>
                    </div>
                    <div class="bo-motion-node bo-motion-node-b">
                        <i class="fas fa-compass" aria-hidden="true"></i>
                        <span>Ruta</span>
                    </div>
                    <div class="bo-motion-node bo-motion-node-c">
                        <i class="fas fa-lock" aria-hidden="true"></i>
                        <span>Pago</span>
                    </div>
                </div>
                <div class="bo-switch-copy">
                    <h2>Tu solicitud</h2>
                    <p>Trabajaremos la estrategia dentro del servicio que corresponde a tu expediente.</p>
                    <div class="bo-service-name"><i class="fas fa-passport"></i> {{ $serviceName }}</div>
                </div>
                <div class="bo-helper-list" aria-label="Ayuda rapida">
                    <span><i class="fas fa-user-check" aria-hidden="true"></i> Revisamos tu contexto</span>
                    <span><i class="fas fa-file-signature" aria-hidden="true"></i> Dejamos trazabilidad del pago</span>
                </div>
            </aside>
        </section>

        @include('banca-online.partials.expediente-context', ['expedienteContext' => $expedienteContext ?? []])

        <section class="bo-panel bo-case-panel" aria-labelledby="case-status-heading">
            <div class="bo-package-heading">
                <div>
                    <span class="bo-section-kicker">Situacion actual</span>
                    <h2 id="case-status-heading">En que punto estas?</h2>
                </div>
                @if($selectedStatus)
                    <p>{{ $selectedStatus['summary'] }}</p>
                @endif
            </div>

            <div class="bo-case-grid">
                @foreach($caseStatusOptions as $slug => $option)
                    <a class="bo-option bo-case-option {{ $selectedCaseStatus === $slug ? 'selected' : '' }}"
                       href="{{ route('banca-online.country', array_merge(['country' => $countrySlug], $flowQuery, ['status' => $slug])) }}">
                        <span class="bo-case-dot"><i class="fas {{ $selectedCaseStatus === $slug ? 'fa-check' : 'fa-circle' }}" aria-hidden="true"></i></span>
                        <span>
                            <h3>{{ $option['label'] }}</h3>
                            <span class="bo-desc {{ $selectedCaseStatus === $slug ? '' : 'bo-reveal-target' }}">{{ $option['summary'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
            <div class="bo-reveal-row">
                <button
                    type="button"
                    class="bo-reveal-button"
                    data-bo-reveal=".bo-case-option .bo-reveal-target"
                    data-bo-reveal-scope=".bo-case-panel"
                    data-show-label="Ver todas las descripciones"
                    data-hide-label="Ocultar descripciones"
                    aria-expanded="false">
                    Ver todas las descripciones <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
            </div>
        </section>

        @if($recommendation['matched'] ?? false)
            <section class="bo-recommendation-band" aria-label="Recomendacion estrategica">
                <div>
                    <span class="bo-section-kicker">Ruta sugerida</span>
                    <h2>{{ $recommendation['plan_title'] ?? 'Estrategia recomendada' }}</h2>
                    <p>{{ $recommendation['reason'] }}</p>
                </div>
                <a class="bo-button bo-button-primary"
                   href="{{ route('banca-online.rationale.country', array_merge(['country' => $countrySlug, 'plan' => $recommendedPlanSlug], $flowQuery)) }}">
                    Ver alcance recomendado <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </section>
        @endif

        <section class="bo-strategy-grid" aria-label="Planes estrategicos">
            @foreach($plans as $slug => $plan)
                @php
                    $isRecommended = $recommendedPlanSlug === $slug;
                    $visibleHighlights = array_slice($plan['highlights'] ?? [], 0, 2);
                    $hiddenHighlights = array_slice($plan['highlights'] ?? [], 2);
                @endphp
                <article class="bo-strategy-card {{ $isRecommended ? 'is-featured' : '' }}">
                    <div class="bo-strategy-head">
                        <span class="bo-strategy-number">{{ $loop->iteration }}</span>
                        <span class="bo-strategy-eyebrow">
                            {{ $isRecommended ? 'Recomendada para tu situacion' : ($plan['eyebrow'] ?? 'Ruta estrategica') }}
                        </span>
                        <h2>{{ $plan['public_title'] ?? $plan['title'] }}</h2>
                    </div>
                    <div class="bo-strategy-body">
                        <p>{{ $plan['intro'] ?? $plan['summary'] }}</p>
                        <ul>
                            @foreach($visibleHighlights as $highlight)
                                <li>{{ $highlight }}</li>
                            @endforeach
                            @foreach($hiddenHighlights as $highlight)
                                <li class="bo-reveal-target">{{ $highlight }}</li>
                            @endforeach
                        </ul>
                        <a class="bo-strategy-action" href="{{ route('banca-online.rationale.country', array_merge(['country' => $countrySlug, 'plan' => $slug], $flowQuery)) }}">
                            <span>{{ $isRecommended ? 'Entender recomendacion' : ($plan['action'] ?? 'Ver estrategia') }}</span>
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>
            @endforeach
        </section>
        @if($hasHiddenHighlights)
            <div class="bo-reveal-row">
                <button
                    type="button"
                    class="bo-reveal-button"
                    data-bo-reveal=".bo-strategy-grid .bo-reveal-target"
                    data-bo-reveal-scope="main"
                    data-show-label="Ver detalles de las rutas"
                    data-hide-label="Ocultar detalles"
                    aria-expanded="false">
                    Ver detalles de las rutas <i class="fas fa-chevron-down" aria-hidden="true"></i>
                </button>
            </div>
        @endif

        <p class="bo-note">Antes de activar veras el alcance, sus servicios incluidos y el importe final.</p>
    </main>
    <script src="{{ asset('js/banca-online-2026.js') }}?v={{ $boJsVersion }}"></script>
</body>
</html>

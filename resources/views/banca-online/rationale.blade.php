@php
    $serviceName = $country['service_name'] ?? 'Servicio Sefar';
    $recommendedPlanSlug = $recommendation['plan_slug'] ?? null;
    $isRecommendedPlan = $recommendedPlanSlug === $planSlug;
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
    <title>{{ $rationale['title'] }} | Banca Online 2026</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/banca-online-2026.css') }}?v={{ $boCssVersion }}">
</head>
<body class="bo-page" data-bo-view="rationale">
    <header class="bo-topbar">
        <div class="bo-topbar-inner">
            <a class="bo-brand" href="{{ route('banca-online.country', array_merge(['country' => $countrySlug], $flowQuery)) }}">
                <img src="{{ asset('img/logo2.png') }}" alt="Sefar Universal">
                <span>Banca Online 2026</span>
            </a>
            <nav class="bo-country-tabs" aria-label="Servicio solicitado">
                @foreach($countries as $slug => $item)
                    <a class="{{ $countrySlug === $slug ? 'active' : '' }}" href="{{ route('banca-online.rationale.country', array_merge(['country' => $slug, 'plan' => $planSlug], $flowQuery)) }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="bo-container bo-rationale-page">
        <section class="bo-rationale-hero" data-bo-rationale-view data-plan="{{ $planSlug }}" data-country="{{ $countrySlug }}" data-status="{{ $selectedCaseStatus }}">
            <div>
                <span class="bo-eyebrow"><i class="fas fa-compass"></i> Recomendacion estrategica</span>
                <h1>{{ $rationale['title'] }}</h1>
                <p>{{ $rationale['reason'] }}</p>
                <div class="bo-rationale-meta">
                    <span><i class="fas fa-clipboard-check" aria-hidden="true"></i> {{ $rationale['case_status_label'] }}</span>
                    <span><i class="fas fa-passport" aria-hidden="true"></i> {{ $serviceName }}</span>
                    <span><i class="fas fa-door-open" aria-hidden="true"></i> {{ $entryPoint }}</span>
                </div>
                @if(!$isRecommendedPlan && $recommendedPlanSlug)
                    <div class="bo-inline-note">
                        La ruta sugerida para tu situacion es {{ $recommendation['plan_title'] ?? 'otra estrategia' }}.
                    </div>
                @endif
            </div>
            <aside class="bo-rationale-summary">
                <span>Objetivo</span>
                <strong>{{ $rationale['objective'] }}</strong>
            </aside>
        </section>

        @include('banca-online.partials.expediente-context', ['expedienteContext' => $expedienteContext ?? []])

        <section class="bo-rationale-grid" aria-label="Detalle de la recomendacion">
            <article class="bo-rationale-card">
                <i class="fas fa-bullseye" aria-hidden="true"></i>
                <h2>Resultado esperado</h2>
                <p>{{ $rationale['expected_result'] }}</p>
            </article>
            <article class="bo-rationale-card">
                <i class="fas fa-users-cog" aria-hidden="true"></i>
                <h2>Equipo que interviene</h2>
                <ul>
                    @foreach($rationale['professionals'] as $professional)
                        <li>{{ $professional }}</li>
                    @endforeach
                </ul>
            </article>
            <article class="bo-rationale-card">
                <i class="fas fa-folder-open" aria-hidden="true"></i>
                <h2>Documentacion habitual</h2>
                <ul>
                    @foreach($rationale['documents'] as $document)
                        <li>{{ $document }}</li>
                    @endforeach
                </ul>
            </article>
        </section>

        <section class="bo-rationale-next">
            <div>
                <span class="bo-section-kicker">Siguiente paso</span>
                <h2>Revisa los alcances antes de activar</h2>
                <p>{{ $rationale['afterwards'] }}</p>
            </div>
            <div class="bo-rationale-actions">
                <a class="bo-button bo-button-secondary" href="{{ route('banca-online.country', array_merge(['country' => $countrySlug], $flowQuery)) }}">
                    Cambiar situacion
                </a>
                <a class="bo-button bo-button-primary" href="{{ route('banca-online.configure.country', array_merge(['country' => $countrySlug, 'plan' => $planSlug], $flowQuery)) }}">
                    Ver alcances disponibles <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </section>
    </main>

    <script src="{{ asset('js/banca-online-2026.js') }}?v={{ $boJsVersion }}"></script>
</body>
</html>

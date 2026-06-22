@php
    $serviceName = $country['service_name'] ?? 'Servicio Sefar';
    $planIcons = [
        'solicitud-estrategica' => 'fas fa-balance-scale',
        'administrativo' => 'fas fa-folder-open',
        'judicial' => 'fas fa-gavel',
        'reforzamiento-seguro' => 'fas fa-shield-alt',
    ];
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
    <link rel="stylesheet" href="{{ asset('css/banca-online-2026.css') }}">
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
                <span class="bo-eyebrow"><i class="fas fa-credit-card"></i> Banca Online 2026</span>
                <h1>{{ $serviceName }}</h1>
                <p>Configura el plan estrategico correspondiente, ajusta sus servicios y pasa directo al pago sin iniciar sesion.</p>
            </div>

            <aside class="bo-switch-panel">
                <div>
                    <h2>Servicio solicitado</h2>
                    <p>El plan quedara asociado al servicio nacional correspondiente.</p>
                    <div class="bo-service-name"><i class="fas fa-passport"></i> {{ $serviceName }}</div>
                </div>
                <nav class="bo-country-tabs" aria-label="Cambiar servicio">
                    @foreach($countries as $slug => $item)
                        <a class="{{ $countrySlug === $slug ? 'active' : '' }}" href="{{ route('banca-online.country', $slug) }}">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </nav>
            </aside>
        </section>

        <section class="bo-plan-grid" aria-label="Planes estrategicos">
            @foreach($plans as $slug => $plan)
                <a class="bo-plan-card" href="{{ route('banca-online.configure.country', [$countrySlug, $slug]) }}">
                    <span>
                        <span class="bo-plan-icon"><i class="{{ $planIcons[$slug] ?? 'fas fa-layer-group' }}"></i></span>
                        <h2>{{ $plan['short_title'] ?? $plan['title'] }}</h2>
                        <p>{{ $plan['summary'] ?? '' }}</p>
                    </span>
                    <span class="bo-plan-action">
                        Configurar <i class="fas fa-arrow-right"></i>
                    </span>
                </a>
            @endforeach
        </section>

        <p class="bo-note">Los importes se calculan segun los servicios seleccionados y los precios configurados por administracion.</p>
    </main>
    <script src="{{ asset('js/banca-online-2026.js') }}"></script>
</body>
</html>

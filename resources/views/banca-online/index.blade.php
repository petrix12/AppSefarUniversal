@php
    $serviceName = $country['service_name'] ?? 'Servicio Sefar';
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
                <h1>Planes estrategicos</h1>
                <p>Elige la ruta que corresponde a la situacion actual de tu expediente. Dentro encontraras tres niveles de cobertura predefinidos.</p>
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

        <section class="bo-strategy-grid" aria-label="Planes estrategicos">
            @foreach($plans as $slug => $plan)
                <article class="bo-strategy-card {{ $loop->iteration === 2 ? 'is-featured' : '' }}">
                    <div class="bo-strategy-head">
                        <span class="bo-strategy-number">{{ $loop->iteration }}</span>
                        <span class="bo-strategy-eyebrow">{{ $plan['eyebrow'] ?? 'Ruta estrategica' }}</span>
                        <h2>{{ $plan['public_title'] ?? $plan['title'] }}</h2>
                    </div>
                    <div class="bo-strategy-body">
                        <p>{{ $plan['intro'] ?? $plan['summary'] }}</p>
                        <ul>
                            @foreach(($plan['highlights'] ?? []) as $highlight)
                                <li>{{ $highlight }}</li>
                            @endforeach
                        </ul>
                        <a class="bo-strategy-action" href="{{ route('banca-online.configure.country', [$countrySlug, $slug]) }}">
                            <span>{{ $plan['action'] ?? 'Ver estrategia' }}</span>
                            <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>
            @endforeach
        </section>

        <p class="bo-note">Cada ruta ofrece paquetes Regular, Medium y Premium con componentes y precio definidos por administracion.</p>
    </main>
    <script src="{{ asset('js/banca-online-2026.js') }}"></script>
</body>
</html>

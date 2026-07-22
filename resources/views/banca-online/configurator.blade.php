@php
    $catalog = app(\App\Services\BancaOnlineCatalog::class);
    $oldPackageId = (int) old('package_id');
    $readyPackages = $packages->filter(fn ($package) => $catalog->packageIsReady($package));
    $defaultPackage = $readyPackages->first(fn ($package) => (bool) (($catalog->metadata($package)['recommended'] ?? false)))
        ?? $readyPackages->first();
    $selectedPackageId = $oldPackageId > 0 ? $oldPackageId : (int) ($defaultPackage?->id ?? 0);
    $selectedStatus = $caseStatusOptions[$selectedCaseStatus] ?? null;
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
    <title>{{ $plan['public_title'] ?? $plan['title'] }} | Banca Online 2026</title>
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
                    <a class="{{ $countrySlug === $slug ? 'active' : '' }}" href="{{ route('banca-online.configure.country', array_merge(['country' => $slug, 'plan' => $planSlug], $flowQuery)) }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="bo-container">
        <section class="bo-config-head">
            <div>
                <span class="bo-eyebrow"><i class="fas fa-route"></i> {{ $plan['eyebrow'] ?? 'Ruta estrategica' }}</span>
                <h1>{{ $plan['public_title'] ?? $plan['title'] }}</h1>
                <p>{{ $plan['intro'] ?? $plan['summary'] }}</p>
                <div class="bo-help-steps" aria-label="Ayuda del alcance">
                    <span><i class="fas fa-check-circle" aria-hidden="true"></i> Alcances claros</span>
                    <span><i class="fas fa-receipt" aria-hidden="true"></i> Importe visible</span>
                    <span><i class="fas fa-envelope-open-text" aria-hidden="true"></i> Seguimiento posterior</span>
                </div>
            </div>
            <div class="bo-service-chip"><i class="fas fa-passport"></i> {{ $country['service_name'] ?? 'Servicio Sefar' }}</div>
        </section>

        @include('banca-online.partials.expediente-context', ['expedienteContext' => $expedienteContext ?? []])

        <section class="bo-panel bo-rationale-panel">
            <div>
                <span class="bo-section-kicker">Por que recomendamos esta estrategia</span>
                <h2>{{ $recommendation['plan_title'] ?? ($plan['public_title'] ?? $plan['title']) }}</h2>
                <p>{{ $recommendation['reason'] ?? 'Esta estrategia corresponde al contexto indicado para tu expediente.' }}</p>
            </div>
            @if($selectedStatus)
                <div class="bo-rationale-meta">
                    <span><i class="fas fa-clipboard-check" aria-hidden="true"></i> {{ $selectedStatus['label'] }}</span>
                    <span><i class="fas fa-door-open" aria-hidden="true"></i> {{ $entryPoint }}</span>
                </div>
            @endif
        </section>

        @if($errors->any())
            <div class="bo-alert" role="alert">Revisa los campos marcados antes de continuar.</div>
        @endif

        @if($packages->isEmpty())
            <div class="bo-empty">
                <i class="fas fa-info-circle"></i>
                Las modalidades de esta estrategia aun no han sido sincronizadas.
            </div>
        @else
            <form method="POST" action="{{ route('banca-online.checkout.country', [$countrySlug, $planSlug]) }}" id="bancaCheckoutForm">
                @csrf
                <input type="hidden" name="country" value="{{ $countrySlug }}">
                <input type="hidden" name="entry_point" value="{{ $entryPoint }}">
                <input type="hidden" name="selected_case_status" value="{{ $selectedCaseStatus }}">
                @foreach($quoteContext as $key => $value)
                    @if($key !== 'authenticated_user_id')
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach

                <section class="bo-cart-resume is-hidden" id="boCartResume" aria-live="polite">
                    <div class="bo-cart-resume-copy">
                        <span><i class="fas fa-history" aria-hidden="true"></i> Activacion en progreso</span>
                        <h2 id="boCartResumeTitle">Progreso guardado</h2>
                        <p id="boCartResumeText">Tu seleccion queda guardada en este navegador.</p>
                    </div>
                    <div class="bo-cart-resume-actions">
                        <a class="bo-button bo-button-primary is-hidden" id="boCartContinue" href="#">
                            Continuar pago <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                        <button class="bo-button bo-button-secondary" type="button" id="boCartClear">
                            Descartar
                        </button>
                    </div>
                </section>

                <section class="bo-package-section" aria-labelledby="package-heading">
                    <div class="bo-package-heading">
                        <div>
                            <span class="bo-section-kicker">Alcance profesional</span>
                            <h2 id="package-heading">Elige un alcance</h2>
                        </div>
                        <p>Veras el resumen completo al seleccionar.</p>
                    </div>

                    <div class="bo-package-grid">
                        @foreach($packages as $package)
                            @php
                                $metadata = $catalog->metadata($package);
                                $items = $catalog->packageDisplayItems($package);
                                $subtotal = $catalog->packageSubtotal($package);
                                $discount = $catalog->packageDiscount($package);
                                $total = $catalog->packageTotal($package);
                                $ready = $catalog->packageIsReady($package);
                                $selected = $ready && $selectedPackageId === (int) $package->id;
                                $componentData = $items->values()->all();
                                $visibleItems = $items->take(3);
                                $hiddenItems = $items->slice(3);
                                $hiddenItemCount = max(0, $items->count() - $visibleItems->count());
                            @endphp
                            <label class="bo-package-card {{ ($metadata['recommended'] ?? false) ? 'is-recommended' : '' }} {{ $selected ? 'selected' : '' }} {{ $ready ? '' : 'is-unavailable' }}">
                                <input
                                    class="package-option"
                                    type="radio"
                                    name="package_id"
                                    value="{{ $package->id }}"
                                    data-price="{{ $total }}"
                                    data-name="{{ $package->nombre }}"
                                    data-components="{{ e(json_encode($componentData, JSON_UNESCAPED_UNICODE)) }}"
                                    {{ $selected ? 'checked' : '' }}
                                    {{ $ready ? 'required' : 'disabled' }}>

                                @if($metadata['recommended'] ?? false)
                                    <span class="bo-package-recommended"><i class="fas fa-star"></i> Recomendado</span>
                                @endif

                                <div class="bo-package-card-head">
                                    <span class="bo-package-tier">Alcance</span>
                                    <h3>{{ $package->nombre }}</h3>
                                    <p>{{ $package->descripcion_publica }}</p>
                                </div>

                                <div class="bo-package-price">
                                    @if($total > 0)
                                        @if($discount > 0)
                                            <del class="bo-package-old-price">{{ number_format($subtotal, 0, ',', '.') }} EUR</del>
                                        @endif
                                        <div class="bo-package-current-price">
                                            <strong>{{ number_format($total, 0, ',', '.') }}</strong>
                                            <span>EUR pago unico</span>
                                        </div>
                                        @if($discount > 0)
                                            <div class="bo-package-saving">
                                                <span>Ahorras {{ number_format($discount, 0, ',', '.') }} EUR</span>
                                            </div>
                                        @endif
                                    @else
                                        <strong>Por definir</strong>
                                    @endif
                                </div>

                                <ul class="bo-package-components">
                                    @forelse($visibleItems as $item)
                                        <li>
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                            <span class="bo-package-component-copy">
                                                <strong>{{ $item['name'] ?? 'Servicio incluido' }}</strong>
                                                @if(!empty($item['description']))<small>{{ $item['description'] }}</small>@endif
                                                @if(array_key_exists('price', $item))<span>{{ number_format((float) $item['price'], 0, ',', '.') }} EUR</span>@endif
                                            </span>
                                        </li>
                                    @empty
                                        <li class="is-empty"><i class="fas fa-clock" aria-hidden="true"></i> Contenido en definicion</li>
                                    @endforelse
                                    @foreach($hiddenItems as $item)
                                        <li class="bo-reveal-target">
                                            <i class="fas fa-check" aria-hidden="true"></i>
                                            <span class="bo-package-component-copy">
                                                <strong>{{ $item['name'] ?? 'Servicio incluido' }}</strong>
                                                @if(!empty($item['description']))<small>{{ $item['description'] }}</small>@endif
                                                @if(array_key_exists('price', $item))<span>{{ number_format((float) $item['price'], 0, ',', '.') }} EUR</span>@endif
                                            </span>
                                        </li>
                                    @endforeach
                                </ul>

                                @if($hiddenItemCount > 0)
                                    <button
                                        type="button"
                                        class="bo-reveal-button bo-reveal-button-small"
                                        data-bo-reveal=".bo-package-components .bo-reveal-target"
                                        data-bo-reveal-scope=".bo-package-card"
                                        data-show-label="Ver todos los servicios"
                                        data-hide-label="Ocultar servicios extra"
                                        aria-expanded="false">
                                        Ver todos los servicios <i class="fas fa-chevron-down" aria-hidden="true"></i>
                                    </button>
                                @endif

                                <span
                                    class="bo-package-select"
                                    data-select-label="{{ $ready ? 'Seleccionar alcance' : 'Proximamente' }}"
                                    data-selected-label="Seleccionado">
                                    {{ $ready ? 'Seleccionar alcance' : 'Proximamente' }}
                                    @if($ready)<i class="fas fa-arrow-right" aria-hidden="true"></i>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('package_id') <div class="bo-error">{{ $message }}</div> @enderror
                </section>

                <div class="bo-package-checkout">
                    <aside class="bo-panel bo-package-summary">
                        <div class="bo-total-label">Alcance seleccionado</div>
                        <h2 id="selectedPackageName">Selecciona una opcion</h2>
                        <div class="bo-total"><span id="totalAmount">0</span> <small>EUR</small></div>
                        <p class="bo-package-helper">Aqui veras todos los servicios incluidos antes de activar.</p>
                        <ul class="bo-selected-list" id="selectedList"></ul>
                    </aside>

                    <section class="bo-panel bo-client-panel">
                        <h2>Datos del cliente</h2>
                        <div class="bo-form">
                            <label class="bo-field">
                                <span>Correo electronico</span>
                                <input type="email" name="email" id="emailLookup" value="{{ old('email') }}" required autocomplete="email">
                                @error('email') <div class="bo-error">{{ $message }}</div> @enderror
                            </label>

                            <div class="bo-status" id="lookupStatus"></div>
                            <div class="bo-lookup-expediente is-hidden" id="boLookupExpediente" aria-live="polite"></div>

                            <div class="bo-inline-note">
                                Usa tu correo principal. Si aun no tienes cuenta Sefar, la crearemos para asociar esta activacion; el registro inicial puede pagarse y completarse despues.
                            </div>

                            <button class="bo-button bo-button-primary" type="submit" {{ $readyPackages->isEmpty() ? 'disabled' : '' }}>
                                Continuar con la activacion <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </section>
                </div>
            </form>
        @endif
    </main>

    <script src="{{ asset('js/banca-online-2026.js') }}?v={{ $boJsVersion }}"></script>
</body>
</html>

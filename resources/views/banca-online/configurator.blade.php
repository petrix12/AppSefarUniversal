@php
    $catalog = app(\App\Services\BancaOnlineCatalog::class);
    $oldPackageId = (int) old('package_id');
    $showNewClientFields = $errors->has('nombres')
        || $errors->has('apellidos')
        || $errors->has('phone')
        || $errors->has('passport')
        || $errors->has('pais_de_nacimiento')
        || $errors->has('referido')
        || $errors->has('tiene_hermanos');
    $readyPackages = $packages->filter(fn ($package) => $catalog->packageIsReady($package));
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
                    <a class="{{ $countrySlug === $slug ? 'active' : '' }}" href="{{ route('banca-online.configure.country', [$slug, $planSlug]) }}">
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
            </div>
            <div class="bo-service-chip"><i class="fas fa-passport"></i> {{ $country['service_name'] ?? 'Servicio Sefar' }}</div>
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

                <section class="bo-package-section" aria-labelledby="package-heading">
                    <div class="bo-package-heading">
                        <div>
                            <span class="bo-section-kicker">Nivel de cobertura</span>
                            <h2 id="package-heading">Elige tu modalidad</h2>
                        </div>
                        <p>Los servicios de cada modalidad son fijos.</p>
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
                                $selected = $ready && $oldPackageId === (int) $package->id;
                                $componentData = $items->values()->all();
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
                                    <span class="bo-package-tier">Modalidad</span>
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
                                    @forelse($items as $item)
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
                                </ul>

                                <span class="bo-package-select">
                                    {{ $ready ? 'Elegir modalidad' : 'Proximamente' }}
                                    @if($ready)<i class="fas fa-arrow-right" aria-hidden="true"></i>@endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('package_id') <div class="bo-error">{{ $message }}</div> @enderror
                </section>

                <div class="bo-package-checkout">
                    <aside class="bo-panel bo-package-summary">
                        <div class="bo-total-label">Modalidad seleccionada</div>
                        <h2 id="selectedPackageName">Selecciona una opcion</h2>
                        <div class="bo-total"><span id="totalAmount">0</span> <small>EUR</small></div>
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

                            <div id="newClientFields" class="bo-form {{ $showNewClientFields ? '' : 'is-hidden' }}">
                                <div class="bo-field-grid">
                                    <label class="bo-field">
                                        <span>Nombres</span>
                                        <input data-required-when-new type="text" name="nombres" value="{{ old('nombres') }}" autocomplete="given-name">
                                        @error('nombres') <div class="bo-error">{{ $message }}</div> @enderror
                                    </label>
                                    <label class="bo-field">
                                        <span>Apellidos</span>
                                        <input data-required-when-new type="text" name="apellidos" value="{{ old('apellidos') }}" autocomplete="family-name">
                                        @error('apellidos') <div class="bo-error">{{ $message }}</div> @enderror
                                    </label>
                                </div>

                                <div class="bo-field-grid">
                                    <label class="bo-field">
                                        <span>Telefono</span>
                                        <input data-required-when-new type="tel" name="phone" value="{{ old('phone') }}" autocomplete="tel">
                                        @error('phone') <div class="bo-error">{{ $message }}</div> @enderror
                                    </label>
                                    <label class="bo-field">
                                        <span>Pasaporte</span>
                                        <input data-required-when-new type="text" name="passport" value="{{ old('passport') }}">
                                        @error('passport') <div class="bo-error">{{ $message }}</div> @enderror
                                    </label>
                                </div>

                                <label class="bo-field">
                                    <span>Pais de nacimiento</span>
                                    <input data-required-when-new type="text" name="pais_de_nacimiento" value="{{ old('pais_de_nacimiento') }}">
                                    @error('pais_de_nacimiento') <div class="bo-error">{{ $message }}</div> @enderror
                                </label>

                                <label class="bo-field">
                                    <span>Referido por</span>
                                    <select data-required-when-new name="referido">
                                        <option value="">Selecciona</option>
                                        <option value="soporteit+familiares@sefarvzla.com" {{ old('referido') === 'soporteit+familiares@sefarvzla.com' ? 'selected' : '' }}>Amigo, conocido o familiar</option>
                                        <option value="soporteit+buscadores@sefarvzla.com" {{ old('referido') === 'soporteit+buscadores@sefarvzla.com' ? 'selected' : '' }}>Anuncio en buscadores</option>
                                        <option value="soporteit+google@sefarvzla.com" {{ old('referido') === 'soporteit+google@sefarvzla.com' ? 'selected' : '' }}>Google</option>
                                        <option value="soporteit+rrss@sefarvzla.com" {{ old('referido') === 'soporteit+rrss@sefarvzla.com' ? 'selected' : '' }}>Redes sociales</option>
                                        <option value="soporteit+otros@sefarvzla.com" {{ old('referido') === 'soporteit+otros@sefarvzla.com' ? 'selected' : '' }}>Otros</option>
                                    </select>
                                    @error('referido') <div class="bo-error">{{ $message }}</div> @enderror
                                </label>

                                <label class="bo-field">
                                    <span>Familiares en proceso</span>
                                    <select data-required-when-new name="tiene_hermanos" id="tieneHermanos">
                                        <option value="">Selecciona</option>
                                        <option value="0" {{ old('tiene_hermanos') === '0' ? 'selected' : '' }}>No</option>
                                        <option value="1" {{ old('tiene_hermanos') === '1' ? 'selected' : '' }}>Si</option>
                                    </select>
                                    @error('tiene_hermanos') <div class="bo-error">{{ $message }}</div> @enderror
                                </label>

                                <label class="bo-field {{ old('tiene_hermanos') === '1' ? '' : 'is-hidden' }}" id="familiarField">
                                    <span>Nombre del familiar</span>
                                    <input type="text" name="nombre_de_familiar_realizando_procesos" value="{{ old('nombre_de_familiar_realizando_procesos') }}">
                                    @error('nombre_de_familiar_realizando_procesos') <div class="bo-error">{{ $message }}</div> @enderror
                                </label>

                                <label class="bo-checkline">
                                    <input data-required-when-new type="checkbox" name="acepta_comunicaciones" value="1" {{ old('acepta_comunicaciones') ? 'checked' : '' }}>
                                    <span>Acepto recibir comunicaciones de Sefar Universal.</span>
                                </label>
                                @error('acepta_comunicaciones') <div class="bo-error">{{ $message }}</div> @enderror

                                <label class="bo-checkline">
                                    <input data-required-when-new type="checkbox" name="acepta_datos" value="1" {{ old('acepta_datos') ? 'checked' : '' }}>
                                    <span>Acepto el almacenamiento y procesamiento de mis datos personales.</span>
                                </label>
                                @error('acepta_datos') <div class="bo-error">{{ $message }}</div> @enderror
                            </div>

                            <button class="bo-button bo-button-primary" type="submit" {{ $readyPackages->isEmpty() ? 'disabled' : '' }}>
                                Continuar al pago <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </section>
                </div>
            </form>
        @endif
    </main>

    <script src="{{ asset('js/banca-online-2026.js') }}"></script>
</body>
</html>

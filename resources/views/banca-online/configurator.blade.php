@php
    $catalog = app(\App\Services\BancaOnlineCatalog::class);
    $oldSelected = collect(old('selected_items', []))->map(fn ($id) => (int) $id);
    $showNewClientFields = $errors->has('nombres')
        || $errors->has('apellidos')
        || $errors->has('phone')
        || $errors->has('passport')
        || $errors->has('pais_de_nacimiento')
        || $errors->has('referido')
        || $errors->has('tiene_hermanos');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $plan['title'] }} | Banca Online 2026</title>
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
                <span class="bo-eyebrow"><i class="fas fa-layer-group"></i> Configurador</span>
                <h1>{{ $plan['title'] }}</h1>
                <p>{{ $plan['summary'] ?? '' }}</p>
            </div>
            <div class="bo-service-chip"><i class="fas fa-passport"></i> {{ $country['service_name'] ?? 'Servicio Sefar' }}</div>
        </section>

        @if($errors->any())
            <div class="bo-alert" role="alert">
                Revisa los campos marcados antes de continuar.
            </div>
        @endif

        @if($groupedServices->isEmpty())
            <div class="bo-empty">
                <i class="fas fa-info-circle"></i>
                El catalogo de este plan aun no esta sincronizado. Administracion debe cargar los items base y sus precios.
            </div>
        @else
            <form method="POST" action="{{ route('banca-online.checkout.country', [$countrySlug, $planSlug]) }}" id="bancaCheckoutForm">
                @csrf
                <input type="hidden" name="country" value="{{ $countrySlug }}">

                <div class="bo-layout">
                    <div class="bo-sections">
                        @foreach($groupedServices as $section => $services)
                            @php
                                $firstMeta = $catalog->metadata($services->first());
                            @endphp
                            <section class="bo-section">
                                <div class="bo-section-head">
                                    <h2>{{ $section }}</h2>
                                    @if(!empty($firstMeta['section_summary']))
                                        <p>{{ $firstMeta['section_summary'] }}</p>
                                    @endif
                                </div>

                                <div class="bo-options">
                                    @foreach($services as $servicio)
                                        @php
                                            $metadata = $catalog->metadata($servicio);
                                            $required = (bool) ($metadata['required'] ?? false);
                                            $locked = (bool) ($metadata['locked'] ?? false);
                                            $defaultSelected = (bool) ($metadata['default_selected'] ?? false);
                                            $checked = $required || $locked || ($oldSelected->isNotEmpty() ? $oldSelected->contains((int) $servicio->id) : $defaultSelected);
                                        @endphp
                                        <label class="bo-option {{ $checked ? 'selected' : '' }} {{ ($required || $locked) ? 'is-disabled' : '' }}">
                                            <input
                                                class="service-option"
                                                type="checkbox"
                                                name="selected_items[]"
                                                value="{{ $servicio->id }}"
                                                data-price="{{ (float) $servicio->precio }}"
                                                data-group="{{ $metadata['group'] ?? '' }}"
                                                data-name="{{ $servicio->nombre }}"
                                                {{ $checked ? 'checked' : '' }}
                                                {{ ($required || $locked) ? 'disabled' : '' }}>
                                            <span>
                                                <h3>{{ $servicio->nombre }}</h3>
                                                @if($servicio->descripcion_publica && $servicio->descripcion_publica !== ($metadata['section_summary'] ?? null))
                                                    <p class="bo-desc">{{ $servicio->descripcion_publica }}</p>
                                                @endif
                                                <span class="bo-badges">
                                                    @if($required)
                                                        <span class="bo-badge bo-badge-required"><i class="fas fa-lock"></i> Obligatorio</span>
                                                    @endif
                                                    @if(!empty($metadata['group']))
                                                        <span class="bo-badge"><i class="fas fa-random"></i> Alternativa</span>
                                                    @endif
                                                    <span class="bo-badge bo-badge-selected selected-label {{ $checked ? '' : 'is-hidden' }}">
                                                        <i class="fas fa-check"></i> Seleccionado
                                                    </span>
                                                </span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </section>
                        @endforeach
                        @error('selected_items')
                            <div class="bo-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <aside class="bo-side">
                        <section class="bo-panel">
                            <div class="bo-total-label">Total a pagar</div>
                            <div class="bo-total"><span id="totalAmount">{{ number_format($totalDefault, 2, ',', '.') }}</span> <small>EUR</small></div>
                            <div class="bo-summary-meta">
                                <span id="selectedCount">0 modulos</span>
                                <span>{{ $country['service_name'] ?? 'Servicio Sefar' }}</span>
                            </div>
                            <ul class="bo-selected-list" id="selectedList"></ul>
                        </section>

                        <section class="bo-panel">
                            <h2>Cliente</h2>
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

                                <button class="bo-button bo-button-primary" type="submit">
                                    Continuar al pago <i class="fas fa-arrow-right"></i>
                                </button>
                            </div>
                        </section>
                    </aside>
                </div>
            </form>
        @endif
    </main>

    <script src="{{ asset('js/banca-online-2026.js') }}"></script>
</body>
</html>

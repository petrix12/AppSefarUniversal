@php
    $catalog = app(\App\Services\BancaOnlineCatalog::class);
    $activePlan = $plans[$planSlug] ?? [];
    $activeTier = $tiers[$packageSlug] ?? [];
    $activePackageDefaults = $activePlan['packages'][$packageSlug] ?? [];
    $activePackageTitle = $activePackageDefaults['title'] ?? ($package?->nombre ?? ($activeTier['title'] ?? 'Paquete'));
    $activePackageSummary = $activePackageDefaults['summary'] ?? ($package?->descripcion_publica ?? ($activeTier['summary'] ?? ''));
    $countryCodes = ['espana' => 'ES', 'portugal' => 'PT', 'italia' => 'IT'];
    $packageMetadata = $package ? $catalog->metadata($package) : [];
    $selectedComponentIds = collect(old('component_ids', $packageMetadata['component_ids'] ?? []))->map(fn ($id) => (int) $id);
    $packageFeatures = $package ? $catalog->packageFeatures($package) : collect();
    $includedCount = $selectedComponentIds->count() ?: $packageFeatures->count();
    $discountType = old('discount_type', $packageMetadata['discount_type'] ?? 'percentage');
    $discountValue = old('discount_value', $packageMetadata['discount_value'] ?? 0);
@endphp

@extends('adminlte::page')

@section('title', 'Banca Online 2026')

@section('content_header')
    <div class="bo-admin-header">
        <div>
            <p class="sefar-eyebrow">Administracion de paquetes</p>
            <h1>Banca Online 2026</h1>
        </div>
        <form method="POST" action="{{ route('admin.banca-online.sync') }}">
            @csrf
            <input type="hidden" name="pais" value="{{ $countrySlug }}">
            <input type="hidden" name="plan" value="{{ $planSlug }}">
            <button type="submit" class="btn bo-sync-button" title="Sincronizar catalogo base">
                <i class="fas fa-sync-alt" aria-hidden="true"></i>
                <span>Sincronizar</span>
            </button>
        </form>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(($errors ?? null) && $errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bo-admin-workspace">
        <nav class="bo-country-switch" aria-label="Catalogo por pais">
            @foreach($countries as $slug => $item)
                @php
                    $countryPlan = array_key_first($catalog->plansForCountry($slug));
                @endphp
                <a class="bo-country-option {{ $countrySlug === $slug ? 'is-active' : '' }}"
                   href="{{ route('admin.banca-online.index', ['pais' => $slug, 'plan' => $countryPlan, 'paquete' => 'regular']) }}">
                    <span class="bo-country-code">{{ $countryCodes[$slug] ?? strtoupper(substr($slug, 0, 2)) }}</span>
                    <span class="bo-country-copy">
                        <strong>{{ $item['label'] }}</strong>
                        <small>{{ $item['service_name'] }}</small>
                    </span>
                    <span class="bo-country-count">{{ (int) ($countryCounts[$slug] ?? 0) }}</span>
                </a>
            @endforeach
        </nav>

        <section class="bo-admin-context">
            <div>
                <span class="bo-context-label">Catalogo activo</span>
                <h2>{{ $country['service_name'] ?? $country['label'] }}</h2>
                <p>
                    @if($expected > 0)
                        {{ $current }} de {{ $expected }} registros sincronizados
                    @else
                        {{ $current }} {{ $current === 1 ? 'registro configurado' : 'registros configurados' }}
                    @endif
                </p>
            </div>
            @if($catalog->isCountryPublic($countrySlug))
                <a class="btn btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('banca-online.country', $countrySlug) }}">
                    <i class="fas fa-external-link-alt mr-1" aria-hidden="true"></i> Ver publico
                </a>
            @else
                <span class="bo-preparation-label"><i class="fas fa-clock mr-1" aria-hidden="true"></i> En preparacion</span>
            @endif
        </section>

        <nav class="bo-plan-switch" aria-label="Ruta estrategica">
            @foreach($plans as $slug => $plan)
                <a class="{{ $planSlug === $slug ? 'is-active' : '' }}"
                   href="{{ route('admin.banca-online.index', ['pais' => $countrySlug, 'plan' => $slug, 'paquete' => 'regular']) }}"
                   title="{{ $plan['public_title'] ?? $plan['title'] }}">
                    {{ $plan['short_title'] ?? $plan['title'] }}
                </a>
            @endforeach
        </nav>

        <header class="bo-plan-header">
            <div>
                <h2>{{ $activePlan['public_title'] ?? $activePlan['title'] ?? 'Ruta estrategica' }}</h2>
                <p>{{ $activePlan['summary'] ?? '' }}</p>
            </div>
            <span>{{ $planCurrent }} {{ $planCurrent === 1 ? 'servicio' : 'servicios' }}</span>
        </header>

        <nav class="bo-package-switch" aria-label="Paquete">
            @foreach($tiers as $slug => $tier)
                @php
                    $tierPackage = $packages->first(fn ($item) => ($catalog->metadata($item)['tier_slug'] ?? null) === $slug);
                    $tierDefaults = $activePlan['packages'][$slug] ?? [];
                    $tierTitle = $tierDefaults['title'] ?? ($tierPackage?->nombre ?? ($tier['title'] ?? ucfirst($slug)));
                    $tierSummary = $tierDefaults['summary'] ?? ($tierPackage?->descripcion_publica ?? ($tier['summary'] ?? ''));
                    $tierRecommended = (bool) ($tierDefaults['recommended'] ?? ($tier['recommended'] ?? false));
                @endphp
                <a class="{{ $packageSlug === $slug ? 'is-active' : '' }}"
                   href="{{ route('admin.banca-online.index', ['pais' => $countrySlug, 'plan' => $planSlug, 'paquete' => $slug]) }}"
                   title="{{ $tierSummary }}">
                    <span>{{ $tierTitle }}</span>
                    @if($tierRecommended)<small>Recomendado</small>@endif
                    <i class="fas {{ $tierPackage && $catalog->packageIsReady($tierPackage) ? 'fa-check-circle is-ready' : 'fa-circle' }}" aria-hidden="true"></i>
                </a>
            @endforeach
        </nav>

        @if(!$package)
            <div class="bo-admin-empty">
                <i class="fas fa-sync-alt" aria-hidden="true"></i>
                <p>Sincroniza el catalogo para crear los paquetes de esta ruta.</p>
            </div>
        @else
            <form
                class="bo-package-editor"
                data-package-editor
                data-fixed-subtotal="{{ $packageMetadata['list_price'] ?? '' }}"
                data-fixed-total="{{ $packageMetadata['price'] ?? '' }}"
                data-fixed-feature-count="{{ $packageFeatures->count() }}"
                method="POST"
                action="{{ route('admin.banca-online.packages.update', $package) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="activo" value="0">

                <section class="bo-package-settings">
                    <div class="bo-package-settings-head">
                        <div>
                            <span class="bo-context-label">Paquete {{ $activePackageTitle }}</span>
                            <h3>Configuracion comercial</h3>
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="package-active" name="activo" value="1" {{ old('activo', $package->activo) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="package-active">Activo</label>
                        </div>
                    </div>

                    <div class="bo-package-settings-grid">
                        <label class="bo-field bo-package-name-field">
                            <span>Nombre del paquete</span>
                            <input class="form-control" type="text" name="nombre" value="{{ old('nombre', $activePackageTitle) }}" required>
                        </label>

                        <div class="bo-field bo-discount-type">
                            <span>Tipo de descuento</span>
                            <div class="bo-pricing-switch">
                                <label>
                                    <input type="radio" name="discount_type" value="percentage" {{ $discountType === 'percentage' ? 'checked' : '' }}>
                                    <span>Porcentaje</span>
                                </label>
                                <label>
                                    <input type="radio" name="discount_type" value="fixed" {{ $discountType === 'fixed' ? 'checked' : '' }}>
                                    <span>Importe fijo</span>
                                </label>
                            </div>
                        </div>

                        <label class="bo-field bo-discount-value">
                            <span>Valor del descuento</span>
                            <div class="bo-input-suffix">
                                <input class="form-control" data-discount-value type="number" name="discount_value" value="{{ $discountValue }}" min="0" step="0.01" required>
                                <span data-discount-unit>{{ $discountType === 'fixed' ? 'EUR' : '%' }}</span>
                            </div>
                        </label>

                        <label class="bo-field bo-package-description">
                            <span>Descripcion publica</span>
                            <textarea class="form-control" name="descripcion_publica" rows="2">{{ old('descripcion_publica', $activePackageSummary) }}</textarea>
                        </label>
                    </div>

                    @if($packageFeatures->isNotEmpty())
                        <div class="bo-fixed-package-preview">
                            <span>Beneficios publicos del paquete</span>
                            <ul>
                                @foreach($packageFeatures as $feature)
                                    <li>{{ $feature['name'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </section>

                <section class="bo-component-editor">
                    <header>
                        <div>
                            <h3>Servicios del paquete</h3>
                            <p>Cada servicio conserva su descripcion y costo base; aqui decides si el paquete lo incluye.</p>
                        </div>
                        <span data-included-count>{{ $includedCount }} {{ $includedCount === 1 ? 'incluido' : 'incluidos' }}</span>
                    </header>

                    @forelse($services as $section => $sectionItems)
                        <div class="bo-component-section">
                            <h4>{{ $section }}</h4>
                            @foreach($sectionItems as $component)
                                @php
                                    $included = $selectedComponentIds->contains((int) $component->id);
                                @endphp
                                <div class="bo-component-row {{ $included ? 'is-included' : '' }}" data-component-row>
                                    <label class="bo-component-check" title="Incluir componente">
                                        <input data-component-toggle type="checkbox" name="component_ids[]" value="{{ $component->id }}" {{ $included ? 'checked' : '' }}>
                                        <span><i class="fas fa-check" aria-hidden="true"></i></span>
                                    </label>
                                    <label class="bo-field">
                                        <span>Servicio</span>
                                        <input class="form-control" type="text" name="component_names[{{ $component->id }}]" value="{{ old("component_names.{$component->id}", $component->nombre) }}">
                                    </label>
                                    <label class="bo-field bo-component-description">
                                        <span>Descripcion</span>
                                        <textarea class="form-control" name="component_descriptions[{{ $component->id }}]" rows="2">{{ old("component_descriptions.{$component->id}", $component->descripcion_publica) }}</textarea>
                                    </label>
                                    <label class="bo-field bo-component-price">
                                        <span>Costo EUR</span>
                                        <input class="form-control" data-component-price type="number" name="component_prices[{{ $component->id }}]" value="{{ old("component_prices.{$component->id}", $component->precio ?? 0) }}" min="0" required>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <div class="bo-admin-empty"><p>No hay componentes disponibles para esta ruta.</p></div>
                    @endforelse
                </section>

                <div class="bo-package-savebar">
                    <div class="bo-package-totals">
                        <div>
                            <strong>Subtotal</strong>
                            <span data-package-subtotal>{{ number_format($catalog->packageSubtotal($package), 0, ',', '.') }} EUR</span>
                        </div>
                        <div>
                            <strong>Descuento</strong>
                            <span data-package-discount>-{{ number_format($catalog->packageDiscount($package), 0, ',', '.') }} EUR</span>
                        </div>
                        <div class="is-total">
                            <strong>Total del paquete</strong>
                            <span data-package-total>{{ number_format($catalog->packageTotal($package), 0, ',', '.') }} EUR</span>
                        </div>
                    </div>
                    <button type="submit" class="btn bo-save-package-button">
                        <i class="fas fa-save mr-1" aria-hidden="true"></i> Guardar paquete
                    </button>
                </div>
            </form>

            <details class="bo-create-service" {{ $errors->has('section') ? 'open' : '' }}>
                <summary>
                    <span><i class="fas fa-plus" aria-hidden="true"></i> Agregar servicio</span>
                    <i class="fas fa-chevron-down bo-details-arrow" aria-hidden="true"></i>
                </summary>
                <form method="POST" action="{{ route('admin.banca-online.items.store') }}">
                    @csrf
                    <input type="hidden" name="pais" value="{{ $countrySlug }}">
                    <input type="hidden" name="plan" value="{{ $planSlug }}">
                    <input type="hidden" name="paquete" value="{{ $packageSlug }}">
                    <input type="hidden" name="activo" value="1">
                    <div class="bo-create-grid">
                        <label class="bo-field bo-create-name">
                            <span>Servicio</span>
                            <input type="text" name="nombre" value="" class="form-control" required>
                        </label>
                        <label class="bo-field">
                            <span>Seccion</span>
                            <input type="text" name="section" value="General" class="form-control" required>
                        </label>
                        <label class="bo-field bo-create-description">
                            <span>Descripcion</span>
                            <input type="text" name="descripcion_publica" value="" class="form-control">
                        </label>
                        <label class="bo-field">
                            <span>Costo EUR</span>
                            <input type="number" name="precio" value="0" min="0" class="form-control" required>
                        </label>
                        <button type="submit" class="btn bo-add-button"><i class="fas fa-plus mr-1" aria-hidden="true"></i> Agregar</button>
                    </div>
                </form>
            </details>
        @endif
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-banca-online-2026.css') }}">
@stop

@section('js')
    <script>
        document.querySelectorAll('[data-package-editor]').forEach(function (editor) {
            const discountTypes = editor.querySelectorAll('input[name="discount_type"]');
            const discountValue = editor.querySelector('[data-discount-value]');
            const discountUnit = editor.querySelector('[data-discount-unit]');
            const rows = editor.querySelectorAll('[data-component-row]');
            const includedCount = editor.querySelector('[data-included-count]');
            const subtotalLabel = editor.querySelector('[data-package-subtotal]');
            const discountLabel = editor.querySelector('[data-package-discount]');
            const totalLabel = editor.querySelector('[data-package-total]');
            const fixedSubtotal = amount(editor.dataset.fixedSubtotal);
            const fixedTotal = amount(editor.dataset.fixedTotal);
            const fixedFeatureCount = Number(editor.dataset.fixedFeatureCount || 0);
            const money = new Intl.NumberFormat('es-ES', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });

            function amount(value) {
                const parsed = Number(value);

                return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
            }

            function refreshPackageTotal() {
                const selectedDiscount = editor.querySelector('input[name="discount_type"]:checked');
                const fixedDiscount = selectedDiscount && selectedDiscount.value === 'fixed';
                let selectedCount = 0;
                let subtotal = 0;

                rows.forEach(function (row) {
                    const toggle = row.querySelector('[data-component-toggle]');
                    const price = row.querySelector('[data-component-price]');
                    const included = toggle.checked;

                    row.classList.toggle('is-included', included);

                    if (included) {
                        selectedCount++;
                        subtotal += amount(price.value);
                    }
                });

                if (selectedCount === 0 && fixedTotal > 0) {
                    const fixedListPrice = fixedSubtotal > 0 ? fixedSubtotal : fixedTotal;
                    const fixedDiscount = Math.max(0, fixedListPrice - fixedTotal);
                    const benefitCount = fixedFeatureCount || selectedCount;

                    includedCount.textContent = benefitCount + (benefitCount === 1 ? ' incluido' : ' incluidos');
                    discountUnit.textContent = 'EUR';
                    subtotalLabel.textContent = money.format(fixedListPrice) + ' EUR';
                    discountLabel.textContent = '-' + money.format(fixedDiscount) + ' EUR';
                    totalLabel.textContent = money.format(fixedTotal) + ' EUR';
                    return;
                }

                const rawDiscount = amount(discountValue.value);
                const discount = fixedDiscount
                    ? Math.min(subtotal, rawDiscount)
                    : subtotal * Math.min(100, rawDiscount) / 100;
                const total = Math.max(0, subtotal - discount);

                includedCount.textContent = selectedCount + (selectedCount === 1 ? ' incluido' : ' incluidos');
                discountUnit.textContent = fixedDiscount ? 'EUR' : '%';
                discountValue.max = fixedDiscount ? '' : '100';
                subtotalLabel.textContent = money.format(subtotal) + ' EUR';
                discountLabel.textContent = '-' + money.format(discount) + ' EUR';
                totalLabel.textContent = money.format(total) + ' EUR';
            }

            discountTypes.forEach(function (input) { input.addEventListener('change', refreshPackageTotal); });
            discountValue.addEventListener('input', refreshPackageTotal);
            rows.forEach(function (row) {
                row.querySelector('[data-component-toggle]').addEventListener('change', refreshPackageTotal);
                row.querySelector('[data-component-price]').addEventListener('input', refreshPackageTotal);
            });
            refreshPackageTotal();
        });
    </script>
@stop

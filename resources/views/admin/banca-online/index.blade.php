@php
    $catalog = app(\App\Services\BancaOnlineCatalog::class);
    $activePlan = $plans[$planSlug] ?? [];
    $activeTier = $tiers[$packageSlug] ?? [];
    $activePackageDefaults = $activePlan['packages'][$packageSlug] ?? [];
    $activePackageTitle = $package?->nombre ?? ($activePackageDefaults['title'] ?? ($activeTier['title'] ?? 'Modalidad'));
    $activePackageSummary = $package?->descripcion_publica ?? ($activePackageDefaults['summary'] ?? ($activeTier['summary'] ?? ''));
    $countryCodes = ['espana' => 'ES', 'portugal' => 'PT', 'italia' => 'IT'];
    $packageMetadata = $package ? $catalog->metadata($package) : [];
    $packageFeatures = $package ? $catalog->packageFeatures($package) : collect();
    $listPrice = old('list_price', $package ? $catalog->packageSubtotal($package) : ($packageMetadata['list_price'] ?? 0));
    $packagePrice = old('price', $package ? $catalog->packageTotal($package) : ($packageMetadata['price'] ?? 0));
    $discountType = old('discount_type', 'fixed');
    $discountValue = old('discount_value', max(0, (float) $listPrice - (float) $packagePrice));
    $installmentPeriods = $package ? $catalog->packageInstallmentPeriods($package) : [];
    $installmentRules = $package ? $catalog->packageInstallmentRules($package) : $catalog->installmentInitialRuleDefaults();
    $featureNames = old('public_feature_names');
    $featureDescriptions = old('public_feature_descriptions');
    $oldRulePercents = old('installment_rule_min_percent');
    $oldRuleCounts = old('installment_rule_max_count');

    if (is_array($featureNames)) {
        $packageFeatures = collect($featureNames)->map(fn ($name, $index) => [
            'name' => $name,
            'description' => $featureDescriptions[$index] ?? null,
        ])->filter(fn ($feature) => trim((string) ($feature['name'] ?? '')) !== '')->values();
    }

    if (is_array($oldRulePercents)) {
        $installmentRules = collect($oldRulePercents)->map(fn ($percent, $index) => [
            'min_initial_percent' => $percent,
            'max_installments' => $oldRuleCounts[$index] ?? 1,
        ])->values()->all();
    }
@endphp

@extends('adminlte::page')

@section('title', 'Banca Online 2026')

@section('content_header')
    <div class="bo-admin-header">
        <div>
            <p class="sefar-eyebrow">Administracion de modalidades</p>
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
                   href="{{ route('admin.banca-online.index', ['pais' => $slug, 'plan' => $countryPlan, 'modalidad' => 'regular']) }}">
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

        @php
            $eventLabels = [
                'bo_case_status_selected' => 'Situacion elegida',
                'bo_nationality_selected' => 'Nacionalidad elegida',
                'bo_strategy_recommended' => 'Recomendacion generada',
                'bo_strategy_rationale_viewed' => 'Explicacion vista',
                'bo_activation_requested' => 'Activacion iniciada',
                'bo_activation_payment_completed' => 'Pago completado',
            ];
            $funnelKeys = [
                'bo_case_status_selected',
                'bo_strategy_rationale_viewed',
                'bo_activation_requested',
                'bo_activation_payment_completed',
            ];
        @endphp

        <section class="bo-admin-analytics" aria-label="Conversiones Banca Online">
            <div class="bo-analytics-head">
                <div>
                    <span class="bo-context-label">Sprint 1</span>
                    <h2>Conversiones y activaciones</h2>
                    <p>Ventana de {{ $eventWindowDays }} dias. Los pagos se leen desde compras de Banca Online.</p>
                </div>
                @unless($hasEventTracking)
                    <span class="bo-preparation-label">
                        <i class="fas fa-database mr-1" aria-hidden="true"></i> Migracion pendiente
                    </span>
                @endunless
            </div>

            <div class="bo-analytics-grid">
                @foreach($funnelKeys as $eventKey)
                    <div class="bo-analytics-card">
                        <span>{{ $eventLabels[$eventKey] }}</span>
                        <strong>{{ number_format((int) ($eventCounts[$eventKey] ?? 0), 0, ',', '.') }}</strong>
                    </div>
                @endforeach
                <div class="bo-analytics-card is-paid">
                    <span>Activaciones pagadas</span>
                    <strong>{{ number_format((int) $activationStats['paid'], 0, ',', '.') }}</strong>
                </div>
                <div class="bo-analytics-card">
                    <span>Ingresos Banca Online</span>
                    <strong>{{ number_format((float) $activationStats['revenue'], 0, ',', '.') }} EUR</strong>
                </div>
            </div>

            <div class="bo-admin-activity-grid">
                <div class="bo-admin-activity">
                    <h3>Activaciones recientes</h3>
                    <div class="bo-admin-activity-list">
                    @forelse($recentActivations as $activation)
                        @php
                            $metadata = $activation->metadata ?? [];
                            $planLabel = $metadata['plan_title'] ?? $metadata['plan_slug'] ?? 'Ruta estrategica';
                            $packageLabel = $metadata['package_title'] ?? $activation->servicio?->nombre ?? 'Alcance';
                            $statusLabel = $metadata['activation_status_label']
                                ?? ((int) $activation->pagado === 1 ? 'Pagado' : 'Pendiente');
                        @endphp
                        <div class="bo-activity-row">
                            <div>
                                <strong>{{ $activation->user?->email ?? 'Sin correo' }}</strong>
                                <span>{{ $planLabel }} · {{ $packageLabel }}</span>
                            </div>
                            <em class="{{ (int) $activation->pagado === 1 ? 'is-paid' : 'is-pending' }}">
                                {{ $statusLabel }}
                            </em>
                        </div>
                    @empty
                        <p class="bo-empty-line">Aun no hay activaciones de Banca Online.</p>
                    @endforelse
                    </div>
                </div>

                <div class="bo-admin-activity">
                    <h3>Eventos recientes</h3>
                    <div class="bo-admin-activity-list">
                    @if(!$hasEventTracking)
                        <p class="bo-empty-line">Aplica la migracion de eventos para comenzar a medir conversiones.</p>
                    @else
                        @forelse($recentEvents as $event)
                            <div class="bo-activity-row">
                                <div>
                                    <strong>{{ $eventLabels[$event->event] ?? $event->event }}</strong>
                                    <span>{{ $event->email ?? $event->user?->email ?? 'Visitante' }}</span>
                                </div>
                                <em>{{ optional($event->occurred_at)->format('d/m H:i') }}</em>
                            </div>
                        @empty
                            <p class="bo-empty-line">Sin eventos en la ventana seleccionada.</p>
                        @endforelse
                    @endif
                    </div>
                </div>
            </div>
        </section>

        <nav class="bo-plan-switch" aria-label="Ruta estrategica">
            @foreach($plans as $slug => $plan)
                <a class="{{ $planSlug === $slug ? 'is-active' : '' }}"
                   href="{{ route('admin.banca-online.index', ['pais' => $countrySlug, 'plan' => $slug, 'modalidad' => 'regular']) }}"
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

        <section class="bo-admin-section bo-document-rules">
            <header>
                <div>
                    <h3>Reglas documentales del expediente</h3>
                    <span>Define que documento activa una recomendacion y que servicio conviene ofrecer.</span>
                </div>
                @if($hasDocumentRulesTable)
                    <span>{{ $documentRules->count() }} regla(s)</span>
                @else
                    <span>Migracion pendiente</span>
                @endif
            </header>

            @unless($hasDocumentRulesTable)
                <div class="bo-admin-empty">
                    <i class="fas fa-database" aria-hidden="true"></i>
                    <p>Ejecuta la migracion de <code>banca_online_document_rules</code> para administrar documentos desde la app.</p>
                </div>
            @else
                <details class="bo-create-service">
                    <summary>
                        <span><i class="fas fa-plus mr-1" aria-hidden="true"></i> Agregar regla documental</span>
                        <i class="fas fa-chevron-down bo-details-arrow" aria-hidden="true"></i>
                    </summary>
                    <form method="POST" action="{{ route('admin.banca-online.document-rules.store') }}">
                        @csrf
                        <input type="hidden" name="pais" value="{{ $countrySlug }}">
                        <input type="hidden" name="plan" value="{{ $planSlug }}">
                        <input type="hidden" name="modalidad" value="{{ $packageSlug }}">

                        <div class="bo-document-rule-create-grid">
                            <label class="bo-field bo-rule-document-name">
                                <span>Documento</span>
                                <input class="form-control" type="text" name="document_name" placeholder="Ej. Partida de nacimiento apostillada" required>
                            </label>

                            <label class="bo-field">
                                <span>Tipo</span>
                                <select class="form-control" name="document_type" required>
                                    <option value="genealogico">Genealogico</option>
                                    <option value="juridico">Juridico</option>
                                    <option value="otro">Otro</option>
                                </select>
                            </label>

                            <label class="bo-field">
                                <span>Plan recomendado</span>
                                <select class="form-control" name="recommended_plan_slug">
                                    <option value="">Usar plan actual</option>
                                    @foreach($plans as $slug => $plan)
                                        <option value="{{ $slug }}">{{ $plan['short_title'] ?? $plan['title'] ?? $slug }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="bo-field bo-rule-service">
                                <span>Servicio recomendado</span>
                                <select class="form-control" name="recommended_service_id">
                                    <option value="">Sin servicio exacto</option>
                                    @foreach($documentServiceOptions as $service)
                                        <option value="{{ $service->id }}">{{ $service->nombre }} @if($service->categoria) · {{ $service->categoria }} @endif</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="bo-field">
                                <span>Prioridad</span>
                                <input class="form-control" type="number" name="priority" value="50" min="1" max="999">
                            </label>

                            <label class="bo-field bo-rule-keywords">
                                <span>Palabras clave</span>
                                <input class="form-control" type="text" name="match_keywords" placeholder="acta, nacimiento, apostilla">
                            </label>

                            <label class="bo-field bo-rule-client-copy">
                                <span>Texto para el cliente</span>
                                <textarea class="form-control" name="client_explanation" rows="2" placeholder="Explica por que recomendamos este apoyo documental."></textarea>
                            </label>

                            <div class="bo-document-rule-flags">
                                <input type="hidden" name="required" value="0">
                                <input type="hidden" name="active" value="0">
                                <input type="hidden" name="client_visible" value="0">
                                <label><input type="checkbox" name="required" value="1" checked> Requerido</label>
                                <label><input type="checkbox" name="active" value="1" checked> Activo</label>
                                <label><input type="checkbox" name="client_visible" value="1" checked> Visible cliente</label>
                            </div>

                            <button type="submit" class="btn bo-add-button">
                                <i class="fas fa-save mr-1" aria-hidden="true"></i> Guardar regla
                            </button>
                        </div>
                    </form>
                </details>

                <div class="bo-document-rule-list">
                    @forelse($documentRules as $rule)
                        @php
                            $keywords = implode(', ', $rule->match_keywords ?? []);
                        @endphp
                        <div class="bo-admin-item bo-document-rule-item">
                            <form method="POST" action="{{ route('admin.banca-online.document-rules.update', $rule) }}" class="bo-document-rule-form">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="pais" value="{{ $countrySlug }}">
                                <input type="hidden" name="plan" value="{{ $planSlug }}">
                                <input type="hidden" name="modalidad" value="{{ $packageSlug }}">

                                <div class="bo-document-rule-main">
                                    <label class="bo-field bo-rule-document-name">
                                        <span>Documento</span>
                                        <input class="form-control" type="text" name="document_name" value="{{ old('document_name', $rule->document_name) }}" required>
                                    </label>

                                    <label class="bo-field">
                                        <span>Tipo</span>
                                        <select class="form-control" name="document_type" required>
                                            @foreach(['genealogico' => 'Genealogico', 'juridico' => 'Juridico', 'otro' => 'Otro'] as $value => $label)
                                                <option value="{{ $value }}" {{ $rule->document_type === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="bo-field">
                                        <span>Plan recomendado</span>
                                        <select class="form-control" name="recommended_plan_slug">
                                            <option value="">Usar plan de la regla</option>
                                            @foreach($plans as $slug => $plan)
                                                <option value="{{ $slug }}" {{ $rule->recommended_plan_slug === $slug ? 'selected' : '' }}>
                                                    {{ $plan['short_title'] ?? $plan['title'] ?? $slug }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="bo-field bo-rule-service">
                                        <span>Servicio recomendado</span>
                                        <select class="form-control" name="recommended_service_id">
                                            <option value="">Sin servicio exacto</option>
                                            @foreach($documentServiceOptions as $service)
                                                <option value="{{ $service->id }}" {{ (int) $rule->recommended_service_id === (int) $service->id ? 'selected' : '' }}>
                                                    {{ $service->nombre }} @if($service->categoria) · {{ $service->categoria }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="bo-field">
                                        <span>Prioridad</span>
                                        <input class="form-control" type="number" name="priority" value="{{ $rule->priority }}" min="1" max="999">
                                    </label>

                                    <button type="submit" class="btn bo-save-button" title="Guardar regla">
                                        <i class="fas fa-save" aria-hidden="true"></i>
                                    </button>
                                </div>

                                <div class="bo-document-rule-options">
                                    <label class="bo-field bo-rule-keywords">
                                        <span>Palabras clave</span>
                                        <input class="form-control" type="text" name="match_keywords" value="{{ $keywords }}" placeholder="Separadas por coma">
                                    </label>

                                    <label class="bo-field bo-rule-client-copy">
                                        <span>Texto para el cliente</span>
                                        <textarea class="form-control" name="client_explanation" rows="2">{{ $rule->client_explanation }}</textarea>
                                    </label>

                                    <label class="bo-field bo-rule-client-copy">
                                        <span>Notas internas</span>
                                        <textarea class="form-control" name="internal_notes" rows="2">{{ $rule->internal_notes }}</textarea>
                                    </label>

                                    <div class="bo-document-rule-flags">
                                        <input type="hidden" name="required" value="0">
                                        <input type="hidden" name="active" value="0">
                                        <input type="hidden" name="client_visible" value="0">
                                        <label><input type="checkbox" name="required" value="1" {{ $rule->required ? 'checked' : '' }}> Requerido</label>
                                        <label><input type="checkbox" name="active" value="1" {{ $rule->active ? 'checked' : '' }}> Activo</label>
                                        <label><input type="checkbox" name="client_visible" value="1" {{ $rule->client_visible ? 'checked' : '' }}> Visible cliente</label>
                                    </div>
                                </div>
                            </form>

                            <form method="POST" action="{{ route('admin.banca-online.document-rules.destroy', $rule) }}" class="bo-delete-rule-form">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="pais" value="{{ $countrySlug }}">
                                <input type="hidden" name="plan" value="{{ $planSlug }}">
                                <input type="hidden" name="modalidad" value="{{ $packageSlug }}">
                                <button type="submit" class="btn bo-delete-rule-button" title="Eliminar regla" onclick="return confirm('Eliminar esta regla documental?')">
                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="bo-admin-empty">
                            <i class="fas fa-file-signature" aria-hidden="true"></i>
                            <p>Aun no hay reglas documentales para este pais y plan.</p>
                        </div>
                    @endforelse
                </div>
            @endunless
        </section>

        <nav class="bo-package-switch" aria-label="Modalidad">
            @foreach($tiers as $slug => $tier)
                @php
                    $tierPackage = $packages->first(fn ($item) => ($catalog->metadata($item)['tier_slug'] ?? null) === $slug);
                    $tierDefaults = $activePlan['packages'][$slug] ?? [];
                    $tierTitle = $tierPackage?->nombre ?? ($tierDefaults['title'] ?? ($tier['title'] ?? ucfirst($slug)));
                    $tierSummary = $tierPackage?->descripcion_publica ?? ($tierDefaults['summary'] ?? ($tier['summary'] ?? ''));
                    $tierRecommended = (bool) ($tierDefaults['recommended'] ?? ($tier['recommended'] ?? false));
                @endphp
                <a class="{{ $packageSlug === $slug ? 'is-active' : '' }}"
                   href="{{ route('admin.banca-online.index', ['pais' => $countrySlug, 'plan' => $planSlug, 'modalidad' => $slug]) }}"
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
                <p>Sincroniza el catalogo para crear las modalidades de esta ruta.</p>
            </div>
        @else
            <form
                class="bo-package-editor"
                data-package-editor
                method="POST"
                action="{{ route('admin.banca-online.packages.update', $package) }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="activo" value="0">
                <input type="hidden" name="discount_type" value="{{ $discountType }}">
                <input type="hidden" name="discount_value" data-discount-value value="{{ $discountValue }}">

                <section class="bo-package-settings">
                    <div class="bo-package-settings-head">
                        <div>
                            <span class="bo-context-label">Modalidad {{ $activePackageTitle }}</span>
                            <h3>Configuracion comercial</h3>
                        </div>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="package-active" name="activo" value="1" {{ old('activo', $package->activo) ? 'checked' : '' }}>
                            <label class="custom-control-label" for="package-active">Activo</label>
                        </div>
                    </div>

                    <div class="bo-package-settings-grid">
                        <label class="bo-field bo-package-name-field">
                            <span>Nombre de la modalidad</span>
                            <input class="form-control" type="text" name="nombre" value="{{ old('nombre', $activePackageTitle) }}" required>
                        </label>

                        <label class="bo-field">
                            <span>Precio lista EUR</span>
                            <input class="form-control" data-list-price type="number" name="list_price" value="{{ $listPrice }}" min="0" step="0.01" required>
                        </label>

                        <label class="bo-field">
                            <span>Precio venta EUR</span>
                            <input class="form-control" data-package-price type="number" name="price" value="{{ $packagePrice }}" min="0" step="0.01" required>
                        </label>

                        <label class="bo-field bo-package-description">
                            <span>Descripcion publica</span>
                            <textarea class="form-control" name="descripcion_publica" rows="2">{{ old('descripcion_publica', $activePackageSummary) }}</textarea>
                        </label>

                        <div class="bo-benefits-editor" data-benefits-editor>
                            <div class="bo-installment-admin-head">
                                <div>
                                    <span class="bo-context-label">Beneficios publicos</span>
                                    <h4>Beneficios que vera el cliente</h4>
                                </div>
                                <button type="button" class="btn bo-inline-action" data-add-benefit>
                                    <i class="fas fa-plus mr-1" aria-hidden="true"></i> Agregar beneficio
                                </button>
                            </div>

                            <div class="bo-benefit-list" data-benefit-list>
                                @forelse($packageFeatures as $feature)
                                    <div class="bo-benefit-row" data-benefit-row>
                                        <label class="bo-field">
                                            <span>Beneficio</span>
                                            <input class="form-control" type="text" name="public_feature_names[]" value="{{ $feature['name'] ?? '' }}">
                                        </label>
                                        <label class="bo-field">
                                            <span>Descripcion opcional</span>
                                            <input class="form-control" type="text" name="public_feature_descriptions[]" value="{{ $feature['description'] ?? '' }}">
                                        </label>
                                        <button type="button" class="btn bo-remove-row" data-remove-row title="Eliminar beneficio">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                @empty
                                    <div class="bo-benefit-row" data-benefit-row>
                                        <label class="bo-field">
                                            <span>Beneficio</span>
                                            <input class="form-control" type="text" name="public_feature_names[]" value="">
                                        </label>
                                        <label class="bo-field">
                                            <span>Descripcion opcional</span>
                                            <input class="form-control" type="text" name="public_feature_descriptions[]" value="">
                                        </label>
                                        <button type="button" class="btn bo-remove-row" data-remove-row title="Eliminar beneficio">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        <div class="bo-installment-admin">
                            <div class="bo-installment-admin-head">
                                <div>
                                    <span class="bo-context-label">Pagos por cuotas</span>
                                    <h4>Periodos y recargos</h4>
                                </div>
                                <small>El recargo se aplica solo al saldo financiado, no a la inicial.</small>
                            </div>

                            <div class="bo-period-grid">
                                @foreach($installmentPeriods as $slug => $period)
                                    @php
                                        $periodOld = old("installment_periods.{$slug}", $period);
                                        $periodEnabled = (bool) ($periodOld['enabled'] ?? false);
                                        $periodSurcharge = $periodOld['surcharge_percent'] ?? ($period['surcharge_percent'] ?? 0);
                                    @endphp
                                    <div class="bo-period-card">
                                        <input type="hidden" name="installment_periods[{{ $slug }}][enabled]" value="0">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="period-{{ $slug }}" name="installment_periods[{{ $slug }}][enabled]" value="1" {{ $periodEnabled ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="period-{{ $slug }}">{{ $period['label'] ?? ucfirst($slug) }}</label>
                                        </div>
                                        <label class="bo-field">
                                            <span>% recargo</span>
                                            <input class="form-control" type="number" name="installment_periods[{{ $slug }}][surcharge_percent]" value="{{ $periodSurcharge }}" min="0" max="100" step="0.01">
                                        </label>
                                    </div>
                                @endforeach
                            </div>

                            <div class="bo-installment-rules">
                                <div class="bo-installment-admin-head">
                                    <div>
                                        <span class="bo-context-label">Reglas por inicial</span>
                                        <h4>Inicial minima y cuotas permitidas</h4>
                                    </div>
                                    <button type="button" class="btn bo-inline-action" data-add-rule>
                                        <i class="fas fa-plus mr-1" aria-hidden="true"></i> Agregar regla
                                    </button>
                                </div>

                                <div class="bo-rule-list" data-rule-list>
                                    @foreach($installmentRules as $index => $rule)
                                        <div class="bo-rule-row" data-rule-row>
                                            <label class="bo-field">
                                                <span>Inicial minima %</span>
                                                <input class="form-control" type="number" name="installment_rule_min_percent[]" value="{{ $rule['min_initial_percent'] ?? 20 }}" min="1" max="99" step="0.01">
                                            </label>
                                            <label class="bo-field">
                                                <span>Maximo de cuotas</span>
                                                <input class="form-control" type="number" name="installment_rule_max_count[]" value="{{ $rule['max_installments'] ?? 1 }}" min="1" max="60">
                                            </label>
                                            <button type="button" class="btn bo-remove-row" data-remove-row title="Eliminar regla">
                                                <i class="fas fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
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
                            <strong>Total de la modalidad</strong>
                            <span data-package-total>{{ number_format($catalog->packageTotal($package), 0, ',', '.') }} EUR</span>
                        </div>
                    </div>
                    <button type="submit" class="btn bo-save-package-button">
                        <i class="fas fa-save mr-1" aria-hidden="true"></i> Guardar modalidad
                    </button>
                </div>
            </form>

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
            const listPriceInput = editor.querySelector('[data-list-price]');
            const packagePriceInput = editor.querySelector('[data-package-price]');
            const discountValue = editor.querySelector('[data-discount-value]');
            const subtotalLabel = editor.querySelector('[data-package-subtotal]');
            const discountLabel = editor.querySelector('[data-package-discount]');
            const totalLabel = editor.querySelector('[data-package-total]');
            const benefitList = editor.querySelector('[data-benefit-list]');
            const ruleList = editor.querySelector('[data-rule-list]');
            const money = new Intl.NumberFormat('es-ES', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });

            function amount(value) {
                const parsed = Number(value);

                return Number.isFinite(parsed) ? Math.max(0, parsed) : 0;
            }

            function refreshPackageTotal() {
                const subtotal = amount(listPriceInput ? listPriceInput.value : 0);
                const total = Math.min(subtotal, amount(packagePriceInput ? packagePriceInput.value : 0));
                const discount = Math.max(0, subtotal - total);

                if (packagePriceInput && amount(packagePriceInput.value) > subtotal) {
                    packagePriceInput.value = subtotal;
                }

                if (discountValue) discountValue.value = discount;

                subtotalLabel.textContent = money.format(subtotal) + ' EUR';
                discountLabel.textContent = '-' + money.format(discount) + ' EUR';
                totalLabel.textContent = money.format(total) + ' EUR';
            }

            function benefitRow() {
                const row = document.createElement('div');
                row.className = 'bo-benefit-row';
                row.dataset.benefitRow = '1';
                row.innerHTML = `
                    <label class="bo-field">
                        <span>Beneficio</span>
                        <input class="form-control" type="text" name="public_feature_names[]" value="">
                    </label>
                    <label class="bo-field">
                        <span>Descripcion opcional</span>
                        <input class="form-control" type="text" name="public_feature_descriptions[]" value="">
                    </label>
                    <button type="button" class="btn bo-remove-row" data-remove-row title="Eliminar beneficio">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                    </button>
                `;

                return row;
            }

            function ruleRow() {
                const row = document.createElement('div');
                row.className = 'bo-rule-row';
                row.dataset.ruleRow = '1';
                row.innerHTML = `
                    <label class="bo-field">
                        <span>Inicial minima %</span>
                        <input class="form-control" type="number" name="installment_rule_min_percent[]" value="20" min="1" max="99" step="0.01">
                    </label>
                    <label class="bo-field">
                        <span>Maximo de cuotas</span>
                        <input class="form-control" type="number" name="installment_rule_max_count[]" value="12" min="1" max="60">
                    </label>
                    <button type="button" class="btn bo-remove-row" data-remove-row title="Eliminar regla">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                    </button>
                `;

                return row;
            }

            if (listPriceInput) listPriceInput.addEventListener('input', refreshPackageTotal);
            if (packagePriceInput) packagePriceInput.addEventListener('input', refreshPackageTotal);

            editor.querySelector('[data-add-benefit]')?.addEventListener('click', function () {
                if (!benefitList) return;
                const row = benefitRow();
                benefitList.appendChild(row);
                row.querySelector('input')?.focus();
            });

            editor.querySelector('[data-add-rule]')?.addEventListener('click', function () {
                if (!ruleList) return;
                const row = ruleRow();
                ruleList.appendChild(row);
                row.querySelector('input')?.focus();
            });

            editor.addEventListener('click', function (event) {
                const button = event.target.closest('[data-remove-row]');
                if (!button) return;

                const row = button.closest('[data-benefit-row], [data-rule-row]');
                if (!row) return;

                const list = row.parentElement;
                row.remove();

                if (list && list.matches('[data-benefit-list]') && list.children.length === 0) {
                    list.appendChild(benefitRow());
                }

                if (list && list.matches('[data-rule-list]') && list.children.length === 0) {
                    list.appendChild(ruleRow());
                }
            });

            refreshPackageTotal();
        });
    </script>
@stop

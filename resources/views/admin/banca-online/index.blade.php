@php
    $catalog = app(\App\Services\BancaOnlineCatalog::class);
    $activePlan = $plans[$planSlug] ?? [];
    $countryCodes = ['espana' => 'ES', 'portugal' => 'PT', 'italia' => 'IT'];
@endphp

@extends('adminlte::page')

@section('title', 'Banca Online 2026')

@section('content_header')
    <div class="bo-admin-header">
        <div>
            <p class="sefar-eyebrow">Administracion de precios</p>
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
                @php($countryPlan = array_key_first($catalog->plansForCountry($slug)))
                <a class="bo-country-option {{ $countrySlug === $slug ? 'is-active' : '' }}"
                   href="{{ route('admin.banca-online.index', ['pais' => $slug, 'plan' => $countryPlan]) }}">
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
                        {{ $current }} de {{ $expected }} servicios sincronizados
                    @else
                        {{ $current }} {{ $current === 1 ? 'servicio configurado' : 'servicios configurados' }}
                    @endif
                </p>
            </div>
            @if($catalog->isCountryPublic($countrySlug))
                <a class="btn btn-outline-secondary"
                   target="_blank"
                   rel="noopener"
                   href="{{ route('banca-online.country', $countrySlug) }}">
                    <i class="fas fa-external-link-alt mr-1" aria-hidden="true"></i>
                    Ver publico
                </a>
            @else
                <span class="bo-preparation-label"><i class="fas fa-clock mr-1" aria-hidden="true"></i> En preparacion</span>
            @endif
        </section>

        <nav class="bo-plan-switch" aria-label="Plan estrategico">
            @foreach($plans as $slug => $plan)
                <a class="{{ $planSlug === $slug ? 'is-active' : '' }}"
                   href="{{ route('admin.banca-online.index', ['pais' => $countrySlug, 'plan' => $slug]) }}">
                    {{ $plan['short_title'] ?? $plan['title'] }}
                </a>
            @endforeach
        </nav>

        <header class="bo-plan-header">
            <div>
                <h2>{{ $activePlan['title'] ?? 'Plan estrategico' }}</h2>
                <p>{{ $activePlan['summary'] ?? '' }}</p>
            </div>
            <span>{{ $planCurrent }} {{ \Illuminate\Support\Str::plural('servicio', $planCurrent) }}</span>
        </header>

        <details class="bo-create-service" {{ $errors->has('section') ? 'open' : '' }}>
            <summary>
                <span><i class="fas fa-plus" aria-hidden="true"></i> Agregar servicio</span>
                <i class="fas fa-chevron-down bo-details-arrow" aria-hidden="true"></i>
            </summary>
            <form method="POST" action="{{ route('admin.banca-online.items.store') }}">
                @csrf
                <input type="hidden" name="pais" value="{{ $countrySlug }}">
                <input type="hidden" name="plan" value="{{ $planSlug }}">
                <input type="hidden" name="activo" value="1">

                <div class="bo-create-grid">
                    <label class="bo-field bo-create-name">
                        <span>Servicio</span>
                        <input type="text" name="nombre" value="{{ old('nombre') }}" class="form-control" required>
                    </label>
                    <label class="bo-field">
                        <span>Seccion</span>
                        <input type="text" name="section" value="{{ old('section', 'General') }}" class="form-control" required>
                    </label>
                    <label class="bo-field">
                        <span>Precio EUR</span>
                        <input type="number" name="precio" value="{{ old('precio', 0) }}" class="form-control" min="0" required>
                    </label>
                    <button type="submit" class="btn bo-add-button">
                        <i class="fas fa-plus mr-1" aria-hidden="true"></i> Agregar
                    </button>
                </div>
            </form>
        </details>

        @if($services->isEmpty())
            <div class="bo-admin-empty">
                <i class="fas fa-info-circle" aria-hidden="true"></i>
                <p>Este catalogo aun no tiene servicios sincronizados.</p>
            </div>
        @else
            @foreach($services as $section => $sectionItems)
                <section class="bo-admin-section">
                    <header>
                        <h3>{{ $section }}</h3>
                        <span>{{ $sectionItems->count() }}</span>
                    </header>

                    <div class="bo-admin-items">
                        @foreach($sectionItems as $servicio)
                            @php($metadata = $catalog->metadata($servicio))
                            <form class="bo-admin-item" method="POST" action="{{ route('admin.banca-online.items.update', $servicio) }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="tipo" value="{{ $servicio->tipo ?? 'servicio' }}">
                                <input type="hidden" name="moneda" value="{{ $servicio->moneda ?? 'EUR' }}">
                                <input type="hidden" name="required" value="0">
                                <input type="hidden" name="default_selected" value="0">
                                <input type="hidden" name="locked" value="0">
                                <input type="hidden" name="activo" value="0">

                                <div class="bo-item-main">
                                    <label class="bo-field bo-field-name">
                                        <span>Servicio</span>
                                        <input type="text" name="nombre" value="{{ old('nombre', $servicio->nombre) }}" class="form-control" required>
                                    </label>

                                    <label class="bo-field bo-field-price">
                                        <span>Precio EUR</span>
                                        <input type="number" name="precio" value="{{ old('precio', $servicio->precio) }}" class="form-control" min="0" required>
                                    </label>

                                    <div class="bo-field bo-field-state">
                                        <span>Estado</span>
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" class="custom-control-input" id="activo-{{ $servicio->id }}" name="activo" value="1" {{ $servicio->activo ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="activo-{{ $servicio->id }}">
                                                {{ $servicio->activo ? 'Activo' : 'Inactivo' }}
                                            </label>
                                        </div>
                                    </div>

                                    <button type="submit" class="btn bo-save-button" title="Guardar servicio" aria-label="Guardar {{ $servicio->nombre }}">
                                        <i class="fas fa-save" aria-hidden="true"></i>
                                    </button>
                                </div>

                                <details class="bo-item-advanced">
                                    <summary>
                                        <span><i class="fas fa-sliders-h" aria-hidden="true"></i> Opciones avanzadas</span>
                                        <i class="fas fa-chevron-down bo-details-arrow" aria-hidden="true"></i>
                                    </summary>

                                    <div class="bo-advanced-grid">
                                        <label class="bo-field">
                                            <span>Orden</span>
                                            <input type="number" name="orden" value="{{ old('orden', $servicio->orden) }}" class="form-control" min="0">
                                        </label>

                                        <label class="bo-field">
                                            <span>Grupo de alternativas</span>
                                            <input type="text" name="group" value="{{ old('group', $metadata['group'] ?? '') }}" class="form-control">
                                        </label>

                                        <div class="bo-selection-options">
                                            <span>Seleccion</span>
                                            <label>
                                                <input type="checkbox" name="required" value="1" {{ ($metadata['required'] ?? false) ? 'checked' : '' }}>
                                                Obligatorio
                                            </label>
                                            <label>
                                                <input type="checkbox" name="default_selected" value="1" {{ ($metadata['default_selected'] ?? false) ? 'checked' : '' }}>
                                                Preseleccionado
                                            </label>
                                            <label>
                                                <input type="checkbox" name="locked" value="1" {{ ($metadata['locked'] ?? false) ? 'checked' : '' }}>
                                                Bloqueado
                                            </label>
                                        </div>

                                        <label class="bo-field bo-field-description">
                                            <span>Descripcion</span>
                                            <textarea name="descripcion_publica" class="form-control" rows="2">{{ old('descripcion_publica', $servicio->descripcion_publica) }}</textarea>
                                        </label>
                                    </div>

                                    <div class="bo-item-code">{{ $servicio->id_hubspot }}</div>
                                </details>
                            </form>
                        @endforeach
                    </div>
                </section>
            @endforeach
        @endif
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-banca-online-2026.css') }}">
@stop

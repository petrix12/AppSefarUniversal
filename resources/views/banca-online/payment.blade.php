@php($packageComponents = collect($metadata['components'] ?? []))
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Pago | Banca Online 2026</title>
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
            <div class="bo-service-chip"><i class="fas fa-passport"></i> {{ $metadata['requested_service'] ?? $user->servicio }}</div>
        </div>
    </header>

    <main class="bo-container">
        <section class="bo-payment-title">
            <span class="bo-eyebrow"><i class="fas fa-lock"></i> Pago seguro</span>
            <h1>Completar contratacion</h1>
            <p>{{ $metadata['plan_title'] ?? 'Plan estrategico' }} · {{ $metadata['package_title'] ?? 'Paquete contratado' }}.</p>
        </section>

        <div class="bo-payment-layout">
            <section class="bo-panel">
                <h2>{{ $metadata['package_title'] ?? 'Paquete contratado' }}</h2>
                <ul class="bo-payment-items">
                    @forelse($packageComponents as $component)
                        <li>
                            <i class="fas fa-check"></i>
                            <span class="bo-service-line">
                                <strong>{{ $component['name'] ?? 'Servicio incluido' }}</strong>
                                @if(!empty($component['description']))<small>{{ $component['description'] }}</small>@endif
                                @isset($component['price'])<span>{{ number_format((float) $component['price'], 0, ',', '.') }} EUR</span>@endisset
                            </span>
                        </li>
                    @empty
                        @foreach($compras as $compra)
                            <li><i class="fas fa-check"></i><span>{{ $compra->servicio?->nombre ?? $compra->descripcion }}</span></li>
                        @endforeach
                    @endforelse
                </ul>
            </section>

            <aside class="bo-panel">
                @if(!$stripeKey)
                    <div class="bo-alert">No esta configurada la clave publica de Stripe para este servicio.</div>
                @endif

                @if((float) ($metadata['package_discount'] ?? 0) > 0)
                    <div class="bo-payment-breakdown">
                        <span>Subtotal <strong>{{ number_format((float) ($metadata['package_subtotal'] ?? $total), 0, ',', '.') }} EUR</strong></span>
                        <span>Descuento <strong>-{{ number_format((float) $metadata['package_discount'], 0, ',', '.') }} EUR</strong></span>
                    </div>
                @endif

                <div class="bo-total-label">Total a pagar</div>
                <div class="bo-total">{{ number_format($total, 0, ',', '.') }} <small>EUR</small></div>

                <form
                    id="payment-form"
                    class="bo-form"
                    data-stripe-key="{{ $stripeKey }}"
                    data-process-url="{{ route('banca-online.payment.process', $token) }}"
                    data-success-url="{{ route('banca-online.thank-you', $token) }}">
                    <div class="bo-field-grid">
                        <label class="bo-field">
                            <span>Nombres</span>
                            <input type="text" name="first_name" value="{{ old('first_name') }}" required>
                        </label>
                        <label class="bo-field">
                            <span>Apellidos</span>
                            <input type="text" name="last_name" value="{{ old('last_name') }}" required>
                        </label>
                    </div>

                    <label class="bo-field">
                        <span>Correo electronico</span>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required>
                    </label>
                    <label class="bo-field">
                        <span>Telefono</span>
                        <input type="tel" name="phone" value="{{ old('phone') }}">
                    </label>
                    <label class="bo-field">
                        <span>Direccion</span>
                        <input type="text" name="address_line1" required>
                    </label>
                    <label class="bo-field">
                        <span>Direccion adicional</span>
                        <input type="text" name="address_line2">
                    </label>

                    <div class="bo-field-grid">
                        <label class="bo-field">
                            <span>Ciudad</span>
                            <input type="text" name="city" required>
                        </label>
                        <label class="bo-field">
                            <span>Estado o provincia</span>
                            <input type="text" name="state">
                        </label>
                    </div>

                    <div class="bo-field-grid">
                        <label class="bo-field">
                            <span>Codigo postal</span>
                            <input type="text" name="postal_code" required>
                        </label>
                        <label class="bo-field">
                            <span>Pais ISO 2</span>
                            <input type="text" name="country" value="VE" maxlength="2" minlength="2" required>
                        </label>
                    </div>

                    <label class="bo-field">
                        <span>Tarjeta</span>
                        <div id="card-element"></div>
                    </label>
                    <div class="bo-card-errors" id="card-errors"></div>

                    <button class="bo-button bo-button-primary" id="submit-button" type="submit" {{ !$stripeKey ? 'disabled' : '' }}>
                        Pagar ahora <i class="fas fa-credit-card"></i>
                    </button>
                </form>
            </aside>
        </div>
    </main>

    <script src="{{ asset('js/banca-online-2026.js') }}"></script>
</body>
</html>

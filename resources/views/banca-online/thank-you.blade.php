@php
    $purchaseMetadata = $compras->first()?->metadata ?? [];
    $packageComponents = collect($purchaseMetadata['components'] ?? []);
    $paymentPlan = $purchaseMetadata['payment_plan'] ?? [];
    $isInstallmentPayment = ($paymentPlan['mode'] ?? 'full') === 'installments';
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pago recibido | Banca Online 2026</title>
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" href="{{ asset('css/banca-online-2026.css') }}">
</head>
<body class="bo-page">
    <main class="bo-confirm-wrap">
        <section class="bo-confirm-card">
            <img class="bo-confirm-logo" src="{{ asset('img/logo2.png') }}" alt="Sefar Universal">
            <div class="bo-confirm-badge"><i class="fas fa-check-circle"></i> {{ $isInstallmentPayment ? 'Inicial recibido' : 'Pago recibido' }}</div>
            <h1>Gracias.</h1>
            <p>Tu contratacion de Banca Online 2026 fue registrada correctamente. El equipo de Sefar Universal continuara el seguimiento operativo del servicio seleccionado.</p>

            @if($isInstallmentPayment)
                <div class="bo-payment-breakdown">
                    <span>Total del plan <strong>{{ number_format((float) ($paymentPlan['contract_total'] ?? $purchaseMetadata['package_total'] ?? $total), 0, ',', '.') }} EUR</strong></span>
                    <span>Inicial recibido <strong>{{ number_format((float) ($paymentPlan['amount_due_now'] ?? $total), 0, ',', '.') }} EUR</strong></span>
                    <span>{{ (int) ($paymentPlan['installments_count'] ?? 0) }} cuotas {{ $paymentPlan['period_plural_label'] ?? 'mensuales' }} <strong>{{ number_format((float) ($paymentPlan['installment_amount'] ?? 0), 0, ',', '.') }} EUR</strong></span>
                </div>
            @endif

            <div class="bo-confirm-total">{{ number_format($total, 0, ',', '.') }} EUR</div>
            @if(!empty($purchaseMetadata['package_title']))
                <h2>{{ $purchaseMetadata['package_title'] }}</h2>
            @endif
            <ul class="bo-confirm-list">
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
    </main>
</body>
</html>

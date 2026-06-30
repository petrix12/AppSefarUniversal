@php
    $purchases = $user->compras
        ->filter(fn ($purchase) => (int) $purchase->id_user === (int) $user->id && filled($purchase->servicio_hs_id))
        ->values();

    $isBancaOnline = fn ($purchase) => $purchase->source === config('banca_online.source', 'banca_online_2026')
        || Illuminate\Support\Str::startsWith((string) $purchase->servicio_hs_id, 'BO2026-');

    $bankPurchases = $purchases->filter($isBancaOnline)->values();
    $regularPurchases = $purchases->reject($isBancaOnline)->values();
    $bankPaymentGroups = $bankPurchases->groupBy(function ($purchase) {
        $checkoutToken = data_get($purchase->metadata, 'checkout_token');

        return $purchase->hash_factura
            ? 'invoice:' . $purchase->hash_factura
            : ($checkoutToken ? 'checkout:' . $checkoutToken : 'purchase:' . $purchase->id);
    });

    $fallbackService = auth()->user()->roles->first()?->id === 1
        ? ($user->servicio ?: ($user->getRoleNames()->first() ?: 'Cliente'))
        : ($user->servicio ?: 'Usuario App');
@endphp

<div class="services-summary">
    @foreach ($regularPurchases as $purchase)
        <div class="service-row">
            <span class="service-name">{{ $purchase->servicio?->nombre ?: $purchase->servicio_hs_id }}</span>
            <span class="{{ (int) $purchase->pagado === 0 ? 'badge-unpaid' : 'badge-paid' }}">
                {{ (int) $purchase->pagado === 0 ? 'No pagó Registro' : 'Pagó Registro' }}
            </span>
        </div>
    @endforeach

    @if ($purchases->isEmpty())
        <div class="service-row">
            <span class="service-name">{{ $fallbackService }}</span>
            <span class="{{ (int) $user->pay === 0 ? 'badge-unpaid' : 'badge-paid' }}">
                {{ (int) $user->pay === 0 ? 'No pagó Registro' : ((int) $user->pay === 1 ? 'Pagó Registro' : 'Pagó Registro y completó información') }}
            </span>
            @if ((int) $user->pay === 3)
                <span class="user-info">Estatus 3</span>
            @endif
        </div>
    @endif

    @foreach ($bankPaymentGroups as $paymentPurchases)
        @php
            $isPaid = $paymentPurchases->every(fn ($purchase) => (int) $purchase->pagado === 1);
            $hasPaidItems = $paymentPurchases->contains(fn ($purchase) => (int) $purchase->pagado === 1);
            $datedPurchase = $paymentPurchases
                ->filter(fn ($purchase) => (int) $purchase->pagado === 1)
                ->sortByDesc(fn ($purchase) => optional($purchase->paid_at ?: $purchase->updated_at)->timestamp ?? 0)
                ->first();
            $paymentDate = $datedPurchase ? ($datedPurchase->paid_at ?: $datedPurchase->updated_at) : null;
            $firstPurchase = $paymentPurchases->first();
            $planTitle = data_get($firstPurchase->metadata, 'plan_short_title')
                ?: data_get($firstPurchase->metadata, 'plan_title');
            $packageTitle = data_get($firstPurchase->metadata, 'package_title');
            $packageComponents = collect(data_get($firstPurchase->metadata, 'components', []));
            $statusTitle = $isPaid
                ? 'Pago Banca Online'
                : ($hasPaidItems ? 'Pago parcial Banca Online' : 'Banca Online pendiente');
        @endphp

        <section class="bank-payment {{ $isPaid ? '' : 'is-pending' }}">
            <div class="bank-payment-header">
                <span class="bank-payment-title">{{ $statusTitle }}</span>
                @if ($paymentDate)
                    <time class="bank-payment-date" datetime="{{ $paymentDate->toIso8601String() }}">
                        {{ $paymentDate->format('d/m/Y') }}
                    </time>
                @endif
            </div>

            @if ($planTitle)
                <div class="bank-payment-plan">
                    {{ $planTitle }}
                    @if($packageTitle) · {{ $packageTitle }} @endif
                    · {{ $packageComponents->isNotEmpty() ? $packageComponents->count() : $paymentPurchases->count() }}
                    {{ Illuminate\Support\Str::plural('servicio', $packageComponents->isNotEmpty() ? $packageComponents->count() : $paymentPurchases->count()) }}
                </div>
            @endif

            <ul class="bank-service-list">
                @forelse ($packageComponents as $component)
                    <li>
                        <span>{{ $component['name'] ?? 'Servicio incluido' }}</span>
                        @isset($component['price'])<strong>{{ number_format((float) $component['price'], 2, ',', '.') }} EUR</strong>@endisset
                    </li>
                @empty
                    @foreach ($paymentPurchases as $purchase)
                        <li>
                            {{ $purchase->servicio?->nombre ?: (Illuminate\Support\Str::after((string) $purchase->descripcion, ': ') ?: $purchase->servicio_hs_id) }}
                            @if ((int) $purchase->pagado === 0 && $hasPaidItems)
                                <span class="badge-unpaid">Pendiente</span>
                            @endif
                        </li>
                    @endforeach
                @endforelse
            </ul>
        </section>
    @endforeach

    @if ($regularPurchases->isNotEmpty() || $bankPurchases->isEmpty())
        <div class="case-status">
            <span class="case-status-item {{ (int) $user->pay === 2 ? 'is-complete' : '' }}">
                <span class="case-status-dot"></span>
                {{ (int) $user->pay === 2 ? 'Información completada' : 'Información pendiente' }}
            </span>
            <span class="case-status-item {{ $user->contrato ? 'is-complete' : '' }}">
                <span class="case-status-dot"></span>
                {{ $user->contrato ? 'Contrato firmado' : 'Contrato pendiente' }}
            </span>
        </div>
    @endif
</div>

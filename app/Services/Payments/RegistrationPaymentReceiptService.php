<?php

namespace App\Services\Payments;

use App\Models\Compras;
use App\Models\Factura;
use App\Services\CosPaymentCrmSyncService;
use App\Services\TeamleaderPhasePaymentService;
use Illuminate\Support\Facades\DB;

class RegistrationPaymentReceiptService
{
    /** Annotate the purchases already paid by /pay. Never create another purchase. */
    public function record(Factura $invoice, iterable $purchases, ?string $gatewayReference = null): void
    {
        $ids = collect($purchases)->pluck('id')->filter()->values();
        DB::transaction(function () use ($invoice, $ids, $gatewayReference): void {
            $receipts = Compras::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
            if ($receipts->count() !== $ids->unique()->count()) throw new \InvalidArgumentException('Falta una compra del registro.');
            foreach ($receipts as $receipt) {
                if ((int) $receipt->id_user !== (int) $invoice->id_cliente || ! $receipt->pagado
                    || $receipt->hash_factura !== $invoice->hash_factura
                    || in_array($receipt->source, [TeamleaderPhasePaymentService::PURCHASE_SOURCE,
                        TeamleaderPhasePaymentService::INSTALLMENT_SOURCE, TeamleaderPhasePaymentService::HISTORY_SOURCE], true)) {
                    throw new \InvalidArgumentException('El comprobante de registro no coincide con la compra confirmada.');
                }
                $metadata = $receipt->metadata ?? [];
                if (data_get($metadata, 'portal_record_kind') !== 'registration_payment') {
                    $metadata = array_merge($metadata, ['portal_record_kind' => 'registration_payment',
                        'registration_payment' => true, 'gateway_reference' => $gatewayReference,
                        'registration_paid_at' => ($receipt->paid_at ?: $invoice->created_at ?: now())->toIso8601String()]);
                    $values = ['metadata' => $metadata, 'paid_at' => $receipt->paid_at ?: $invoice->created_at ?: now()];
                    // A full coupon is a zero-value document, not external cash revenue.
                    if ($invoice->met === 'cupon') {
                        $values += ['monto' => 0, 'montooriginal' => $receipt->montooriginal ?? $receipt->monto,
                            'porcentajedescuento' => 100];
                    }
                    $receipt->forceFill($values)->save();
                } elseif (! empty($metadata['registration_paid_at'])) {
                    // A retried gateway callback must retain the first confirmation date.
                    $receipt->forceFill(['paid_at' => $metadata['registration_paid_at']])->save();
                }
                app(CosPaymentCrmSyncService::class)->queueReceipt($receipt);
                app(\App\Services\RegistrationMeetingService::class)->queueConfirmed($receipt, $invoice);
            }
        });
    }
}

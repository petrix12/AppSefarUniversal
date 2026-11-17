<?php

namespace App\Services\Payments;

use App\Models\Factura;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ConfirmedPaymentInvoiceService
{
    /** Reuse the existing document when a confirmed gateway transaction is retried. */
    public function getOrCreate(int $userId, string $method, ?string $gatewayReference, string $invoiceHash, ?string $customerId = null): Factura
    {
        return DB::transaction(function () use ($userId, $method, $gatewayReference, $invoiceHash, $customerId): Factura {
            User::lockForUpdate()->findOrFail($userId);
            $query = Factura::where('id_cliente', $userId)->where('met', $method);
            if (filled($gatewayReference)) $query->where('idcharge', $gatewayReference);
            else $query->where('hash_factura', $invoiceHash);
            $matches = $query->get();
            if ($matches->count() > 1) {
                throw new PaymentSyncReviewException('La transacción ya tiene varios comprobantes; requiere conciliación.');
            }
            return $matches->first() ?? Factura::create([
                'id_cliente' => $userId, 'hash_factura' => $invoiceHash,
                'met' => $method, 'idcharge' => $gatewayReference, 'idcus' => $customerId,
            ]);
        });
    }
}

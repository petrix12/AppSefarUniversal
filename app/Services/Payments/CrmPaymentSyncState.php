<?php

namespace App\Services\Payments;

use App\Models\Compras;
use Illuminate\Support\Facades\DB;

/** Durable checkpoints on the existing receipt, never a second payment. */
class CrmPaymentSyncState
{
    public function __construct(
        public readonly int $receiptId,
        public readonly string $provider,
        private readonly string $lease,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return data_get(Compras::findOrFail($this->receiptId)->metadata,
            "crm_payment_sync.providers.{$this->provider}.{$key}", $default);
    }

    public function save(array $values): void
    {
        DB::transaction(function () use ($values): void {
            $receipt = Compras::lockForUpdate()->findOrFail($this->receiptId);
            $metadata = $receipt->metadata ?? [];
            $state = data_get($metadata, "crm_payment_sync.providers.{$this->provider}", []);
            if (($state['lease'] ?? null) !== $this->lease) {
                throw new \RuntimeException('La sincronización está siendo procesada por otro trabajador.');
            }
            if (! array_key_exists('lease_until', $values)) {
                $values['lease_until'] = now()->addMinutes(10)->toIso8601String();
            }
            data_set($metadata, "crm_payment_sync.providers.{$this->provider}", array_merge($state, $values));
            $receipt->forceFill(['metadata' => $metadata])->save();
        });
    }

    /**
     * Persist intent BEFORE creating remotely. After a timeout/crash, only
     * recover the record by its exact reference; never blindly repeat a POST.
     */
    public function createOnce(string $key, callable $find, callable $create): string
    {
        if ($id = $this->get($key.'_id')) {
            return (string) $id;
        }
        if ($id = $find()) {
            $this->save([$key.'_id' => (string) $id]);
            return (string) $id;
        }
        if ($this->get($key.'_create_started')) {
            throw new \RuntimeException("Confirmación pendiente del registro {$key}; no se repetirá su creación.");
        }
        $this->save([$key.'_create_started' => true]);
        $id = (string) $create();
        if ($id === '') {
            throw new \RuntimeException("El CRM no confirmó el identificador del registro {$key}.");
        }
        $this->save([$key.'_id' => $id]);
        return $id;
    }
}

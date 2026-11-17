<?php

namespace App\Console\Commands;

use App\Models\Compras;
use App\Services\CosPaymentCrmSyncService;
use App\Services\TeamleaderPhasePaymentService;
use Illuminate\Console\Command;

class SyncCosPaymentReceipts extends Command
{
    protected $signature = 'cos:sync-payment-receipts
        {--receipt=* : ID de abono confirmado (puede repetirse)}
        {--pending : Procesar solo abonos ya encolados por el portal}
        {--provider= : hubspot o teamleader; vacío procesa ambos}
        {--limit=2 : Máximo de abonos pendientes}
        {--write : Aplicar sincronización; sin esta opción solo muestra el plan}';

    protected $description = 'Vincula abonos existentes y sus comprobantes con los CRM sin crear nuevos pagos locales';

    public function handle(CosPaymentCrmSyncService $sync): int
    {
        $provider = $this->option('provider') ?: null;
        if ($provider && ! in_array($provider, CosPaymentCrmSyncService::PROVIDERS, true)) {
            $this->error('Proveedor inválido.'); return self::INVALID;
        }
        $ids = $this->option('receipt');
        if ((! $ids && ! $this->option('pending')) || ($ids && $this->option('pending'))
            || count($ids) > 50 || collect($ids)->contains(fn ($id) => ! ctype_digit((string) $id))) {
            $this->error('Indica --receipt=ID o --pending.'); return self::INVALID;
        }
        $query = Compras::where('pagado', 1)->where(fn ($q) => $q->where('source', TeamleaderPhasePaymentService::INSTALLMENT_SOURCE)
            ->orWhere(fn ($q) => $q->where('metadata->registration_payment', true)->where('metadata->portal_record_kind', 'registration_payment')));
        if ($ids) {
            $receipts = $query->whereIn('id', $ids)->orderBy('id')->get();
            if ($receipts->count() !== count(array_unique($ids))) {
                $this->error('Algún ID no corresponde a un abono confirmado del portal.'); return self::FAILURE;
            }
        } else {
            $receipts = $query->where('metadata->crm_payment_sync->pending', true)
                ->where(function ($query) use ($provider): void {
                    foreach ($provider ? [$provider] : CosPaymentCrmSyncService::PROVIDERS as $p) {
                        $prefix = 'metadata->crm_payment_sync->providers->'.$p.'->';
                        $query->orWhere(function ($q) use ($prefix): void {
                            $q->whereIn($prefix.'status', ['pending', 'retry', 'processing'])
                                ->where(fn ($q) => $q->whereNull($prefix.'next_attempt_at')->orWhere($prefix.'next_attempt_at', '<=', now()->toIso8601String()))
                                ->where(fn ($q) => $q->whereNull($prefix.'lease_until')->orWhere($prefix.'lease_until', '<=', now()->toIso8601String()));
                        });
                    }
                })->orderBy('id')->limit(max(1, min(50, (int) $this->option('limit'))))->get();
        }
        if ($this->option('write') && ! config('cos_payments.crm_sync_enabled')) {
            $this->error('La sincronización de comprobantes está desactivada.'); return self::FAILURE;
        }
        $failed = false;
        foreach ($receipts as $receipt) {
            if ($this->option('write')) {
                $sync->queueReceipt($receipt);
                $sync->process($receipt->id, $provider, (bool) $ids);
                $receipt->refresh();
                $states = data_get($receipt->metadata, 'crm_payment_sync.providers', []);
                foreach ($provider ? [$provider] : CosPaymentCrmSyncService::PROVIDERS as $p) {
                    $state = $states[$p] ?? [];
                    $status = $state['status'] ?? 'pending';
                    $this->line("Abono {$receipt->id} · {$p}: {$status}");
                    if ($status !== 'synced') {
                        $failed = true;
                        $this->warn($state['last_error'] ?? 'Pendiente de confirmación.');
                    }
                    $links = array_intersect_key($state, array_flip(['invoice_id', 'reference', 'remote_invoice_id', 'invoice_number', 'invoice_status', 'calculated_totals_available', 'payment_id', 'note_id', 'document_url', 'last_operation', 'exception_type', 'http_status', 'http_category', 'required_scopes', 'curl_error', 'remote_errors']));
                    $links = array_filter($links, static fn ($value) => $value !== null && $value !== []);
                    $this->line(json_encode($links, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                }
            } else {
                try {
                    $c = $sync->context($receipt);
                    $this->line(json_encode([
                        'receipt_id' => $receipt->id, 'amount' => $c['amount'], 'currency' => $c['currency'],
                        'invoice_id' => $c['invoice']->id, 'document_url' => $c['invoice_url'],
                        'hubspot_contact_id' => $c['user']->hs_id, 'hubspot_deal_id' => $c['deal']?->hubspot_id,
                        'teamleader_contact_id' => $c['user']->tl_id, 'teamleader_project_id' => $c['project_id'],
                        'reference' => $c['reference'], 'current_state' => data_get($receipt->metadata, 'crm_payment_sync.providers', []),
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                } catch (\App\Services\Payments\PaymentSyncReviewException $e) {
                    $this->error($e->getMessage()); $failed = true;
                }
            }
        }
        if (! $this->option('write')) $this->info('Solo lectura. No se enviaron pagos ni se modificaron registros.');
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}

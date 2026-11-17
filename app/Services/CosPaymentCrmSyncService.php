<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\Factura;
use App\Models\Negocio;
use App\Models\User;
use App\Services\Payments\CrmPaymentSyncState;
use App\Services\Payments\HubspotPaymentReceiptWriter;
use App\Services\Payments\PaymentSyncReviewException;
use App\Services\Payments\TeamleaderPaymentReceiptWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CosPaymentCrmSyncService
{
    public const PROVIDERS = ['hubspot', 'teamleader'];

    public static function isRegistration(Compras $receipt): bool
    {
        return data_get($receipt->metadata, 'registration_payment') === true
            && data_get($receipt->metadata, 'portal_record_kind') === 'registration_payment';
    }

    /** Only real confirmed portal installments enter the outbound flow. */
    public function queueReceipt(Compras $receipt): void
    {
        if ((! self::isRegistration($receipt) && $receipt->source !== TeamleaderPhasePaymentService::INSTALLMENT_SOURCE) || ! $receipt->pagado) {
            throw new \InvalidArgumentException('Solo se sincronizan pagos confirmados del portal.');
        }
        DB::transaction(function () use ($receipt): void {
            $stored = Compras::lockForUpdate()->findOrFail($receipt->id);
            $metadata = $stored->metadata ?? [];
            if (! isset($metadata['crm_payment_sync'])) {
                $metadata['crm_payment_sync'] = [
                    'version' => 1, 'queued_at' => now()->toIso8601String(), 'pending' => true,
                    'providers' => array_fill_keys(self::PROVIDERS, ['status' => 'pending', 'attempts' => 0]),
                ];
                $stored->forceFill(['metadata' => $metadata])->save();
            }
        });
    }

    public function context(Compras $receipt): array
    {
        $registration = self::isRegistration($receipt);
        if ((! $registration && $receipt->source !== TeamleaderPhasePaymentService::INSTALLMENT_SOURCE)
            || ! $receipt->pagado || $receipt->monto < 0 || (! $registration && $receipt->monto == 0)
            || ! $receipt->paid_at || ! $receipt->hash_factura) {
            throw new PaymentSyncReviewException('El abono no tiene pago y comprobante confirmados.');
        }
        if (strtoupper((string) data_get($receipt->metadata, 'display_currency', 'EUR')) !== 'EUR') {
            throw new PaymentSyncReviewException('La moneda del abono no corresponde al cobro EUR confirmado por el portal.');
        }
        $user = User::findOrFail($receipt->id_user);
        $invoices = Factura::where('hash_factura', $receipt->hash_factura)->get();
        if ($invoices->count() !== 1 || (int) $invoices->first()->id_cliente !== (int) $user->id) {
            throw new PaymentSyncReviewException('El comprobante no pertenece inequívocamente al cliente del abono.');
        }
        if ($registration) return $this->registrationContext($receipt, $user, $invoices->first());
        $project = trim((string) data_get($receipt->metadata, 'teamleader_project_id'));
        $phase = (int) data_get($receipt->metadata, 'phase', $receipt->phasenum);
        if (! $project || ! isset(TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS[$phase])) {
            throw new PaymentSyncReviewException('Falta la referencia del proyecto o de la fase.');
        }
        $deals = Negocio::where('user_id', $user->id)->where('teamleader_id', $project)
            ->whereNotNull('hubspot_id')->where('hubspot_id', '!=', '')->get();
        if ($deals->count() !== 1 || ($receipt->deal_id && (int) $receipt->deal_id !== (int) $deals->first()->id)
            || ! $user->hs_id || ! $user->tl_id) {
            throw new PaymentSyncReviewException('Falta un enlace único entre cliente, negocio de HubSpot y proyecto de Teamleader.');
        }
        $deal = $deals->first();
        if (! ctype_digit((string) $user->hs_id) || ! ctype_digit((string) $deal->hubspot_id)
            || ! Str::isUuid($project) || ! Str::isUuid((string) $user->tl_id)) {
            throw new PaymentSyncReviewException('Los identificadores de contacto, negocio o proyecto no tienen un formato válido.');
        }
        if (Negocio::where('user_id', '!=', $user->id)->where(function ($q) use ($project, $deal) {
            $q->where('teamleader_id', $project)->orWhere('hubspot_id', $deal->hubspot_id);
        })->exists() || User::where('id', '!=', $user->id)->where(function ($q) use ($user) {
            $q->where('hs_id', $user->hs_id)->orWhere('tl_id', $user->tl_id);
        })->exists()) {
            throw new PaymentSyncReviewException('Los identificadores CRM están compartidos con otra cuenta.');
        }
        $invoice = $invoices->first();
        return [
            'receipt' => $receipt, 'user' => $user, 'invoice' => $invoice, 'deal' => $deal,
            'kind' => 'phase',
            'project_id' => $project, 'phase' => $phase, 'currency' => 'EUR',
            'reference' => 'phase-payment-'.$receipt->id,
            'invoice_url' => rtrim((string) config('app.url'), '/').'/viewcomprobante/'.$invoice->id,
            'amount' => number_format($receipt->monto, 2, '.', ''),
            'date' => $receipt->paid_at->toIso8601String(),
        ];
    }

    private function registrationContext(Compras $receipt, User $user, Factura $invoice): array
    {
        if ($receipt->monto == 0 && $invoice->met !== 'cupon') {
            throw new PaymentSyncReviewException('Un pago monetario de registro no puede acreditarse con importe cero.');
        }
        foreach (['hs_id', 'tl_id'] as $field) {
            if (filled($user->$field) && User::where('id', '!=', $user->id)->where($field, $user->$field)->exists()) {
                throw new PaymentSyncReviewException('El contacto de registro está compartido con otra cuenta.');
            }
        }
        if ((filled($user->hs_id) && ! ctype_digit((string) $user->hs_id))
            || (filled($user->tl_id) && ! Str::isUuid((string) $user->tl_id))) {
            throw new PaymentSyncReviewException('El identificador CRM del registro no es válido.');
        }
        // Registration is accounted for even before the first deal/project exists.
        // It does not import a lead profile or create a deal to force a match.
        $deals = Negocio::where('user_id', $user->id)->whereNotNull('hubspot_id')->where('hubspot_id', '!=', '')->get();
        $deal = $receipt->deal_id ? $deals->firstWhere('id', $receipt->deal_id) : ($deals->count() === 1 ? $deals->first() : null);
        if ($receipt->deal_id && ! $deal) throw new PaymentSyncReviewException('El negocio del pago de registro no pertenece a ese cliente.');
        if ($deal && (! ctype_digit((string) $deal->hubspot_id)
            || Negocio::where('user_id', '!=', $user->id)->where('hubspot_id', $deal->hubspot_id)->exists())) {
            throw new PaymentSyncReviewException('El negocio del registro está cruzado con otra cuenta.');
        }
        return ['receipt' => $receipt, 'user' => $user, 'invoice' => $invoice, 'deal' => $deal,
            'kind' => 'registration', 'project_id' => '', 'phase' => 0, 'currency' => 'EUR',
            'reference' => 'registration-payment-'.$receipt->id,
            'invoice_url' => rtrim((string) config('app.url'), '/').'/viewcomprobante/'.$invoice->id,
            'amount' => number_format($receipt->monto, 2, '.', ''), 'date' => $receipt->paid_at->toIso8601String()];
    }

    public function process(int $receiptId, ?string $onlyProvider = null, bool $force = false): void
    {
        if (! config('cos_payments.crm_sync_enabled')) return;
        foreach (self::PROVIDERS as $provider) {
            if ($onlyProvider && $onlyProvider !== $provider) continue;
            $state = $this->claim($receiptId, $provider, $force);
            if (! $state) continue;
            try {
                $context = $this->context(Compras::findOrFail($receiptId));
                // Financial links are immutable after the first outbound write.
                $fingerprint = hash('sha256', json_encode([
                    $context['amount'], $context['date'], $context['invoice']->id, $context['invoice_url'],
                    $context['user']->id, $context['user']->hs_id, $context['user']->tl_id,
                    $context['user']->email, $context['deal']?->hubspot_id, $context['project_id'], $context['phase'],
                ], JSON_THROW_ON_ERROR));
                if ($state->get('fingerprint') && $state->get('fingerprint') !== $fingerprint) {
                    foreach (['remote_invoice_id', 'remote_invoice_create_started', 'payment_id', 'payment_create_started', 'phase_update_started', 'note_id', 'note_create_started'] as $key) {
                        if ($state->get($key)) {
                            throw new PaymentSyncReviewException('Cambió el pago o su enlace CRM; requiere conciliación antes de continuar.');
                        }
                    }
                }
                $state->save(['fingerprint' => $fingerprint, 'invoice_id' => $context['invoice']->id,
                    'reference' => $context['reference']]);
                $lock = Cache::lock('cos-payment-crm:'.$provider.':'.sha1($context['project_id'] ?: 'client-'.$context['user']->id), 600);
                $lock->block(2, function () use ($context, $state, $provider): void {
                    $writer = $provider === 'hubspot' ? HubspotPaymentReceiptWriter::class : TeamleaderPaymentReceiptWriter::class;
                    app($writer)->sync($context, $state);
                });
                $state->save(['status' => 'synced', 'synced_at' => now()->toIso8601String(),
                    'last_error' => null, 'lease_until' => null, 'next_attempt_at' => null]);
            } catch (\Throwable $e) {
                $review = $e instanceof PaymentSyncReviewException || $e instanceof \App\Exceptions\CosIdentityConflictException
                    || (int) $state->get('attempts') >= 8;
                // Exception messages and HTTP bodies can contain tokens/PII. Store a safe reason only.
                $reason = $e instanceof PaymentSyncReviewException ? $e->getMessage()
                    : 'Sincronización no confirmada; se conservaron los pasos completados para reintentar.';
                if ($e instanceof \App\Exceptions\CosIdentityConflictException) {
                    $reason = 'Los datos del contacto CRM están cruzados con otra cuenta.';
                } elseif ($e instanceof \App\Exceptions\TeamleaderAuthenticationException) {
                    $reason = 'Teamleader requiere volver a autorizar la conexión OAuth de la App.';
                } elseif ($e instanceof \Illuminate\Http\Client\RequestException) {
                    $code = $e->response->status();
                    $reason = ucfirst($provider)." devolvió HTTP {$code}. ".(
                        in_array($code, [401, 403], true)
                            ? 'Revisa los permisos de la integración para contactos, negocios, facturas, líneas y pagos.'
                            : 'Se conservaron las referencias confirmadas para reintentar sin duplicar.');
                }
                $diagnostic = ['exception_type' => get_class($e)];
                if ($e instanceof \App\Exceptions\TeamleaderServiceUnavailableException) $diagnostic['http_status'] = $e->getCode();
                if ($e instanceof \Illuminate\Http\Client\RequestException) {
                    $diagnostic['http_status'] = $e->response->status();
                    $body = $e->response->json() ?? [];
                    $category = data_get($body, 'category');
                    if (is_string($category) && preg_match('/^[A-Z_]{1,80}$/', $category)) $diagnostic['http_category'] = $category;
                    $scopes = collect([
                        data_get($body, 'context.requiredScopes', []), data_get($body, 'requiredScopes', []),
                        data_get($body, 'errors.*.context.requiredScopes', []),
                        data_get($body, 'errors.*.context.requiredGranularScopes', []),
                    ])->flatten()->filter(fn ($scope) => is_string($scope) && preg_match('/^crm\.[a-z0-9_.-]{1,100}$/', $scope))->unique()->values()->all();
                    if ($scopes) $diagnostic['required_scopes'] = $scopes;
                    if ($provider === 'teamleader') {
                        $diagnostic['remote_errors'] = collect(data_get($body, 'errors', []))->take(3)
                            ->map(function ($error): string {
                                $title = strip_tags((string) ($error['title'] ?? ''));
                                $title = preg_replace('/[\'\"].*?[\'\"]/u', '[valor]', $title);
                                $title = preg_replace('/https?:\/\/\S+|[\w.+-]+@[\w.-]+|\b[A-Za-z0-9_+=.\/-]{24,}\b|\b\d{4,}\b/u', '[valor]', $title);
                                return mb_substr($title, 0, 200);
                            })->filter()->values()->all();
                    }
                }
                if (preg_match('/cURL error (\d+)/', $e->getMessage(), $match)) $diagnostic['curl_error'] = (int) $match[1];
                $state->save($diagnostic + ['status' => $review ? 'review' : 'retry', 'lease_until' => null,
                    'last_error' => $reason, 'next_attempt_at' => now()->addMinutes(min(60, 5 * (int) $state->get('attempts')))->toIso8601String()]);
                Log::warning('COS: sincronización del comprobante pendiente', [
                    'receipt_id' => $receiptId, 'provider' => $provider,
                    'exception_type' => get_class($e), 'review' => $review,
                    'operation' => $state->get('last_operation'),
                ]);
            }
        }
        DB::transaction(function () use ($receiptId): void {
            $receipt = Compras::lockForUpdate()->findOrFail($receiptId);
            $metadata = $receipt->metadata ?? [];
            $states = data_get($metadata, 'crm_payment_sync.providers', []);
            data_set($metadata, 'crm_payment_sync.pending', collect($states)->contains(
                fn ($s) => in_array($s['status'] ?? '', ['pending', 'retry', 'processing'], true)));
            $receipt->forceFill(['metadata' => $metadata])->save();
        });
    }

    private function claim(int $receiptId, string $provider, bool $force): ?CrmPaymentSyncState
    {
        return DB::transaction(function () use ($receiptId, $provider, $force) {
            $receipt = Compras::lockForUpdate()->findOrFail($receiptId);
            $metadata = $receipt->metadata ?? [];
            $value = data_get($metadata, "crm_payment_sync.providers.{$provider}");
            if (! is_array($value) || ($value['status'] ?? '') === 'synced'
                || (! $force && ($value['status'] ?? '') === 'review')
                || (! empty($value['lease_until']) && now()->lt($value['lease_until']))
                || (! $force && ! empty($value['next_attempt_at']) && now()->lt($value['next_attempt_at']))) return null;
            $lease = (string) Str::uuid();
            data_set($metadata, "crm_payment_sync.providers.{$provider}", array_merge($value, [
                'status' => 'processing', 'attempts' => (int) ($value['attempts'] ?? 0) + 1,
                'lease' => $lease, 'lease_until' => now()->addMinutes(10)->toIso8601String(),
            ]));
            $receipt->forceFill(['metadata' => $metadata])->save();
            return new CrmPaymentSyncState($receiptId, $provider, $lease);
        });
    }
}

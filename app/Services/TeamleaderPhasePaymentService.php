<?php

namespace App\Services;

use App\Mail\TeamleaderPhaseOverpayment;
use App\Mail\TeamleaderSmallBalance;
use App\Models\Compras;
use App\Models\Negocio;
use App\Models\TeamleaderOverpaymentNotice;
use App\Models\TeamleaderSmallBalanceNotice;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Turns the amount fields maintained in Teamleader into independent portal
 * payment records. Every Teamleader project / phase pair has at most one
 * active purchase record, so phase 1, 2 and 3 never affect one another.
 */
class TeamleaderPhasePaymentService
{
    public const PURCHASE_SOURCE = 'teamleader_phase';

    public const INSTALLMENT_SOURCE = 'teamleader_phase_installment';

    public const HISTORY_SOURCE = 'teamleader_phase_history';

    private const SEQUENTIAL_PHASES = [1, 2, 3];

    private const MINIMUM_COLLECTIBLE_BALANCE = 50.00;

    private const OVERPAYMENT_RECIPIENTS = TeamleaderPhasePaymentRecipients::ADDRESSES;

    public function sync(User $user, array &$analysis): Collection
    {
        $existing = Compras::query()
            ->where('id_user', $user->id)
            ->where('source', self::PURCHASE_SOURCE)
            ->get()
            ->keyBy(fn (Compras $purchase) => $this->purchaseKey(
                (string) data_get($purchase->metadata, 'teamleader_project_id'),
                (int) data_get($purchase->metadata, 'phase')
            ));
        $historicalEntries = Compras::query()
            ->where('id_user', $user->id)
            ->where('source', self::HISTORY_SOURCE)
            ->get()
            ->keyBy(fn (Compras $purchase) => (string) data_get($purchase->metadata, 'teamleader_payment_reference'));

        $records = collect();
        $historicalPaidAmounts = $this->historicalPaidAmounts($user);

        if (! isset($analysis['projects']) || ! is_array($analysis['projects'])) {
            return $records;
        }

        // Keep the same reconciled figures available to the internal COS
        // view. Previously the purchase record used converted installments,
        // while the view still rendered the pre-conversion analyzer result.
        foreach ($analysis['projects'] as &$project) {
            if (! isset($project['phases']) || ! is_array($project['phases'])) {
                continue;
            }

            foreach ($project['phases'] as &$phase) {
                $projectId = (string) ($project['project_id'] ?? '');
                $phaseNumber = (int) ($phase['phase'] ?? 0);

                if ($projectId === '' || ! array_key_exists($phaseNumber, TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS)) {
                    continue;
                }

                $key = $this->purchaseKey($projectId, $phaseNumber);
                $preestablished = round((float) ($phase['effective_preestab_amount'] ?? 0), 2);
                $foreignConversion = app(TeamleaderHistoricalExchangeRateService::class)
                    ->convertForeignEntriesToEuro(data_get($phase, 'paid_parse.dated_entries', []));
                $this->syncHistoricalPaymentEntries($user, $project, $phase, $foreignConversion, $historicalEntries);
                $hasUnconvertedCurrency = ! empty(data_get($phase, 'preestab_parse.requires_currency_conversion'))
                    || ! empty($foreignConversion['unconverted_entries']);
                if (! $hasUnconvertedCurrency) {
                    $phase['review_reasons'] = array_values(array_filter(
                        $phase['review_reasons'] ?? [],
                        fn (string $reason) => $reason !== 'currency_conversion_required'
                    ));
                    $phase['needs_review'] = ! empty($phase['review_reasons']);
                }

                $teamleaderPaid = round(
                    (float) ($phase['effective_paid_amount'] ?? 0) + (float) ($foreignConversion['converted_amount'] ?? 0),
                    2
                );
                $historicalPaid = (float) ($historicalPaidAmounts[$key] ?? 0);
                // A historic abono can exist both in Teamleader and in the
                // legacy negocio totals. It must count once, so reconcile
                // the two independently confirmed totals instead of adding
                // them together.
                $paid = $this->reconciledPaidAmount($teamleaderPaid, $historicalPaid);
                $balance = round(max($preestablished - $paid, 0), 2);
                $overpaid = round(max($paid - $preestablished, 0), 2);

                $phase['teamleader_paid_amount'] = $teamleaderPaid;
                $phase['converted_foreign_paid_amount'] = (float) ($foreignConversion['converted_amount'] ?? 0);
                $phase['foreign_payment_conversions'] = $foreignConversion['conversions'] ?? [];
                $phase['historical_paid_amount'] = $historicalPaid;
                $phase['paid_amount'] = $paid;
                $phase['effective_paid_amount'] = $paid;
                $phase['balance_amount'] = $balance;
                $phase['overpaid_amount'] = $overpaid;
                $phase['difference_amount'] = round($preestablished - $paid, 2);
                if (empty($phase['needs_review'])) {
                    $phase['status'] = $overpaid > 0.01
                        ? 'review'
                        : ($balance <= 0.01 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'));
                }

                $this->notifyPhaseThreeOverpayment($user, $project, $phase);
                $this->notifySmallBalance($user, $project, $phase);

                // A payment record only makes sense when Teamleader contains a
                // real, readable pre-established amount for this phase.
                // An overpayment is a settled phase, not a portal debt. It is
                // still visibly flagged in COS and phase 3 sends its internal
                // notification above; only other data-quality issues pause
                // record creation.
                $hasBlockingReview = ! empty($phase['needs_review']) && $overpaid <= 0.01;

                $purchase = $existing->get($key) ?? $this->legacyPurchaseFor($user, $projectId, $phaseNumber);
                if ($preestablished <= 0 || $hasBlockingReview) {
                    // A review-required phase must remain in the sequence so
                    // later phases are not enabled, but it is never payable
                    // from the portal until Finance validates the conversion.
                    if ($preestablished > 0 && $hasBlockingReview) {
                        $reviewMetadata = array_merge($this->metadata($project, array_merge($phase, [
                            'portal_disposition' => 'review_required',
                            'is_collectible_in_portal' => false,
                        ])), ['portal_record_kind' => 'balance']);

                        if (! $purchase) {
                            $purchase = Compras::create([
                                'id_user' => $user->id,
                                'source' => self::PURCHASE_SOURCE,
                                'servicio_hs_id' => $user->servicio ?: 'teamleader-phase-payment',
                                'descripcion' => $this->description($project, $phase),
                                'pagado' => 0,
                                'monto' => $balance,
                                'phasenum' => $phaseNumber,
                                'metadata' => $reviewMetadata,
                            ]);
                        } else {
                            $purchase->forceFill([
                                'source' => self::PURCHASE_SOURCE,
                                'descripcion' => $this->description($project, $phase),
                                'monto' => $balance,
                                'pagado' => 0,
                                'paid_at' => null,
                                'metadata' => array_merge($purchase->metadata ?? [], $reviewMetadata),
                            ])->save();
                        }
                    }

                    continue;
                }
                $portalRecordAmount = $this->portalRecordAmount($paid, $balance);
                $metadata = array_merge($this->metadata($project, $phase), ['portal_record_kind' => 'balance']);

                if (! $purchase) {
                    $purchase = Compras::create([
                        'id_user' => $user->id,
                        'source' => self::PURCHASE_SOURCE,
                        'servicio_hs_id' => $user->servicio ?: 'teamleader-phase-payment',
                        'descripcion' => $this->description($project, $phase),
                        'pagado' => $this->isPortalPaymentPending($balance) ? 0 : 1,
                        // For a pending phase, only charge what is actually
                        // outstanding, never the original full phase amount.
                        'monto' => $portalRecordAmount,
                        'phasenum' => $phaseNumber,
                        'metadata' => $metadata,
                        'paid_at' => $balance <= 0.01 ? now() : null,
                    ]);
                } else {
                    $previousMetadata = $purchase->metadata ?? [];
                    $metadata = array_merge($previousMetadata, $metadata);
                    $teamleaderHasNotReflectedLastPortalInstallment = ! $purchase->pagado
                        && data_get($previousMetadata, 'last_portal_installment_id')
                        && $paid <= ((float) data_get($previousMetadata, 'paid_amount_at_sync', 0) + 0.01);
                    $amountToShow = $teamleaderHasNotReflectedLastPortalInstallment
                        ? min((float) $purchase->monto, $portalRecordAmount)
                        : $portalRecordAmount;

                    $purchase->fill([
                        'source' => self::PURCHASE_SOURCE,
                        'descripcion' => $this->description($project, $phase),
                        // If Teamleader is momentarily behind a successful
                        // portal write, retain the reduced local balance.
                        // When its paid amount advances, it remains the
                        // source of truth and replaces this value.
                        'monto' => $purchase->pagado ? (float) $purchase->monto : $amountToShow,
                        'metadata' => $metadata,
                    ]);

                    // A manual/offline payment reflected in Teamleader closes
                    // the portal obligation without generating a new charge.
                    if ($this->isPortalPaymentPending($balance)
                        && $purchase->pagado
                        && in_array(data_get($previousMetadata, 'portal_disposition'), ['small_balance_ignored', 'review_required'], true)) {
                        $purchase->monto = $portalRecordAmount;
                        $purchase->pagado = 0;
                        $purchase->paid_at = null;
                    } elseif (! $this->isPortalPaymentPending($balance) && ! $purchase->pagado) {
                        $purchase->pagado = 1;
                        $purchase->paid_at = $balance <= 0.01 ? now() : null;
                    }

                    $purchase->save();
                }

                $records->push($purchase);
            }

            unset($phase);
        }

        unset($project);
        $this->refreshAnalysisTotals($analysis);

        return $records;
    }

    /**
     * Rebuilds the totals after historical installments, dated currency
     * conversion and legacy payments have been reconciled per phase.
     */
    private function refreshAnalysisTotals(array &$analysis): void
    {
        $projectTotals = collect();

        foreach ($analysis['projects'] as &$project) {
            $phases = collect($project['phases'] ?? []);
            $project['needs_review'] = $phases->contains('needs_review', true);
            $project['review_count'] = $phases->where('needs_review', true)->count();
            $project['totals'] = [
                'preestab_amount' => round($phases->sum('effective_preestab_amount'), 2),
                'paid_amount' => round($phases->sum('effective_paid_amount'), 2),
                'balance_amount' => round($phases->sum('balance_amount'), 2),
                'overpaid_amount' => round($phases->sum('overpaid_amount'), 2),
                'difference_amount' => round($phases->sum('difference_amount'), 2),
            ];
            $projectTotals->push($project);
        }

        unset($project);

        $analysis['totals'] = [
            'projects' => $projectTotals->count(),
            'preestab_amount' => round($projectTotals->sum('totals.preestab_amount'), 2),
            'paid_amount' => round($projectTotals->sum('totals.paid_amount'), 2),
            'balance_amount' => round($projectTotals->sum('totals.balance_amount'), 2),
            'overpaid_amount' => round($projectTotals->sum('totals.overpaid_amount'), 2),
            'difference_amount' => round($projectTotals->sum('totals.difference_amount'), 2),
            'projects_to_review' => $projectTotals->where('needs_review', true)->count(),
        ];
    }

    /**
     * Returns every phase charge that the solicitante may see in the portal.
     * Sequential phases stay visible as debt even when an earlier phase must
     * be paid first; hidden and review-required records remain internal.
     */
    public function visiblePortalPurchases(Collection $purchases): Collection
    {
        return $purchases
            ->reject(fn (Compras $purchase) => $purchase->source === self::PURCHASE_SOURCE
                && $this->isHiddenFromClient($purchase))
            ->reject(fn (Compras $purchase) => $purchase->source === self::PURCHASE_SOURCE
                && data_get($purchase->metadata, 'portal_disposition') === 'review_required')
            ->values();
    }
    /**
     * Returns the phase charges that may be paid now. Phases 1–3 advance in
     * order per project; other independently configured payment fields keep
     * their own availability.
     */
    public function currentPortalPurchases(Collection $purchases): Collection
    {
        $phasePurchases = $purchases
            ->filter(fn (Compras $purchase) => $purchase->source === self::PURCHASE_SOURCE)
            // An internal completion decision treats the residual as settled
            // for the portal sequence, without changing its financial record.
            ->reject(fn (Compras $purchase) => $this->isHiddenFromClient($purchase))
            ->groupBy(fn (Compras $purchase) => (string) data_get($purchase->metadata, 'teamleader_project_id', $purchase->id));

        $availableIds = $phasePurchases
            ->flatMap(function (Collection $projectPurchases) {
                return $projectPurchases
                    ->filter(fn (Compras $purchase) => in_array((int) data_get($purchase->metadata, 'phase', $purchase->phasenum), self::SEQUENTIAL_PHASES, true))
                    ->sortBy(fn (Compras $purchase) => (int) data_get($purchase->metadata, 'phase', $purchase->phasenum))
                    ->take(1);
            })
            ->pluck('id')
            ->flip();

        return $this->visiblePortalPurchases($purchases)
            ->reject(fn (Compras $purchase) => $purchase->source === self::PURCHASE_SOURCE
                && in_array((int) data_get($purchase->metadata, 'phase', $purchase->phasenum), self::SEQUENTIAL_PHASES, true)
                && ! $availableIds->has($purchase->id))
            ->values();
    }

    /**
     * Returns every visible outstanding phase in the same project as the
     * currently payable phase. This permits an optional one-time payment of
     * the complete remaining project balance without making later phases
     * individually payable out of sequence.
     */
    public function remainingProjectPortalPurchases(Compras $anchor, Collection $purchases): Collection
    {
        if ($anchor->source !== self::PURCHASE_SOURCE) {
            return collect();
        }

        $projectId = (string) data_get($anchor->metadata, 'teamleader_project_id');
        if ($projectId === '' || ! $this->currentPortalPurchases($purchases)->contains('id', $anchor->id)) {
            return collect();
        }

        return $this->visiblePortalPurchases($purchases)
            ->filter(fn (Compras $purchase) => $purchase->source === self::PURCHASE_SOURCE)
            ->filter(fn (Compras $purchase) => (string) data_get($purchase->metadata, 'teamleader_project_id') === $projectId)
            ->sortBy(fn (Compras $purchase) => (int) data_get($purchase->metadata, 'phase', $purchase->phasenum))
            ->values();
    }

    public function canPayPortalPurchase(Compras $purchase): bool
    {
        if ($purchase->source !== self::PURCHASE_SOURCE) {
            return true;
        }

        return $this->currentPortalPurchases(
            Compras::query()
                ->where('id_user', $purchase->id_user)
                ->where('source', self::PURCHASE_SOURCE)
                ->where('pagado', 0)
                ->where('monto', '>', 0)
                ->get()
        )->contains('id', $purchase->id);
    }

    private function isHiddenFromClient(Compras $purchase): bool
    {
        return filter_var(
            data_get($purchase->metadata, 'hidden_from_client', false),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /**
     * Stores a completed installment separately from the outstanding balance,
     * so each payment is auditable and the remaining amount remains payable.
     */
    public function recordPortalInstallment(
        Compras $balancePurchase,
        float $amount,
        string $invoiceHash,
        \Carbon\Carbon $paidAt,
        ?string $gatewayReference = null
    ): Compras {
        if ($balancePurchase->source !== self::PURCHASE_SOURCE || $balancePurchase->pagado) {
            throw new \InvalidArgumentException('El pago de fase ya no está disponible.');
        }

        $balance = round((float) $balancePurchase->monto, 2);
        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > $balance) {
            throw new \InvalidArgumentException('El abono debe ser mayor que cero y no puede superar el saldo pendiente.');
        }

        $receipt = Compras::query()
            ->where('id_user', $balancePurchase->id_user)
            ->where('source', self::INSTALLMENT_SOURCE)
            ->where('hash_factura', $invoiceHash)
            ->get()
            ->first(function (Compras $candidate) use ($balancePurchase): bool {
                $linkedBalanceId = data_get($candidate->metadata, 'balance_purchase_id');

                if ($linkedBalanceId !== null) {
                    return (int) $linkedBalanceId === (int) $balancePurchase->id;
                }

                // Compatibility with receipts created before the balance
                // purchase identifier was stored in metadata.
                return (int) $candidate->phasenum === (int) $balancePurchase->phasenum
                    && (string) data_get($candidate->metadata, 'teamleader_project_id')
                        === (string) data_get($balancePurchase->metadata, 'teamleader_project_id');
            });

        if (! $receipt) {
            $receiptMetadata = array_merge($balancePurchase->metadata ?? [], [
                'portal_record_kind' => 'installment',
                'balance_purchase_id' => $balancePurchase->id,
                'gateway_reference' => $gatewayReference,
            ]);

            $receipt = Compras::create([
                'id_user' => $balancePurchase->id_user,
                'source' => self::INSTALLMENT_SOURCE,
                'servicio_hs_id' => $balancePurchase->servicio_hs_id,
                'descripcion' => 'Abono · ' . $balancePurchase->descripcion,
                'pagado' => 1,
                'monto' => $amount,
                'deal_id' => $balancePurchase->deal_id,
                'phasenum' => $balancePurchase->phasenum,
                'metadata' => $receiptMetadata,
                'hash_factura' => $invoiceHash,
                'paid_at' => $paidAt,
            ]);
        }

        $remaining = round(max($balance - $amount, 0), 2);
        $balanceMetadata = array_merge($balancePurchase->metadata ?? [], [
            'portal_record_kind' => 'balance',
            'last_portal_installment_id' => $receipt->id,
            'last_portal_installment_at' => $paidAt->toIso8601String(),
        ]);
        $balancePurchase->forceFill([
            'monto' => $remaining,
            'pagado' => $remaining <= 0.01 ? 1 : 0,
            'metadata' => $balanceMetadata,
            'paid_at' => $remaining <= 0.01 ? $paidAt : null,
        ])->save();

        try {
            $projectId = (string) data_get($balancePurchase->metadata, 'teamleader_project_id');
            $phase = (int) data_get($balancePurchase->metadata, 'phase', $balancePurchase->phasenum);

            if ($projectId === '' || ! array_key_exists($phase, TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS)) {
                throw new \InvalidArgumentException('El pago de fase no contiene la referencia necesaria.');
            }

            app(TeamleaderProjectPaymentWriter::class)->appendPhasePayment(
                $projectId,
                $phase,
                $amount,
                $paidAt->format('Y-m-d'),
                'phase-payment-' . $receipt->id,
                'EUR'
            );
        } catch (\Throwable $exception) {
            Log::channel('teamleader')->error('El abono fue registrado localmente pero no se pudo sincronizar en Teamleader', [
                'purchase_id' => $balancePurchase->id,
                'receipt_id' => $receipt->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return $receipt;
    }
    /**
     * Records a portal payment in the corresponding Teamleader paid field.
     * The Teamleader service fetches the current project and sends all mutable
     * project information back with the changed custom field, avoiding the
     * partial-update data loss reported for projects.update.
     */
    public function syncPortalPayment(Compras $purchase, TeamleaderService $teamleader): void
    {
        if ($purchase->source !== self::PURCHASE_SOURCE) {
            return;
        }

        $projectId = (string) data_get($purchase->metadata, 'teamleader_project_id');
        $phase = (int) data_get($purchase->metadata, 'phase');

        if ($projectId === '' || ! array_key_exists($phase, TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS)) {
            throw new \InvalidArgumentException('El pago de fase no contiene la referencia de Teamleader necesaria.');
        }

        app(TeamleaderProjectPaymentWriter::class)->appendPhasePayment(
            $projectId,
            $phase,
            (float) $purchase->monto,
            ($purchase->paid_at ?: now())->format('Y-m-d'),
            'purchase-' . $purchase->id,
            'EUR'
        );
    }

    public function isTeamleaderPhasePurchase(Compras $purchase): bool
    {
        return $purchase->source === self::PURCHASE_SOURCE;
    }

    /**
     * Reconciles the same historical payment when it appears in both the
     * Teamleader custom field and the local legacy negocio record.
     */
    private function reconciledPaidAmount(float $teamleaderPaid, float $historicalPaid): float
    {
        return round(max($teamleaderPaid, $historicalPaid, 0), 2);
    }

    /**
     * Older phase payments were stored in the negocio totals before the
     * Teamleader payment fields became the normal source. Treat those totals
     * as a fallback so an existing abono cannot make the portal request the
     * whole phase again. The maximum per phase avoids double counting a value
     * that has already reached Teamleader.
     *
     * @return array<string, float>
     */
    private function historicalPaidAmounts(User $user): array
    {
        $fieldsByPhase = [
            1 => ['monto_fase_1_pagado', 'fase_1_pagado'],
            2 => ['monto_fase_2_pagado', 'fase_2_pagado'],
            3 => ['monto_fase_3_pagado', 'fase_3_pagado'],
            98 => ['carta_nat_montopagado', 'carta_nat_pagado'],
            99 => ['cilfcje_montopagado', 'cil___fcje_pagado'],
        ];
        $analyzer = app(TeamleaderProjectPaymentAnalyzer::class);
        $amounts = [];
        $deals = Negocio::query()
            ->where('user_id', $user->id)
            ->whereNotNull('teamleader_id')
            ->get();
        $projectByDealId = $deals
            ->mapWithKeys(fn (Negocio $deal) => [$deal->id => trim((string) $deal->teamleader_id)])
            ->filter();

        foreach ($deals as $deal) {
            $projectId = trim((string) $deal->teamleader_id);
            if ($projectId === '') {
                continue;
            }

            foreach ($fieldsByPhase as $phase => $fields) {
                $confirmedAmount = collect($fields)
                    ->map(function (string $field) use ($deal, $analyzer): float {
                        $value = $deal->{$field};
                        if ($value === null || $value === '') {
                            return 0.0;
                        }

                        return (float) ($analyzer->parseMoneyText((string) $value)['total'] ?? 0);
                    })
                    ->max() ?? 0.0;

                if ($confirmedAmount <= 0) {
                    continue;
                }

                $key = $this->purchaseKey($projectId, $phase);
                $amounts[$key] = max((float) ($amounts[$key] ?? 0), round($confirmedAmount, 2));
            }
        }

        // Some older abonos only have a paid purchase receipt. Sum those
        // receipts per negocio/phase, then reconcile them with the legacy
        // total above using max() so a mirrored receipt is never counted twice.
        $paidPurchaseAmounts = [];
        if ($projectByDealId->isNotEmpty()) {
            $historicalPurchases = Compras::query()
                ->where('id_user', $user->id)
                ->where('pagado', 1)
                ->whereIn('deal_id', $projectByDealId->keys())
                ->whereIn('phasenum', array_keys($fieldsByPhase))
                ->where('monto', '>', 0)
                ->where(function ($query) {
                    $query->whereNull('source')
                        ->orWhereNotIn('source', [self::PURCHASE_SOURCE, self::INSTALLMENT_SOURCE]);
                })
                ->get(['deal_id', 'phasenum', 'monto']);

            foreach ($historicalPurchases as $purchase) {
                $projectId = (string) $projectByDealId->get($purchase->deal_id);
                if ($projectId === '') {
                    continue;
                }

                $key = $this->purchaseKey($projectId, (int) $purchase->phasenum);
                $paidPurchaseAmounts[$key] = round(
                    (float) ($paidPurchaseAmounts[$key] ?? 0) + (float) $purchase->monto,
                    2
                );
            }
        }

        foreach ($paidPurchaseAmounts as $key => $amount) {
            $amounts[$key] = max((float) ($amounts[$key] ?? 0), $amount);
        }

        return $amounts;
    }

    /**
     * Stores every historic Teamleader installment as a read-only completed
     * payment for COS. Portal-generated installments carry a [COS:...] marker
     * and are deliberately skipped because their invoice/receipt is already
     * rendered by the portal payment history.
     */
    private function syncHistoricalPaymentEntries(
        User $user,
        array $project,
        array $phase,
        array $foreignConversion,
        Collection $existingEntries
    ): void {
        $conversionQueues = [];
        foreach ($foreignConversion['conversions'] ?? [] as $conversion) {
            $conversionKey = $this->historicalConversionKey(
                (float) ($conversion['amount'] ?? 0),
                (string) ($conversion['currency'] ?? ''),
                (string) ($conversion['payment_date'] ?? '')
            );
            $conversionQueues[$conversionKey][] = $conversion;
        }

        foreach (array_values(data_get($phase, 'paid_parse.dated_entries', [])) as $index => $entry) {
            $amount = round((float) ($entry['amount'] ?? 0), 2);
            $currency = strtoupper(trim((string) ($entry['currency'] ?? 'EUR')));
            $paymentDate = trim((string) ($entry['date'] ?? ''));

            if ($amount <= 0 || $currency === '' || ! empty($entry['cos_reference'])) {
                continue;
            }

            $conversion = null;
            $displayAmount = $amount;
            $displayCurrency = $currency;
            if ($currency !== 'EUR') {
                $conversionKey = $this->historicalConversionKey($amount, $currency, $paymentDate);
                $conversion = ! empty($conversionQueues[$conversionKey])
                    ? array_shift($conversionQueues[$conversionKey])
                    : null;
                if ($conversion) {
                    $displayAmount = round((float) ($conversion['eur_amount'] ?? 0), 2);
                    $displayCurrency = 'EUR';
                }
            }

            $reference = sha1(implode('|', [
                (string) ($project['project_id'] ?? ''),
                (string) ($phase['phase'] ?? ''),
                (string) $index,
                number_format($amount, 2, '.', ''),
                $currency,
                $paymentDate,
            ]));
            $attributes = [
                'id_user' => $user->id,
                'source' => self::HISTORY_SOURCE,
                'servicio_hs_id' => $user->servicio ?: 'teamleader-phase-payment',
                'descripcion' => $this->historicalDescription($project, $phase, $amount, $currency),
                'pagado' => 1,
                'monto' => $displayAmount,
                'phasenum' => (int) ($phase['phase'] ?? 0),
                'metadata' => [
                    'portal_record_kind' => 'historical_installment',
                    'teamleader_project_id' => (string) ($project['project_id'] ?? ''),
                    'phase' => (int) ($phase['phase'] ?? 0),
                    'payment_label' => (string) ($phase['payment_label'] ?? ''),
                    'teamleader_payment_reference' => $reference,
                    'original_amount' => $amount,
                    'original_currency' => $currency,
                    'display_currency' => $displayCurrency,
                    'payment_date' => $paymentDate ?: null,
                    'conversion' => $conversion,
                ],
                'paid_at' => $paymentDate !== '' ? \Carbon\Carbon::createFromFormat('Y-m-d', $paymentDate)->startOfDay() : null,
            ];

            $purchase = $existingEntries->get($reference);
            if ($purchase) {
                $purchase->fill($attributes)->save();
            } else {
                $purchase = Compras::create($attributes);
                $existingEntries->put($reference, $purchase);
            }
        }
    }

    private function historicalConversionKey(float $amount, string $currency, string $paymentDate): string
    {
        return implode('|', [number_format($amount, 2, '.', ''), strtoupper(trim($currency)), $paymentDate]);
    }

    private function historicalDescription(array $project, array $phase, float $amount, string $currency): string
    {
        $title = trim((string) ($project['project_title'] ?? 'Proyecto'));
        $label = (string) ($phase['payment_label'] ?? 'Fase ' . ($phase['phase'] ?? '-'));
        $originalAmount = format_money($amount, 2, ',', '.');

        return $currency === 'EUR'
            ? "Abono {$label} · {$title}"
            : "Abono {$label} · {$title} ({$originalAmount} {$currency})";
    }

    private function legacyPurchaseFor(User $user, string $projectId, int $phase): ?Compras
    {
        $dealIds = Negocio::query()
            ->where('user_id', $user->id)
            ->where('teamleader_id', $projectId)
            ->pluck('id');

        if ($dealIds->isEmpty()) {
            return null;
        }

        return Compras::query()
            ->where('id_user', $user->id)
            ->whereIn('deal_id', $dealIds)
            ->where('phasenum', $phase)
            ->where('pagado', 0)
            ->orderBy('id')
            ->first();
    }

    private function metadata(array $project, array $phase): array
    {
        $disposition = (string) ($phase['portal_disposition'] ?? $this->paymentDisposition($phase));
        return [
            'teamleader_project_id' => (string) ($project['project_id'] ?? ''),
            'teamleader_project_title' => (string) ($project['project_title'] ?? ''),
            'phase' => (int) ($phase['phase'] ?? 0),
            'payment_key' => $phase['payment_key'] ?? null,
            'payment_label' => $phase['payment_label'] ?? null,
            'preestablished_amount' => round((float) ($phase['effective_preestab_amount'] ?? 0), 2),
            'teamleader_paid_amount_at_sync' => round((float) ($phase['teamleader_paid_amount'] ?? $phase['effective_paid_amount'] ?? 0), 2),
            'converted_foreign_paid_amount_at_sync' => round((float) ($phase['converted_foreign_paid_amount'] ?? 0), 2),
            'foreign_payment_conversions_at_sync' => $phase['foreign_payment_conversions'] ?? [],
            'historical_paid_amount_at_sync' => round((float) ($phase['historical_paid_amount'] ?? 0), 2),
            'paid_amount_at_sync' => round((float) ($phase['effective_paid_amount'] ?? 0), 2),
            'portal_disposition' => $disposition,
            'minimum_collectible_balance' => self::MINIMUM_COLLECTIBLE_BALANCE,
            'is_collectible_in_portal' => $disposition === 'pending',
            'balance_amount_at_sync' => round((float) ($phase['balance_amount'] ?? 0), 2),
            'teamleader_payment_status' => $phase['status'] ?? 'empty',
            'teamleader_synced_at' => now()->toIso8601String(),
        ];
    }

    private function description(array $project, array $payment): string
    {
        $title = trim((string) ($project['project_title'] ?? 'Proyecto'));
        $label = (string) ($payment['payment_label'] ?? 'Fase ' . ($payment['phase'] ?? '-'));

        return "Pago {$label} · {$title}";
    }

    private function purchaseKey(string $projectId, int $phase): string
    {
        return $projectId . ':' . $phase;
    }

    private function paymentDisposition(array $phase): string
    {
        $balance = round((float) ($phase['balance_amount'] ?? 0), 2);

        if ($balance <= 0.01) {
            return 'recorded_payment';
        }

        return $this->isPortalPaymentPending($balance)
            ? 'pending'
            : 'small_balance_ignored';
    }

    private function isPortalPaymentPending(float $balance): bool
    {
        return $balance >= self::MINIMUM_COLLECTIBLE_BALANCE;
    }

    private function portalRecordAmount(float $paid, float $balance): float
    {
        if ($this->isPortalPaymentPending($balance)) {
            return $balance;
        }

        // A non-collectible balance is settled for the client; the ordinary
        // paid table must show the actual amount received, not that ignored
        // residual difference.
        return max($paid, 0.0);
    }

    private function isSmallBalance(array $payment): bool
    {
        $balance = round((float) ($payment['balance_amount'] ?? 0), 2);

        return $balance > 0.01 && ! $this->isPortalPaymentPending($balance);
    }

    private function notifySmallBalance(User $user, array $project, array $payment): void
    {
        if (! $this->isSmallBalance($payment)) {
            return;
        }

        $notice = TeamleaderSmallBalanceNotice::firstOrCreate(
            [
                'teamleader_project_id' => (string) ($project['project_id'] ?? ''),
                'phase' => (int) ($payment['phase'] ?? 0),
            ],
            [
                'user_id' => $user->id,
                'preestablished_amount' => (float) ($payment['effective_preestab_amount'] ?? 0),
                'paid_amount' => (float) ($payment['effective_paid_amount'] ?? 0),
                'balance_amount' => (float) ($payment['balance_amount'] ?? 0),
            ]
        );

        if ($notice->notified_at) {
            return;
        }

        try {
            Mail::to(self::OVERPAYMENT_RECIPIENTS)
                ->send(new TeamleaderSmallBalance($user, array_merge($project, ['payment_data' => $payment])));

            $notice->forceFill(['notified_at' => now()])->save();
        } catch (\Throwable $exception) {
            Log::channel('teamleader')->error('No se pudo enviar aviso de diferencial menor al mínimo de cobro', [
                'project_id' => $project['project_id'] ?? null,
                'user_id' => $user->id,
                'phase' => $payment['phase'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function notifyPhaseThreeOverpayment(User $user, array $project, array $phase): void
    {
        if ((int) ($phase['phase'] ?? 0) !== 3 || (float) ($phase['overpaid_amount'] ?? 0) <= 0.01) {
            return;
        }

        $notice = TeamleaderOverpaymentNotice::firstOrCreate(
            [
                'teamleader_project_id' => (string) ($project['project_id'] ?? ''),
                'phase' => 3,
            ],
            [
                'user_id' => $user->id,
                'preestablished_amount' => (float) ($phase['effective_preestab_amount'] ?? 0),
                'paid_amount' => (float) ($phase['effective_paid_amount'] ?? 0),
                'overpaid_amount' => (float) ($phase['overpaid_amount'] ?? 0),
            ]
        );

        if ($notice->notified_at) {
            return;
        }

        try {
            Mail::to(self::OVERPAYMENT_RECIPIENTS)
                ->send(new TeamleaderPhaseOverpayment($user, array_merge($project, ['phase_data' => $phase])));

            $notice->forceFill(['notified_at' => now()])->save();
        } catch (\Throwable $exception) {
            Log::channel('teamleader')->error('No se pudo enviar aviso de sobrepago de Teamleader', [
                'project_id' => $project['project_id'] ?? null,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}

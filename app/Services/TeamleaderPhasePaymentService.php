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

    private const MINIMUM_COLLECTIBLE_BALANCE = 50.00;

    private const OVERPAYMENT_RECIPIENTS = TeamleaderPhasePaymentRecipients::ADDRESSES;

    public function sync(User $user, array $analysis): Collection
    {
        $existing = Compras::query()
            ->where('id_user', $user->id)
            ->where('source', self::PURCHASE_SOURCE)
            ->get()
            ->keyBy(fn (Compras $purchase) => $this->purchaseKey(
                (string) data_get($purchase->metadata, 'teamleader_project_id'),
                (int) data_get($purchase->metadata, 'phase')
            ));

        $records = collect();

        foreach ($analysis['projects'] ?? [] as $project) {
            foreach ($project['phases'] ?? [] as $phase) {
                $projectId = (string) ($project['project_id'] ?? '');
                $phaseNumber = (int) ($phase['phase'] ?? 0);

                if ($projectId === '' || ! array_key_exists($phaseNumber, TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS)) {
                    continue;
                }

                $this->notifyPhaseThreeOverpayment($user, $project, $phase);

                $preestablished = round((float) ($phase['effective_preestab_amount'] ?? 0), 2);
                $paid = round((float) ($phase['effective_paid_amount'] ?? 0), 2);
                $this->notifySmallBalance($user, $project, $phase);

                $balance = round((float) ($phase['balance_amount'] ?? 0), 2);

                // A payment record only makes sense when Teamleader contains a
                // real, readable pre-established amount for this phase.
                // An overpayment is a settled phase, not a portal debt. It is
                // still visibly flagged in COS and phase 3 sends its internal
                // notification above; only other data-quality issues pause
                // record creation.
                $hasBlockingReview = ! empty($phase['needs_review']) && (float) ($phase['overpaid_amount'] ?? 0) <= 0.01;

                if ($preestablished <= 0 || $hasBlockingReview) {
                    continue;
                }

                $key = $this->purchaseKey($projectId, $phaseNumber);
                $purchase = $existing->get($key) ?? $this->legacyPurchaseFor($user, $projectId, $phaseNumber);
                $portalRecordAmount = $this->portalRecordAmount($paid, $balance);
                $metadata = $this->metadata($project, $phase);

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

                    $purchase->fill([
                        'source' => self::PURCHASE_SOURCE,
                        'descripcion' => $this->description($project, $phase),
                        'monto' => max($portalRecordAmount, (float) $purchase->monto),
                        'metadata' => $metadata,
                    ]);

                    // A manual/offline payment reflected in Teamleader closes
                    // the portal obligation without generating a new charge.
                    if ($this->isPortalPaymentPending($balance)
                        && $purchase->pagado
                        && data_get($previousMetadata, 'portal_disposition') === 'small_balance_ignored') {
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
        }

        return $records;
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
            now()->format('Y/m/d')
        );
    }

    public function isTeamleaderPhasePurchase(Compras $purchase): bool
    {
        return $purchase->source === self::PURCHASE_SOURCE;
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
        $disposition = $this->paymentDisposition($phase);
        return [
            'teamleader_project_id' => (string) ($project['project_id'] ?? ''),
            'teamleader_project_title' => (string) ($project['project_title'] ?? ''),
            'phase' => (int) ($phase['phase'] ?? 0),
            'payment_key' => $phase['payment_key'] ?? null,
            'payment_label' => $phase['payment_label'] ?? null,
            'preestablished_amount' => round((float) ($phase['effective_preestab_amount'] ?? 0), 2),
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
        $title = trim((string) ($project['project_title'] ?? 'Proyecto Teamleader'));
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

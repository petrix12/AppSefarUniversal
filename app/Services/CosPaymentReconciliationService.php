<?php

namespace App\Services;

use App\Models\Compras;
use Illuminate\Support\Collection;

/** Removes mirrored obligations from presentation, never deletes receipts or changes charges. */
class CosPaymentReconciliationService
{
    public function __construct(private TeamleaderPhasePaymentService $phases)
    {
    }

    public function reconcile(Collection $deals, Collection $pending, Collection $paid, Collection $rows): array
    {
        $identities = $deals->mapWithKeys(fn ($deal) => [$deal->id => filled($deal->teamleader_id) ? 'tl:'.$deal->teamleader_id
            : (filled($deal->hubspot_id) ? 'hs:'.$deal->hubspot_id : 'local:'.$deal->id)]);
        $purchaseKey = fn (Compras $purchase) => $this->purchaseKey($purchase, $identities);
        $rowKey = function (array $row) use ($identities): string {
            $identity = $identities->get($row['deal_id'] ?? 0)
                ?: (filled($row['teamleader_project_id'] ?? null) ? 'tl:'.$row['teamleader_project_id'] : (($row['source'] ?? '') === 'Teamleader' ? 'tl:' : 'hs:').$row['project_id']);
            return $identity.':'.(int) $row['phase'].':'.strtoupper($row['currency'] ?? 'EUR');
        };
        $rows = $rows->unique($rowKey)->values();
        $balanceKeys = $pending->concat($paid)->where('source', TeamleaderPhasePaymentService::PURCHASE_SOURCE)->map($purchaseKey)->flip();
        $authoritativeKeys = $rows->filter(fn ($row) => (($row['source'] ?? '') === 'Teamleader' || filled($row['teamleader_project_id'] ?? null))
            && ((float) ($row['effective_preestab_amount'] ?? 0) > 0 || filled($row['preestab_raw'] ?? null)))->map($rowKey)->flip();
        $pending = $pending->unique('id')->groupBy($purchaseKey)->flatMap(function ($group, $key) use ($balanceKeys, $authoritativeKeys) {
            $balances = $group->where('source', TeamleaderPhasePaymentService::PURCHASE_SOURCE);
            // A TL phase balance replaces its HS mirror, while retaining the actual payable record ID.
            if ($balances->isNotEmpty()) return [$balances->sortByDesc('id')->first()];
            // A settled or review-required phase must not reappear as an old HS charge.
            return $balanceKeys->has($key) || $authoritativeKeys->has($key) ? [] : $group->all();
        })->values();
        $paid = $paid->unique('id')->groupBy($purchaseKey)->flatMap(function ($group) {
            $portal = $group->reject(fn ($purchase) => in_array($purchase->source, [TeamleaderPhasePaymentService::HISTORY_SOURCE, TeamleaderPhasePaymentService::PURCHASE_SOURCE], true));
            // Imported phase totals mirror portal receipts. Real installments remain individual receipts.
            if ($portal->isNotEmpty()) return $portal->all();
            $history = $group->where('source', TeamleaderPhasePaymentService::HISTORY_SOURCE);
            return $history->isNotEmpty() ? $history->all() : [$group->sortByDesc('id')->first()];
        })->values();
        $pendingByKey = $pending->groupBy($purchaseKey)->map(fn ($group) => (float) $group->sum('monto'));
        $paidByKey = $paid->groupBy($purchaseKey)->map(fn ($group) => (float) $group->sum('monto'));
        $suppressedKeys = $pending->filter(fn ($purchase) => data_get($purchase->metadata, 'hidden_from_client')
            || data_get($purchase->metadata, 'portal_disposition') === 'review_required')->map($purchaseKey)->flip();
        $pendingRows = $rows->map(function ($row) use ($rowKey, $pendingByKey, $suppressedKeys, $balanceKeys) {
            $key = $rowKey($row);
            $row['display_amount'] = $balanceKeys->has($key) || $suppressedKeys->has($key) || ($row['status'] ?? '') === 'review' ? 0.0
                : round(max((float) ($row['collectible_balance_amount'] ?? $row['balance_amount'] ?? 0) - $pendingByKey->get($key, 0), 0), 2);
            return $row;
        })->where('display_amount', '>', 0.01)->values();
        $paidRows = $rows->map(function ($row) use ($rowKey, $paidByKey) {
            $row['display_amount'] = round(max((float) ($row['effective_paid_amount'] ?? 0) - $paidByKey->get($rowKey($row), 0), 0), 2);
            return $row;
        })->where('display_amount', '>', 0.01)->values();
        $visiblePending = $this->phases->visiblePortalPurchases($pending);
        $currencies = $visiblePending->concat($paid)->map(fn ($purchase) => $this->currency($purchase))
            ->concat($rows->map(fn ($row) => strtoupper($row['currency'] ?? 'EUR')))->push('EUR')->unique();
        $summary = $currencies->map(function ($currency) use ($visiblePending, $paid, $pendingRows, $paidRows) {
            $paidAmount = $paid->filter(fn ($purchase) => $this->currency($purchase) === $currency)->sum('monto')
                + $paidRows->filter(fn ($row) => strtoupper($row['currency'] ?? 'EUR') === $currency)->sum('display_amount');
            $debt = $visiblePending->filter(fn ($purchase) => $this->currency($purchase) === $currency)->sum('monto')
                + $pendingRows->filter(fn ($row) => strtoupper($row['currency'] ?? 'EUR') === $currency)->sum('display_amount');
            return ['currency' => $currency, 'paid' => round($paidAmount, 2), 'owed' => round($debt, 2), 'debt' => round(max($debt, 0), 2)];
        })->values();

        return ['pending' => $pending, 'paid' => $paid, 'rows' => $rows, 'pending_rows' => $pendingRows, 'paid_rows' => $paidRows, 'summary' => $summary];
    }

    private function purchaseKey(Compras $purchase, Collection $identities): string
    {
        $phase = (int) data_get($purchase->metadata, 'phase', $purchase->phasenum);
        $project = data_get($purchase->metadata, 'teamleader_project_id');
        $identity = $project ? 'tl:'.$project : $identities->get($purchase->deal_id);
        return $identity && $phase > 0 ? $identity.':'.$phase.':'.$this->currency($purchase) : 'purchase:'.$purchase->id;
    }

    private function currency(Compras $purchase): string
    {
        return strtoupper((string) data_get($purchase->metadata, 'display_currency', 'EUR'));
    }
}

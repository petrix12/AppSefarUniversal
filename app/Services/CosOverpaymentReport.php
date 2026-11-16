<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** A separate view of the last reconciliation; never creates charges or calls a CRM. */
class CosOverpaymentReport
{
    public function remember(User $user, iterable $phases): void
    {
        $rows = collect($phases)->filter(fn ($phase) => (float) ($phase['overpaid_amount'] ?? 0) > 0.01)
            ->map(fn ($phase) => \Illuminate\Support\Arr::only($phase, [
                'source', 'project_title', 'payment_label', 'effective_preestab_amount',
                'effective_paid_amount', 'overpaid_amount',
            ]))->values()->all();
        Cache::put($this->key($user), ['rows' => $rows, 'generated_at' => now()->toIso8601String()], now()->addDay());
    }

    public function get(User $user): ?array
    {
        return Cache::get($this->key($user));
    }

    private function key(User $user): string { return 'cos.overpayments.v1.'.$user->id; }
}

<?php

namespace App\Services;

use App\Models\TlContact;
use App\Models\TlDeal;
use App\Models\TlInvoice;
use App\Models\TlProject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only payment history sourced from the Teamleader migration.
 *
 * A client can only be associated with one Teamleader contact: the strongest
 * available match wins. This is deliberate, because aggregating every contact
 * with a matching email could disclose a relative's financial information.
 */
class TeamleaderClientHistoryService
{
    public function __construct(
        private readonly TeamleaderProjectPaymentAnalyzer $paymentAnalyzer,
    ) {
    }

    public function for(User $user): array
    {
        if (! $this->migrationTablesAreAvailable()) {
            return $this->emptyHistory();
        }

        $match = $this->bestContactMatch($user);
        $contact = $match['contact'] ?? null;

        if (! $contact instanceof TlContact) {
            return $this->emptyHistory();
        }

        $deals = TlDeal::query()
            ->where('customer_type', 'contact')
            ->where('customer_id', $contact->id)
            ->orderByDesc('tl_updated_at')
            ->orderByDesc('tl_created_at')
            ->get();

        $projects = TlProject::query()
            ->where('customer_type', 'contact')
            ->where('customer_id', $contact->id)
            ->orderByDesc('tl_updated_at')
            ->orderByDesc('tl_created_at')
            ->get();

        $invoices = $this->invoicesFor($contact, $deals, $projects);
        $paidInvoices = $invoices->filter(fn (TlInvoice $invoice) => $this->invoiceIsPaid($invoice));
        $outstandingInvoices = $invoices->filter(fn (TlInvoice $invoice) => ! $this->invoiceIsPaid($invoice));

        return [
            'contact' => $contact,
            'match_labels' => $match['reasons'] ?? [],
            'deals' => $deals,
            'projects' => $projects,
            'invoices' => $invoices,
            'project_payments' => $this->paymentAnalyzer->analyzeProjects($projects),
            'summary' => [
                'invoices' => $invoices->count(),
                'paid_invoices' => $paidInvoices->count(),
                'outstanding_invoices' => $outstandingInvoices->count(),
                'paid_amounts' => $this->amountsByCurrency($paidInvoices),
                'outstanding_amounts' => $this->amountsByCurrency($outstandingInvoices),
                'first_activity_at' => $this->activityDate($invoices, false),
                'last_activity_at' => $this->activityDate($invoices, true),
            ],
        ];
    }

    private function migrationTablesAreAvailable(): bool
    {
        return Schema::hasTable('tl_contacts')
            && Schema::hasTable('tl_deals')
            && Schema::hasTable('tl_projects')
            && Schema::hasTable('tl_invoices');
    }

    private function bestContactMatch(User $user): ?array
    {
        $matches = collect();

        $remember = function (?TlContact $contact, string $reason, int $priority) use ($matches): void {
            if (! $contact) {
                return;
            }

            $key = (string) $contact->getKey();
            $existing = $matches->get($key, [
                'contact' => $contact,
                'reasons' => [],
                'priority' => 0,
            ]);

            $existing['reasons'][] = $reason;
            $existing['reasons'] = array_values(array_unique($existing['reasons']));
            $existing['priority'] = max($existing['priority'], $priority);

            $matches->put($key, $existing);
        };

        if (Schema::hasColumn('users', 'tl_id') && filled($user->getAttribute('tl_id'))) {
            $remember(
                TlContact::query()->find((string) $user->getAttribute('tl_id')),
                'ID Teamleader asociado',
                100,
            );
        }

        $passport = $this->normalizedValue($user->passport);
        if ($passport !== '') {
            TlContact::query()
                ->whereRaw('LOWER(TRIM(passport)) = ?', [$passport])
                ->get()
                ->each(fn (TlContact $contact) => $remember($contact, 'Pasaporte', 80));
        }

        $emails = collect([
            $user->email,
            $user->email_2 ?? null,
            $user->email_alternativo ?? null,
        ])
            ->map(fn ($email) => $this->normalizedValue($email))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isNotEmpty()) {
            TlContact::query()
                ->whereIn(DB::raw('LOWER(TRIM(email))'), $emails->all())
                ->get()
                ->each(fn (TlContact $contact) => $remember($contact, 'Correo', 70));
        }

        $orderedMatches = $matches
            ->sortByDesc('priority')
            ->values();
        $bestMatch = $orderedMatches->first();

        if (! $bestMatch) {
            return null;
        }

        // A duplicate primary match is a data-quality issue. Hide the
        // financial history until it is resolved instead of choosing one
        // arbitrarily and exposing another person's records.
        if ($orderedMatches->where('priority', $bestMatch['priority'])->count() > 1) {
            return null;
        }

        return $bestMatch;
    }

    private function invoicesFor(TlContact $contact, Collection $deals, Collection $projects): Collection
    {
        $dealIds = $deals->pluck('id')->filter()->values();
        $projectIds = $projects->pluck('id')->filter()->values();

        return TlInvoice::query()
            ->where(function ($query) use ($contact, $dealIds, $projectIds) {
                $query->where(function ($contactQuery) use ($contact) {
                    $contactQuery
                        ->where('customer_type', 'contact')
                        ->where('customer_id', $contact->id);
                });

                if ($dealIds->isNotEmpty()) {
                    $query->orWhereIn('deal_id', $dealIds->all());
                }

                if ($projectIds->isNotEmpty()) {
                    $query->orWhereIn('project_id', $projectIds->all());
                }
            })
            ->orderByDesc('paid_date')
            ->orderByDesc('invoice_date')
            ->orderByDesc('tl_created_at')
            ->get()
            ->unique('id')
            ->values();
    }

    private function invoiceIsPaid(TlInvoice $invoice): bool
    {
        return in_array(mb_strtolower(trim((string) $invoice->status)), ['matched', 'paid'], true);
    }

    private function amountsByCurrency(Collection $invoices): array
    {
        return $invoices
            ->groupBy(fn (TlInvoice $invoice) => $invoice->currency ?: 'Sin moneda')
            ->map(fn (Collection $group, string $currency) => [
                'currency' => $currency,
                'amount' => round($group->sum(fn (TlInvoice $invoice) => (float) $invoice->total_price_incl_tax), 2),
                'invoices' => $group->count(),
            ])
            ->values()
            ->all();
    }

    private function activityDate(Collection $invoices, bool $latest): mixed
    {
        $dates = $invoices
            ->map(fn (TlInvoice $invoice) => $invoice->paid_date
                ?? $invoice->invoice_date
                ?? $invoice->tl_created_at)
            ->filter();

        return $dates->isEmpty()
            ? null
            : ($latest
                ? $dates->sortByDesc(fn ($date) => $date->getTimestamp())->first()
                : $dates->sortBy(fn ($date) => $date->getTimestamp())->first());
    }

    private function normalizedValue(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function emptyHistory(): array
    {
        return [
            'contact' => null,
            'match_labels' => [],
            'deals' => collect(),
            'projects' => collect(),
            'invoices' => collect(),
            'project_payments' => [
                'projects' => [],
                'totals' => [
                    'projects' => 0,
                    'preestab_amount' => 0.0,
                    'paid_amount' => 0.0,
                    'balance_amount' => 0.0,
                    'overpaid_amount' => 0.0,
                ],
            ],
            'summary' => [
                'invoices' => 0,
                'paid_invoices' => 0,
                'outstanding_invoices' => 0,
                'paid_amounts' => [],
                'outstanding_amounts' => [],
                'first_activity_at' => null,
                'last_activity_at' => null,
            ],
        ];
    }
}

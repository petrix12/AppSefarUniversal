<?php

namespace App\Services;

use App\Models\Compras;
use App\Models\Negocio;
use App\Models\TlContact;
use App\Models\TlProject;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A bulk, read-only view of imported data. Does not refresh COS or synchronize payments. */
class LocalCosPortfolioService
{
    public const STATUS_LABELS = [
        'debt' => 'Con saldo conocido',
        'debt_review' => 'Con saldo conocido y revisión pendiente',
        'review' => 'Requiere revisión',
        'no_recorded_balance' => 'Sin saldo registrado',
        'no_data' => 'Sin datos suficientes',
    ];

    public function __construct(
        private DeterministicTeamleaderDealMatcher $matcher,
        private TeamleaderProjectPaymentAnalyzer $payments,
        private TeamleaderPhasePaymentService $paymentRules,
    ) {
    }

    public function generate(?int $ownerId = null): array
    {
        $clients = User::role('Cliente')->get(['id', 'name', 'email', 'passport', 'tl_id', 'email_2', 'owner_id']);
        $contacts = TlContact::get(['id', 'passport', 'email']);
        $indexes = ['id' => [], 'passport' => [], 'email' => []];
        foreach ($contacts as $contact) {
            foreach (array_keys($indexes) as $field) {
                $value = $this->identity((string) $contact->{$field});
                if ($value !== '') {
                    $indexes[$field][$value][] = (string) $contact->id;
                }
            }
        }
        unset($contacts);

        $resolved = [];
        $owners = [];
        foreach ($clients as $client) {
            $resolution = $this->contactFor($client->toArray(), $indexes);
            $resolved[$client->id] = $resolution;
            if ($resolution['id']) {
                $owners[$resolution['id']][] = $client->id;
            }
        }
        foreach ($resolved as &$resolution) {
            if ($resolution['id'] && count($owners[$resolution['id']]) > 1) {
                $resolution = ['id' => null, 'reason' => 'Contacto Teamleader compartido por varios usuarios'];
            }
        }
        unset($resolution, $indexes, $owners);

        // Keep all existing reservations, including records outside the selected owner's portfolio.
        $deals = Negocio::get()->groupBy('user_id');
        $allDeals = $deals->flatten(1);
        $reservations = $allDeals->pluck('teamleader_id')->filter()->map(fn ($id) => (string) $id)->all();
        $projectOwners = $allDeals->filter(fn ($deal) => filled($deal->teamleader_id))
            ->groupBy('teamleader_id')->map(fn ($group) => $group->pluck('user_id')->unique()->all());
        $projects = [];
        $byContact = [];
        foreach (TlProject::select('id', 'title', 'customer_id', 'customer_type', 'custom_fields', 'tl_updated_at', 'updated_at')->cursor() as $project) {
            $facts = $this->matcher->projectFacts($project->toArray());
            $facts['updated_at'] = ($project->updated_at ?? $project->tl_updated_at)?->toIso8601String();
            $projects[(string) $project->id] = $facts;
            if ($project->customer_type === 'contact') {
                $byContact[(string) $project->customer_id][] = (string) $project->id;
            }
        }
        $purchases = Compras::where(function ($query) {
            $query->where('source', TeamleaderPhasePaymentService::PURCHASE_SOURCE)
                ->orWhere(function ($paid) {
                    $paid->where('pagado', 1)->whereNotNull('deal_id')->whereNotNull('phasenum');
                });
        })->get(['id_user', 'deal_id', 'source', 'phasenum', 'monto', 'pagado', 'metadata'])->groupBy('id_user');
        $rows = [];
        $matches = [];
        foreach ($clients as $client) {
            if ($ownerId !== null && (int) $client->owner_id !== $ownerId) {
                continue;
            }
            $clientDeals = $deals->get($client->id, collect())->map->toArray()->all();
            $contactId = $resolved[$client->id]['id'];
            $ids = array_unique(array_merge(
                $contactId ? ($byContact[$contactId] ?? []) : [],
                array_filter(array_column($clientDeals, 'teamleader_id')),
            ));
            $contactIssue = $resolved[$client->id]['reason'];
            $clientProjects = array_values(array_filter(
                array_intersect_key($projects, array_fill_keys($ids, true)),
                function ($project) use ($projectOwners, $client, &$contactIssue) {
                    $assigned = $projectOwners->get($project['id'], []);
                    if (count($assigned) > 1 || ($assigned !== [] && (int) $assigned[0] !== (int) $client->id)) {
                        $contactIssue = 'Proyecto reservado para otro usuario o compartido; revisar asociación';
                        return false;
                    }
                    return true;
                },
            ));
            $decisions = $this->matcher->plan($clientDeals, $contactId ? $clientProjects : [], $reservations);
            $dealTitles = array_column($clientDeals, 'dealname', 'id');
            $projectTitles = array_column($clientProjects, 'title', 'id');
            foreach ($decisions as $decision) {
                $matches[] = [
                    'client_id' => $client->id, 'client_name' => $client->name,
                    'deal_title' => $dealTitles[$decision['deal_id']] ?? '',
                    'project_title' => $projectTitles[$decision['project_id'] ?? ''] ?? '',
                    ...$decision,
                ];
            }
            $rows[] = $this->clientRow(
                $client->toArray(), $clientDeals, $clientProjects, $decisions,
                $purchases->get($client->id, collect())->map->toArray()->all(),
                $contactIssue,
            );
        }
        usort($rows, fn ($a, $b) => $b['known_balance_eur'] <=> $a['known_balance_eur'] ?: strcmp($a['name'], $b['name']));

        return [
            'generated_at' => now()->toIso8601String(),
            'source' => 'Datos locales importados; no se han actualizado los CRM',
            'summary' => [
                'clients' => count($rows),
                'with_certificate' => count(array_filter($rows, fn ($row) => $row['has_certificate'])),
                'with_known_debt' => count(array_filter($rows, fn ($row) => $row['known_balance_eur'] > 0.01)),
                'with_certificate_and_known_debt' => count(array_filter($rows, fn ($row) => $row['has_certificate'] && $row['known_balance_eur'] > 0.01)),
                'requiring_review' => count(array_filter($rows, fn ($row) => $row['review_reasons'] !== [])),
                'automatic_matches' => count(array_filter($matches, fn ($match) => $match['status'] === 'matched')),
            ],
            'rows' => $rows,
            'associations' => $matches,
        ];
    }

    /** Explicit batch action: only local IDs and audit metadata, with a lock per project. */
    public function applyMatches(array $matches): int
    {
        $linked = 0;
        foreach ($matches as $match) {
            if ($match['status'] !== 'matched') {
                continue;
            }
            $linked += DB::transaction(function () use ($match): int {
                $client = User::whereKey($match['client_id'])->lockForUpdate()->first();
                $project = TlProject::whereKey($match['project_id'])->lockForUpdate()->first();
                $deal = Negocio::whereKey($match['deal_id'])->where('user_id', $match['client_id'])->lockForUpdate()->first();
                if (! $client || ! $project || ! $deal || filled($deal->teamleader_id)
                    || Negocio::where('teamleader_id', $match['project_id'])->exists()) {
                    return 0;
                }
                // Revalidate ownership and the complete candidate set after acquiring locks.
                $contacts = TlContact::where(function ($query) use ($client) {
                    $query->where('id', $client->tl_id ?: '__no_contact__');
                    if (filled($client->passport)) {
                        $query->orWhereRaw('LOWER(TRIM(passport)) = ?', [$this->identity($client->passport)]);
                    }
                    $emails = array_values(array_filter([$this->identity((string) $client->email), $this->identity((string) $client->email_2)]));
                    if ($emails !== []) {
                        $query->orWhereIn(DB::raw('LOWER(TRIM(email))'), $emails);
                    }
                })->get(['id', 'passport', 'email']);
                $indexes = ['id' => [], 'passport' => [], 'email' => []];
                foreach ($contacts as $contact) {
                    foreach (array_keys($indexes) as $field) {
                        $indexes[$field][$this->identity((string) $contact->{$field})][] = (string) $contact->id;
                    }
                }
                $contactId = $this->contactFor($client->toArray(), $indexes)['id'];
                if (! $contactId || $project->customer_type !== 'contact' || (string) $project->customer_id !== $contactId
                    || User::role('Cliente')->where('tl_id', $contactId)->where('id', '!=', $client->id)->exists()) {
                    return 0;
                }
                $peers = $this->matcher->canonicalDeals(Negocio::where('user_id', $client->id)->get()->map->toArray()->all());
                $projects = TlProject::where('customer_type', 'contact')->where('customer_id', $contactId)->get()
                    ->map(fn ($candidate) => $this->matcher->projectFacts($candidate->toArray()))->all();
                $reserved = Negocio::whereNotNull('teamleader_id')->pluck('teamleader_id')->all();
                $proof = collect($this->matcher->plan($peers, $projects, $reserved))->firstWhere('deal_id', $deal->id);
                if (($proof['status'] ?? '') !== 'matched' || $proof['project_id'] !== $match['project_id']) {
                    return 0;
                }
                $deal->teamleader_id = $match['project_id'];
                if (Schema::hasColumn('negocios', 'teamleader_match_reason')) {
                    $deal->teamleader_match_reason = 'Reglas sin IA: '.$proof['reason'];
                    $deal->teamleader_match_confidence = $proof['score'];
                    $deal->teamleader_matched_at = now();
                }
                $deal->save();
                return 1;
            });
        }

        return $linked;
    }

    public function writeCsv($stream, iterable $rows): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['ID', 'Cliente', 'Pasaporte', 'Correo', 'Certificado registrado', 'Fechas certificado', 'Saldo conocido EUR', 'Cobrable en portal EUR', 'Estado', 'Revisión'], ';');
        foreach ($rows as $row) {
            $cells = [$row['client_id'], $row['name'], $row['passport'], $row['email'], $row['has_certificate'] ? 'Sí' : 'No registrado', implode(' | ', $row['certificate_dates']), $row['known_balance_eur'], $row['collectible_balance_eur'], self::STATUS_LABELS[$row['status']], implode(' | ', $row['review_reasons'])];
            fputcsv($stream, array_map(fn ($cell) => preg_match('/^[\s]*[=+@\-]/u', (string) $cell) ? "'".$cell : $cell, $cells), ';');
        }
    }

    public function contactFor(array $client, array $indexes): array
    {
        foreach (['id' => ['tl_id'], 'passport' => ['passport'], 'email' => ['email', 'email_2']] as $field => $properties) {
            $ids = [];
            foreach ($properties as $property) {
                $key = $this->identity((string) ($client[$property] ?? ''));
                $ids = array_merge($ids, $key !== '' ? ($indexes[$field][$key] ?? []) : []);
            }
            $ids = array_values(array_unique($ids));
            if (count($ids) > 1) {
                return ['id' => null, 'reason' => 'Identidad Teamleader ambigua por '.$field];
            }
            if ($ids !== []) {
                return ['id' => $ids[0], 'reason' => ''];
            }
        }

        return ['id' => null, 'reason' => 'Sin contacto Teamleader identificado'];
    }

    public function clientRow(array $client, array $deals, array $projects, array $decisions = [], array $purchases = [], string $contactIssue = ''): array
    {
        $dealByProject = [];
        $issues = $contactIssue !== '' && $contactIssue !== 'Sin contacto Teamleader identificado' ? [$contactIssue] : [];
        foreach ($deals as $deal) {
            if (! empty($deal['teamleader_id'])) {
                $dealByProject[(string) $deal['teamleader_id']][] = $deal;
            }
        }
        foreach ($decisions as $decision) {
            if ($decision['status'] === 'matched') {
                foreach ($deals as $deal) {
                    if ((int) $deal['id'] === $decision['deal_id']) {
                        $dealByProject[$decision['project_id']][] = $deal;
                    }
                }
            }
        }
        $usedDeals = [];
        $cases = [];
        foreach ($projects as $project) {
            $linked = $dealByProject[$project['id']] ?? [];
            if (count($linked) > 1) {
                $issues[] = 'Varios tratos asociados al mismo proyecto';
            }
            foreach ($linked as $deal) {
                $usedDeals[(string) $deal['id']] = true;
            }
            $cases[] = $this->caseRow($project, count($linked) === 1 ? $linked[0] : null, $purchases);
        }
        $unmatched = array_values(array_filter($deals, fn ($deal) => ! isset($usedDeals[(string) $deal['id']])));
        if ($projects !== [] && $unmatched !== []) {
            // The unmatched HS case may duplicate a TL project. Never add both amounts.
            $issues[] = 'Tratos pendientes de asociación; el saldo conocido puede ser parcial';
        }
        if ($projects === []) {
            $groups = collect($unmatched)->groupBy(fn ($deal) => filled($deal['hubspot_id'] ?? null) ? 'hs:'.$deal['hubspot_id'] : 'local:'.$deal['id']);
            foreach ($groups as $group) {
                if ($group->count() > 1) {
                    $issues[] = 'Trato HubSpot duplicado; importe pendiente de revisión';
                    continue;
                }
                $deal = $group->first();
                $cases[] = $this->caseRow(['id' => '', 'title' => $deal['dealname'] ?? '', 'phase_fields' => [], 'certificate' => null], $deal, $purchases);
            }
            if ($contactIssue !== '' && $deals !== []) {
                $issues[] = $contactIssue;
            }
        }
        $dates = array_values(array_filter(array_merge(
            array_column($cases, 'certificate_date'),
            array_map(fn ($deal) => $this->certificateDate($deal['n4__certificado_descargado'] ?? null), $deals),
        )));
        $balance = 0.0;
        $collectible = 0.0;
        $hasData = false;
        foreach ($cases as $case) {
            $balance += $case['known_balance_eur'];
            $collectible += $case['collectible_balance_eur'];
            $hasData = $hasData || $case['has_payment_data'];
            $issues = array_merge($issues, $case['review_reasons']);
        }
        $issues = array_values(array_unique($issues));

        return [
            'client_id' => (int) $client['id'],
            'name' => (string) ($client['name'] ?? ''),
            'email' => (string) ($client['email'] ?? ''),
            'passport' => (string) ($client['passport'] ?? ''),
            'has_certificate' => $dates !== [],
            'certificate_dates' => array_values(array_unique($dates)),
            'known_balance_eur' => round($balance, 2),
            'collectible_balance_eur' => round($collectible, 2),
            'status' => $balance > 0.01 ? ($issues ? 'debt_review' : 'debt') : ($issues ? 'review' : ($hasData ? 'no_recorded_balance' : 'no_data')),
            'review_reasons' => $issues,
            'cases' => $cases,
        ];
    }

    private function caseRow(array $project, ?array $deal, array $purchases): array
    {
        $customFields = [];
        foreach (TeamleaderProjectPaymentAnalyzer::PHASE_FIELDS as $phase => $definition) {
            $raw = $project['phase_fields'][$phase] ?? [];
            $fields = DeterministicTeamleaderDealMatcher::DEAL_PHASES[$phase];
            $preestab = ($raw['preestab'] ?? '') !== '' && ($raw['preestab'] ?? null) !== null
                ? $raw['preestab'] : ($deal[$fields[0]] ?? null);
            $paid = $this->payments->resolvePaidRaw(
                $deal[$fields[1]] ?? null, $deal[$fields[2]] ?? null,
                $deal[$fields[3]] ?? null, $raw['paid'] ?? null,
            );
            // Reconcile confirmed legacy receipts with the mirrored phase total, never add both.
            $legacyPaidChannels = ['portal' => 0.0, 'imported' => 0.0];
            foreach ($purchases as $purchase) {
                if ($deal && (int) ($purchase['deal_id'] ?? 0) === (int) $deal['id']
                    && (int) ($purchase['phasenum'] ?? 0) === $phase && ! empty($purchase['pagado'])
                    && ! in_array($purchase['source'] ?? null, [TeamleaderPhasePaymentService::PURCHASE_SOURCE, TeamleaderPhasePaymentService::INSTALLMENT_SOURCE], true)) {
                    $channel = ($purchase['source'] ?? null) === TeamleaderPhasePaymentService::HISTORY_SOURCE ? 'imported' : 'portal';
                    $legacyPaidChannels[$channel] += (float) $purchase['monto'];
                }
            }
            $legacyPaid = max($legacyPaidChannels);
            $parsedPaid = $this->payments->parseMoneyText($paid);
            if (! $parsedPaid['requires_currency_conversion'] && ! $parsedPaid['exonerated'] && ! $parsedPaid['included']
                && $legacyPaid > $parsedPaid['total']) {
                $paid = number_format($legacyPaid, 2, '.', '').' EUR';
            }
            $customFields[] = ['definition' => ['id' => $definition['preestab']['id']], 'value' => $preestab];
            $customFields[] = ['definition' => ['id' => $definition['paid']['id']], 'value' => $paid];
        }
        $analysis = $this->payments->analyzeProject(new TlProject(['id' => $project['id'], 'custom_fields' => $customFields]));
        $balance = 0.0;
        $collectible = 0.0;
        $hasData = false;
        $issues = [];
        foreach ($analysis['phases'] as $phase) {
            $hidden = false;
            $hasData = $hasData || $phase['status'] !== 'empty';
            if ($phase['needs_review']) {
                $labels = [
                    'currency_conversion_required' => 'Moneda pendiente de conversión a EUR',
                    'unreadable_preestablished_amount' => 'Importe preestablecido ilegible',
                    'unreadable_paid_amount' => 'Importe pagado ilegible',
                    'paid_exceeds_preestablished_amount' => 'Sobrepago pendiente de conciliación',
                    'payment_without_preestablished_amount' => 'Pago sin importe preestablecido',
                ];
                $issues[] = $phase['payment_label'].': '.implode(', ', array_map(fn ($reason) => $labels[$reason] ?? 'Importe pendiente de revisión', $phase['review_reasons']));
                continue;
            }
            // A successful portal installment may be newer than the imported project.
            foreach ($purchases as $purchase) {
                $metadata = $purchase['metadata'] ?? [];
                if ($project['id'] !== '' && (string) ($metadata['teamleader_project_id'] ?? '') === $project['id']
                    && (int) ($metadata['phase'] ?? 0) === $phase['phase']) {
                    $hidden = $hidden || ! empty($metadata['hidden_from_client']);
                    if (! empty($metadata['last_portal_installment_id'])
                        && $phase['effective_paid_amount'] <= (float) ($metadata['paid_amount_at_sync'] ?? 0) + 0.01) {
                        $phase['balance_amount'] = min($phase['balance_amount'], (float) $purchase['monto']);
                    }
                }
            }
            $summary = $this->paymentRules->paymentSummaryRow($phase);
            $balance += $phase['balance_amount'];
            $collectible += $hidden ? 0 : $summary['collectible_balance_amount'];
        }
        $rawCertificate = ($project['certificate'] ?? null) ?: ($deal['n4__certificado_descargado'] ?? null);
        $date = $this->certificateDate($rawCertificate);
        if (filled($rawCertificate) && $date === null) {
            $issues[] = 'Fecha de certificado inválida';
        }

        return [
            'teamleader_id' => $project['id'],
            'deal_id' => $deal['id'] ?? null,
            'title' => $project['title'],
            'certificate_date' => $date,
            'known_balance_eur' => round($balance, 2),
            'collectible_balance_eur' => round($collectible, 2),
            'has_payment_data' => $hasData,
            'review_reasons' => $issues,
            'imported_at' => $project['updated_at'] ?? ($deal['updated_at'] ?? null),
        ];
    }

    private function identity(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function certificateDate(mixed $value): ?string
    {
        if (! is_scalar($value) || trim((string) $value) === '') {
            return null;
        }
        $text = trim((string) $value);
        if (preg_match('/^\d{10}(?:\d{3})?$/', $text)) {
            $text = Carbon::createFromTimestampUTC((int) $text / (strlen($text) === 13 ? 1000 : 1))->toDateString();
        }
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:$|[T ])/D', $text, $parts)
            || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            || (int) $parts[1] < 1900 || substr($text, 0, 10) > now()->toDateString()) {
            return null;
        }

        return substr($text, 0, 10);
    }
}

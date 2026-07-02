<?php

namespace App\Services;

use App\Models\Servicio;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BancaOnlineCatalog
{
    public function category(): string
    {
        return config('banca_online.category', 'banca_online_2026');
    }

    public function source(): string
    {
        return config('banca_online.source', 'banca_online_2026');
    }

    public function plans(): array
    {
        return config('banca_online.plans', []);
    }

    public function countries(): array
    {
        return config('banca_online.countries', []);
    }

    public function packages(): array
    {
        return config('banca_online.packages', []);
    }

    public function installmentPeriodDefaults(): array
    {
        return config('banca_online.installments.periods', []);
    }

    public function installmentInitialRuleDefaults(): array
    {
        return config('banca_online.installments.initial_rules', []);
    }

    public function packageDefaults(?string $planSlug, string $tierSlug): array
    {
        $tier = $this->packages()[$tierSlug] ?? [];
        $planPackage = $this->plan($planSlug)['packages'][$tierSlug] ?? [];

        return array_merge($tier, $planPackage);
    }

    public function publicCountries(): array
    {
        return array_filter(
            $this->countries(),
            fn (array $country) => (bool) ($country['public_enabled'] ?? false)
        );
    }

    public function isCountryPublic(string $countrySlug): bool
    {
        $countrySlug = $this->normalizeCountry($countrySlug);

        return array_key_exists($countrySlug, $this->publicCountries());
    }

    public function plan(?string $slug): ?array
    {
        $plans = $this->plans();

        return $plans[$slug] ?? null;
    }

    public function plansForCountry(?string $country): array
    {
        $countrySlug = $this->normalizeCountry($country);

        return array_filter($this->plans(), function (array $plan) use ($countrySlug) {
            $scope = $plan['service_scope'] ?? [];

            return ($plan['enabled'] ?? true) && (empty($scope) || in_array($countrySlug, $scope, true));
        });
    }

    public function planForCountry(?string $country, ?string $planSlug): ?array
    {
        $plans = $this->plansForCountry($country);

        return $plans[$planSlug] ?? null;
    }

    public function country(?string $slug): ?array
    {
        $countries = $this->countries();
        $slug = $this->normalizeCountry($slug);

        return $countries[$slug] ?? null;
    }

    public function normalizeCountry(?string $country): string
    {
        $country = Str::lower(Str::ascii((string) $country));
        $country = str_replace(['_', ' '], '-', $country);

        return match ($country) {
            'es', 'espana', 'espana-sefardi', 'spain' => 'espana',
            'pt', 'portugal', 'portuguesa', 'portuguesa-sefardi' => 'portugal',
            'it', 'italia', 'italiana', 'italy' => 'italia',
            default => 'espana',
        };
    }

    public function serviceNameForCountry(?string $country): string
    {
        return $this->country($country)['service_name'] ?? 'Española Sefardi';
    }

    public function stripeAccountForCountry(?string $country): string
    {
        return $this->country($country)['stripe_account'] ?? 'default';
    }

    public function servicesForPlan(string $countrySlug, string $planSlug, bool $activeOnly = true): Collection
    {
        return $this->recordsForPlan($countrySlug, $planSlug, $activeOnly)
            ->filter(fn (Servicio $servicio) => $this->recordType($servicio) === 'component')
            ->values();
    }

    public function packagesForPlan(string $countrySlug, string $planSlug, bool $activeOnly = true): Collection
    {
        return $this->recordsForPlan($countrySlug, $planSlug, $activeOnly)
            ->filter(fn (Servicio $servicio) => $this->recordType($servicio) === 'package')
            ->sortBy(fn (Servicio $servicio) => (int) ($this->metadata($servicio)['tier_order'] ?? $servicio->orden))
            ->values();
    }

    public function packageForTier(string $countrySlug, string $planSlug, string $tierSlug, bool $activeOnly = true): ?Servicio
    {
        return $this->packagesForPlan($countrySlug, $planSlug, $activeOnly)
            ->first(fn (Servicio $servicio) => ($this->metadata($servicio)['tier_slug'] ?? null) === $tierSlug);
    }

    public function packageComponents(Servicio $package, bool $activeOnly = true): Collection
    {
        $metadata = $this->metadata($package);
        $componentIds = collect($metadata['component_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique();

        return $this->servicesForPlan(
            $this->countrySlugForService($package),
            (string) ($metadata['plan_slug'] ?? ''),
            $activeOnly
        )->filter(fn (Servicio $servicio) => $componentIds->contains((int) $servicio->id))->values();
    }

    public function packageFeatures(Servicio $package): Collection
    {
        return collect($this->metadata($package)['features'] ?? [])
            ->map(function ($feature) {
                if (is_array($feature)) {
                    $name = trim((string) ($feature['name'] ?? $feature['title'] ?? ''));

                    return [
                        'name' => $name,
                        'description' => trim((string) ($feature['description'] ?? '')) ?: null,
                    ];
                }

                return [
                    'name' => trim((string) $feature),
                    'description' => null,
                ];
            })
            ->filter(fn (array $feature) => $feature['name'] !== '')
            ->values();
    }

    public function packageDisplayItems(Servicio $package, bool $activeOnly = true): Collection
    {
        $features = $this->packageFeatures($package);

        if ($features->isNotEmpty()) {
            return $features;
        }

        $showPrices = $this->showsComponentPrices($package);

        return $this->packageComponents($package, $activeOnly)
            ->map(function (Servicio $component) use ($package, $showPrices) {
                $item = [
                    'id' => $component->id,
                    'name' => $component->nombre,
                    'description' => $component->descripcion_publica,
                ];

                if ($showPrices) {
                    $item['price'] = $this->packageComponentPrice($package, $component);
                }

                return $item;
            })
            ->values();
    }

    public function showsComponentPrices(Servicio $package): bool
    {
        return (bool) ($this->metadata($package)['show_component_prices'] ?? true);
    }

    public function usesComponentCatalog(Servicio $package): bool
    {
        return collect($this->metadata($package)['component_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->isNotEmpty();
    }

    public function packageComponentPrice(Servicio $package, Servicio $component): float
    {
        return max(0, (float) ($component->precio ?? 0));
    }

    public function packageSubtotal(Servicio $package): float
    {
        $metadata = $this->metadata($package);

        if (array_key_exists('list_price', $metadata)) {
            return round(max(0, (float) $metadata['list_price']), 2);
        }

        return round((float) $this->packageComponents($package)
            ->sum(fn (Servicio $component) => $this->packageComponentPrice($package, $component)), 2);
    }

    public function packageDiscount(Servicio $package): float
    {
        $metadata = $this->metadata($package);
        $subtotal = $this->packageSubtotal($package);

        if (array_key_exists('price', $metadata)) {
            return round(max(0, $subtotal - max(0, (float) $metadata['price'])), 2);
        }

        if (array_key_exists('saving', $metadata)) {
            return round(min($subtotal, max(0, (float) $metadata['saving'])), 2);
        }

        $value = max(0, (float) ($metadata['discount_value'] ?? 0));

        if (($metadata['discount_type'] ?? 'percentage') === 'fixed') {
            return round(min($subtotal, $value), 2);
        }

        return round($subtotal * min(100, $value) / 100, 2);
    }

    public function packageTotal(Servicio $package): float
    {
        $metadata = $this->metadata($package);

        if (array_key_exists('price', $metadata)) {
            return round(max(0, (float) $metadata['price']), 2);
        }

        return round(max(0, $this->packageSubtotal($package) - $this->packageDiscount($package)), 2);
    }

    public function packageInstallmentPeriods(Servicio $package): array
    {
        $metadataPeriods = $this->metadata($package)['installment_periods'] ?? [];

        return collect($this->installmentPeriodDefaults())
            ->map(function (array $defaults, string $slug) use ($metadataPeriods) {
                $period = array_merge($defaults, $metadataPeriods[$slug] ?? []);

                return [
                    'slug' => $slug,
                    'label' => $period['label'] ?? ucfirst($slug),
                    'plural_label' => $period['plural_label'] ?? Str::lower($period['label'] ?? $slug),
                    'enabled' => (bool) ($period['enabled'] ?? false),
                    'surcharge_percent' => round(max(0, (float) ($period['surcharge_percent'] ?? 0)), 2),
                    'stripe_interval' => $period['stripe_interval'] ?? ($defaults['stripe_interval'] ?? 'month'),
                    'stripe_interval_count' => max(1, (int) ($period['stripe_interval_count'] ?? ($defaults['stripe_interval_count'] ?? 1))),
                    'start_after_days' => isset($period['start_after_days']) ? (int) $period['start_after_days'] : null,
                ];
            })
            ->all();
    }

    public function enabledInstallmentPeriods(Servicio $package): array
    {
        return array_filter(
            $this->packageInstallmentPeriods($package),
            fn (array $period) => (bool) ($period['enabled'] ?? false)
        );
    }

    public function packageInstallmentRules(Servicio $package): array
    {
        $metadata = $this->metadata($package);
        $rules = $metadata['installment_initial_rules'] ?? $this->installmentInitialRuleDefaults();

        return collect($rules)
            ->map(fn (array $rule) => [
                'min_initial_percent' => round(max(0, min(100, (float) ($rule['min_initial_percent'] ?? 0))), 2),
                'max_installments' => max(1, (int) ($rule['max_installments'] ?? 1)),
            ])
            ->filter(fn (array $rule) => $rule['min_initial_percent'] > 0 && $rule['max_installments'] > 0)
            ->sortBy('min_initial_percent')
            ->values()
            ->all();
    }

    public function packageInstallmentSettings(Servicio $package): array
    {
        $total = $this->packageTotal($package);
        $periods = $this->enabledInstallmentPeriods($package);
        $rules = $this->packageInstallmentRules($package);
        $minInitialPercent = collect($rules)->min('min_initial_percent') ?: 100;
        $maxInstallments = collect($rules)->max('max_installments') ?: 1;

        return [
            'enabled' => $total > 0 && ! empty($periods) && ! empty($rules) && $minInitialPercent < 100,
            'min_initial_percent' => (float) $minInitialPercent,
            'max_initial_percent' => 99.0,
            'max_installments' => (int) $maxInstallments,
            'periods' => array_values($periods),
            'rules' => $rules,
        ];
    }

    public function maxInstallmentsForInitialPercent(Servicio $package, float $initialPercent): int
    {
        $matched = collect($this->packageInstallmentRules($package))
            ->filter(fn (array $rule) => $initialPercent >= (float) $rule['min_initial_percent'])
            ->sortByDesc('min_initial_percent')
            ->first();

        return max(1, (int) ($matched['max_installments'] ?? 1));
    }

    public function packageInstallmentQuote(Servicio $package, ?string $periodSlug = null, ?float $initialPercent = null, ?int $installments = null): array
    {
        $settings = $this->packageInstallmentSettings($package);
        $total = $this->packageTotal($package);
        $periods = collect($settings['periods']);
        $period = $periods->firstWhere('slug', $periodSlug) ?? $periods->first();
        $initialPercent = round(max(
            (float) $settings['min_initial_percent'],
            min(99, (float) ($initialPercent ?: $settings['min_initial_percent']))
        ), 2);
        $maxCount = $this->maxInstallmentsForInitialPercent($package, $initialPercent);
        $count = max(1, min($maxCount, (int) ($installments ?: $maxCount)));
        $initial = round($total * $initialPercent / 100, 2);
        $remaining = round(max(0, $total - $initial), 2);
        $surchargePercent = $period ? (float) ($period['surcharge_percent'] ?? 0) : 0.0;
        $surchargeAmount = round($remaining * $surchargePercent / 100, 2);
        $financedAmount = round($remaining + $surchargeAmount, 2);
        $installmentAmount = $count > 0 ? round($financedAmount / $count, 2) : 0.0;

        return array_merge($settings, [
            'selected_count' => $count,
            'max_count' => $maxCount,
            'contract_total' => round($initial + $financedAmount, 2),
            'base_total' => $total,
            'amount_due_now' => $initial,
            'remaining_amount' => $remaining,
            'financed_amount' => $financedAmount,
            'surcharge_percent' => $surchargePercent,
            'surcharge_amount' => $surchargeAmount,
            'installment_amount' => $installmentAmount,
            'initial_percent' => $initialPercent,
            'period' => $period,
        ]);
    }

    public function packageIsReady(Servicio $package): bool
    {
        return (bool) $package->activo
            && $this->packageDisplayItems($package)->isNotEmpty()
            && $this->packageTotal($package) > 0;
    }

    public function recordType(Servicio $servicio): string
    {
        return (string) ($this->metadata($servicio)['record_type'] ?? 'component');
    }

    private function recordsForPlan(string $countrySlug, string $planSlug, bool $activeOnly = true): Collection
    {
        $countrySlug = $this->normalizeCountry($countrySlug);

        return Servicio::where('categoria', $this->category())
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->filter(function (Servicio $servicio) use ($countrySlug, $planSlug, $activeOnly) {
                $metadata = $this->metadata($servicio);

                return ($metadata['plan_slug'] ?? null) === $planSlug
                    && $this->countrySlugForService($servicio) === $countrySlug
                    && (! $activeOnly || (bool) ($servicio->activo ?? true));
            })
            ->values();
    }

    public function groupedServicesForPlan(string $countrySlug, string $planSlug, bool $activeOnly = true): Collection
    {
        return $this->servicesForPlan($countrySlug, $planSlug, $activeOnly)
            ->groupBy(fn (Servicio $servicio) => $this->metadata($servicio)['section'] ?? 'General')
            ->sortBy(function (Collection $items) {
                $first = $items->first();

                return (int) ($this->metadata($first)['section_order'] ?? 0);
            });
    }

    public function selectedServices(string $countrySlug, string $planSlug, array $selectedIds): Collection
    {
        $selectedIds = collect($selectedIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $services = $this->servicesForPlan($countrySlug, $planSlug);
        $selected = collect();
        $selectedGroups = [];

        foreach ($services as $servicio) {
            $metadata = $this->metadata($servicio);
            $isRequired = (bool) ($metadata['required'] ?? false);
            $isLocked = (bool) ($metadata['locked'] ?? false);
            $isSelected = $selectedIds->contains((int) $servicio->id);
            $group = $metadata['group'] ?? null;

            if (! $isRequired && ! $isLocked && ! $isSelected) {
                continue;
            }

            if ($group) {
                if (! $isRequired && ! $isLocked && isset($selectedGroups[$group])) {
                    continue;
                }

                $selectedGroups[$group] = true;
            }

            $selected->push($servicio);
        }

        return $selected->values();
    }

    public function checkoutTotal(Collection $services): float
    {
        return (float) $services->sum(fn (Servicio $servicio) => (float) $servicio->precio);
    }

    public function syncBaseCatalog(): array
    {
        $created = 0;
        $updated = 0;

        foreach ($this->countries() as $countrySlug => $country) {
            if (! (bool) ($country['seed_catalog'] ?? true)) {
                continue;
            }

            foreach ($this->plansForCountry($countrySlug) as $planSlug => $plan) {
                foreach (($plan['sections'] ?? []) as $sectionIndex => $section) {
                    foreach (($section['items'] ?? []) as $itemIndex => $item) {
                        $itemScope = $item['service_scope'] ?? [];

                        if (! empty($itemScope) && ! in_array($countrySlug, $itemScope, true)) {
                            continue;
                        }

                        $idHubspot = $this->hubspotId($countrySlug, $planSlug, $item['slug']);
                        $servicio = Servicio::firstOrNew(['id_hubspot' => $idHubspot]);
                        $exists = $servicio->exists;
                        $oldMetadata = $this->metadata($servicio);

                        $metadata = array_merge($this->baseMetadata($countrySlug, $country, $planSlug, $plan, $section, $sectionIndex, $item), [
                            'required' => array_key_exists('required', $oldMetadata) ? (bool) $oldMetadata['required'] : (bool) ($item['required'] ?? false),
                            'default_selected' => array_key_exists('default_selected', $oldMetadata) ? (bool) $oldMetadata['default_selected'] : (bool) ($item['default_selected'] ?? false),
                            'locked' => array_key_exists('locked', $oldMetadata) ? (bool) $oldMetadata['locked'] : (bool) ($item['required'] ?? false),
                            'group' => $oldMetadata['group'] ?? ($item['group'] ?? null),
                        ]);

                        $servicio->fill([
                            'nombre' => $servicio->nombre ?: $item['name'],
                            'precio' => $exists ? (int) $servicio->precio : ($countrySlug === 'espana' ? (int) ($item['price'] ?? 0) : 0),
                            'categoria' => $this->category(),
                            'tipo' => $servicio->tipo ?: 'servicio',
                            'descripcion_publica' => $servicio->descripcion_publica ?: ($section['summary'] ?? $plan['summary'] ?? null),
                            'activo' => $exists ? (bool) $servicio->activo : true,
                            'visible_cliente' => false,
                            'moneda' => $servicio->moneda ?: 'EUR',
                            'orden' => $exists ? (int) $servicio->orden : (($sectionIndex + 1) * 100 + $itemIndex + 1),
                            'metadata' => $metadata,
                        ]);

                        $servicio->save();

                        $exists ? $updated++ : $created++;
                    }
                }

                foreach ($this->packages() as $tierSlug => $tier) {
                    $planPackageDefaults = $plan['packages'][$tierSlug] ?? [];
                    $hasPlanPackageDefaults = ! empty($planPackageDefaults);
                    $defaults = array_merge($tier, $planPackageDefaults);
                    $idHubspot = $this->packageHubspotId($countrySlug, $planSlug, $tierSlug);
                    $servicio = Servicio::firstOrNew(['id_hubspot' => $idHubspot]);
                    $exists = $servicio->exists;
                    $oldMetadata = $this->metadata($servicio);
                    $genericTitle = $tier['title'] ?? ucfirst($tierSlug);
                    $defaultTitle = $defaults['title'] ?? $genericTitle;
                    $genericSummary = $tier['summary'] ?? null;
                    $defaultSummary = $defaults['summary'] ?? $genericSummary;
                    $cmsManaged = (bool) ($oldMetadata['cms_managed'] ?? false);
                    $metadata = array_merge($oldMetadata, [
                        'banca_online' => true,
                        'record_type' => 'package',
                        'country_slug' => $countrySlug,
                        'country_label' => $country['label'] ?? $countrySlug,
                        'requested_service' => $country['service_name'] ?? null,
                        'plan_slug' => $planSlug,
                        'plan_title' => $plan['title'] ?? $planSlug,
                        'plan_short_title' => $plan['short_title'] ?? ($plan['title'] ?? $planSlug),
                        'tier_slug' => $tierSlug,
                        'tier_title' => $exists ? ($oldMetadata['tier_title'] ?? $defaultTitle) : $defaultTitle,
                        'tier_summary' => $exists ? ($oldMetadata['tier_summary'] ?? $defaultSummary) : $defaultSummary,
                        'tier_order' => (int) ($defaults['order'] ?? $tier['order'] ?? 0),
                        'recommended' => $exists
                            ? (bool) ($oldMetadata['recommended'] ?? ($defaults['recommended'] ?? $tier['recommended'] ?? false))
                            : (bool) ($defaults['recommended'] ?? $tier['recommended'] ?? false),
                        'component_ids' => $exists
                            ? ($oldMetadata['component_ids'] ?? ($defaults['component_ids'] ?? []))
                            : ($defaults['component_ids'] ?? []),
                        'discount_type' => $exists
                            ? ($oldMetadata['discount_type'] ?? ($defaults['discount_type'] ?? 'percentage'))
                            : ($defaults['discount_type'] ?? 'percentage'),
                        'discount_value' => $exists
                            ? (float) ($oldMetadata['discount_value'] ?? ($defaults['discount_value'] ?? 0))
                            : (float) ($defaults['discount_value'] ?? 0),
                    ]);

                    if ($hasPlanPackageDefaults && ! $cmsManaged) {
                        $metadata['features'] = $defaults['features'] ?? [];
                        $metadata['show_component_prices'] = (bool) ($defaults['show_component_prices'] ?? true);

                        foreach (['list_price', 'price', 'saving'] as $pricingKey) {
                            if (! array_key_exists($pricingKey, $defaults)) {
                                continue;
                            }

                            $metadata[$pricingKey] = (float) $defaults[$pricingKey];
                        }
                    }

                    if (! $hasPlanPackageDefaults) {
                        unset(
                            $metadata['features'],
                            $metadata['show_component_prices'],
                            $metadata['list_price'],
                            $metadata['price'],
                            $metadata['saving']
                        );
                    }

                    unset($metadata['pricing_mode'], $metadata['component_prices']);

                    $currentName = trim((string) $servicio->nombre);
                    $packageName = (
                        ! $exists
                        || $currentName === ''
                        || (! $cmsManaged && ($currentName === $genericTitle || $currentName === ($oldMetadata['tier_title'] ?? null)))
                    )
                        ? $defaultTitle
                        : $currentName;

                    $currentDescription = trim((string) $servicio->descripcion_publica);
                    $packageDescription = (
                        ! $exists
                        || (! $cmsManaged && ($currentDescription === '' || $currentDescription === $genericSummary))
                    )
                        ? $defaultSummary
                        : $currentDescription;

                    $servicio->fill([
                        'nombre' => $packageName,
                        'precio' => $exists ? (int) $servicio->precio : 0,
                        'categoria' => $this->category(),
                        'tipo' => $servicio->tipo ?: 'servicio',
                        'descripcion_publica' => $packageDescription,
                        'activo' => $exists ? (bool) $servicio->activo : true,
                        'visible_cliente' => false,
                        'moneda' => $servicio->moneda ?: 'EUR',
                        'orden' => $exists ? (int) $servicio->orden : (9000 + (int) ($defaults['order'] ?? $tier['order'] ?? 0)),
                        'metadata' => $metadata,
                    ]);
                    $servicio->save();

                    $exists ? $updated++ : $created++;
                }
            }
        }

        return compact('created', 'updated');
    }

    public function purgeSeededCatalog(): array
    {
        return DB::transaction(function () {
            $deleted = Servicio::query()
                ->where(function ($query) {
                    $query
                        ->where('categoria', $this->category())
                        ->orWhere('id_hubspot', 'like', 'BO2026-%');
                })
                ->delete();

            return compact('deleted');
        });
    }

    public function updateServiceMetadata(Servicio $servicio, array $values): void
    {
        $metadata = $this->metadata($servicio);

        foreach (['required', 'default_selected', 'locked'] as $booleanKey) {
            if (array_key_exists($booleanKey, $values)) {
                $metadata[$booleanKey] = (bool) $values[$booleanKey];
            }
        }

        if (array_key_exists('group', $values)) {
            $metadata['group'] = trim((string) $values['group']) ?: null;
        }

        $servicio->metadata = $metadata;
        $servicio->save();
    }

    public function metadata(?Servicio $servicio): array
    {
        if (! $servicio) {
            return [];
        }

        return is_array($servicio->metadata) ? $servicio->metadata : [];
    }

    public function countrySlugForService(Servicio $servicio): string
    {
        $metadata = $this->metadata($servicio);

        if (! empty($metadata['country_slug'])) {
            return $this->normalizeCountry($metadata['country_slug']);
        }

        if (Str::startsWith($servicio->id_hubspot, 'BO2026-PORTUGAL-')) {
            return 'portugal';
        }

        if (Str::startsWith($servicio->id_hubspot, 'BO2026-ITALIA-')) {
            return 'italia';
        }

        return 'espana';
    }

    public function expectedItemsForCountry(string $countrySlug): int
    {
        $countrySlug = $this->normalizeCountry($countrySlug);
        $country = $this->country($countrySlug);

        if (! (bool) ($country['seed_catalog'] ?? true)) {
            return 0;
        }

        return collect($this->plansForCountry($countrySlug))->sum(function (array $plan) use ($countrySlug) {
            return collect($plan['sections'] ?? [])->sum(function (array $section) use ($countrySlug) {
                return collect($section['items'] ?? [])->filter(function (array $item) use ($countrySlug) {
                    $scope = $item['service_scope'] ?? [];

                    return empty($scope) || in_array($countrySlug, $scope, true);
                })->count();
            }) + count($this->packages());
        });
    }

    public function createCustomService(string $countrySlug, string $planSlug, array $values): Servicio
    {
        $countrySlug = $this->normalizeCountry($countrySlug);
        $country = $this->country($countrySlug);
        $plan = $this->planForCountry($countrySlug, $planSlug);

        abort_unless($country && $plan, 404);

        $itemSlug = Str::slug($values['nombre']);
        $idHubspot = $this->hubspotId($countrySlug, $planSlug, $itemSlug);
        $suffix = 2;

        while (Servicio::where('id_hubspot', $idHubspot)->exists()) {
            $idHubspot = $this->hubspotId($countrySlug, $planSlug, $itemSlug . '-' . $suffix);
            $suffix++;
        }

        $order = (int) ($values['orden'] ?? 0);

        if ($order === 0) {
            $order = ((int) $this->servicesForPlan($countrySlug, $planSlug, false)->max('orden')) + 1;
        }

        return Servicio::create([
            'id_hubspot' => $idHubspot,
            'nombre' => trim($values['nombre']),
            'precio' => (int) ($values['precio'] ?? 0),
            'categoria' => $this->category(),
            'tipo' => 'servicio',
            'descripcion_publica' => $values['descripcion_publica'] ?? null,
            'activo' => (bool) ($values['activo'] ?? true),
            'visible_cliente' => false,
            'moneda' => 'EUR',
            'orden' => $order,
            'metadata' => [
                'banca_online' => true,
                'record_type' => 'component',
                'custom_item' => true,
                'country_slug' => $countrySlug,
                'country_label' => $country['label'] ?? $countrySlug,
                'requested_service' => $country['service_name'] ?? null,
                'plan_slug' => $planSlug,
                'plan_title' => $plan['title'] ?? $planSlug,
                'plan_short_title' => $plan['short_title'] ?? ($plan['title'] ?? $planSlug),
                'section' => trim($values['section'] ?? '') ?: 'General',
                'section_summary' => null,
                'section_order' => $order,
                'item_slug' => $itemSlug,
                'required' => (bool) ($values['required'] ?? false),
                'default_selected' => (bool) ($values['default_selected'] ?? false),
                'locked' => (bool) ($values['locked'] ?? false),
                'group' => trim($values['group'] ?? '') ?: null,
            ],
        ]);
    }

    public function hubspotId(string $countrySlug, string $planSlug, string $itemSlug): string
    {
        $countrySlug = $this->normalizeCountry($countrySlug);
        $prefix = $countrySlug === 'espana' ? '' : Str::upper($countrySlug) . '-';

        return 'BO2026-' . $prefix . Str::upper(Str::slug($planSlug . '-' . $itemSlug));
    }

    public function packageHubspotId(string $countrySlug, string $planSlug, string $tierSlug): string
    {
        return $this->hubspotId($countrySlug, $planSlug, 'paquete-' . $tierSlug);
    }

    private function baseMetadata(string $countrySlug, array $country, string $planSlug, array $plan, array $section, int $sectionIndex, array $item): array
    {
        return [
            'banca_online' => true,
            'record_type' => 'component',
            'country_slug' => $countrySlug,
            'country_label' => $country['label'] ?? $countrySlug,
            'requested_service' => $country['service_name'] ?? null,
            'plan_slug' => $planSlug,
            'plan_title' => $plan['title'] ?? $planSlug,
            'plan_short_title' => $plan['short_title'] ?? ($plan['title'] ?? $planSlug),
            'section' => $section['title'] ?? 'General',
            'section_summary' => $section['summary'] ?? null,
            'section_order' => ($sectionIndex + 1) * 100,
            'item_slug' => $item['slug'],
        ];
    }
}

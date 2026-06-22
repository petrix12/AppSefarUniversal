<?php

namespace App\Services;

use App\Models\Servicio;
use Illuminate\Support\Collection;
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

            return empty($scope) || in_array($countrySlug, $scope, true);
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
        $countrySlug = $this->normalizeCountry($countrySlug);

        return Servicio::where('categoria', $this->category())
            ->orderBy('orden')
            ->orderBy('id')
            ->get()
            ->filter(function (Servicio $servicio) use ($countrySlug, $planSlug, $activeOnly) {
                $metadata = $this->metadata($servicio);

                if (($metadata['plan_slug'] ?? null) !== $planSlug) {
                    return false;
                }

                if ($this->countrySlugForService($servicio) !== $countrySlug) {
                    return false;
                }

                return ! $activeOnly || (bool) ($servicio->activo ?? true);
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
                            'tipov' => (int) ($servicio->tipov ?? 0),
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
            }
        }

        return compact('created', 'updated');
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
            });
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
            'tipov' => 0,
            'categoria' => $this->category(),
            'tipo' => 'servicio',
            'descripcion_publica' => $values['descripcion_publica'] ?? null,
            'activo' => (bool) ($values['activo'] ?? true),
            'visible_cliente' => false,
            'moneda' => 'EUR',
            'orden' => $order,
            'metadata' => [
                'banca_online' => true,
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

    private function baseMetadata(string $countrySlug, array $country, string $planSlug, array $plan, array $section, int $sectionIndex, array $item): array
    {
        return [
            'banca_online' => true,
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

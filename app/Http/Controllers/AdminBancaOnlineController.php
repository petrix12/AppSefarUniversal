<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Services\BancaOnlineCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBancaOnlineController extends Controller
{
    public function __construct(private BancaOnlineCatalog $catalog)
    {
    }

    public function index()
    {
        $request = request();
        $countrySlug = $this->catalog->normalizeCountry($request->query('pais'));
        $country = $this->catalog->country($countrySlug);
        $countries = $this->catalog->countries();
        $plans = $this->catalog->plansForCountry($countrySlug);
        $planSlug = (string) $request->query('plan', array_key_first($plans));

        if (! array_key_exists($planSlug, $plans)) {
            $planSlug = array_key_first($plans);
        }

        $allServices = Servicio::where('categoria', $this->catalog->category())
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        $services = $this->catalog->servicesForPlan($countrySlug, $planSlug, false)
            ->groupBy(fn (Servicio $servicio) => $this->catalog->metadata($servicio)['section'] ?? 'General');

        $packages = $this->catalog->packagesForPlan($countrySlug, $planSlug, false);
        $tiers = $this->catalog->packages();
        $packageSlug = (string) $request->query('modalidad', $request->query('paquete', array_key_first($tiers)));

        if (! array_key_exists($packageSlug, $tiers)) {
            $packageSlug = array_key_first($tiers);
        }

        $package = $this->catalog->packageForTier($countrySlug, $planSlug, $packageSlug, false);
        $planSlugs = array_keys($plans);

        $countryCounts = $allServices
            ->filter(fn (Servicio $servicio) => in_array($this->catalog->metadata($servicio)['plan_slug'] ?? null, $planSlugs, true))
            ->groupBy(fn (Servicio $servicio) => $this->catalog->countrySlugForService($servicio))
            ->map->count();

        $expected = $this->catalog->expectedItemsForCountry($countrySlug);
        $current = (int) ($countryCounts[$countrySlug] ?? 0);
        $planCurrent = $services->flatten(1)->count();

        return view('admin.banca-online.index', compact(
            'countries',
            'countrySlug',
            'country',
            'countryCounts',
            'plans',
            'planSlug',
            'services',
            'packages',
            'tiers',
            'packageSlug',
            'package',
            'expected',
            'current',
            'planCurrent'
        ));
    }

    public function sync(Request $request)
    {
        $result = $this->catalog->syncBaseCatalog();
        $countrySlug = $this->catalog->normalizeCountry($request->input('pais'));
        $planSlug = (string) $request->input('plan', 'solicitud-estrategica');

        return redirect()
            ->route('admin.banca-online.index', ['pais' => $countrySlug, 'plan' => $planSlug])
            ->with('success', "Catalogo sincronizado. Creados: {$result['created']}. Actualizados: {$result['updated']}.");
    }

    public function update(Request $request, Servicio $servicio)
    {
        abort_unless($servicio->categoria === $this->catalog->category(), 404);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'precio' => ['required', 'integer', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'activo' => ['nullable', 'boolean'],
            'required' => ['nullable', 'boolean'],
            'default_selected' => ['nullable', 'boolean'],
            'locked' => ['nullable', 'boolean'],
            'group' => ['nullable', 'string', 'max:255'],
            'descripcion_publica' => ['nullable', 'string'],
            'tipo' => ['required', Rule::in(['servicio', 'cos_fase', 'consulta', 'miscelaneo'])],
        ]);

        $servicio->fill([
            'nombre' => trim($data['nombre']),
            'precio' => (int) $data['precio'],
            'orden' => (int) ($data['orden'] ?? 0),
            'moneda' => strtoupper($data['moneda'] ?? 'EUR'),
            'activo' => $request->boolean('activo'),
            'descripcion_publica' => $data['descripcion_publica'] ?? null,
            'tipo' => $data['tipo'],
        ]);
        $servicio->save();

        $this->catalog->updateServiceMetadata($servicio, [
            'required' => $request->boolean('required'),
            'default_selected' => $request->boolean('default_selected'),
            'locked' => $request->boolean('locked'),
            'group' => $data['group'] ?? null,
        ]);

        $metadata = $this->catalog->metadata($servicio);

        return redirect()
            ->route('admin.banca-online.index', [
                'pais' => $metadata['country_slug'] ?? 'espana',
                'plan' => $metadata['plan_slug'] ?? 'solicitud-estrategica',
                'modalidad' => $request->input('modalidad', $request->input('paquete', 'regular')),
            ])
            ->with('success', 'Item actualizado.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pais' => ['required', Rule::in(array_keys($this->catalog->countries()))],
            'plan' => ['required', 'string'],
            'nombre' => ['required', 'string', 'max:255'],
            'section' => ['required', 'string', 'max:255'],
            'precio' => ['required', 'integer', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'group' => ['nullable', 'string', 'max:255'],
            'descripcion_publica' => ['nullable', 'string'],
            'activo' => ['nullable', 'boolean'],
            'required' => ['nullable', 'boolean'],
            'default_selected' => ['nullable', 'boolean'],
            'locked' => ['nullable', 'boolean'],
            'modalidad' => ['nullable', 'string'],
            'paquete' => ['nullable', 'string'],
        ]);

        abort_unless($this->catalog->planForCountry($data['pais'], $data['plan']), 404);
        $modalidad = $data['modalidad'] ?? $data['paquete'] ?? 'regular';

        $this->catalog->createCustomService($data['pais'], $data['plan'], array_merge($data, [
            'activo' => $request->boolean('activo'),
            'required' => $request->boolean('required'),
            'default_selected' => $request->boolean('default_selected'),
            'locked' => $request->boolean('locked'),
        ]));

        return redirect()
            ->route('admin.banca-online.index', [
                'pais' => $data['pais'],
                'plan' => $data['plan'],
                'modalidad' => $modalidad,
            ])
            ->with('success', 'Servicio agregado al catalogo.');
    }

    public function updatePackage(Request $request, Servicio $servicio)
    {
        abort_unless(
            $servicio->categoria === $this->catalog->category()
            && $this->catalog->recordType($servicio) === 'package',
            404
        );

        $metadata = $this->catalog->metadata($servicio);
        $countrySlug = $this->catalog->countrySlugForService($servicio);
        $planSlug = (string) ($metadata['plan_slug'] ?? '');

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion_publica' => ['nullable', 'string', 'max:1000'],
            'list_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => [
                'required',
                'numeric',
                'min:0',
                $request->input('discount_type') === 'percentage' ? 'max:100' : 'max:999999999',
            ],
            'activo' => ['nullable', 'boolean'],
            'public_feature_names' => ['nullable', 'array'],
            'public_feature_names.*' => ['nullable', 'string', 'max:255'],
            'public_feature_descriptions' => ['nullable', 'array'],
            'public_feature_descriptions.*' => ['nullable', 'string', 'max:2000'],
            'installment_periods' => ['nullable', 'array'],
            'installment_periods.*.enabled' => ['nullable', 'boolean'],
            'installment_periods.*.surcharge_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'installment_rule_min_percent' => ['nullable', 'array'],
            'installment_rule_min_percent.*' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'installment_rule_max_count' => ['nullable', 'array'],
            'installment_rule_max_count.*' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $packageName = trim($data['nombre']);
        $packageDescription = trim((string) ($data['descripcion_publica'] ?? ''));
        $listPrice = round(max(0, (float) $data['list_price']), 2);
        $price = round(max(0, min($listPrice, (float) $data['price'])), 2);

        $metadata['component_ids'] = [];
        $metadata['features'] = $this->normalizedPublicFeatures(
            $request->input('public_feature_names', []),
            $request->input('public_feature_descriptions', [])
        );
        $metadata['show_component_prices'] = false;
        $metadata['list_price'] = $listPrice;
        $metadata['price'] = $price;
        $metadata['saving'] = round(max(0, $listPrice - $price), 2);
        $metadata['discount_type'] = $data['discount_type'];
        $metadata['discount_value'] = (float) $data['discount_value'];
        $metadata['cms_managed'] = true;
        $metadata['tier_title'] = $packageName;
        $metadata['tier_summary'] = $packageDescription !== '' ? $packageDescription : null;
        $metadata['installment_periods'] = $this->normalizedInstallmentPeriods($request->input('installment_periods', []));
        $metadata['installment_initial_rules'] = $this->normalizedInstallmentRules(
            $request->input('installment_rule_min_percent', []),
            $request->input('installment_rule_max_count', [])
        );

        unset($metadata['pricing_mode'], $metadata['component_prices']);

        $servicio->fill([
            'nombre' => $packageName,
            'descripcion_publica' => $packageDescription !== '' ? $packageDescription : null,
            'precio' => 0,
            'activo' => $request->boolean('activo'),
            'metadata' => $metadata,
        ])->save();

        return redirect()
            ->route('admin.banca-online.index', [
                'pais' => $countrySlug,
                'plan' => $planSlug,
                'modalidad' => $metadata['tier_slug'] ?? 'regular',
            ])
            ->with('success', 'Modalidad actualizada.');
    }

    private function normalizedPublicFeatures(array $names, array $descriptions): array
    {
        return collect($names)
            ->map(function ($name, $index) use ($descriptions) {
                $name = trim((string) $name);
                $description = trim((string) ($descriptions[$index] ?? ''));

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'description' => $description !== '' ? $description : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function normalizedInstallmentPeriods(array $input): array
    {
        return collect($this->catalog->installmentPeriodDefaults())
            ->map(function (array $defaults, string $slug) use ($input) {
                $values = $input[$slug] ?? [];

                return [
                    'enabled' => filter_var($values['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'surcharge_percent' => round(max(0, min(100, (float) ($values['surcharge_percent'] ?? ($defaults['surcharge_percent'] ?? 0)))), 2),
                ];
            })
            ->all();
    }

    private function normalizedInstallmentRules(array $minPercents, array $maxCounts): array
    {
        $rules = collect($minPercents)
            ->map(function ($percent, $index) use ($maxCounts) {
                if (trim((string) $percent) === '' || trim((string) ($maxCounts[$index] ?? '')) === '') {
                    return null;
                }

                $percent = round(max(1, min(99, (float) $percent)), 2);
                $maxCount = max(1, min(60, (int) ($maxCounts[$index] ?? 1)));

                return [
                    'min_initial_percent' => $percent,
                    'max_installments' => $maxCount,
                ];
            })
            ->filter(fn ($rule) => is_array($rule) && $rule['min_initial_percent'] > 0 && $rule['max_installments'] > 0)
            ->unique('min_initial_percent')
            ->sortBy('min_initial_percent')
            ->values();

        return $rules->isNotEmpty()
            ? $rules->all()
            : $this->catalog->installmentInitialRuleDefaults();
    }
}

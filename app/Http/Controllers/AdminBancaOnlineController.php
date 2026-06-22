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

        $services = $allServices
            ->filter(fn (Servicio $servicio) => $this->catalog->countrySlugForService($servicio) === $countrySlug)
            ->filter(fn (Servicio $servicio) => ($this->catalog->metadata($servicio)['plan_slug'] ?? null) === $planSlug)
            ->groupBy(fn (Servicio $servicio) => $this->catalog->metadata($servicio)['section'] ?? 'General');

        $countryCounts = $allServices
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
        ]);

        abort_unless($this->catalog->planForCountry($data['pais'], $data['plan']), 404);

        $this->catalog->createCustomService($data['pais'], $data['plan'], array_merge($data, [
            'activo' => $request->boolean('activo'),
            'required' => $request->boolean('required'),
            'default_selected' => $request->boolean('default_selected'),
            'locked' => $request->boolean('locked'),
        ]));

        return redirect()
            ->route('admin.banca-online.index', ['pais' => $data['pais'], 'plan' => $data['plan']])
            ->with('success', 'Servicio agregado al catalogo.');
    }
}

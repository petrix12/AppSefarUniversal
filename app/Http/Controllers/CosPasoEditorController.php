<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Cos;
use App\Models\CosPaso;
use App\Models\CosItem;
use App\Models\CosSubfase;
use App\Models\CosTextoAdicional;
use App\Services\CosHelperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CosPasoEditorController extends Controller
{
    public function index()
    {
        $procesos = Cos::orderBy('id')->get(['id', 'nombre']);
        return view('admin.procesos.index', compact('procesos'));
    }

    public function show(Cos $cos)
    {
        $cos->load([
            'fases' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
            'fases.pasos' => fn ($q) => $q->orderBy('numero')->orderBy('id'),
            'fases.pasos.items' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
            'fases.pasos.subfases' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
            'fases.pasos.textosAdicionales' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
        ]);

        return view('admin.procesos.show', compact('cos'));
    }

    public function updatePasoFull(Request $request, CosPaso $paso, CosHelperService $cosHelper)
    {
        $data = $request->validate([
            // Paso
            'numero' => 'required|integer|min:1',
            'titulo' => 'required|string|max:255',
            'nombre_corto' => 'nullable|string|max:255',
            'promesa' => 'nullable|string',
            'main_cta_texto' => 'nullable|string|max:255',
            'main_cta_url' => 'nullable|string|max:255',

            // CTAs (cos_items tipo=cta)
            'ctas' => 'array',
            'ctas.*.id' => 'nullable|integer',
            'ctas.*.texto' => 'nullable|string|max:255',
            'ctas.*.url' => 'nullable|string|max:255',
            'ctas.*.orden' => 'nullable|integer|min:1',

            // Subfases
            'subfases' => 'array',
            'subfases.*.id' => 'nullable|integer',
            'subfases.*.titulo' => 'nullable|string|max:255',
            'subfases.*.orden' => 'nullable|integer|min:1',

            // Textos adicionales
            'textos' => 'array',
            'textos.*.id' => 'nullable|integer',
            'textos.*.nombre' => 'nullable|string|max:255',
            'textos.*.texto' => 'nullable|string',
            'textos.*.orden' => 'nullable|integer|min:1',
        ]);

        DB::transaction(function () use ($paso, $data) {

            // 1) Update del paso
            $paso->update([
                'numero' => $data['numero'],
                'titulo' => $data['titulo'],
                'nombre_corto' => $data['nombre_corto'] ?? null,
                'promesa' => $data['promesa'] ?? null,
                'main_cta_texto' => $data['main_cta_texto'] ?? null,
                'main_cta_url' => $data['main_cta_url'] ?? null,
            ]);

            // Helpers: filtrar filas vacías
            $notEmpty = fn ($v) => is_string($v) ? trim($v) !== '' : !is_null($v);

            // 2) CTAs (items tipo cta)
            $submittedCtas = collect($data['ctas'] ?? [])
                ->filter(fn ($r) => $notEmpty($r['texto'] ?? null) || $notEmpty($r['url'] ?? null))
                ->values();

            $existingCtaIds = CosItem::where('paso_id', $paso->id)->where('tipo', 'cta')->pluck('id')->all();
            $submittedCtaIds = $submittedCtas->pluck('id')->filter()->all();
            $toDelete = array_diff($existingCtaIds, $submittedCtaIds);

            if (!empty($toDelete)) {
                CosItem::whereIn('id', $toDelete)->delete();
            }

            foreach ($submittedCtas as $idx => $cta) {
                $orden = (int) ($cta['orden'] ?? ($idx + 1));

                if (!empty($cta['id'])) {
                    CosItem::where('id', $cta['id'])
                        ->where('paso_id', $paso->id)
                        ->where('tipo', 'cta')
                        ->update([
                            'texto' => $cta['texto'] ?? '',
                            'url' => $cta['url'] ?? null,
                            'orden' => $orden,
                        ]);
                } else {
                    CosItem::create([
                        'paso_id' => $paso->id,
                        'subfase_id' => null,
                        'tipo' => 'cta',
                        'texto' => $cta['texto'] ?? '',
                        'url' => $cta['url'] ?? null,
                        'orden' => $orden,
                    ]);
                }
            }

            // 3) Subfases
            $submittedSubfases = collect($data['subfases'] ?? [])
                ->filter(fn ($r) => $notEmpty($r['titulo'] ?? null))
                ->values();

            $existingSubfaseIds = CosSubfase::where('paso_id', $paso->id)->pluck('id')->all();
            $submittedSubfaseIds = $submittedSubfases->pluck('id')->filter()->all();
            $toDelete = array_diff($existingSubfaseIds, $submittedSubfaseIds);

            if (!empty($toDelete)) {
                CosSubfase::whereIn('id', $toDelete)->delete();
            }

            foreach ($submittedSubfases as $idx => $sf) {
                $orden = (int) ($sf['orden'] ?? ($idx + 1));

                if (!empty($sf['id'])) {
                    CosSubfase::where('id', $sf['id'])
                        ->where('paso_id', $paso->id)
                        ->update([
                            'titulo' => $sf['titulo'],
                            'orden' => $orden,
                        ]);
                } else {
                    CosSubfase::create([
                        'paso_id' => $paso->id,
                        'titulo' => $sf['titulo'],
                        'orden' => $orden,
                    ]);
                }
            }

            // 4) Textos adicionales
            $submittedTextos = collect($data['textos'] ?? [])
                ->filter(fn ($r) => $notEmpty($r['nombre'] ?? null) || $notEmpty($r['texto'] ?? null))
                ->values();

            $existingTextoIds = CosTextoAdicional::where('paso_id', $paso->id)->pluck('id')->all();
            $submittedTextoIds = $submittedTextos->pluck('id')->filter()->all();
            $toDelete = array_diff($existingTextoIds, $submittedTextoIds);

            if (!empty($toDelete)) {
                CosTextoAdicional::whereIn('id', $toDelete)->delete();
            }

            foreach ($submittedTextos as $idx => $t) {
                $orden = (int) ($t['orden'] ?? ($idx + 1));

                if (!empty($t['id'])) {
                    CosTextoAdicional::where('id', $t['id'])
                        ->where('paso_id', $paso->id)
                        ->update([
                            'nombre' => $t['nombre'] ?? null,
                            'texto' => $t['texto'] ?? null,
                            'orden' => $orden,
                        ]);
                } else {
                    CosTextoAdicional::create([
                        'paso_id' => $paso->id,
                        'nombre' => $t['nombre'] ?? null,
                        'texto' => $t['texto'] ?? null,
                        'orden' => $orden,
                    ]);
                }
            }
        });

        $cosHelper->clearCache();

        return back()->with('success', 'Paso actualizado ✅');
    }
}

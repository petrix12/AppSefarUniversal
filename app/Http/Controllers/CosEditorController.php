<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cos;
use App\Models\CosFase;
use App\Models\CosPaso;
use App\Services\CosHelperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CosEditorController extends Controller
{
    public function index()
    {
        $cosList = Cos::orderBy('id')->get();
        return view('admin.cos.index', compact('cosList'));
    }

    public function show(Cos $cos)
    {
        $cos->load([
            'fases' => fn ($q) => $q->orderBy('orden')->orderBy('id'),
            'fases.pasos' => fn ($q) => $q->orderBy('numero')->orderBy('id'),
        ]);

        return view('admin.cos.show', compact('cos'));
    }

    public function updateFase(Request $request, CosFase $fase, CosHelperService $cosHelper)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'numero' => 'nullable|integer|min:1', // si quieres permitir editar numero
        ]);

        $fase->update($data);
        $cosHelper->clearCache();

        return back()->with('success', 'Fase actualizada ✅');
    }

    public function updatePaso(Request $request, CosPaso $paso, CosHelperService $cosHelper)
    {
        $data = $request->validate([
            'numero' => 'required|integer|min:1',
            'titulo' => 'required|string|max:255',
            'nombre_corto' => 'nullable|string|max:255',
            'promesa' => 'nullable|string',
            'promesa_pasado' => 'nullable|string',
            'main_cta_texto' => 'nullable|string|max:255',
            'main_cta_url' => 'nullable|string|max:255',
        ]);

        $paso->update($data);
        $cosHelper->clearCache();

        return back()->with('success', 'Paso actualizado ✅');
    }

    public function reorderFases(Request $request, CosHelperService $cosHelper)
    {
        $payload = $request->validate([
            'cos_id' => 'required|integer|exists:cos,id',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:cos_fases,id',
        ]);

        DB::transaction(function () use ($payload) {
            foreach (array_values($payload['ids']) as $i => $id) {
                CosFase::where('id', $id)
                    ->where('cos_id', $payload['cos_id'])
                    ->update(['orden' => $i + 1]);
            }
        });

        $cosHelper->clearCache();
        return response()->json(['ok' => true]);
    }

    public function reorderPasos(Request $request, CosHelperService $cosHelper)
    {
        $payload = $request->validate([
            'fase_id' => 'required|integer|exists:cos_fases,id',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:cos_pasos,id',
        ]);

        DB::transaction(function () use ($payload) {
            foreach (array_values($payload['ids']) as $i => $id) {
                CosPaso::where('id', $id)
                    ->where('fase_id', $payload['fase_id'])
                    ->update(['numero' => $i + 1]); // si luego agregas 'orden' en pasos, cambias aquí
            }
        });

        $cosHelper->clearCache();
        return response()->json(['ok' => true]);
    }
}

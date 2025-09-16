<?php

namespace App\Http\Controllers;

use App\Models\CosVisit;

class CosVisitController extends Controller
{
    /**
     * Mostrar todas las visitas (sin paginar).
     */
    public function index()
    {
        $visitas = CosVisit::with(['user', 'cliente'])
            ->orderByDesc('fecha_visita')
            ->get();

        if (request()->wantsJson()) {
            return response()->json($visitas);
        }

        return view('cosvisitas.index', compact('visitas'));
    }

}

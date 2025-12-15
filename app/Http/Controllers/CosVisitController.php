<?php

namespace App\Http\Controllers;

use App\Models\CosVisit;
use Illuminate\Http\Request;

class CosVisitController extends Controller
{
    /**
     * Listado de visitas al COS (con filtros).
     */
    public function listado(Request $request)
    {
        $filtro = $request->get('filtro', 'todo');

        $inicio = match ($filtro) {
            'semana' => now()->subDays(7),
            'mes'    => now()->startOfMonth(),
            'anio'   => now()->startOfYear(),
            default  => null,
        };

        $baseQuery = \App\Models\CosVisit::query()
            ->with([
                'user:id,name,nombres,apellidos',
                'cliente:id,name,nombres,apellidos',
            ])
            ->when($inicio, fn ($q) => $q->where('fecha_visita', '>=', $inicio));

        // ✅ Tabla (paginada)
        $visitas = (clone $baseQuery)
            ->orderByDesc('fecha_visita')
            ->paginate(50)
            ->withQueryString();

        // ✅ Total (NO paginado)
        $totalVisitas = (clone $baseQuery)->count();

        // ✅ Chart (NO paginado)
        $visitasPorUsuario = (clone $baseQuery)
            ->selectRaw('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->with(['user:id,name,nombres,apellidos'])
            ->get()
            ->map(function ($row) {
                $u = $row->user;
                $nombre = $u?->nombres
                    ? trim($u->nombres . ' ' . ($u->apellidos ?? ''))
                    : ($u?->name ?? 'Usuario desconocido');

                return ['label' => $nombre, 'total' => (int) $row->total];
            })
            ->values(); // importante para @json

        return view('cosvisitas.index', compact('visitas','visitasPorUsuario','totalVisitas','filtro'));
    }
}

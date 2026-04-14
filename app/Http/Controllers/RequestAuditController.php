<?php

namespace App\Http\Controllers;

use App\Models\RequestAudit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequestAuditController extends Controller
{
    public function index(Request $request)
    {
        $filtro = $request->get('filtro', 'semana');

        $query = RequestAudit::query();

        switch ($filtro) {
            case 'semana':
                $query->where('visited_at', '>=', now()->startOfWeek());
                break;

            case 'mes':
                $query->where('visited_at', '>=', now()->startOfMonth());
                break;

            case 'anio':
                $query->where('visited_at', '>=', now()->startOfYear());
                break;

            case 'todo':
            default:
                break;
        }

        $totalVisitas = (clone $query)->count();

        $visitasPorUsuario = (clone $query)
            ->select(
                DB::raw("COALESCE(name, email, 'No autenticado') as label"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('label')
            ->orderByDesc('total')
            ->get();

        $visitas = (clone $query)
            ->orderByDesc('visited_at')
            ->get();

        $visitasAgrupadas = $visitas->groupBy(function ($item) {
            if ($item->user_id) {
                return 'user_'.$item->user_id;
            }

            if ($item->email) {
                return 'email_'.$item->email;
            }

            return 'guest';
        })->map(function ($items) {
            $first = $items->first();

            $label = $first->name
                ?: $first->email
                ?: 'No autenticado';

            return [
                'label' => $label,
                'email' => $first->email,
                'user_id' => $first->user_id,
                'total' => $items->count(),
                'items' => $items->values(),
            ];
        })->values();

        return view('request_audits.index', compact(
            'filtro',
            'totalVisitas',
            'visitasPorUsuario',
            'visitasAgrupadas'
        ));
    }
}

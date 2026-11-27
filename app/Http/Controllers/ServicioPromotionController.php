<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioPromotionController extends Controller
{
    public function index()
    {
        $request = request();
        abort_if($request->user()->hasRole('Cliente'), 403);
        $servicios = Servicio::temporaryPriceEligible()->orderBy('nombre')->get();
        return response()->view('crud.servicios.promotions', compact('servicios'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        abort_if($request->user()->hasRole('Cliente'), 403);
        $data = $request->validate([
            'servicios' => ['required', 'array', 'min:1', 'max:200'],
            'servicios.*' => ['required', 'integer', 'distinct', 'exists:servicios,id'],
            'precio_temporal' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'precio_temporal_desde' => ['required', 'date_format:Y-m-d'],
            'precio_temporal_hasta' => ['required', 'date_format:Y-m-d', 'after_or_equal:precio_temporal_desde'],
        ], [
            'servicios.required' => 'Selecciona al menos un servicio.',
            'precio_temporal_hasta.after_or_equal' => 'El último día debe ser igual o posterior a la fecha de inicio.',
        ], ['precio_temporal' => 'precio temporal', 'precio_temporal_desde' => 'fecha de inicio', 'precio_temporal_hasta' => 'último día']);

        DB::transaction(function () use ($data) {
            $services = Servicio::temporaryPriceEligible()->whereIn('id', $data['servicios'])->orderBy('id')->lockForUpdate()->get();
            if ($services->count() !== count($data['servicios'])) {
                throw ValidationException::withMessages(['servicios' => 'Selecciona servicios activos de registro en EUR. No se incluyen fases ni consultas.']);
            }
            foreach ($services as $service) {
                if ($data['precio_temporal'] > $service->precio) {
                    throw ValidationException::withMessages(['precio_temporal' => 'El precio temporal supera el precio habitual de '.$service->nombre.'.']);
                }
                $service->update([
                    'precio_temporal' => $data['precio_temporal'],
                    'precio_temporal_desde' => $data['precio_temporal_desde'],
                    'precio_temporal_hasta' => $data['precio_temporal_hasta'],
                ]);
            }
        });

        return redirect()->route('crud.servicios.promotions.index')->with('success', 'Precios temporales guardados. Al terminar se aplicará el precio habitual de cada servicio.');
    }
}

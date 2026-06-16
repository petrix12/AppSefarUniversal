<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServicioController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $servicios = Servicio::orderBy('created_at', 'desc')->get();
        return view('crud.servicios.index', compact('servicios'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.servicios.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $data = $this->validatedData($request);

        try {
            Servicio::create($data);
        } catch(\Illuminate\Database\QueryException $ex){
            Alert::error('Error', 'El servicio ya existe');
            return back();
        }

        // Mensaje
        Alert::success('¡Éxito!', 'Se ha añadido el servicio: ' . $request->nombre);

        // Redireccionar a la vista que invocó este método
        $servicios = Servicio::orderBy('created_at', 'desc')->get();
        return view('crud.servicios.index', compact('servicios'));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Servicio  $servicio
     * @return \Illuminate\Http\Response
     */
    public function show(Servicio $servicio)
    {
        return view('crud.servicios.edit', compact('servicio'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Servicio  $servicio
     * @return \Illuminate\Http\Response
     */
    public function edit(Servicio $servicio)
    {
        return view('crud.servicios.edit', compact('servicio'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Servicio  $servicio
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Servicio $servicio)
    {
        $servicio->fill($this->validatedData($request, $servicio));

        try {
            $servicio->save();
        } catch(\Illuminate\Database\QueryException $ex){
            Alert::error('Error', 'El servicio ya existe. No puedes duplicarlo.');
            return back();
        }

        // Mensaje
        Alert::success('¡Éxito!', 'Se ha actualizado el servicio: ' . $request->nombre);

        // Redireccionar a la vista que invocó este método
        $servicios = Servicio::orderBy('created_at', 'desc')->get();
        return view('crud.servicios.index', compact('servicios'));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Servicio  $servicio
     * @return \Illuminate\Http\Response
     */
    public function destroy(Servicio $servicio)
    {
        $titulo = $servicio->couponcode;

        $servicio->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el cupón: ' . $titulo);

        $servicios = Servicio::orderBy('created_at', 'desc')->get();
        return view('crud.servicios.index', compact('servicios'));
    }

    public function getservicio(Request $request)
    {
        // Obtener el término de búsqueda del request
        $terminoBusqueda = $request->get('servicio');

        // Realizar la búsqueda en la base de datos
        $resultados = Servicio::where('id_hubspot', '=', $terminoBusqueda)
                              ->first();

        $precio = [];

        if ($resultados) {
            $precio["precio"] = $resultados->precio;
        } else {
            $precio["precio"] = 0;
        }

        return response()->json($precio);
    }

    private function validatedData(Request $request, ?Servicio $servicio = null): array
    {
        $id = $servicio?->id;

        $data = $request->validate([
            'id_hubspot' => [
                'required',
                'string',
                'max:255',
                Rule::unique('servicios', 'id_hubspot')->ignore($id),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'precio' => ['required', 'integer', 'min:0'],
            'tipov' => ['nullable', 'integer', 'in:0,1'],
            'categoria' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', 'string', Rule::in(['servicio', 'cos_fase', 'consulta', 'miscelaneo'])],
            'descripcion_publica' => ['nullable', 'string'],
            'activo' => ['nullable', 'boolean'],
            'visible_cliente' => ['nullable', 'boolean'],
            'moneda' => ['nullable', 'string', 'size:3'],
            'duracion_minutos' => ['nullable', 'integer', 'min:15', 'max:480'],
            'requiere_agenda' => ['nullable', 'boolean'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'hubspot_pipeline_id' => ['nullable', 'string', 'max:255'],
            'hubspot_stage_id' => ['nullable', 'string', 'max:255'],
        ]);

        $data['id_hubspot'] = trim($data['id_hubspot']);
        $data['nombre'] = trim($data['nombre']);
        $data['tipov'] = (int) ($data['tipov'] ?? 0);
        $data['categoria'] = trim($data['categoria'] ?? 'general') ?: 'general';
        $data['moneda'] = strtoupper($data['moneda'] ?? 'EUR');
        $data['activo'] = $request->boolean('activo');
        $data['visible_cliente'] = $request->boolean('visible_cliente');
        $data['requiere_agenda'] = $request->boolean('requiere_agenda');
        $data['orden'] = (int) ($data['orden'] ?? 0);

        if ($data['tipo'] === 'consulta') {
            $data['requiere_agenda'] = true;
            $data['duracion_minutos'] = $data['duracion_minutos'] ?: 60;
        }

        return $data;
    }
}

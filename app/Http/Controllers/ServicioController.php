<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use App\Services\MondayCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ServicioController extends Controller
{
    public function mondayBoards(MondayCatalogService $catalog): JsonResponse
    {
        return $this->mondayOptionsResponse(fn (): array => $catalog->boards());
    }

    public function mondayGroups(Request $request, MondayCatalogService $catalog): JsonResponse
    {
        $data = $request->validate([
            'board_id' => ['required', 'regex:/^\d+$/'],
        ]);

        return $this->mondayOptionsResponse(
            fn (): array => $catalog->groups($data['board_id'])
        );
    }

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
        } catch (Throwable $exception) {
            return $this->serviceSaveFailure($request, $exception, 'crear');
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
        } catch (Throwable $exception) {
            return $this->serviceSaveFailure($request, $exception, 'actualizar', $servicio);
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

        $request->merge([
            'tipo' => $request->filled('tipo') ? $request->input('tipo') : 'servicio',
            'categoria' => $request->filled('categoria') ? $request->input('categoria') : 'general',
            'moneda' => $request->filled('moneda') ? strtoupper($request->input('moneda')) : 'EUR',
            'tipov' => $request->filled('tipov') ? $request->input('tipov') : 0,
            'duracion_minutos' => $request->filled('duracion_minutos') ? $request->input('duracion_minutos') : null,
            'orden' => $request->filled('orden') ? $request->input('orden') : 0,
            'descripcion_publica' => $request->filled('descripcion_publica') ? $request->input('descripcion_publica') : null,
            'hubspot_pipeline_id' => $request->filled('hubspot_pipeline_id') ? $request->input('hubspot_pipeline_id') : null,
            'hubspot_stage_id' => $request->filled('hubspot_stage_id') ? $request->input('hubspot_stage_id') : null,
            'monday_board_id' => $request->filled('monday_board_id') ? $request->input('monday_board_id') : null,
            'monday_group_id' => $request->filled('monday_group_id') ? $request->input('monday_group_id') : null,
        ]);

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
            'monday_sync_enabled' => ['nullable', 'boolean'],
            'monday_board_id' => ['nullable', 'regex:/^\d+$/', 'max:255', 'required_if:monday_sync_enabled,1'],
            'monday_group_id' => ['nullable', 'string', 'max:255', 'required_if:monday_sync_enabled,1'],
        ], [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'El campo :attribute es obligatorio cuando el envío a Monday está activo.',
            'unique' => 'Ya existe un servicio con el mismo :attribute.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'boolean' => 'El campo :attribute debe ser sí o no.',
            'size' => 'El campo :attribute debe tener exactamente :size caracteres.',
            'in' => 'El valor seleccionado para :attribute no es válido.',
            'regex' => 'El Board ID de Monday debe contener solamente números.',
        ], [
            'id_hubspot' => 'ID de HubSpot',
            'nombre' => 'nombre del servicio',
            'precio' => 'precio',
            'tipov' => 'servicio de vinculación',
            'categoria' => 'categoría',
            'tipo' => 'tipo',
            'descripcion_publica' => 'descripción pública',
            'activo' => 'estado activo',
            'visible_cliente' => 'visibilidad para clientes',
            'moneda' => 'moneda',
            'duracion_minutos' => 'duración',
            'requiere_agenda' => 'requerimiento de agenda',
            'orden' => 'orden',
            'hubspot_pipeline_id' => 'HubSpot Pipeline ID',
            'hubspot_stage_id' => 'HubSpot Stage ID',
            'monday_sync_enabled' => 'envío a Monday',
            'monday_board_id' => 'tablero de Monday',
            'monday_group_id' => 'grupo/subtablero de Monday',
        ]);

        $data['id_hubspot'] = trim($data['id_hubspot']);
        $data['nombre'] = trim($data['nombre']);
        $data['tipov'] = (int) ($data['tipov'] ?? 0);
        $data['categoria'] = trim($data['categoria'] ?? 'general') ?: 'general';
        $data['moneda'] = strtoupper($data['moneda'] ?? 'EUR');
        $data['activo'] = $request->boolean('activo');
        $data['visible_cliente'] = $request->boolean('visible_cliente');
        $data['requiere_agenda'] = $request->boolean('requiere_agenda');
        $data['monday_sync_enabled'] = $request->boolean('monday_sync_enabled');
        $data['orden'] = (int) ($data['orden'] ?? 0);

        if ($data['tipo'] === 'consulta') {
            $data['requiere_agenda'] = true;
            $data['duracion_minutos'] = $data['duracion_minutos'] ?: 60;
        }

        return $data;
    }

    private function serviceSaveFailure(
        Request $request,
        Throwable $exception,
        string $operation,
        ?Servicio $servicio = null
    ): RedirectResponse
    {
        $reference = Str::upper(Str::random(8));
        $message = $exception instanceof QueryException
            ? $this->databaseErrorMessage($exception, $reference)
            : "No se pudo {$operation} el servicio. Referencia: {$reference}.";

        Log::error("No se pudo {$operation} el servicio", [
            'reference' => $reference,
            'operation' => $operation,
            'service_id' => $servicio?->id,
            'submitted_fields' => array_keys($request->except([
                '_token',
                '_method',
            ])),
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'sql_state' => $exception instanceof QueryException ? ($exception->errorInfo[0] ?? null) : null,
            'driver_code' => $exception instanceof QueryException ? ($exception->errorInfo[1] ?? null) : null,
        ]);

        Alert::error('No se pudo guardar el servicio', $message);

        return back()
            ->withInput()
            ->withErrors(['service_save' => $message]);
    }

    private function databaseErrorMessage(QueryException $exception, string $reference): string
    {
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        $driverMessage = (string) ($exception->errorInfo[2] ?? '');
        $field = $this->databaseFieldFromMessage($driverMessage);

        $message = match ($driverCode) {
            1048 => 'Falta un valor obligatorio'.($field ? " en {$field}" : '').'.',
            1054 => 'La estructura de la base de datos está desactualizada'.($field ? ": no existe {$field}" : '').'. Ejecuta las migraciones.',
            1062 => str_contains($driverMessage, 'servicios_id_hubspot_unique')
                ? 'Ya existe otro servicio con el mismo ID de HubSpot.'
                : 'Ya existe un registro con uno de esos valores únicos.',
            1264 => 'Uno de los valores numéricos está fuera del rango permitido'.($field ? " ({$field})" : '').'.',
            1366 => 'Un valor tiene un formato incompatible con la base de datos'.($field ? " ({$field})" : '').'.',
            1406 => 'El contenido supera el tamaño permitido'.($field ? " en {$field}" : '').'.',
            1451, 1452 => 'El servicio está relacionado con otros datos y la operación viola esa relación.',
            default => 'Error de base de datos'.($driverCode ? " (código {$driverCode})" : '').'.',
        };

        if (config('app.debug') && $driverMessage !== '') {
            $message .= ' Detalle técnico: '.Str::limit($driverMessage, 500);
        }

        return $message." Referencia: {$reference}.";
    }

    private function databaseFieldFromMessage(string $message): ?string
    {
        if (preg_match("/(?:column|field) ['`]([^'`]+)['`]/i", $message, $matches)) {
            return "el campo «{$matches[1]}»";
        }

        return null;
    }

    private function mondayOptionsResponse(callable $options): JsonResponse
    {
        try {
            return response()->json(['data' => $options()]);
        } catch (\Throwable $exception) {
            Log::error('No se pudo cargar el catálogo de Monday', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => $exception->getMessage(),
            ], 502);
        }
    }
}

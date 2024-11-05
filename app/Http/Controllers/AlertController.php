<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class AlertController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $alertas = Alert::all();

        return view('crud.alerts.index', compact('alertas'));
    }

    public function getactivealerts()
    {
        $today = now()->toDateString(); // Obtener la fecha actual en formato 'Y-m-d'

        // Consultar las alertas activas en el día actual
        $activeAlerts = Alert::where('start_date', '<=', $today)
                            ->where('end_date', '>=', $today)
                            ->get();

        // Retornar el resultado en formato JSON
        return response()->json($activeAlerts);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.alerts.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validación de datos
        $request->validate([
            'title' => 'required|string|max:255',
            'text' => 'nullable|string',  // El texto es opcional
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg', // La imagen es obligatoria
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        // Inicializar datos de la alerta
        $data = $request->only(['title', 'text', 'start_date', 'end_date']);

        // Procesar y guardar la imagen en S3
        if ($request->hasFile('image')) {
            // Crear instancia del ImageManager con el driver GD
            $manager = new ImageManager(new Driver());

            // Generar un nombre de archivo aleatorio con extensión
            $filename = uniqid() . '.' . $request->file('image')->getClientOriginalExtension();

            // Leer la imagen del sistema de archivos
            $image = $manager->read($request->file('image')->getRealPath());

            // Redimensionar la imagen si su altura es mayor a 1080 píxeles
            if ($image->height() > 1080) {
                $image->scale(height: 1080);
            }

            // Convertir a PNG y guardar temporalmente
            $tempPath = storage_path('app/public/' . $filename);
            $image->toPng()->save($tempPath);

            // Subir la imagen a S3 y obtener la URL
            Storage::disk('s3')->put('alerts/' . $filename, file_get_contents($tempPath), 'public');
            $data['image'] = Storage::disk('s3')->url('alerts/' . $filename);

            // Eliminar el archivo temporal
            unlink($tempPath);
        }

        // Crear la alerta en la base de datos
        Alert::create($data);

        // Redireccionar con un mensaje de éxito
        return redirect()->route('alerts.index')->with('success', 'La alerta ha sido registrada exitosamente.');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Alert  $alert
     * @return \Illuminate\Http\Response
     */
    public function show(Alert $alert)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Alert  $alert
     * @return \Illuminate\Http\Response
     */
    public function edit(Alert $alert)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Alert  $alert
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Alert $alert)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Alert  $alert
     * @return \Illuminate\Http\Response
     */
    public function destroy(Alert $alert)
    {
        // Verificar si la alerta tiene una imagen y eliminarla de S3
        if ($alert->image) {
            // Extraer la ruta relativa del archivo en S3
            $imagePath = ltrim(parse_url($alert->image, PHP_URL_PATH), '/');

            // Eliminar el archivo de S3
            Storage::disk('s3')->delete($imagePath);
        }

        // Eliminar la alerta de la base de datos
        $alert->delete();

        // Redireccionar con un mensaje de éxito
        return redirect()->route('alerts.index')->with('success', 'La alerta ha sido eliminada exitosamente.');
    }

}

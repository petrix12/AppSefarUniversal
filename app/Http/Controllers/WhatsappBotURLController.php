<?php

namespace App\Http\Controllers;

use App\Models\WhatsappBotURL;
use Illuminate\Http\Request;

class WhatsappBotURLController extends Controller
{
    /**
     * Muestra el formulario con la URL actual (si existe).
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Intentamos obtener el primer (y único) registro.
        // Si no existe, devuelve null.
        $urlRecord = WhatsappBotURL::first();

        // Si el registro no existe, creamos un objeto vacío para el formulario.
        if (!$urlRecord) {
            $urlRecord = new WhatsappBotURL();
        }

        return view('crud.whatsappurl.index', compact('urlRecord'));
    }

    /**
     * Almacena o actualiza la URL.
     * Implementa la lógica de Singleton: si ya existe, la actualiza; si no, la crea.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeOrUpdate(Request $request)
    {
        // 1. Validar el input para asegurar que es una URL válida y requerida
        $request->validate([
            'url' => 'required|url|max:2048', // url: asegura que sea un formato de URL
        ]);

        // 2. Lógica para manejar el registro único:
        // Buscamos el primer registro (que debería ser el único).
        // Si lo encuentra, lo usamos. Si no lo encuentra, crea una nueva instancia
        // pero NO la guarda aún en la base de datos.
        $record = WhatsappBotURL::firstOrNew([]);

        // Asignamos el valor del campo 'url' del formulario
        $record->url = $request->input('url');

        // 3. Guardar el registro.
        // Si la instancia ya existía (fue obtenida por firstOrNew), la actualiza.
        // Si la instancia fue creada por firstOrNew, la inserta.
        $record->save();

        // 4. Redirigir de vuelta con un mensaje de éxito
        return redirect()->route('whatsapp.url.form')->with('success', '¡URL de WhatsApp guardada/actualizada con éxito!');
    }
}

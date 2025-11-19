<?php

namespace App\Http\Controllers;

use App\Models\ReportPhoneNumbers; // Corregido: Usar el nombre del modelo singular
use Illuminate\Http\Request;

class ReportPhoneNumbersController extends Controller
{
    /**
     * Display a listing of the resource (Index).
     * Muestra la lista de todos los números registrados.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Obtener todos los números de teléfono ordenados por el más reciente
        $phoneNumbers = ReportPhoneNumbers::orderBy('created_at', 'desc')->get(); // Usar ReportPhoneNumbers

        return view('crud.whatsappurl.numbers', compact('phoneNumbers')); // Corregido: Ruta de la vista
    }

    /**
     * Store a newly created resource in storage (Store).
     * Almacena un nuevo número de teléfono.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // 1. Validar el input
        $request->validate([
            'phone_number' => 'required|string|unique:report_phone_numbers,phone_number|max:20',
        ]);

        // 2. Crear el registro
        ReportPhoneNumbers::create([ // Usar ReportPhoneNumbers
            'phone_number' => $request->phone_number,
        ]);

        // 3. Redirigir con mensaje de éxito
        return redirect()->route('whatsapp.numbers.index')->with('success', '¡Número de teléfono agregado con éxito!'); // Corregido: Nueva ruta
    }

    /**
     * Update the specified resource in storage (Update).
     * Actualiza un número de teléfono existente.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ReportPhoneNumbers  $ReportPhoneNumbers
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, ReportPhoneNumbers $ReportPhoneNumbers)
    {
        // 1. Validar el input
        $request->validate([
            // La validación unique debe ignorar el ID actual
            'phone_number' => 'required|string|unique:report_phone_numbers,phone_number,' . $ReportPhoneNumbers->id . '|max:20',
        ]);

        // 2. Actualizar el registro
        $ReportPhoneNumbers->update([
            'phone_number' => $request->phone_number,
        ]);

        // 3. Redirigir con mensaje de éxito
        return redirect()->route('whatsapp.numbers.index')->with('success', '¡Número de teléfono actualizado con éxito!'); // Corregido: Nueva ruta
    }

    /**
     * Remove the specified resource from storage (Destroy).
     * Elimina un número de teléfono.
     *
     * @param  \App\Models\ReportPhoneNumbers  $ReportPhoneNumbers
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(ReportPhoneNumbers $ReportPhoneNumbers)
    {
        $ReportPhoneNumbers->delete();

        return redirect()->route('whatsapp.numbers.index')->with('success', '¡Número de teléfono eliminado con éxito!'); // Corregido: Nueva ruta
    }
}

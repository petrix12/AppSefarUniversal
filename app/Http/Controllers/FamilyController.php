<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use App\Models\Family;
use App\Models\Lado;
use App\Models\Parentesco;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class FamilyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.families.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $parentescos = Parentesco::all();
        $lados = Lado::all();
        $connections = Connection::all();
        return view('crud.families.create', compact('parentescos', 'lados', 'connections'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validación
        $request->validate([
            'IDCliente' => 'required|max:150',
            'IDFamiliar' => 'required|max:150',
            'Cliente' => 'nullable|max:250',
            'Familiar' => 'nullable|max:250',
        ]);

        $IDCliente = trim($request->IDCliente);
        $IDFamiliar = trim($request->IDFamiliar);
        $IDCombinado = $IDCliente.'-'.$IDFamiliar;

        // Creando familiar
        Family::create([
            'IDCombinado' => $IDCombinado,
            'IDCliente' => $IDCliente,
            'Cliente' => trim($request->Cliente),
            'IDFamiliar' => $IDFamiliar,
            'Familiar' => trim($request->Familiar),
            'Parentesco' => $request->Parentesco,
            'Lado' => $request->Lado,
            'Rama' => $request->Rama,
            'Nota' => $request->Nota,
        ]);

        // Mensaje 
        Alert::success('¡Éxito!', "Se ha añadido a $request->Familiar como familiar de $request->Cliente");
        
        // Redireccionar a la vista index
        return redirect()->route('crud.families.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Family  $family
     * @return \Illuminate\Http\Response
     */
    public function show(Family $family)
    {
        $parentescos = Parentesco::all();
        $lados = Lado::all();
        $connections = Connection::all();
        return view('crud.families.edit', compact('family','parentescos','lados','connections'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Family  $family
     * @return \Illuminate\Http\Response
     */
    public function edit(Family $family)
    {
        $parentescos = Parentesco::all();
        $lados = Lado::all();
        $connections = Connection::all();
        return view('crud.families.edit', compact('family','parentescos','lados','connections'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Family  $family
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Family $family)
    {
        // Validación
        $request->validate([
            'IDCliente' => 'required|max:150',
            'IDFamiliar' => 'required|max:150',
            'Cliente' => 'nullable|max:250',
            'Familiar' => 'nullable|max:250',
        ]);

        
        $IDCliente = trim($request->IDCliente);
        $IDFamiliar = trim($request->IDFamiliar);
        $IDCombinado = $IDCliente.'-'.$IDFamiliar;

        // Actualizando familiar
        $family->IDCombinado = $IDCombinado;
        $family->IDCliente = $IDCliente;
        $family->Cliente = trim($request->Cliente);
        $family->IDFamiliar = $IDFamiliar;
        $family->Familiar = trim($request->Familiar);
        $family->Parentesco = $request->Parentesco;
        $family->Lado = $request->Lado;
        $family->Rama = $request->Rama;
        $family->Nota = $request->Nota;
        $family->save();

        // Mensaje 
        Alert::success('¡Éxito!', "Se ha actualizado a $request->Familiar como familiar de $request->Cliente");
        
        // Redireccionar a la vista index
        return redirect()->route('crud.families.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Family  $family
     * @return \Illuminate\Http\Response
     */
    public function destroy(Family $family)
    {
        $mensaje = "Se ha eliminado de la lista a $family->Familiar como familiar de $family->Cliente";
        
        $family->delete();

        Alert::info('¡Advertencia!', $mensaje);

        //return redirect()->route('crud.families.index');
        return back();
    }
}

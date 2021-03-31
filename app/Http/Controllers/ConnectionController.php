<?php

namespace App\Http\Controllers;

use App\Models\Connection;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class ConnectionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.connections.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.connections.create');
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
            'Conexion' => 'required|max:15|unique:connections,Conexion',
            'Significado' => 'required'
        ]);

        // Creando connection
        Connection::create([
            'Conexion' => $request->Conexion,
            'Significado' => $request->Significado
        ]);

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido a la lista la conexión: ' . $request->Conexion);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.connections.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Connection  $connection
     * @return \Illuminate\Http\Response
     */
    public function show(Connection $connection)
    {
        return view('crud.connections.edit', compact('connection'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Connection  $connection
     * @return \Illuminate\Http\Response
     */
    public function edit(Connection $connection)
    {
        return view('crud.connections.edit', compact('connection'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Connection  $connection
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Connection $connection)
    {
        // Validación
        $request->validate([
            'Conexion' => 'required|max:15|unique:connections,Conexion,'.$connection->Conexion.',Conexion',
            'Significado' => 'required'
        ]);

        // Acutalizando parentesco
        $connection->Conexion = $request->Conexion;
        $connection->Significado = $request->Significado;
        $connection->save();

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado la conexión: ' . $request->Conexion);
        
        // Redireccionar a la vista index
        return redirect()->route('crud.connections.index');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Connection  $connection
     * @return \Illuminate\Http\Response
     */
    public function destroy(Connection $connection)
    {
        $nombre = $connection->Conexion;
        
        $connection->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el registro: ' . $nombre);

        return redirect()->route('crud.connections.index');
    }
}

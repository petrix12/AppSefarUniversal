<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Servicio;
use App\Models\HsReferido;
use App\Models\Compras;
use App\Models\Factura;
use App\Models\File;
use App\Models\AssocTlHs;
use Monday;

class EtiquetaGenealogiaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {
        try {
            // Validar los datos del formulario
            $validatedData = $request->validate([
                'boardId' => 'required|integer',
                'user_id' => 'required|exists:users,id',
                // Puedes añadir más validaciones según los campos del formulario
            ]);

            // Obtener datos del usuario
            $user = User::findOrFail($request->user_id);
            $monday_id = $user->monday_id;
            $boardId = $request->boardId;

            // Eliminar campos no necesarios para la mutación
            $datos = $request->except(['_token', 'boardId', 'user_id']);

            // Construir la consulta de mutación
            $query = '
            change_multiple_column_values(
                board_id: '.$boardId.',
                item_id: '.$monday_id.',
                column_values: '.json_encode(json_encode($datos)).'
            ) {
                id
            }
            ';

            // Ejecutar la mutación en Monday
            $updateResult = json_decode(json_encode(Monday::customMutation($query)), true);

            // Verificar si la mutación fue exitosa
            if (isset($updateResult['change_multiple_column_values']['id'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Datos actualizados correctamente.',
                    'data' => $updateResult['change_multiple_column_values'],
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar los datos en Monday.',
                    'errors' => $updateResult['errors'] ?? ['Error desconocido.'],
                ], 500);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Retornar errores de validación
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // Retornar errores generales
            return response()->json([
                'success' => false,
                'message' => 'Ocurrió un error inesperado.',
                'errors' => [$e->getMessage()],
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}

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
use PHPUnit\Framework\Constraint\IsNull;

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

        // Procesar campos de etiquetas
        foreach ($datos as $key => $value) {
            if (strpos($key, 'men__desplegable') !== false && $datos[$key] != null) {
                // Si el campo es de tipo "etiquetas", procesar sus valores
                $datos[$key] = $this->procesarEtiquetas($value);
            }
        }

        $datos = array_filter($datos, function ($value) {
            return $value !== null; // Solo mantener valores que no sean null
        });

        // Convertir el array a JSON y escaparlo correctamente
        $columnValues = json_encode($datos, JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        // Escapar comillas dobles en el JSON para que sea válido en la consulta GraphQL
        $columnValues = str_replace('"', '\"', $columnValues);


        // Construir la consulta de mutación
        $query = '
        mutation {
            change_multiple_column_values(
                board_id: ' . $boardId . ',
                item_id: ' . $monday_id . ',
                column_values: "' . $columnValues . '"
            ) {
                id
            }
        }
        ';

        // Configurar la solicitud HTTP a la API de Monday.com
        $client = new \GuzzleHttp\Client();
        $apiUrl = 'https://api.monday.com/v2'; // Usar HTTPS
        $apiKey = env('MONDAY_TOKEN'); // Asegúrate de tener tu API key en el archivo .env

        try {
            // Realizar la solicitud a la API de Monday.com
            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'query' => $query,
                ],
            ]);

            // Decodificar la respuesta
            $responseData = json_decode($response->getBody(), true);

            // Verificar si la mutación fue exitosa
            if (isset($responseData['data']['change_multiple_column_values']['id'])) {
                return response()->json([
                    'success' => true,
                    'message' => 'Datos actualizados correctamente.',
                    'data' => $responseData['data']['change_multiple_column_values'],
                ], 200);
            } else {
                // Si no hay un ID en la respuesta, hubo un error
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo actualizar los datos en Monday.',
                    'errors' => $responseData['errors'] ?? ['Error desconocido.'],
                ], 500);
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // Capturar errores de la solicitud HTTP
            $errorResponse = $e->getResponse();
            $errorBody = $errorResponse ? json_decode($errorResponse->getBody(), true) : null;

            return response()->json([
                'success' => false,
                'message' => 'Error al conectarse con la API de Monday.',
                'errors' => $errorBody ?? ['Error desconocido en la solicitud HTTP.'],
            ], 500);
        }
    }

    /**
     * Procesar valores de etiquetas.
     *
     * @param string $value Valores de etiquetas separados por comas.
     * @return string Valores procesados en formato JSON string.
     */
    private function procesarEtiquetas($value)
    {
        // Decodificar el JSON string
        $data = json_decode($value, true);

        $tags = [];

        // Extraer los IDs de los tags
        foreach ($data as $values) {
            $tags[] = (string)$values["id"]; // Convertir el ID a string
        }

        // Convertir a JSON string para enviar a Monday.com
        return ["ids" => $tags];
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

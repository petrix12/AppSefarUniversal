<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Http;
use App\Models\Treena;

class ChatController extends Controller
{
    // Crear una nueva sesión de chat
    public function iniciarChat()
    {
        // Mensaje inicial del sistema
        $mensajeSistema = [
            [
                "role" => "system",
                "content" => Treena::find(1)
            ]
        ];

        // Crear una nueva sesión con un ID único y guardar el mensaje del sistema
        $chatSession = ChatSession::create([
            'session_id' => \Illuminate\Support\Str::uuid()->toString(),
            'messages' => $mensajeSistema,
            'expires_at' => now()->addMinutes(30),
        ]);

        return response()->json(['session_id' => $chatSession->session_id]);
    }

    // Enviar un mensaje al chatbot
    public function enviarMensaje(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'mensaje' => 'required|string',
        ]);

        // Buscar la sesión en la base de datos
        $chatSession = ChatSession::where('session_id', $request->session_id)->first();

        // Si no se encuentra, crear una nueva sesión
        if (!$chatSession) {
            return response()->json(['error' => 'Sesión no encontrada'], 404);
        }

        $apiKey = env('OPENROUTER_API_KEY');

        // Obtener el historial de mensajes y añadir el nuevo mensaje del usuario
        $mensajes = $chatSession->messages;
        $mensajes[] = [
            'role' => 'user',
            'content' => $request->mensaje,
        ];

        // Llamar a la API de OpenRouter
        $response = Http::withHeaders([
            'Authorization' => "Bearer $apiKey",
            'Content-Type' => 'application/json',
        ])->post("https://openrouter.ai/api/v1/chat/completions", [
            'model' => 'openai/gpt-4.1',
            'messages' => $mensajes,
        ]);

        if ($response->successful()) {
            dd($response->json());

            $mensajeBot = $response->json()['choices'][0]['message']['content'];

            // Reemplazar saltos de línea por <br>
            $mensajeBot = str_replace("\n", "<br>", $mensajeBot);

            // Reemplazar tabulaciones por espacios HTML
            $mensajeBot = str_replace("Pienso... ", "", $mensajeBot);

            $mensajeBot = str_replace('html', '', $mensajeBot);
            $mensajeBot = str_replace('```', '', $mensajeBot);

            $mensajeBot = str_replace("\t", "&nbsp;&nbsp;&nbsp;&nbsp;", $mensajeBot);

            // Convertir **negrita** en <b>negrita</b> usando expresiones regulares
            $mensajeBot = preg_replace('/\*\*(.*?)\*\*/', '<b>$1</b>', $mensajeBot);
            $mensajeBot = trim($mensajeBot);

            $mensajes[] = [
                'role' => 'assistant',
                'content' => $mensajeBot,
            ];

            // Guardar los mensajes en la sesión
            $chatSession->update(['messages' => $mensajes]);



            return response()->json(['mensaje_bot' => $mensajeBot, 'response' => $response]);
        }

        return response()->json(['error' => 'Error en la API'], 500);
    }
}

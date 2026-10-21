<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientNoteRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ClientNoteController extends Controller
{
    public function store(StoreClientNoteRequest $request, User $cliente): JsonResponse
    {
        abort_unless($cliente->hasRole('Cliente'), 404, 'Cliente no encontrado.');

        $author = $request->user();
        $note = $cliente->notes()->create([
            'user_id' => $author->id,
            'note' => $request->validated('note'),
        ]);

        return response()->json([
            'message' => 'Nota creada correctamente.',
            'data' => [
                'id' => $note->id,
                'client_id' => $note->client_id,
                'note' => $note->note,
                'author' => ['id' => $author->id, 'name' => $author->name],
                'created_at' => $note->created_at->toIso8601String(),
            ],
        ], 201)->header('Cache-Control', 'no-store, private');
    }
}

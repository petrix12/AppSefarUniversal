<?php

namespace App\Http\Controllers;

use App\Models\ClientChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientChatController extends Controller
{
    public function messages(Request $request, User $user): JsonResponse
    {
        $this->authorizeChatAccess();

        $afterId = (int) $request->query('after_id', 0);

        $messages = ClientChatMessage::query()
            ->with('author:id,name')
            ->where('client_id', $user->id)
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(fn (ClientChatMessage $message) => $this->formatMessage($message));

        return response()->json([
            'messages' => $messages,
        ]);
    }

    public function storeMessage(Request $request, User $user): JsonResponse
    {
        $this->authorizeChatAccess();

        $data = $request->validate([
            'message' => 'required|string|max:2000',
        ]);

        $message = ClientChatMessage::create([
            'client_id' => $user->id,
            'user_id' => auth()->id(),
            'message' => trim($data['message']),
        ])->load('author:id,name');

        return response()->json([
            'message' => $this->formatMessage($message),
        ], 201);
    }

    private function authorizeChatAccess(): void
    {
        if (auth()->user()?->hasRole('Cliente')) {
            abort(403, 'No tienes acceso al chat interno de clientes.');
        }
    }

    private function formatMessage(ClientChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'message' => $message->message,
            'author' => $message->author?->name ?? 'Usuario eliminado',
            'is_mine' => $message->user_id === auth()->id(),
            'created_at' => optional($message->created_at)->format('d/m/Y H:i'),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RoleAiChatSession;
use App\Models\RoleAiKnowledgeEntry;
use App\Services\RoleAiAssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class RoleAiAssistantController extends Controller
{
    public function __construct(private readonly RoleAiAssistantService $assistants)
    {
    }

    public function access(Request $request): JsonResponse
    {
        $assistants = $this->assistants->availableAssistantsForUser($request->user());

        return response()->json([
            'assistants' => $assistants->map(fn ($assistant) => $this->assistantPayload($assistant))->values(),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assistant_id' => ['nullable', 'integer'],
        ]);

        $assistant = $this->assistants->assistantForUser($request->user(), $data['assistant_id'] ?? null);

        if (! $assistant) {
            return response()->json(['message' => 'No tienes un asistente IA disponible para tus roles.'], 403);
        }

        $session = $this->assistants->createSession($assistant, $request->user());

        return response()->json([
            'session_id' => $session->session_id,
            'assistant' => $this->assistantPayload($assistant),
        ]);
    }

    public function message(Request $request): JsonResponse
    {
        $data = $request->validate([
            'session_id' => ['required', 'string'],
            'mensaje' => ['required', 'string', 'max:4000'],
            'screen_context' => ['nullable', 'string', 'max:8000'],
        ]);

        $session = RoleAiChatSession::with('assistant.role')
            ->where('session_id', $data['session_id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $session) {
            return response()->json(['message' => 'Sesion de IA no encontrada.'], 404);
        }

        if ($session->expires_at && $session->expires_at->isPast()) {
            return response()->json(['message' => 'La sesion expiro. Abre el asistente de nuevo.'], 419);
        }

        if (! $this->assistants->userCanAccessAssistant($request->user(), $session->assistant)) {
            return response()->json(['message' => 'No tienes acceso a este asistente.'], 403);
        }

        try {
            $answer = $this->assistants->reply(
                $session,
                $request->user(),
                $data['mensaje'],
                $data['screen_context'] ?? null
            );
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'No se pudo contactar la IA. Revisa la configuracion de OpenRouter o intenta nuevamente.',
            ], 500);
        }

        return response()->json([
            'mensaje_bot' => $answer,
        ]);
    }

    public function knowledge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assistant_id' => ['nullable', 'integer'],
        ]);

        $assistant = $this->assistants->assistantForUser($request->user(), $data['assistant_id'] ?? null);

        if (! $assistant) {
            return response()->json(['message' => 'No tienes acceso a este asistente.'], 403);
        }

        $entries = $assistant->activeKnowledgeEntries()
            ->with('createdBy')
            ->latest()
            ->limit(12)
            ->get();

        return response()->json([
            'knowledge' => $entries->map(fn (RoleAiKnowledgeEntry $entry) => [
                'id' => $entry->id,
                'title' => $entry->title,
                'excerpt' => $entry->excerpt(),
                'created_by' => $entry->createdBy?->name,
                'created_at' => optional($entry->created_at)->format('d/m/Y H:i'),
            ]),
        ]);
    }

    public function train(Request $request): JsonResponse
    {
        $data = $request->validate([
            'assistant_id' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:160'],
            'content' => ['required', 'string', 'min:10', 'max:12000'],
        ]);

        $assistant = $this->assistants->assistantForUser($request->user(), $data['assistant_id'] ?? null);

        if (! $assistant) {
            return response()->json(['message' => 'No tienes acceso a este asistente.'], 403);
        }

        if (! $assistant->training_enabled) {
            return response()->json(['message' => 'El entrenamiento esta desactivado para este asistente.'], 422);
        }

        $entry = $assistant->knowledgeEntries()->create([
            'user_id' => $request->user()->id,
            'title' => $data['title'] ?? null,
            'content' => $data['content'],
            'status' => 'active',
            'metadata' => [
                'source' => 'user_training',
                'ip' => $request->ip(),
            ],
        ]);

        return response()->json([
            'message' => 'Contexto guardado. Este asistente lo usara en sus proximas respuestas.',
            'knowledge' => [
                'id' => $entry->id,
                'title' => $entry->title,
                'excerpt' => $entry->excerpt(),
                'created_by' => $request->user()->name,
                'created_at' => optional($entry->created_at)->format('d/m/Y H:i'),
            ],
        ], 201);
    }

    private function assistantPayload($assistant): array
    {
        $assistant->loadMissing('role');

        return [
            'id' => $assistant->id,
            'name' => $assistant->name,
            'role' => $assistant->role?->name,
            'training_enabled' => $assistant->training_enabled,
        ];
    }
}

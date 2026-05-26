<?php

namespace App\Http\Controllers;

use App\Models\ClientChatMessage;
use App\Models\ClientChatAttachment;
use App\Models\User;
use App\Notifications\ClientAppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ClientChatController extends Controller
{
    public function messages(Request $request, User $user): JsonResponse
    {
        $this->authorizeChatAccess();

        $afterId = (int) $request->query('after_id', 0);

        $messages = ClientChatMessage::query()
            ->with(['author:id,name', 'attachments'])
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
            'message' => 'nullable|string|max:2000',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:20480',
        ], [
            'attachments.max' => 'Puedes subir hasta 5 archivos por mensaje.',
            'attachments.*.file' => 'Uno de los adjuntos no es un archivo valido.',
            'attachments.*.max' => 'Cada archivo debe pesar maximo 20 MB.',
        ]);

        $messageText = trim((string) ($data['message'] ?? ''));
        $files = $request->file('attachments', []);
        $files = is_array($files) ? array_values(array_filter($files)) : [$files];

        if ($messageText === '' && count($files) === 0) {
            return response()->json([
                'message' => 'Escribe un mensaje o adjunta al menos un archivo.',
            ], 422);
        }

        $message = ClientChatMessage::create([
            'client_id' => $user->id,
            'user_id' => auth()->id(),
            'message' => $messageText,
        ]);

        $uploadedPaths = [];

        try {
            foreach ($files as $file) {
                $extension = $file->getClientOriginalExtension();
                $filename = Str::uuid()->toString() . ($extension ? ".{$extension}" : '');
                $path = Storage::disk('s3')->putFileAs(
                    "client-chat/{$user->id}/{$message->id}",
                    $file,
                    $filename,
                    'public'
                );

                abort_unless($path, 500, 'No se pudo subir el archivo al almacenamiento S3.');
                $uploadedPaths[] = $path;

                $message->attachments()->create([
                    'disk' => 's3',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize() ?: 0,
                ]);
            }
        } catch (Throwable $exception) {
            foreach ($uploadedPaths as $uploadedPath) {
                Storage::disk('s3')->delete($uploadedPath);
            }

            $message->delete();

            throw $exception;
        }

        $message->load(['author:id,name', 'attachments']);
        $this->notifyUnreadChatParticipants($message, $user);

        return response()->json([
            'message' => $this->formatMessage($message),
        ], 201);
    }

    public function downloadChatAttachment(User $user, ClientChatAttachment $attachment)
    {
        $this->authorizeChatAccess();

        $attachment->loadMissing('message');

        abort_unless($attachment->message && (int) $attachment->message->client_id === (int) $user->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
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
            'attachments' => $message->attachments->map(fn (ClientChatAttachment $attachment) => [
                'id' => $attachment->id,
                'name' => $attachment->original_name,
                'mime_type' => $attachment->mime_type,
                'size' => $attachment->size,
                'size_label' => $this->formatBytes((int) $attachment->size),
                'download_url' => route('crud.users.internal-chat.attachments.download', [$message->client_id, $attachment]),
            ])->values(),
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    private function notifyUnreadChatParticipants(ClientChatMessage $message, User $client): void
    {
        try {
            $notificationsReady = Schema::hasTable('notifications');
        } catch (Throwable) {
            $notificationsReady = false;
        }

        if (! $notificationsReady) {
            return;
        }

        $recipientIds = ClientChatMessage::query()
            ->where('client_id', $client->id)
            ->whereNotNull('user_id')
            ->where('user_id', '!=', auth()->id())
            ->distinct()
            ->pluck('user_id');

        if (! empty($client->owner_id) && (int) $client->owner_id !== (int) auth()->id()) {
            $recipientIds->push((int) $client->owner_id);
        }

        $recipients = User::query()
            ->whereIn('id', $recipientIds->unique()->values())
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Cliente'))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $preview = trim((string) $message->message);
        $preview = $preview !== ''
            ? Str::limit($preview, 180)
            : 'Se adjunto un archivo al chat interno.';

        foreach ($recipients as $recipient) {
            $recipient->notify(new ClientAppNotification(
                title: 'Nuevo mensaje interno sobre ' . ($client->name ?: "cliente #{$client->id}"),
                body: $preview,
                actionUrl: route('crud.users.edit', $client) . '#client-chat',
                actionText: 'Abrir chat',
                category: 'internal_chat',
            ));
        }
    }
}

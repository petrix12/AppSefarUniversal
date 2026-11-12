<?php

namespace App\Http\Controllers;

use App\Models\BrightbeanMessage;
use App\Models\BrightbeanOutboundMessage;
use App\Models\BrightbeanSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BrightbeanInboxController extends Controller
{
    public function inbox(Request $request)
    {
        $query = $request->validate(['conversation' => ['nullable', 'uuid']]);
        $conversation = $query['conversation'] ?? null;
        $target = $conversation ? BrightbeanMessage::where('conversation_id', $conversation)
            ->where('internal', false)->where('status', '<>', 'deleted')->latest('source_created_at')->first() : null;

        return view('admin.integrations.brightbean', [
            'settings' => BrightbeanSetting::current(),
            'conversation' => $conversation,
            'replyTarget' => $target,
            'replyRequestId' => (string) Str::uuid(),
            'outbound' => $target ? BrightbeanOutboundMessage::where('workspace_id', $target->workspace_id)
                ->where('conversation_id', $target->conversation_id)->latest()->limit(30)->get() : collect(),
            'messages' => BrightbeanMessage::query()
                ->when($query['conversation'] ?? null, fn ($q, $id) => $q->where('conversation_id', $id))
                ->orderByDesc('source_created_at')->orderByDesc('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function send(Request $request, BrightbeanMessage $message)
    {
        app(\App\Services\ClientRestrictionService::class)->assertContactDeliverable($message->contact ?? []);
        abort_if($message->internal || $message->status === 'deleted', 422, 'Selecciona un mensaje público de la conversación.');
        $data = $request->validate([
            'request_id' => ['required', 'uuid'],
            'text' => ['required', 'string', 'max:4000', function ($attribute, $value, $fail) {
                if (trim($value) === '') {
                    $fail('Escribe una respuesta.');
                }
            }],
        ]);
        $outbound = BrightbeanOutboundMessage::firstOrCreate(['request_id' => $data['request_id']], [
            'created_by_user_id' => $request->user()->id,
            'workspace_id' => $message->workspace_id,
            'conversation_id' => $message->conversation_id,
            'contact_id' => $message->contact_id,
            'connection_id' => $message->connection_id,
            'text' => trim($data['text']),
        ]);
        if ((int) $outbound->created_by_user_id !== (int) $request->user()->id
            || $outbound->workspace_id !== $message->workspace_id
            || $outbound->conversation_id !== $message->conversation_id
            || $outbound->text !== trim($data['text'])) {
            throw ValidationException::withMessages(['text' => 'Este envío ya se utilizó. Recarga la conversación antes de escribir otra respuesta.']);
        }

        return redirect()->route('admin.integrations.brightbean.index', ['conversation' => $message->conversation_id])
            ->with('success', 'Respuesta en cola. BrightBean la enviará por el canal de esta conversación.');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:10000'],
        ]);
        BrightbeanSetting::current()->update($data);

        return back()->with('success', 'Configuración de BrightBean actualizada.');
    }
}

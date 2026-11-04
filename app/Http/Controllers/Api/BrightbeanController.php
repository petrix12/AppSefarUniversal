<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrightbeanMessage;
use App\Models\BrightbeanSetting;
use App\Services\BrightbeanAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class BrightbeanController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messages' => ['present', 'array', 'max:50'],
            'messages.*' => ['array:workspace_id,external_id,conversation_id,contact_id,connection_id,platform,direction,source,status,internal,text,body,contact,source_created_at,source_updated_at'],
            'messages.*.workspace_id' => ['required', 'uuid'],
            'messages.*.external_id' => ['required', 'uuid'],
            'messages.*.conversation_id' => ['required', 'uuid'],
            'messages.*.contact_id' => ['required', 'uuid'],
            'messages.*.connection_id' => ['required', 'uuid'],
            'messages.*.platform' => ['required', 'string', 'max:32'],
            'messages.*.direction' => ['required', 'in:in,out'],
            'messages.*.source' => ['nullable', 'string', 'max:32'],
            'messages.*.status' => ['required', 'in:queued,sent,delivered,read,failed,deleted'],
            'messages.*.internal' => ['required', 'boolean'],
            'messages.*.text' => ['nullable', 'string', 'max:20000'],
            'messages.*.body' => ['present', 'array'],
            'messages.*.contact' => ['present', 'array'],
            'messages.*.contact.first_name' => ['nullable', 'string', 'max:255'],
            'messages.*.contact.last_name' => ['nullable', 'string', 'max:255'],
            'messages.*.contact.email' => ['nullable', 'string', 'max:255'],
            'messages.*.contact.phone' => ['nullable', 'string', 'max:255'],
            'messages.*.source_created_at' => ['required', 'date'],
            'messages.*.source_updated_at' => ['required', 'date'],
        ]);

        $result = DB::transaction(function () use ($data): array {
            $result = [];
            foreach ($data['messages'] as $entry) {
                $identity = ['workspace_id' => $entry['workspace_id'], 'external_id' => $entry['external_id']];
                $message = BrightbeanMessage::firstOrCreate($identity, $entry);
                $message = BrightbeanMessage::lockForUpdate()->findOrFail($message->id);
                if (Carbon::parse($entry['source_updated_at'])->greaterThanOrEqualTo($message->source_updated_at)) {
                    if ($entry['status'] === 'deleted') {
                        $entry['text'] = null;
                        $entry['body'] = [];
                        $message->forceFill(['ai_reply' => null, 'ai_replied_at' => null]);
                    }
                    $message->fill($entry)->save();
                }
                $result[] = ['id' => $message->id, 'external_id' => $message->external_id];
            }
            BrightbeanSetting::current()->update(['last_synced_at' => now()]);

            return $result;
        });

        return response()->json(['data' => $result], 200)->header('Cache-Control', 'no-store, private');
    }

    public function reply(BrightbeanMessage $message, BrightbeanAiService $ai): JsonResponse
    {
        try {
            $reply = $ai->reply($message);
        } catch (Throwable $exception) {
            // Provider errors may contain credentials or conversation text.
            report(new \RuntimeException('Falló la respuesta de IA de BrightBean: '.get_class($exception)));

            return response()->json(['message' => 'No se pudo generar la respuesta. Reintenta más tarde.'], 502);
        }

        return response()->json([
            'data' => ['reply' => $reply, 'idempotency_key' => 'sefar-ai:'.$message->workspace_id.':'.$message->external_id],
        ])->header('Cache-Control', 'no-store, private');
    }
}

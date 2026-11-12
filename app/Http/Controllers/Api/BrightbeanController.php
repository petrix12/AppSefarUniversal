<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrightbeanMessage;
use App\Models\BrightbeanOutboundMessage;
use App\Models\BrightbeanSetting;
use App\Services\BrightbeanAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class BrightbeanController extends Controller
{
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'uuid'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);
        $rows = DB::transaction(function () use ($data) {
            $query = BrightbeanOutboundMessage::where('workspace_id', $data['workspace_id'])
                ->when($data['conversation_id'] ?? null, fn ($q, $id) => $q->where('conversation_id', $id))
                ->where(fn ($q) => $q->where('status', 'queued')
                    ->orWhere(fn ($q) => $q->where('status', 'processing')->where('leased_until', '<=', now())));
            $rows = $query->orderBy('id')->limit(20)->lockForUpdate()->get();
            foreach ($rows as $row) {
                $target = BrightbeanMessage::where('workspace_id', $row->workspace_id)
                    ->where('contact_id', $row->contact_id)->latest('id')->first();
                try {
                    if ($target) {
                        app(\App\Services\ClientRestrictionService::class)->assertContactDeliverable($target->contact ?? []);
                    }
                } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                    $row->update(['status' => 'failed', 'error_code' => 'client_restricted', 'lease_token' => null, 'leased_until' => null]);
                    continue;
                }
                if ($row->attempts >= 5) {
                    $row->update(['status' => 'failed', 'error_code' => 'retry_limit', 'lease_token' => null, 'leased_until' => null]);
                    continue;
                }
                $row->update(['status' => 'processing', 'attempts' => $row->attempts + 1,
                    'lease_token' => (string) Str::uuid(), 'leased_until' => now()->addMinutes(5)]);
            }

            return $rows->where('status', 'processing')->map(fn ($row) => $row->only([
                'id', 'request_id', 'workspace_id', 'conversation_id', 'contact_id', 'connection_id', 'text', 'lease_token',
            ]))->values();
        });

        return response()->json(['data' => $rows])->header('Cache-Control', 'no-store, private');
    }

    public function acknowledge(Request $request, BrightbeanOutboundMessage $outbound): JsonResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'uuid'],
            'lease_token' => ['required', 'uuid'],
            'status' => ['required', 'in:submitted,failed'],
            'external_id' => ['nullable', 'required_if:status,submitted', 'uuid'],
            'error_code' => ['nullable', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_:-]+$/'],
        ]);
        DB::transaction(function () use ($outbound, $data) {
            $row = BrightbeanOutboundMessage::lockForUpdate()->findOrFail($outbound->id);
            abort_unless($row->workspace_id === $data['workspace_id'], 404);
            abort_unless($row->lease_token && hash_equals($row->lease_token, $data['lease_token']), 409);
            if (in_array($row->status, ['submitted', 'failed'], true)) {
                abort_unless($row->status === $data['status'] && $row->external_id === ($data['external_id'] ?? null), 409);
                return;
            }
            abort_unless($row->status === 'processing', 409);
            $row->update(['status' => $data['status'], 'external_id' => $data['external_id'] ?? null,
                'error_code' => $data['status'] === 'failed' ? ($data['error_code'] ?? 'brightbean_rejected') : null,
                'leased_until' => null]);
        });

        return response()->json(['data' => ['status' => $data['status']]])->header('Cache-Control', 'no-store, private');
    }

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

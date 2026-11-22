<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KalendiaWebhookController extends Controller
{
    public const APPOINTMENT_EVENTS = ['appointment.created', 'appointment.confirmed', 'appointment.rescheduled', 'appointment.cancelled'];

    public function __invoke(Request $request)
    {
        $secret = (string) config('kalendia.webhook_secret');
        $business = (string) config('kalendia.webhook_business_id');
        if ($secret === '') return response()->json(['received' => false], 503);
        $body = $request->getContent();
        if (strlen($body) > 32768) return response()->json(['received' => false], 413);
        $timestamp = (string) $request->header('X-Kalendia-Timestamp');
        $delivery = (string) $request->header('X-Kalendia-Delivery');
        $type = (string) $request->header('X-Kalendia-Event');
        $signature = (string) $request->header('X-Kalendia-Signature');
        if (!preg_match('/^[0-9]{10,11}$/D', $timestamp) || strlen($delivery) > 255 || $delivery === '' || strlen($type) > 80
            || !preg_match('/^v1=[a-f0-9]{64}$/D', $signature) || abs(time() - (int) $timestamp) > 300) {
            return response()->json(['received' => false], 401);
        }
        $expected = 'v1='.hash_hmac('sha256', $timestamp.'.'.$delivery.'.'.$type.'.'.$body, $secret);
        if (!hash_equals($expected, $signature)) return response()->json(['received' => false], 401);
        // Only inspect JSON after verifying the signature over the original bytes.
        try { $event = json_decode($body, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { return response()->json(['received' => false], 400); }
        if (!is_array($event)) return response()->json(['received' => false], 400);
        $validator = Validator::make($event, [
            'event_id' => ['required', 'string', 'max:255'], 'business_id' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:80'], 'event_version' => ['required', 'integer', 'in:1'],
        ]);
        if ($validator->fails() || ($event['event_type'] ?? null) !== $type) return response()->json(['received' => false], 422);
        if ($business !== '' && !hash_equals($business, $event['business_id'])) return response()->json(['received' => false], 403);
        $business = $event['business_id'];
        if (!in_array($type, self::APPOINTMENT_EVENTS, true)) {
            $catalog = app(\App\Services\PlatformCatalog::class);
            $resource = $catalog->kalendiaEvent($type);
            if (!$resource) return response()->json(['received' => true, 'ignored' => 'event_type']);
            $id = str_starts_with($type,'customer.note_') ? data_get($event,'data.customer_id') : ($event['resource_id'] ?? null);
            if (!$catalog->validId('kalendia',$resource,$id)) return response()->json(['received'=>false],422);
            if (strlen($event['event_id'])>190) return response()->json(['received'=>false],422);
            if (!\Illuminate\Support\Facades\Schema::hasTable('platform_events')) return response()->json(['received'=>false],503);
            $created = \App\Services\PlatformEventProcessor::receive('kalendia','main',$event['event_id'],$type,$resource,$id);
            return response()->json(['received'=>true,'duplicate'=>!$created],$created ? 202 : 200)->header('Cache-Control','no-store');
        }
        $validator = Validator::make($event, ['resource_id' => ['required','string','max:255','regex:/^apt_[A-Za-z0-9_-]+$/D'],
            'occurred_at' => ['required','string','max:64','date']]);
        if ($validator->fails() || (isset($event['data']['appointment_id']) && $event['data']['appointment_id'] !== $event['resource_id'])) {
            return response()->json(['received' => false], 422);
        }
        // Persist references only: no contact details, payment data or webhook body.
        $created = DB::table('kalendia_webhook_events')->insertOrIgnore([
            'event_id' => $event['event_id'], 'business_id' => $business, 'event_type' => $type,
            'resource_id' => $event['resource_id'], 'occurred_at' => CarbonImmutable::parse($event['occurred_at'])->utc()->format('Y-m-d H:i:s'),
            'status' => 'pending', 'next_attempt_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $stored = DB::table('kalendia_webhook_events')->where('event_id', $event['event_id'])->first();
        if (!$stored || $stored->resource_id !== $event['resource_id'] || $stored->event_type !== $type || $stored->business_id !== $business) {
            return response()->json(['received' => false], 409);
        }
        return response()->json(['received' => true, 'duplicate' => !$created], $created ? 202 : 200)->header('Cache-Control', 'no-store');
    }
}

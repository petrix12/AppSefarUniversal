<?php

namespace App\Services;

use Illuminate\Http\Request;

class CrmWebhookSignature
{
    public function verify(Request $request, string $provider): void
    {
        abort_unless(config("crm_sync.$provider.enabled"), 503);
        if ($provider === 'teamleader') {
            $secret = (string) config('crm_sync.teamleader.secret');
            abort_unless(strlen($secret) >= 32 && hash_equals($secret, (string) $request->query('token')), 401);
            return;
        }
        $secret = (string) config('crm_sync.hubspot.client_secret');
        abort_unless($secret !== '', 401);
        // Pin the version: a missing/invalid v3 header must never downgrade to v1.
        if (config('crm_sync.hubspot.signature_version') === 'v1') {
            $expected = hash('sha256', $secret.$request->getContent());
            abort_unless(hash_equals($expected, (string) $request->header('X-HubSpot-Signature')), 401);
            return;
        }
        $timestamp = (string) $request->header('X-HubSpot-Request-Timestamp');
        abort_unless(ctype_digit($timestamp) && abs(now()->getTimestampMs() - (int) $timestamp) <= 300000, 401);
        $uri = preg_replace_callback('/%(3A|2F|3F|40|21|24|27|28|29|2A|2C|3B)/i',
            fn ($match) => rawurldecode($match[0]), $request->fullUrl());
        $expected = base64_encode(hash_hmac('sha256', $request->method().$uri.$request->getContent().$timestamp, $secret, true));
        abort_unless(hash_equals($expected, (string) $request->header('X-HubSpot-Signature-V3')), 401);
    }
}

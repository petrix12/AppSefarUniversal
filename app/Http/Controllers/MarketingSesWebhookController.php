<?php

namespace App\Http\Controllers;

use App\Models\MarketingCampaignEvent;
use App\Models\MarketingCampaignRecipient;
use App\Models\MarketingContact;
use App\Models\MarketingWebhookEvent;
use App\Services\SnsMessageVerifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MarketingSesWebhookController extends Controller
{
    public function __invoke(Request $request, SnsMessageVerifier $verifier)
    {
        $message = json_decode($request->getContent(), true);
        if (!is_array($message) || empty($message['MessageId']) || !$verifier->verify($message)) {
            abort(403, 'Notificación SNS no verificada.');
        }

        $webhook = MarketingWebhookEvent::firstOrCreate(
            ['provider_message_id' => (string) ($message['MessageId'] ?? '')],
            [
                'provider' => 'ses_sns',
                'event_type' => $message['Type'] ?? null,
                'payload' => $message,
                'received_at' => now(),
            ]
        );
        if (!$webhook->wasRecentlyCreated || ($message['Type'] ?? '') !== 'Notification') {
            return response()->json(['ok' => true]);
        }

        $event = json_decode((string) ($message['Message'] ?? ''), true);
        if (is_array($event)) {
            $this->recordSesEvent($event);
        }

        return response()->json(['ok' => true]);
    }

    private function recordSesEvent(array $event): void
    {
        $eventType = ucfirst(strtolower((string) ($event['eventType'] ?? $event['notificationType'] ?? '')));
        $messageId = data_get($event, 'mail.messageId');
        $token = data_get($event, 'mail.tags.marketing_recipient_token.0');
        $campaignId = data_get($event, 'mail.tags.marketing_campaign_id.0');
        if (!$messageId && !$token) {
            return;
        }
        $recipient = MarketingCampaignRecipient::query()
            ->when($messageId, fn ($query) => $query->where('ses_message_id', $messageId))
            ->when(!$messageId && $token, fn ($query) => $query->where('tracking_token', $token))
            ->first();
        if (!$recipient || ($campaignId && (string) $recipient->marketing_campaign_id !== (string) $campaignId)) {
            return;
        }

        $key = strtolower($eventType);
        $timestamp = data_get($event, "{$key}.timestamp") ?: data_get($event, 'mail.timestamp');
        try {
            $occurredAt = $timestamp ? Carbon::parse($timestamp) : now();
        } catch (\Throwable) {
            $occurredAt = now();
        }
        $field = match ($eventType) {
            'Delivery' => 'delivered_at', 'Open' => 'opened_at', 'Click' => 'clicked_at',
            'Bounce' => 'bounced_at', 'Complaint' => 'complained_at', default => null,
        };
        if (!$field) {
            return;
        }

        $changes = [];
        if (!$recipient->{$field}) {
            $changes[$field] = $occurredAt;
        }
        if ($eventType === 'Delivery') {
            $changes['status'] = 'delivered';
        } elseif ($eventType === 'Bounce') {
            $changes['status'] = 'bounced';
        } elseif ($eventType === 'Complaint') {
            $changes['status'] = 'complained';
        }
        if ($changes) {
            $recipient->update($changes);
        }
        if (in_array($eventType, ['Bounce', 'Complaint'], true)) {
            MarketingContact::query()->whereKey($recipient->marketing_contact_id)->update([
                'suppressed_at' => now(),
                'suppression_reason' => strtolower($eventType),
            ]);
        }
        MarketingCampaignEvent::create([
            'marketing_campaign_id' => $recipient->marketing_campaign_id,
            'marketing_campaign_recipient_id' => $recipient->id,
            'event_type' => strtolower($eventType),
            'occurred_at' => $occurredAt,
            'metadata' => $event,
        ]);
    }
}

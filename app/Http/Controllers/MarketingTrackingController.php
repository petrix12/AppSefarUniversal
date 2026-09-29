<?php

namespace App\Http\Controllers;

use App\Models\MarketingCampaignEvent;
use App\Models\MarketingCampaignRecipient;
use App\Services\MarketingEmailRenderer;
use Illuminate\Http\Request;

class MarketingTrackingController extends Controller
{
    public function open(string $recipient)
    {
        $model = MarketingCampaignRecipient::query()->where('tracking_token', $recipient)->first();
        if ($model) {
            $this->record($model, 'open', ['ip' => request()->ip(), 'user_agent' => request()->userAgent()]);
        }
        return response(base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='), 200, ['Content-Type' => 'image/gif', 'Cache-Control' => 'no-store, private']);
    }

    public function click(Request $request, string $recipient, string $url, string $signature, MarketingEmailRenderer $renderer)
    {
        $destination = base64_decode(strtr($url, '-_', '+/').str_repeat('=', (4 - strlen($url) % 4) % 4), true);
        if (!$destination || !filter_var($destination, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $destination)
            || !$renderer->validClickSignature($recipient, $destination, $signature)) {
            abort(404);
        }
        $model = MarketingCampaignRecipient::query()->where('tracking_token', $recipient)->first();
        if ($model) {
            $this->record($model, 'click', ['url' => $destination, 'ip' => $request->ip(), 'user_agent' => $request->userAgent()]);
        }
        return redirect()->away($destination);
    }

    public function unsubscribeForm(string $recipient)
    {
        $model = MarketingCampaignRecipient::query()->where('tracking_token', $recipient)->firstOrFail();
        return view('marketing.unsubscribe', compact('model'));
    }

    public function unsubscribe(Request $request, string $recipient)
    {
        $model = MarketingCampaignRecipient::query()->with('contact')->where('tracking_token', $recipient)->firstOrFail();
        $model->contact?->update(['unsubscribed_at' => now()]);
        $model->update(['unsubscribed_at' => now(), 'status' => 'unsubscribed']);
        MarketingCampaignEvent::create([
            'marketing_campaign_id' => $model->marketing_campaign_id,
            'marketing_campaign_recipient_id' => $model->id,
            'event_type' => 'unsubscribe',
            'occurred_at' => now(),
            'metadata' => ['ip' => $request->ip()],
        ]);
        return view('marketing.unsubscribed');
    }

    private function record(MarketingCampaignRecipient $recipient, string $event, array $metadata): void
    {
        $field = $event === 'open' ? 'opened_at' : 'clicked_at';
        if (!$recipient->{$field}) {
            $recipient->update([$field => now()]);
        }
        MarketingCampaignEvent::create([
            'marketing_campaign_id' => $recipient->marketing_campaign_id,
            'marketing_campaign_recipient_id' => $recipient->id,
            'event_type' => $event,
            'occurred_at' => now(),
            'metadata' => $metadata,
        ]);
    }
}

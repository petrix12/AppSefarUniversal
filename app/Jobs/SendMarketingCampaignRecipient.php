<?php

namespace App\Jobs;

use App\Models\MarketingCampaignRecipient;
use App\Services\MarketingCampaignDispatcher;
use App\Services\MarketingEmailRenderer;
use App\Services\MarketingSesSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMarketingCampaignRecipient implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 300];
    public int $timeout = 60;

    public function __construct(public readonly int $recipientId)
    {
    }

    public function handle(MarketingEmailRenderer $renderer, MarketingSesSender $ses, MarketingCampaignDispatcher $dispatcher): void
    {
        $recipient = MarketingCampaignRecipient::query()->with(['campaign', 'contact'])->find($this->recipientId);
        if (!$recipient || !$recipient->campaign || $recipient->campaign->status !== 'sending') {
            return;
        }
        if (!$recipient->contact?->isEmailable()) {
            $recipient->update(['status' => 'skipped', 'failure_reason' => 'Contacto dado de baja o suprimido antes del envío.']);
            $dispatcher->finishIfComplete($recipient->campaign);
            return;
        }
        if (MarketingCampaignRecipient::query()->whereKey($recipient->id)->where('status', 'pending')->update(['status' => 'sending']) !== 1) {
            return;
        }

        try {
            $rendered = $renderer->render($recipient->campaign, $recipient);
            $messageId = $ses->send($recipient->campaign, $recipient->email, $rendered + ['recipient_token' => $recipient->tracking_token]);
            $recipient->update(['status' => 'sent', 'ses_message_id' => $messageId, 'sent_at' => now(), 'failure_reason' => null]);
            $dispatcher->finishIfComplete($recipient->campaign);
        } catch (\Throwable $exception) {
            $recipient->update(['status' => 'pending', 'failure_reason' => mb_substr($exception->getMessage(), 0, 500)]);
            Log::warning('No fue posible enviar correo masivo por SES.', ['recipient_id' => $recipient->id, 'error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $recipient = MarketingCampaignRecipient::query()->with('campaign')->find($this->recipientId);
        if (!$recipient) {
            return;
        }
        $recipient->update(['status' => 'failed', 'failure_reason' => mb_substr($exception->getMessage(), 0, 500)]);
        if ($recipient->campaign) {
            app(MarketingCampaignDispatcher::class)->finishIfComplete($recipient->campaign);
        }
    }
}

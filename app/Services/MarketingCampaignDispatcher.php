<?php

namespace App\Services;

use App\Jobs\SendMarketingCampaignRecipient;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingCampaignDispatcher
{
    public function queue(MarketingCampaign $campaign): int
    {
        DB::transaction(function () use ($campaign): void {
            $campaign = MarketingCampaign::query()->lockForUpdate()->findOrFail($campaign->id);
            if (!in_array($campaign->status, [MarketingCampaign::STATUS_DRAFT, MarketingCampaign::STATUS_SCHEDULED, MarketingCampaign::STATUS_PAUSED], true)) {
                throw new \RuntimeException('Esta campaña ya fue enviada o se encuentra en proceso.');
            }
            if (!$campaign->marketing_list_id) {
                throw new \RuntimeException('Selecciona una lista de destinatarios antes de enviar.');
            }

            if ($campaign->recipients()->doesntExist()) {
                $campaign->list->members()->with('contact')->orderBy('id')->each(function ($member) use ($campaign): void {
                    $contact = $member->contact;
                    if (!$contact || !$contact->isEmailable()) {
                        return;
                    }
                    MarketingCampaignRecipient::firstOrCreate(
                        ['marketing_campaign_id' => $campaign->id, 'email' => $contact->email],
                        [
                            'marketing_contact_id' => $contact->id,
                            'first_name' => $contact->first_name,
                            'last_name' => $contact->last_name,
                            'attributes' => $contact->getAttribute('attributes'),
                            'tracking_token' => (string) Str::uuid(),
                        ]
                    );
                });
            }
            if ($campaign->recipients()->doesntExist()) {
                throw new \RuntimeException('La lista no contiene destinatarios que puedan recibir correos.');
            }

            $campaign->update(['status' => MarketingCampaign::STATUS_SENDING, 'started_at' => now(), 'finished_at' => null]);
        });

        $pending = MarketingCampaignRecipient::query()
            ->where('marketing_campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->pluck('id');
        foreach ($pending as $recipientId) {
            SendMarketingCampaignRecipient::dispatch($recipientId)
                ->onConnection(config('marketing.queue_connection'))
                ->onQueue(config('marketing.queue_name'));
        }

        return $pending->count();
    }

    public function finishIfComplete(MarketingCampaign $campaign): void
    {
        if ($campaign->status === MarketingCampaign::STATUS_SENDING
            && !$campaign->recipients()->whereIn('status', ['pending', 'sending'])->exists()) {
            $campaign->update(['status' => MarketingCampaign::STATUS_SENT, 'finished_at' => now()]);
        }
    }
}

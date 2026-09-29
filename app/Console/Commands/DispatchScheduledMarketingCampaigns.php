<?php

namespace App\Console\Commands;

use App\Models\MarketingCampaign;
use App\Services\MarketingCampaignDispatcher;
use Illuminate\Console\Command;

class DispatchScheduledMarketingCampaigns extends Command
{
    protected $signature = 'marketing:dispatch-scheduled';
    protected $description = 'Encola las campañas masivas cuya hora programada ya llegó.';

    public function handle(MarketingCampaignDispatcher $dispatcher): int
    {
        MarketingCampaign::query()
            ->where('status', MarketingCampaign::STATUS_SCHEDULED)
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->each(function (MarketingCampaign $campaign) use ($dispatcher): void {
                try {
                    $dispatcher->queue($campaign);
                    $this->info("Campaña {$campaign->id} encolada.");
                } catch (\Throwable $exception) {
                    $this->error("Campaña {$campaign->id}: {$exception->getMessage()}");
                }
            });

        return self::SUCCESS;
    }
}

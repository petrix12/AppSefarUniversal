<?php

namespace App\Observers;

use App\Services\HubspotOutboxService;
use App\Services\HubspotSyncOrigin;
use Illuminate\Database\Eloquent\Model;

class HubspotOutboundObserver
{
    public function created(Model $model): void
    {
        $this->queue($model, array_keys($model->getAttributes()));
    }

    public function updated(Model $model): void
    {
        $this->queue($model, array_keys($model->getChanges()));
    }

    private function queue(Model $model, array $fields): void
    {
        if (HubspotSyncOrigin::isRemoteWrite()) return;
        app(HubspotOutboxService::class)->queue($model, $fields);
    }
}

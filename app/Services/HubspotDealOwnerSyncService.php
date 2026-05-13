<?php

namespace App\Services;

use App\Models\Negocio;
use Illuminate\Support\Facades\Schema;

class HubspotDealOwnerSyncService
{
    public function syncForContact(
        HubspotService $hubspot,
        string $hubspotContactId,
        string $hubspotOwnerId,
        ?int $appUserId = null
    ): int {
        $updatedDeals = $hubspot->updateDealOwnersByContactId($hubspotContactId, $hubspotOwnerId);

        if (
            $appUserId
            && Schema::hasTable('negocios')
            && Schema::hasColumn('negocios', 'hubspot_owner_id')
        ) {
            Negocio::where('user_id', $appUserId)->update([
                'hubspot_owner_id' => $hubspotOwnerId,
            ]);
        }

        return $updatedDeals;
    }
}

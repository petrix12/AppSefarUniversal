<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CrmCosCache
{
    public static function invalidate(User $user): void
    {
        Cache::forget('hubspot_data_'.$user->hs_id);
        Cache::forget('teamleader_data_'.$user->id);
        Cache::forget('cos.page_refreshed.'.$user->id);
        Cache::forget('cos.portfolio.v2.all');
        Cache::forget('cos.portfolio.v2.'.$user->owner_id);
        $user->forceFill(['arraycos_expire' => now()->subSecond()])->save();
    }
}

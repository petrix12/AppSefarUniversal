<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CosPortfolioBackfill
{
    public const PROGRESS_KEY = 'cos.portfolio.hubspot-backfill.v2';

    public static function state(): array
    {
        return Cache::get(self::PROGRESS_KEY, []);
    }

    public static function hubspotFieldsOnly(): bool
    {
        return (bool) config('crm_sync.hubspot.cos_fields_only');
    }

    public static function allowsCrmProcessing(string $provider): bool
    {
        if ($provider === 'hubspot' && self::hubspotFieldsOnly()) {
            return isset(self::state()['completed_at']);
        }

        return (bool) config('crm_sync.processing_enabled');
    }
}

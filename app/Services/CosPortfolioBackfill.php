<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class CosPortfolioBackfill
{
    public const PROGRESS_KEY = 'cos.portfolio.hubspot-backfill.v2';

    public static function state(): array
    {
        $path = self::progressPath();

        return File::exists($path) ? File::json($path) : Cache::get(self::PROGRESS_KEY, []);
    }

    public static function progressPath(): string
    {
        return config('cos_snapshot.backfill_progress_path', storage_path('app/private/cos-backfill-progress.json'));
    }

    public static function save(array $state): void
    {
        $path = self::progressPath();
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, json_encode($state, JSON_THROW_ON_ERROR));
    }

    public static function report(): array
    {
        $state = self::state() ?: ['status' => 'pending'];
        $state['hubspot_scoped_processing'] = self::hubspotFieldsOnly()
            && config('crm_sync.hubspot.enabled') && isset($state['completed_at']);

        return $state;
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

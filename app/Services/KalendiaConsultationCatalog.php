<?php

namespace App\Services;

use App\Models\KalendiaConsultationOffer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KalendiaConsultationCatalog
{
    public function __construct(private KalendiaApi $api) {}

    public function sync(bool $refresh = false): array
    {
        // Fetch the complete catalog before changing anything locally.
        $services = $this->api->services($refresh);
        $existing = KalendiaConsultationOffer::whereNotNull('catalog_service_id')->pluck('catalog_service_id')->flip();
        $rows = [];
        $available = 0;
        foreach ($services as $service) {
            $price = $this->price($service['price']);
            $currency = strtoupper(trim((string) $service['currency']));
            $validCurrency = preg_match('/^[A-Z]{3}$/D', $currency);
            $enabled = $service['active'] && $price !== null && (float) $price > 0 && $currency === 'EUR';
            if (! $enabled && ! $existing->has($service['id'])) continue;
            $available += (int) $enabled;
            $rows[] = [
                'catalog_service_id' => $service['id'], 'remote_service_id' => $service['id'],
                'name' => Str::limit(trim($service['name']), 255, ''), 'type' => 'Consulta',
                'description' => $service['description'] !== null ? Str::limit(strip_tags($service['description']), 3000, '') : null,
                'price' => $price, 'currency' => $validCurrency ? $currency : 'EUR',
                'active' => true, 'catalog_available' => $enabled, 'catalog_synced_at' => now(),
            ];
        }

        DB::transaction(function () use ($rows, $services) {
            if ($rows !== []) {
                KalendiaConsultationOffer::upsert($rows, ['catalog_service_id'], [
                    'remote_service_id', 'name', 'description', 'price', 'currency', 'catalog_available', 'catalog_synced_at',
                ]);
            }
            // Preserve orders, administrator visibility and manual price variants.
            KalendiaConsultationOffer::whereNotNull('catalog_service_id')
                ->whereNotIn('catalog_service_id', array_column($services, 'id'))
                ->update(['catalog_available' => false, 'catalog_synced_at' => now()]);
        });

        return ['services' => $services, 'available' => $available, 'skipped' => count($services) - $available];
    }

    private function price(mixed $price): ?string
    {
        if ((! is_string($price) && ! is_int($price) && ! is_float($price))
            || ! preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $price)) return null;

        return number_format((float) $price, 2, '.', '');
    }
}

<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Converts dated historical payments with ECB reference rates. The returned
 * rate is the number of foreign-currency units per EUR, so USD amounts are
 * divided by it to obtain their historical EUR reference value.
 */
class TeamleaderHistoricalExchangeRateService
{
    private const ECB_EXCHANGE_RATE_URL = 'https://data-api.ecb.europa.eu/service/data/EXR/D.%s.EUR.SP00.A';

    /**
     * @return array{converted_amount: float, conversions: array<int, array<string, mixed>>, unconverted_entries: array<int, array<string, mixed>>}
     */
    public function convertForeignEntriesToEuro(array $entries): array
    {
        $convertedAmount = 0.0;
        $conversions = [];
        $unconvertedEntries = [];

        foreach ($entries as $entry) {
            $currency = strtoupper(trim((string) ($entry['currency'] ?? 'EUR')));
            if ($currency === 'EUR') {
                continue;
            }

            $amount = round((float) ($entry['amount'] ?? 0), 2);
            $date = (string) ($entry['date'] ?? '');
            $rate = $amount > 0 ? $this->euroReferenceRate($currency, $date) : null;
            if ($rate === null) {
                $unconvertedEntries[] = $entry;
                continue;
            }

            $eurAmount = round($amount / $rate, 2);
            $convertedAmount += $eurAmount;
            $conversions[] = [
                'amount' => $amount,
                'currency' => $currency,
                'payment_date' => $date,
                'reference_rate' => $rate,
                'eur_amount' => $eurAmount,
                'source' => 'ECB',
            ];
        }

        return [
            'converted_amount' => round($convertedAmount, 2),
            'conversions' => $conversions,
            'unconverted_entries' => $unconvertedEntries,
        ];
    }

    private function euroReferenceRate(string $currency, string $paymentDate): ?float
    {
        if (! preg_match('/^[A-Z]{3}$/', $currency)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $paymentDate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $cacheKey = "teamleader.ecb-rate.{$currency}.{$date->toDateString()}";

        return Cache::remember($cacheKey, now()->addDays(90), function () use ($currency, $date): ?float {
            try {
                $response = Http::accept('text/csv')
                    ->timeout(5)
                    ->get(sprintf(self::ECB_EXCHANGE_RATE_URL, $currency), [
                        'startPeriod' => $date->copy()->subWeek()->toDateString(),
                        'endPeriod' => $date->toDateString(),
                        'format' => 'csvdata',
                    ]);

                if (! $response->successful()) {
                    return null;
                }

                return $this->latestRateFromCsv($response->body());
            } catch (\Throwable $exception) {
                Log::channel('teamleader')->warning('No se pudo obtener la tasa histórica del BCE', [
                    'currency' => $currency,
                    'payment_date' => $date->toDateString(),
                    'error' => $exception->getMessage(),
                ]);

                return null;
            }
        });
    }

    private function latestRateFromCsv(string $csv): ?float
    {
        $lines = array_values(array_filter(preg_split('/\R/', trim($csv)) ?: []));
        if (count($lines) < 2) {
            return null;
        }

        $headers = str_getcsv(array_shift($lines));
        $valueIndex = array_search('OBS_VALUE', $headers, true);
        if ($valueIndex === false) {
            return null;
        }

        $rate = null;
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            $value = $row[$valueIndex] ?? null;
            if (is_numeric($value) && (float) $value > 0) {
                $rate = (float) $value;
            }
        }

        return $rate;
    }
}
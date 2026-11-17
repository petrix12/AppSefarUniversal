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
     * @return array{converted_amount: float, conversions: array<int, array<string, mixed>>, unconverted_entries: array<int, array<string, mixed>>, complete: bool}
     */
    public function convertForeignEntriesToEuro(array $entries, array $knownConversions = []): array
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
            $rate = $amount > 0
                ? ($this->recordedRate($entry, $knownConversions) ?? $this->euroReferenceRate($currency, $date))
                : null;
            if ($rate === null) {
                $unconvertedEntries[] = $entry + ['reason' => $date === '' ? 'missing_payment_date' : 'rate_unavailable'];
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
            'complete' => $unconvertedEntries === [],
        ];
    }

    private function recordedRate(array $entry, array $conversions): ?float
    {
        foreach ($conversions as $conversion) {
            if (! is_array($conversion)) {
                continue;
            }
            $rate = (float) ($conversion['reference_rate'] ?? 0);
            if (($conversion['source'] ?? '') === 'ECB' && $rate > 0
                && ($conversion['currency'] ?? '') === ($entry['currency'] ?? '')
                && ($conversion['payment_date'] ?? '') === ($entry['date'] ?? '')
                && round((float) ($conversion['amount'] ?? 0), 2) === round((float) ($entry['amount'] ?? 0), 2)
                && abs(round((float) $entry['amount'] / $rate, 2) - (float) ($conversion['eur_amount'] ?? -1)) < 0.005) {
                return $rate;
            }
        }

        return null;
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
                $response = \App\Support\CosPageTiming::external('ecb_rates', 'BCE: tasas históricas', fn () =>
                    Http::accept('text/csv')
                        ->connectTimeout(5)
                        ->timeout(max(5, min(30, (int) config('services.ecb_rates.timeout', 15))))
                        ->withOptions([
                            'on_stats' => static fn (\GuzzleHttp\TransferStats $stats) => \App\Support\CosPageTiming::httpTransfer('BCE', $stats),
                        ])->get(sprintf(self::ECB_EXCHANGE_RATE_URL, $currency), [
                        'startPeriod' => $date->copy()->subWeek()->toDateString(),
                        'endPeriod' => $date->toDateString(),
                        'format' => 'csvdata',
                    ])
                );

                if (! $response->successful()) {
                    return null;
                }

                return $this->latestRateFromCsv($response->body(), $date->toDateString());
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

    private function latestRateFromCsv(string $csv, ?string $paymentDate = null): ?float
    {
        $lines = array_values(array_filter(preg_split('/\R/', trim($csv)) ?: []));
        if (count($lines) < 2) {
            return null;
        }

        $headers = str_getcsv(array_shift($lines));
        $valueIndex = array_search('OBS_VALUE', $headers, true);
        $dateIndex = array_search('TIME_PERIOD', $headers, true);
        if ($valueIndex === false || $dateIndex === false) {
            return null;
        }

        $rate = null;
        $latestDate = '';
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            $value = $row[$valueIndex] ?? null;
            $observationDate = $row[$dateIndex] ?? '';
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $observationDate)
                && ($paymentDate === null || $observationDate <= $paymentDate)
                && $observationDate >= $latestDate
                && is_numeric($value) && (float) $value > 0) {
                $rate = (float) $value;
                $latestDate = $observationDate;
            }
        }

        return $rate;
    }
}

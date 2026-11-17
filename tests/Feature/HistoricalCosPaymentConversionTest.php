<?php

namespace Tests\Feature;

use App\Services\TeamleaderHistoricalExchangeRateService;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HistoricalCosPaymentConversionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'services.ecb_rates.timeout' => 15]);
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_both_dated_dollar_installments_are_converted_and_cached(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertSame(15, $options['timeout']);
            $this->assertSame(5, $options['connect_timeout']);
            $first = str_contains($request->url(), '2022-10-28');
            return Http::response("TIME_PERIOD,OBS_VALUE\n".($first ? "2022-10-28,0.9951\n" : "2023-03-23,1.0879\n"));
        });
        $service = new TeamleaderHistoricalExchangeRateService();
        $result = $service->convertForeignEntriesToEuro($this->entries());

        $this->assertTrue($result['complete']);
        $this->assertSame(3448.64, $result['converted_amount']);
        $this->assertSame([1744.66, 1703.98], array_column($result['conversions'], 'eur_amount'));
        $this->assertSame([], $result['unconverted_entries']);
        $this->assertSame($result, $service->convertForeignEntriesToEuro($this->entries()));
        Http::assertSentCount(2);
    }

    public function test_a_missing_rate_is_explicit_and_is_not_cached_as_a_success(): void
    {
        Http::fakeSequence()
            ->push('', 503)
            ->push("TIME_PERIOD,OBS_VALUE\n2023-03-23,1.0879\n")
            ->push("TIME_PERIOD,OBS_VALUE\n2022-10-28,0.9951\n");
        $service = new TeamleaderHistoricalExchangeRateService();
        $partial = $service->convertForeignEntriesToEuro($this->entries());

        $this->assertFalse($partial['complete']);
        $this->assertSame(1703.98, $partial['converted_amount']);
        $this->assertSame(1736.11, $partial['unconverted_entries'][0]['amount']);
        $this->assertSame('rate_unavailable', $partial['unconverted_entries'][0]['reason']);
        $recovered = $service->convertForeignEntriesToEuro($this->entries());
        $this->assertTrue($recovered['complete']);
        $this->assertSame(3448.64, $recovered['converted_amount']);
        Http::assertSentCount(3);
    }

    public function test_confirmed_receipt_conversions_are_reused_without_remote_requests(): void
    {
        $known = [
            ['amount' => 1736.11, 'currency' => 'USD', 'payment_date' => '2022-10-28', 'reference_rate' => .9951, 'eur_amount' => 1744.66, 'source' => 'ECB'],
            ['amount' => 1853.76, 'currency' => 'USD', 'payment_date' => '2023-03-23', 'reference_rate' => 1.0879, 'eur_amount' => 1703.98, 'source' => 'ECB'],
        ];
        $result = (new TeamleaderHistoricalExchangeRateService())->convertForeignEntriesToEuro($this->entries(), $known);

        $this->assertTrue($result['complete']);
        $this->assertSame(3448.64, $result['converted_amount']);
        Http::assertNothingSent();
    }

    public function test_inconsistent_recorded_conversions_are_not_reused(): void
    {
        Http::fake(['*' => Http::response("TIME_PERIOD,OBS_VALUE\n2022-10-28,0.9951\n")]);
        $result = (new TeamleaderHistoricalExchangeRateService())->convertForeignEntriesToEuro([$this->entries()[0]], [
            ['amount' => 1736.11, 'currency' => 'USD', 'payment_date' => '2022-10-28', 'reference_rate' => .9951, 'eur_amount' => 99, 'source' => 'ECB'],
        ]);

        $this->assertSame(1744.66, $result['converted_amount']);
        Http::assertSentCount(1);
    }

    private function entries(): array
    {
        return (new TeamleaderProjectPaymentAnalyzer())->parseMoneyText(
            'Abono 1736.11$ 28/10/2022 + 1853.76$ 23/03/2023'
        )['dated_entries'];
    }
}

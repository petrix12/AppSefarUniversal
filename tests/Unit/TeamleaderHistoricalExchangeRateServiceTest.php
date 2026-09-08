<?php

namespace Tests\Unit;

use App\Services\TeamleaderHistoricalExchangeRateService;
use PHPUnit\Framework\TestCase;

class TeamleaderHistoricalExchangeRateServiceTest extends TestCase
{
    public function test_it_uses_the_latest_reference_rate_returned_by_the_ecb_csv(): void
    {
        $service = new TeamleaderHistoricalExchangeRateService();
        $method = new \ReflectionMethod($service, 'latestRateFromCsv');
        $csv = "TIME_PERIOD,OBS_VALUE\n2021-11-16,1.1368\n2021-11-17,1.1316\n";

        $this->assertSame(1.1316, $method->invoke($service, $csv));
    }
}
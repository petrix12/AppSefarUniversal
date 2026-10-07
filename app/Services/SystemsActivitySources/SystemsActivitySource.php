<?php

namespace App\Services\SystemsActivitySources;

use Carbon\CarbonImmutable;

interface SystemsActivitySource
{
    /** @return array<int, array{source:string, occurred_at:string, kind:string, title:string, detail:string}> */
    public function collect(string $email, CarbonImmutable $from, CarbonImmutable $to): array;
}

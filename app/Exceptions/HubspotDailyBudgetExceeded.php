<?php

namespace App\Exceptions;

use RuntimeException;

class HubspotDailyBudgetExceeded extends RuntimeException
{
    public function __construct(public readonly int $retryAfterSeconds)
    {
        parent::__construct('HubSpot daily request budget reached.');
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;

class HubspotApiException extends RuntimeException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly ?int $retryAfterSeconds = null,
        string $operation = 'request',
    ) {
        parent::__construct("HubSpot {$operation} failed (HTTP {$statusCode}).", $statusCode);
    }
}

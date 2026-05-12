<?php

namespace App\Exceptions;

use RuntimeException;

class TeamleaderRateLimitException extends RuntimeException
{
    public function __construct(
        string $message = 'Teamleader API rate limit exceeded.',
        private readonly int $retryAfterSeconds = 90,
    ) {
        parent::__construct($message);
    }

    public function retryAfterSeconds(): int
    {
        return max(30, $this->retryAfterSeconds);
    }
}

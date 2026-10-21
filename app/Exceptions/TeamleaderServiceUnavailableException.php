<?php

namespace App\Exceptions;

use RuntimeException;

class TeamleaderServiceUnavailableException extends RuntimeException
{
    public function __construct(int $status = 503)
    {
        parent::__construct('Teamleader no está disponible temporalmente (HTTP ' . $status . ').', $status);
    }
}

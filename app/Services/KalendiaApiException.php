<?php

namespace App\Services;

class KalendiaApiException extends \RuntimeException
{
    public function __construct(public readonly ?int $httpStatus = null, public readonly ?string $errorCode = null)
    {
        // Do not retain the provider's request, body or exception (tokens and client data).
        parent::__construct('No se pudo confirmar la respuesta de la agenda.');
    }
}

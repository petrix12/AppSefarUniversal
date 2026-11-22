<?php
namespace App\Services;

class PlatformApiException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode = 'unavailable', public readonly ?int $httpStatus = null)
    {
        parent::__construct('No se pudo confirmar la operación con la plataforma.');
    }
}

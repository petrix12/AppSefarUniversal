<?php

namespace App\Exceptions;

use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

class CosSourceRefreshException extends RuntimeException
{
    public function __construct(public readonly string $source, ?Throwable $previous = null)
    {
        $label = match ($source) {
            'hubspot' => 'HubSpot',
            'teamleader' => 'Teamleader',
            'monday' => 'Monday',
            default => 'las fuentes externas',
        };

        parent::__construct("No se pudo actualizar {$label}; se conserva el COS anterior para reintentar.", 0, $previous);
    }

    public function reason(): string
    {
        for ($cause = $this->getPrevious(); $cause; $cause = $cause->getPrevious()) {
            if ($this->source === 'teamleader'
                && ($cause instanceof TeamleaderServiceUnavailableException
                    || $cause instanceof ConnectException || $cause instanceof ConnectionException)) {
                return 'service_unavailable';
            }
            if ($this->source === 'teamleader' && $cause instanceof TeamleaderAuthenticationException) {
                return 'authentication_required';
            }
            if ($this->source === 'teamleader' && $cause instanceof UnexpectedValueException) {
                return 'invalid_authorization_response';
            }
        }

        return 'upstream_error';
    }
}

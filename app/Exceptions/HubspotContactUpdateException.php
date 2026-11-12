<?php

namespace App\Exceptions;

use HubSpot\Client\Crm\Contacts\ApiException;
use RuntimeException;

class HubspotContactUpdateException extends RuntimeException
{
    public function __construct(string $contactId, ApiException $previous)
    {
        $body = $previous->getResponseBody();
        $detail = $body === null ? $previous->getMessage()
            : (is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE));

        parent::__construct(
            "Error actualizando contacto {$contactId} en HubSpot ({$previous->getCode()}): {$detail}",
            (int) $previous->getCode(),
            $previous,
        );
    }

    public function isEmailConflict(): bool
    {
        $body = $this->getPrevious()->getResponseBody();
        $data = is_string($body) ? json_decode($body, true) : (array) $body;
        $message = (string) ($data['message'] ?? '');

        return in_array($this->getCode(), [400, 409], true)
            && str_contains($message, 'propertyName=email,')
            && str_contains($message, 'already has that value');
    }
}

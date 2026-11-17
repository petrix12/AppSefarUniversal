<?php

namespace App\Exceptions;

use Throwable;

class CosIdentityConflictException extends CosSourceRefreshException
{
    /** @param array<string, array<int, int>> $conflicts Internal account IDs, never identity values. */
    public function __construct(public readonly array $conflicts, ?Throwable $previous = null)
    {
        parent::__construct('hubspot', $previous);
        $this->message = 'La identidad del contacto de HubSpot entra en conflicto con otra cuenta de la App. Revisa la vinculación antes de actualizar el COS.';
    }

    public function reason(): string
    {
        return 'identity_conflict';
    }
}

<?php

namespace App\Exceptions;

class CrmProjectAssociationConflictException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The Teamleader project customer conflicts with its linked App client.');
    }
}

<?php

namespace App\Support;

final class ClientOnboardingFlow
{
    public const PAY = 'clientes.pay';
    public const GET_INFO = 'clientes.getinfo';
    public const CONTRACT = 'cliente.contrato';
    public const TREE = 'clientes.tree';

    /**
     * Returns the only valid next route for a client in the onboarding flow.
     */
    public static function destination(int $pay, bool $hasSignedContract, bool $hasPendingInitialPayment): string
    {
        if ($hasPendingInitialPayment || $pay === 0) {
            return self::PAY;
        }

        return match ($pay) {
            1, 3 => self::GET_INFO,
            2 => $hasSignedContract ? self::TREE : self::CONTRACT,
            default => self::GET_INFO,
        };
    }
}

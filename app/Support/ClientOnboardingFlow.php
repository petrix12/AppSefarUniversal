<?php

namespace App\Support;

final class ClientOnboardingFlow
{
    public const PAY = 'clientes.pay';
    public const SERVICE_STORE = 'service-store.index';
    public const GET_INFO = 'clientes.getinfo';
    public const CONTRACT = 'cliente.contrato';
    public const TREE = 'clientes.tree';

    /**
     * Returns the only valid next route for a client in the onboarding flow.
     */
    public static function destination(
        int $pay,
        bool $hasSignedContract,
        bool $hasPendingInitialPayment,
        bool $hasSelectedService = true
    ): string
    {
        if ($hasPendingInitialPayment) {
            return self::PAY;
        }

        if ($pay === 0) {
            return $hasSelectedService ? self::PAY : self::SERVICE_STORE;
        }

        return match ($pay) {
            1, 3 => self::GET_INFO,
            2 => $hasSignedContract ? self::TREE : self::CONTRACT,
            default => self::GET_INFO,
        };
    }
}

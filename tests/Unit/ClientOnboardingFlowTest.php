<?php

namespace Tests\Unit;

use App\Support\ClientOnboardingFlow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ClientOnboardingFlowTest extends TestCase
{
    #[DataProvider('onboardingStates')]
    public function testItResolvesEachClientOnboardingState(
        int $pay,
        bool $hasSignedContract,
        bool $hasPendingInitialPayment,
        bool $hasSelectedService,
        string $expectedRoute
    ): void {
        $this->assertSame(
            $expectedRoute,
            ClientOnboardingFlow::destination(
                $pay,
                $hasSignedContract,
                $hasPendingInitialPayment,
                $hasSelectedService
            )
        );
    }

    public static function onboardingStates(): array
    {
        return [
            'initial payment is pending' => [0, false, true, false, ClientOnboardingFlow::PAY],
            'not paid with a selected service' => [0, false, false, true, ClientOnboardingFlow::PAY],
            'not paid without a selected service' => [0, false, false, false, ClientOnboardingFlow::SERVICE_STORE],
            'paid and awaiting information' => [1, false, false, false, ClientOnboardingFlow::GET_INFO],
            'awaiting information after retry' => [3, false, false, false, ClientOnboardingFlow::GET_INFO],
            'information complete and contract pending' => [2, false, false, false, ClientOnboardingFlow::CONTRACT],
            'onboarding complete' => [2, true, false, false, ClientOnboardingFlow::TREE],
            'unknown legacy state is contained in the information step' => [99, true, false, false, ClientOnboardingFlow::GET_INFO],
        ];
    }
}

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
        string $expectedRoute
    ): void {
        $this->assertSame(
            $expectedRoute,
            ClientOnboardingFlow::destination($pay, $hasSignedContract, $hasPendingInitialPayment)
        );
    }

    public static function onboardingStates(): array
    {
        return [
            'initial payment is pending' => [0, false, true, ClientOnboardingFlow::PAY],
            'not paid' => [0, false, false, ClientOnboardingFlow::PAY],
            'paid and awaiting information' => [1, false, false, ClientOnboardingFlow::GET_INFO],
            'awaiting information after retry' => [3, false, false, ClientOnboardingFlow::GET_INFO],
            'information complete and contract pending' => [2, false, false, ClientOnboardingFlow::CONTRACT],
            'onboarding complete' => [2, true, false, ClientOnboardingFlow::TREE],
            'unknown legacy state is contained in the information step' => [99, true, false, ClientOnboardingFlow::GET_INFO],
        ];
    }
}

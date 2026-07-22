<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ClientStageResolver;
use Tests\TestCase;

class ClientStageResolverTest extends TestCase
{
    private ClientStageResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ClientStageResolver();
    }

    public function test_guest_is_visitor(): void
    {
        $stage = $this->resolver->resolve(null);

        $this->assertSame('visitor', $stage['stage']);
        $this->assertSame('visitor', $stage['profile']);
        $this->assertTrue($stage['is_visitor']);
        $this->assertFalse($stage['is_candidate']);
        $this->assertFalse($stage['is_represented']);
    }

    public function test_cosready_does_not_make_unpaid_user_represented(): void
    {
        $user = new User([
            'pay' => 0,
            'contrato' => 0,
            'cosready' => 1,
            'servicio' => 'Española Sefardi',
        ]);

        $stage = $this->resolver->resolve($user);

        $this->assertSame('candidate_registered', $stage['stage']);
        $this->assertSame('candidate', $stage['profile']);
        $this->assertFalse($stage['is_represented']);
    }

    public function test_pending_purchase_marks_activation_pending_payment(): void
    {
        $user = new User([
            'pay' => 0,
            'contrato' => 0,
            'servicio' => 'Española Sefardi',
        ]);

        $stage = $this->resolver->resolve($user, ['has_pending_purchase' => true]);

        $this->assertSame('candidate_activation_pending_payment', $stage['stage']);
        $this->assertSame('candidate', $stage['profile']);
    }

    public function test_paid_user_pending_getinfo_is_not_represented_until_contract_is_signed(): void
    {
        $user = new User([
            'pay' => 1,
            'contrato' => 0,
            'servicio' => 'Española Sefardi',
        ]);

        $stage = $this->resolver->resolve($user);

        $this->assertSame('candidate_paid_pending_info', $stage['stage']);
        $this->assertSame('candidate', $stage['profile']);
        $this->assertTrue($stage['is_candidate']);
        $this->assertFalse($stage['is_represented']);
    }

    public function test_user_with_completed_info_but_without_contract_is_not_represented(): void
    {
        $user = new User([
            'pay' => 2,
            'contrato' => 0,
            'servicio' => 'Española Sefardi',
        ]);

        $stage = $this->resolver->resolve($user);

        $this->assertSame('candidate_pending_contract', $stage['stage']);
        $this->assertSame('candidate', $stage['profile']);
        $this->assertTrue($stage['is_candidate']);
        $this->assertFalse($stage['is_represented']);
    }

    public function test_paid_info_and_signed_contract_is_represented(): void
    {
        $user = new User([
            'pay' => 2,
            'contrato' => 1,
            'servicio' => 'Española Sefardi',
        ]);

        $stage = $this->resolver->resolve($user);

        $this->assertSame('represented_initial', $stage['stage']);
        $this->assertSame('represented', $stage['profile']);
        $this->assertTrue($stage['is_represented']);
    }

    public function test_high_pay_status_is_normalized_for_existing_process_flags(): void
    {
        $user = new User([
            'pay' => 12,
            'contrato' => 1,
            'cosready' => 1,
            'servicio' => 'Española Sefardi',
        ]);

        $stage = $this->resolver->resolve($user);

        $this->assertSame('represented_with_cos', $stage['stage']);
        $this->assertSame('represented', $stage['profile']);
    }
}

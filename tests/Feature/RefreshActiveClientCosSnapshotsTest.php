<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ClientAppNotification;
use App\Services\ClientCosSnapshotService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RefreshActiveClientCosSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Cliente', 'guard_name' => 'web']);
    }

    public function test_it_only_refreshes_expired_clients_with_paid_service_and_signed_contract(): void
    {
        Notification::fake();
        config()->set('cos_snapshot.inter_client_delay_seconds', 0);

        $dueClient = User::factory()->create([
            'pay' => 2,
            'contrato' => 1,
            'arraycos' => [$this->cosStatus('Española Sefardi', 'Documentos en revisión', 2)],
            'arraycos_expire' => now()->subDay(),
        ]);

        User::factory()->create([
            'pay' => 1,
            'contrato' => 1,
            'arraycos_expire' => now()->subDay(),
        ]);
        User::factory()->create([
            'pay' => 2,
            'contrato' => 0,
            'arraycos_expire' => now()->subDay(),
        ]);
        User::factory()->create([
            'pay' => 2,
            'contrato' => 1,
            'arraycos_expire' => now()->addDay(),
        ]);

        $this->mock(ClientCosSnapshotService::class, function ($mock) use ($dueClient) {
            $mock->shouldReceive('refresh')
                ->once()
                ->with(Mockery::on(fn (User $user) => $user->is($dueClient)), true)
                ->andReturnUsing(function (User $user) {
                    $user->forceFill([
                        'arraycos' => [$this->cosStatus('Española Sefardi', 'Expediente formalizado', 3)],
                        'arraycos_expire' => Carbon::now()->addDays(30),
                        'cosready' => 1,
                    ])->save();

                    return [
                        'client' => $user->fresh(),
                        'cos' => $user->fresh()->arraycos,
                    ];
                });
        });

        $this->artisan('cos:refresh-active-clients --limit=10')
            ->expectsOutputToContain("Cliente {$dueClient->id}: COS actualizado (con cambio de estatus).")
            ->assertExitCode(0);

        $dueClient->refresh();
        $this->assertSame('Expediente formalizado', $dueClient->arraycos[0]['currentStepName']);
        $this->assertTrue($dueClient->arraycos_expire->between(now()->addDays(29), now()->addDays(31)));
        Notification::assertSentTo($dueClient, ClientAppNotification::class);
    }

    public function test_it_does_not_notify_when_the_status_is_unchanged(): void
    {
        Notification::fake();
        config()->set('cos_snapshot.inter_client_delay_seconds', 0);

        $client = User::factory()->create([
            'pay' => 2,
            'contrato' => 1,
            'arraycos' => [$this->cosStatus('Española Sefardi', 'Documentos en revisión', 2)],
            'arraycos_expire' => now()->subDay(),
        ]);

        $this->mock(ClientCosSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refresh')
                ->once()
                ->andReturnUsing(function (User $user) {
                    $user->arraycos_expire = Carbon::now()->addDays(30);
                    $user->save();

                    return [
                        'client' => $user->fresh(),
                        'cos' => $user->arraycos,
                    ];
                });
        });

        $this->artisan('cos:refresh-active-clients')
            ->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_it_builds_and_notifies_the_first_cos_snapshot_for_an_eligible_client(): void
    {
        Notification::fake();
        config()->set('cos_snapshot.inter_client_delay_seconds', 0);

        $client = User::factory()->create([
            'pay' => 2,
            'contrato' => 1,
            'arraycos' => null,
            'arraycos_expire' => null,
        ]);

        $this->mock(ClientCosSnapshotService::class, function ($mock) {
            $mock->shouldReceive('refresh')
                ->once()
                ->andReturnUsing(function (User $user) {
                    $user->forceFill([
                        'arraycos' => [$this->cosStatus('Española Sefardi', 'Documentos en revisión', 2)],
                        'arraycos_expire' => Carbon::now()->addDays(30),
                        'cosready' => 1,
                    ])->save();

                    return [
                        'client' => $user->fresh(),
                        'cos' => $user->fresh()->arraycos,
                    ];
                });
        });

        $this->artisan('cos:refresh-active-clients')
            ->assertExitCode(0);

        $client->refresh();
        $this->assertSame('Documentos en revisión', $client->arraycos[0]['currentStepName']);
        Notification::assertSentTo($client, ClientAppNotification::class);
    }

    private function cosStatus(string $service, string $step, int $stepNumber): array
    {
        return [
            'servicio' => $service,
            'currentStepName' => $step,
            'currentStepGen' => $stepNumber,
            'currentStepJur' => -1,
            'progressPercentageGen' => $stepNumber * 10,
        ];
    }
}

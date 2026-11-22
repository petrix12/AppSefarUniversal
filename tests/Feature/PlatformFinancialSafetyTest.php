<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\{PlatformOperation, PlatformRecord, User};
use App\Services\{PlatformEventProcessor, PlatformMirror};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\{DB, Http, Mail, Schema};
use Illuminate\Support\Str;
use Spatie\Permission\Models\{Permission, Role};
use Tests\TestCase;

class PlatformFinancialSafetyTest extends TestCase
{
    use \Tests\Concerns\RegistrationSchema;

    private const BASE = '/admin/plataformas/stripe/main';

    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->useEnvironmentPath('/dev')->loadEnvironmentFrom('null');
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array', 'session.driver' => 'array',
            'services.stripe.secret' => 'sk_test_fake',
            'services.stripe.admin_webhook_secret' => 'whsec_admin',
            'kalendia.token' => 'fake', 'kalendia.webhook_secret' => 'kalendia_secret',
            'kalendia.webhook_business_id' => 'site_test',
            'app.key' => 'base64:'.base64_encode(str_repeat('s', 32)),
        ]);
        $this->withoutMiddleware(AuditRequests::class);
        Mail::fake();
        Http::preventStrayRequests();
        $this->createRegistrationSchema();
        Schema::table('users', fn ($t) => $t->boolean('is_superadmin')->default(false));
        $this->artisan('migrate', ['--path' => [
            'database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php',
            'database/migrations/2026_11_20_190000_create_kalendia_consultation_management_tables.php',
            'database/migrations/2026_11_22_120000_create_kalendia_webhook_events_table.php',
            'database/migrations/2026_11_22_180000_create_platform_management_tables.php',
        ]])->assertExitCode(0);
        Role::findOrCreate('Administrador');
    }

    private function superadmin(): User
    {
        $user = User::factory()->create();
        $user->syncRoles(['Administrador']);
        foreach (['crud.stripeverify.index', 'admin.payments'] as $permission) {
            Permission::findOrCreate($permission);
            $user->givePermissionTo($permission);
        }
        $user->forceFill(['is_superadmin' => true])->save();

        return $user;
    }

    private function charge(array $extra = []): array
    {
        return array_replace([
            'id' => 'ch_one', 'object' => 'charge', 'amount' => 10000, 'amount_refunded' => 0,
            'captured' => true, 'paid' => true, 'currency' => 'eur', 'status' => 'succeeded',
            'livemode' => false, 'description' => 'TEST MODE financial fixture',
        ], $extra);
    }

    private function record(array $extra = []): PlatformRecord
    {
        return app(PlatformMirror::class)->save('stripe', 'main', 'charges', $this->charge($extra));
    }

    private function refundInput(array $extra = []): array
    {
        return array_replace([
            'operation_id' => (string) Str::uuid(), 'action' => 'refund', 'amount' => '25.50',
            'reason' => 'requested_by_customer', 'comment' => 'Solicitud revisada con el cliente', 'confirmed' => '1',
        ], $extra);
    }

    private function uncertainRefund(PlatformRecord $record, User $actor): PlatformOperation
    {
        $input = $this->refundInput();

        return PlatformOperation::create([
            'record_id' => $record->id, 'actor_id' => $actor->id, 'uuid' => $input['operation_id'],
            'action' => 'refund', 'status' => 'uncertain', 'created_at' => now()->subHours(24),
            'payload' => [
                'input' => array_diff_key($input, array_flip(['operation_id', 'action', 'confirmed'])),
                'request' => [
                    'method' => 'POST', 'path' => 'refunds', 'resource' => 'refunds',
                    'data' => ['charge' => $record->remote_id, 'amount' => 2550,
                        'reason' => 'requested_by_customer', 'metadata' => ['app_operation' => $input['operation_id']]],
                ],
            ],
        ]);
    }

    private function refund(PlatformOperation $operation, array $extra = []): array
    {
        return array_replace([
            'id' => 're_one', 'object' => 'refund', 'charge' => $operation->record->remote_id,
            'amount' => 2550, 'currency' => 'eur', 'status' => 'succeeded', 'livemode' => false,
            'metadata' => ['app_operation' => $operation->uuid],
        ], $extra);
    }

    public function test_a_forbidden_retry_preserves_uncertainty_and_blocks_another_refund_uuid(): void
    {
        $this->actingAs($this->superadmin());
        $record = $this->record();
        $input = $this->refundInput();
        $posts = 0;
        Http::fake(function ($request) use (&$posts) {
            if ($request->method() === 'GET') return Http::response($this->charge());
            $posts++;
            if ($posts === 1) throw new ConnectionException('simulated response lost');

            return Http::response(['error' => ['code' => 'permission_denied']], 403);
        });
        $url = self::BASE.'/registros/'.$record->id.'/operar';

        $this->postJson($url, $input)->assertUnprocessable();
        $operation = PlatformOperation::sole();
        $this->assertSame('uncertain', $operation->status);
        $this->postJson(self::BASE.'/operaciones/'.$operation->id.'/reintentar', ['confirmed' => '1'])->assertUnprocessable();
        $this->assertSame('uncertain', $operation->fresh()->status);
        $this->postJson($url, $this->refundInput())->assertUnprocessable();

        $this->assertSame(2, $posts);
        $this->assertDatabaseCount('platform_operations', 1);
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_switching_to_live_hides_test_records_and_does_not_execute_their_events_or_sync(): void
    {
        $this->actingAs($this->superadmin());
        $record = $this->record();
        $mirror = app(PlatformMirror::class);
        $mirror->queue('stripe', 'main', 'charges');
        PlatformEventProcessor::receive('stripe', 'main', 'evt_testMode', 'charge.succeeded', 'charges', $record->remote_id);
        $this->assertSame('test', $record->mode);
        $this->assertSame('test', DB::table('platform_events')->value('mode'));
        $this->assertSame('test', DB::table('platform_sync_states')->value('mode'));

        config(['services.stripe.secret' => 'sk_live_fake']);
        $this->get(self::BASE.'/datos/charges')->assertOk()->assertDontSee('TEST MODE financial fixture');
        $this->getJson(self::BASE.'/registros/'.$record->id)->assertNotFound();
        $this->postJson(self::BASE.'/registros/'.$record->id.'/operar', $this->refundInput())->assertNotFound();
        $this->assertSame(0, app(PlatformEventProcessor::class)->process()['synced']);
        $this->assertSame(0, $mirror->sync()['pages']);

        Http::assertNothingSent();
        $this->assertDatabaseCount('platform_operations', 0);
    }

    public function test_a_refund_read_reconciles_an_old_uncertain_operation_by_its_metadata_without_another_write(): void
    {
        $operation = $this->uncertainRefund($this->record(), $this->superadmin());
        Http::fake(['api.stripe.com/*' => Http::response($this->refund($operation))]);
        PlatformEventProcessor::receive('stripe', 'main', 'evt_reconcile', 'refund.updated', 'refunds', 're_one');

        $this->assertSame(1, app(PlatformEventProcessor::class)->process()['synced']);
        $this->assertSame('succeeded', $operation->fresh()->status);
        $this->assertSame('re_one', $operation->fresh()->remote_id);
        $this->assertTrue(Http::recorded()->every(fn ($pair) => $pair[0]->method() === 'GET'));
        $this->assertDatabaseCount('platform_operations', 1);
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('facturas', 0);
    }

    public function test_reconciliation_requires_the_original_charge_amount_and_currency(): void
    {
        $actor = $this->superadmin();
        $responses = [];
        Http::fake(function ($request) use (&$responses) {
            $id = basename(parse_url($request->url(), PHP_URL_PATH));

            return isset($responses[$id]) ? Http::response($responses[$id]) : Http::response([], 404);
        });
        foreach (['charge' => 'ch_unrelated', 'amount' => 2500, 'currency' => 'usd'] as $field => $value) {
            $operation = $this->uncertainRefund($this->record(['id' => 'ch_'.$field]), $actor);
            $refundId = 're_'.$field;
            $responses[$refundId] = $this->refund($operation, ['id' => $refundId, $field => $value]);
            PlatformEventProcessor::receive('stripe', 'main', 'evt_'.$field, 'refund.updated', 'refunds', $refundId);

            app(PlatformEventProcessor::class)->process();

            $this->assertSame('uncertain', $operation->fresh()->status, $field.' must match the original refund request');
            $this->assertNull($operation->fresh()->remote_id);
        }
        $this->assertTrue(Http::recorded()->every(fn ($pair) => $pair[0]->method() === 'GET'));
    }

    public function test_reconciliation_cannot_cross_account_or_live_test_boundaries(): void
    {
        $actor = $this->superadmin();
        $operation = $this->uncertainRefund($this->record(), $actor);
        $historicalRecord = $this->record(['id' => 'ch_historical']);
        $historicalRecord->forceFill(['account' => 'retired'])->save();
        $historicalOperation = $this->uncertainRefund($historicalRecord, $actor);
        Http::fake(function ($request) use ($operation, $historicalOperation) {
            $id = basename(parse_url($request->url(), PHP_URL_PATH));
            return Http::response($this->refund($id === 're_otherAccount' ? $historicalOperation : $operation, [
                'id' => $id,
                'livemode' => $request->header('Authorization') === ['Bearer sk_live_fake'],
            ]));
        });
        $mirror = app(PlatformMirror::class);
        $mirror->fetch('stripe', 'main', 'refunds', 're_otherAccount');
        $this->assertSame('uncertain', $historicalOperation->fresh()->status);
        $this->assertNull($historicalOperation->fresh()->remote_id);
        $this->assertSame('retired', $historicalRecord->fresh()->account);

        config(['services.stripe.secret' => 'sk_live_fake']);
        $liveRefund = $mirror->fetch('stripe', 'main', 'refunds', 're_otherMode');
        $this->assertSame('live', $liveRefund->mode);
        $this->assertSame('test', $operation->record->mode);
        $this->assertSame('uncertain', $operation->fresh()->status);
        $this->assertNull($operation->fresh()->remote_id);
        $this->assertTrue(Http::recorded()->every(fn ($pair) => $pair[0]->method() === 'GET'));
    }

    public function test_a_refund_response_with_another_amount_never_confirms_the_operation(): void
    {
        $this->actingAs($this->superadmin());
        $record = $this->record();
        Http::fake(function ($request) {
            if ($request->method() === 'GET') return Http::response($this->charge());

            return Http::response(['id' => 're_wrongAmount', 'object' => 'refund', 'charge' => 'ch_one',
                'amount' => 2500, 'currency' => 'eur', 'status' => 'succeeded', 'livemode' => false]);
        });

        $this->postJson(self::BASE.'/registros/'.$record->id.'/operar', $this->refundInput())->assertUnprocessable();

        $operation = PlatformOperation::sole();
        $this->assertSame('uncertain', $operation->status);
        $this->assertNull($operation->remote_id);
        $this->assertSame('refund_mismatch', $operation->error_code);
        $this->assertDatabaseMissing('platform_records', ['resource' => 'refunds']);
        $this->assertSame(1, Http::recorded()->filter(fn ($pair) => $pair[0]->method() === 'POST')->count());
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('facturas', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\AuditRequests;
use App\Models\BrightbeanMessage;
use App\Models\BrightbeanSetting;
use App\Models\User;
use App\Services\AiProcessSettingsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BrightbeanIntegrationTest extends TestCase
{
    private string $auditPath;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array',
            'services.openrouter.key' => 'test-private-key',
            'services.openrouter.url' => 'https://openrouter.test/chat/completions']);
        DB::purge('sqlite');
        Cache::flush();
        $this->withoutMiddleware(AuditRequests::class);
        Http::preventStrayRequests();
        $this->auditPath = tempnam(sys_get_temp_dir(), 'sefar-brightbean-test-');
        config(['mcp.audit_log' => $this->auditPath]);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });
        require_once database_path('migrations/2021_03_08_173429_create_permission_tables.php');
        (new \CreatePermissionTables())->up();
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_11_04_120000_create_brightbean_integration_tables.php'))->up();
        $this->mock(AiProcessSettingsService::class)->shouldReceive('get')->with('brightbean_chat')
            ->andReturn(['model' => 'test-model', 'fallback_models' => ['fallback'], 'timeout_seconds' => 30, 'max_tokens' => 500]);
    }

    protected function tearDown(): void
    {
        if (isset($this->auditPath)) {
            @unlink($this->auditPath);
        }
        parent::tearDown();
    }

    public function test_sync_requires_a_real_internal_token_with_its_own_permission(): void
    {
        $body = ['messages' => [$this->payload()]];
        $this->postJson('/api/brightbean/messages', $body)->assertUnauthorized();
        $this->withToken('invalid')->postJson('/api/brightbean/messages', $body)->assertUnauthorized();
        $this->withToken($this->token(['read']))->postJson('/api/brightbean/messages', $body)->assertForbidden();
        $this->withToken($this->token(['brightbean:sync'], 'Cliente'))->postJson('/api/brightbean/messages', $body)->assertForbidden();
        $this->assertDatabaseCount('brightbean_messages', 0);
    }

    public function test_revoked_tokens_are_rejected(): void
    {
        $token = $this->token(['brightbean:sync']);
        PersonalAccessToken::findToken($token)->delete();
        $this->withToken($token)->postJson('/api/brightbean/messages', ['messages' => []])->assertUnauthorized();
    }

    public function test_sync_is_idempotent_and_preserves_newer_statuses(): void
    {
        $this->withToken($this->token(['brightbean:sync']));
        $entry = $this->payload();
        $this->postJson('/api/brightbean/messages', ['messages' => [$entry]])->assertOk();
        $entry['source_updated_at'] = now()->addSecond()->toISOString();
        $entry['status'] = 'read';
        $this->postJson('/api/brightbean/messages', ['messages' => [$entry]])->assertOk();
        $entry['source_updated_at'] = now()->subMinute()->toISOString();
        $entry['status'] = 'sent';
        $this->postJson('/api/brightbean/messages', ['messages' => [$entry]])->assertOk();

        $this->assertDatabaseCount('brightbean_messages', 1);
        $this->assertSame('read', BrightbeanMessage::first()->status);
        $this->assertNotNull(BrightbeanSetting::current()->last_synced_at);
    }

    public function test_sync_cannot_inject_cached_ai_answers(): void
    {
        $entry = [...$this->payload(), 'ai_reply' => 'Injected'];
        $this->withToken($this->token(['brightbean:sync']))
            ->postJson('/api/brightbean/messages', ['messages' => [$entry]])->assertUnprocessable();
        $this->assertDatabaseCount('brightbean_messages', 0);
    }

    public function test_deletions_redact_the_mirror_and_the_cached_reply(): void
    {
        $entry = $this->payload();
        $message = BrightbeanMessage::create($entry);
        $message->forceFill(['ai_reply' => 'Old reply'])->save();
        $entry['status'] = 'deleted';
        $entry['source_updated_at'] = now()->addSecond()->toISOString();
        $this->withToken($this->token(['brightbean:sync']))
            ->postJson('/api/brightbean/messages', ['messages' => [$entry]])->assertOk();

        $this->assertNull($message->fresh()->text);
        $this->assertSame([], $message->fresh()->body);
        $this->assertNull($message->fresh()->ai_reply);
    }

    public function test_ai_uses_existing_openrouter_configuration_and_caches_the_reply(): void
    {
        BrightbeanSetting::current()->update(['ai_enabled' => true]);
        Http::fake([
            'openrouter.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'Hola, ¿cómo te ayudamos?']]],
            ]),
        ]);
        $message = BrightbeanMessage::create($this->payload());
        $this->withToken($this->token(['brightbean:ai']));
        $url = '/api/brightbean/messages/'.$message->id.'/reply';
        $this->postJson($url)->assertOk()->assertJsonPath('data.reply', 'Hola, ¿cómo te ayudamos?');
        $this->postJson($url)->assertOk()->assertJsonPath('data.reply', 'Hola, ¿cómo te ayudamos?');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer test-private-key')
            && $request['models'] === ['test-model', 'fallback']
            && $request['messages'][1]['content'] === 'Hola');
    }

    public function test_message_history_does_not_include_other_conversations_or_internal_notes(): void
    {
        BrightbeanSetting::current()->update(['ai_enabled' => true]);
        $entry = $this->payload();
        BrightbeanMessage::create([...$entry, 'external_id' => (string) Str::uuid(), 'internal' => true, 'text' => 'INTERNAL SECRET']);
        BrightbeanMessage::create([...$entry, 'external_id' => (string) Str::uuid(), 'conversation_id' => (string) Str::uuid(), 'text' => 'OTHER THREAD']);
        BrightbeanMessage::create([...$entry, 'external_id' => (string) Str::uuid(), 'direction' => 'out', 'status' => 'failed', 'text' => 'UNSENT ANSWER']);
        $message = BrightbeanMessage::create($entry);
        Http::fake([
            'openrouter.test/*' => Http::response([
                'choices' => [['message' => ['content' => 'Answer']]],
            ]),
        ]);
        $this->withToken($this->token(['brightbean:ai']))->postJson('/api/brightbean/messages/'.$message->id.'/reply')->assertOk();

        Http::assertSent(fn ($request) => ! str_contains(json_encode($request['messages']), 'INTERNAL SECRET')
            && ! str_contains(json_encode($request['messages']), 'OTHER THREAD')
            && ! str_contains(json_encode($request['messages']), 'UNSENT ANSWER'));
    }

    public function test_outgoing_internal_deleted_empty_and_disabled_messages_never_call_ai(): void
    {
        $this->withToken($this->token(['brightbean:ai']));
        $message = BrightbeanMessage::create($this->payload());
        $this->postJson('/api/brightbean/messages/'.$message->id.'/reply')->assertOk()->assertJsonPath('data.reply', null);
        BrightbeanSetting::current()->update(['ai_enabled' => true]);
        foreach ([['direction' => 'out'], ['internal' => true], ['status' => 'deleted'], ['text' => '']] as $changes) {
            $message = BrightbeanMessage::create([...$this->payload(), ...$changes]);
            $this->postJson('/api/brightbean/messages/'.$message->id.'/reply')->assertOk()->assertJsonPath('data.reply', null);
        }
        Http::assertNothingSent();
    }

    public function test_sync_permission_does_not_allow_ai_spending(): void
    {
        $message = BrightbeanMessage::create($this->payload());
        $this->withToken($this->token(['brightbean:sync']))
            ->postJson('/api/brightbean/messages/'.$message->id.'/reply')->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_provider_failures_do_not_store_an_answer_and_can_be_retried(): void
    {
        BrightbeanSetting::current()->update(['ai_enabled' => true]);
        $message = BrightbeanMessage::create($this->payload());
        Http::fake([
            'openrouter.test/*' => Http::sequence()->push(['error' => 'Private provider error'], 503)
                ->push(['choices' => [['message' => ['content' => 'Recovered']]]]),
        ]);
        $this->withToken($this->token(['brightbean:ai']));
        $this->postJson('/api/brightbean/messages/'.$message->id.'/reply')->assertStatus(502)
            ->assertDontSee('Private provider error')->assertDontSee('test-private-key');
        $this->assertNull($message->fresh()->ai_reply);
        $this->postJson('/api/brightbean/messages/'.$message->id.'/reply')->assertOk()->assertJsonPath('data.reply', 'Recovered');
    }

    public function test_only_administrators_can_enable_automatic_replies(): void
    {
        $editor = $this->actor('Genealogista');
        $this->actingAs($editor)->put(route('admin.integrations.brightbean.update'), ['ai_enabled' => true])->assertForbidden();
        $admin = $this->actor('Administrador');
        $this->actingAs($admin)->put(route('admin.integrations.brightbean.update'), ['ai_enabled' => true, 'instructions' => 'Instrucciones públicas'])
            ->assertRedirect();
        $this->assertTrue(BrightbeanSetting::current()->ai_enabled);
    }

    public function test_administrators_can_read_the_synced_inbox(): void
    {
        BrightbeanMessage::create($this->payload());
        $this->actingAs($this->actor('Administrador'))->get(route('admin.integrations.brightbean.index'))
            ->assertOk()->assertSee('Hola')->assertSee('Responder automáticamente con IA');
    }

    private function actor(string $role = 'Administrador'): User
    {
        Role::findOrCreate($role, 'web');
        return User::withoutEvents(fn () => User::create(['name' => 'Integration', 'email' => uniqid().'@example.test', 'password' => 'test']))->assignRole($role);
    }

    private function token(array $abilities, string $role = 'Administrador'): string
    {
        return $this->actor($role)->createToken('BrightBean test', $abilities)->plainTextToken;
    }

    private function payload(): array
    {
        return [
            'workspace_id' => (string) Str::uuid(), 'external_id' => (string) Str::uuid(),
            'conversation_id' => (string) Str::uuid(), 'contact_id' => (string) Str::uuid(),
            'connection_id' => (string) Str::uuid(), 'platform' => 'whatsapp', 'direction' => 'in',
            'source' => '', 'status' => 'sent', 'internal' => false, 'text' => 'Hola',
            'body' => ['blocks' => [['type' => 'text', 'text' => 'Hola']]],
            'contact' => ['first_name' => 'Test', 'last_name' => '', 'email' => '', 'phone' => '+580000000'],
            'source_created_at' => now()->toISOString(), 'source_updated_at' => now()->toISOString(),
        ];
    }
}

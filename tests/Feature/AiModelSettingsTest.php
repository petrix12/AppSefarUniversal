<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminAiModelSettingsController;
use App\Http\Controllers\ChatController;
use App\Models\ChatSession;
use App\Models\RoleAiAssistant;
use App\Services\AiProcessSettingsService;
use App\Services\RoleAiAssistantService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AiModelSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        (require database_path('migrations/2026_10_09_120000_create_ai_process_settings_table.php'))->up();
        config(['services.openrouter.key' => 'test-key']);
        Http::preventStrayRequests();
    }

    public function test_saved_process_settings_override_defaults_and_refresh_the_cache(): void
    {
        $settings = app(AiProcessSettingsService::class);
        $this->assertSame('google/gemini-3.5-flash-lite', $settings->get('assistant_chat')['model']);

        $settings->save('assistant_chat', [
            'model' => 'provider/custom-model',
            'fallback_models' => ['provider/backup'],
            'timeout_seconds' => 25,
            'max_tokens' => 1500,
        ], 42);

        $actual = $settings->get('assistant_chat');
        $this->assertSame('provider/custom-model', $actual['model']);
        $this->assertSame(['provider/backup'], $actual['fallback_models']);
        $this->assertSame(25, $actual['timeout_seconds']);
        $this->assertSame(1500, $actual['max_tokens']);
        $this->assertDatabaseHas('ai_process_settings', ['process' => 'assistant_chat', 'updated_by' => 42]);
    }

    public function test_catalog_includes_multimodal_text_models_and_prioritises_the_latest_flash_lite(): void
    {
        $textModel = fn (string $id) => [
            'id' => $id,
            'architecture' => ['input_modalities' => ['text', 'image'], 'output_modalities' => ['text']],
            'pricing' => ['prompt' => '0.0000003', 'completion' => '0.0000025'],
        ];
        Http::fake(['openrouter.ai/api/v1/models' => Http::response(['data' => [
            $textModel('provider/another-model'),
            $textModel(AiProcessSettingsService::FALLBACK_MODEL),
            $textModel(AiProcessSettingsService::DEFAULT_MODEL),
            ['id' => 'provider/image-only', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['image']]],
        ]])]);

        $view = app(AdminAiModelSettingsController::class)->show(app(AiProcessSettingsService::class));
        $models = $view->getData()['models'];

        $this->assertSame([
            AiProcessSettingsService::DEFAULT_MODEL,
            AiProcessSettingsService::FALLBACK_MODEL,
            'provider/another-model',
        ], array_column($models, 'id'));
        $this->assertEqualsWithDelta(0.30, $models[0]['input_price'], 0.00001);
        $this->assertEqualsWithDelta(2.50, $models[0]['output_price'], 0.00001);
    }

    public function test_role_assistants_use_the_process_model_and_forward_its_backup_and_reasoning_settings(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);
        $assistant = new RoleAiAssistant(['model' => null]);
        $service = app(RoleAiAssistantService::class);

        $answer = (new \ReflectionMethod($service, 'callOpenRouter'))->invoke(
            $service, $assistant, [['role' => 'user', 'content' => 'Prueba']]
        );

        $this->assertSame('OK', $answer);
        Http::assertSent(fn ($request) => $request['models'] === [
            'google/gemini-3.5-flash-lite', 'google/gemini-3.1-flash-lite',
        ] && $request['reasoning']['effort'] === 'minimal'
            && $request['provider']['sort'] === 'latency');
    }

    public function test_custom_role_model_remains_primary_and_duplicate_backups_are_removed(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);
        $service = app(RoleAiAssistantService::class);

        (new \ReflectionMethod($service, 'callOpenRouter'))->invoke(
            $service,
            new RoleAiAssistant(['model' => AiProcessSettingsService::FALLBACK_MODEL]),
            [['role' => 'user', 'content' => 'Prueba']]
        );

        Http::assertSent(fn ($request) => $request['models'] === [AiProcessSettingsService::FALLBACK_MODEL]);
    }

    public function test_chat_rejects_empty_successful_responses_without_saving_a_blank_message(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('session_id');
            $table->json('messages');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        $session = ChatSession::create(['session_id' => 'test-session', 'messages' => []]);
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '']]]])]);

        $response = app(ChatController::class)->enviarMensaje(Request::create('/chat/enviar', 'POST', [
            'session_id' => 'test-session', 'mensaje' => 'Prueba',
        ]));

        $this->assertSame(502, $response->status());
        $this->assertSame([], $session->fresh()->messages);
    }
}

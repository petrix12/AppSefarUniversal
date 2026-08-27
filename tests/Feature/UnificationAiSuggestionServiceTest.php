<?php

namespace Tests\Feature;

use App\Services\UnificationAiSuggestionService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnificationAiSuggestionServiceTest extends TestCase
{
    public function test_it_requests_a_structured_audit_suggestion_without_writing_any_mapping(): void
    {
        config()->set('services.openrouter.key', 'test-key');
        config()->set('services.openrouter.url', 'https://openrouter.test/chat');
        config()->set('services.openrouter.unification_model', 'qwen/qwen3.5-flash-02-23');

        Http::fake([
            'https://openrouter.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            'recommendation' => 'review_match',
                            'confidence' => 86,
                            'reason' => 'Las etiquetas Estado documental coinciden.',
                            'suggested_app_field_key' => 'estado_documental',
                            'suggested_app_field_label' => 'Estado documental',
                        ]),
                    ],
                ]],
            ]),
        ]);

        $suggestion = app(UnificationAiSuggestionService::class)->suggest([
            'app' => null,
            'hubspot' => [],
            'teamleader' => [],
            'monday_matches' => [[
                'key' => 'status',
                'label' => 'Estado documental',
                'scope_key' => 'ventas',
                'type' => 'status',
            ]],
            'match_method' => 'unmatched',
        ], [[
            'key' => 'estado_documental',
            'label' => 'Estado documental',
            'storage' => 'proposed',
        ]]);

        $this->assertSame('review_match', $suggestion['recommendation']);
        $this->assertSame(86, $suggestion['confidence']);
        $this->assertSame('estado_documental', $suggestion['suggested_app_field_key']);
        $this->assertSame('qwen/qwen3.5-flash-02-23', $suggestion['model']);

        Http::assertSent(function (ClientRequest $request): bool {
            return $request->url() === 'https://openrouter.test/chat'
                && $request['model'] === 'qwen/qwen3.5-flash-02-23'
                && data_get($request->data(), 'provider.require_parameters') === true
                && data_get($request->data(), 'response_format.type') === 'json_schema';
        });
    }
}

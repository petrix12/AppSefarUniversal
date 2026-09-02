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
                            'suggestions' => [[
                                'candidate_index' => 0,
                                'confidence' => 86,
                                'reason' => 'Las etiquetas Estado documental coinciden.',
                            ]],
                        ]),
                    ],
                ]],
            ]),
        ]);

        $suggestion = app(UnificationAiSuggestionService::class)->suggestPlatformPair('app', 'monday', [$this->candidate()]);

        $this->assertSame(86, $suggestion['suggestions'][0]['confidence']);
        $this->assertSame('estado_documental', $suggestion['suggestions'][0]['left']['key']);
        $this->assertSame('status', $suggestion['suggestions'][0]['right']['key']);
        $this->assertSame('qwen/qwen3.5-flash-02-23', $suggestion['model']);
        $this->assertTrue($suggestion['used_ai']);

        Http::assertSent(function (ClientRequest $request): bool {
            $prompt = json_decode((string) data_get($request->data(), 'messages.1.content'), true);

            return $request->url() === 'https://openrouter.test/chat'
                && $request['models'] === ['qwen/qwen3.5-flash-02-23', 'google/gemini-2.5-flash']
                && data_get($request->data(), 'provider.require_parameters') === true
                && data_get($request->data(), 'response_format.type') === 'json_object'
                && data_get($prompt, 'left_platform') === 'app'
                && data_get($prompt, 'right_platform') === 'monday'
                && count(data_get($prompt, 'candidate_pairs', [])) === 1;
        });
    }

    public function test_it_preserves_a_safe_openrouter_error_message_for_the_audit_screen(): void
    {
        config()->set('services.openrouter.key', 'secret-test-key');
        config()->set('services.openrouter.url', 'https://openrouter.test/chat');
        config()->set('services.openrouter.unification_model', 'qwen/qwen3.5-flash-02-23');

        Http::fake([
            'https://openrouter.test/chat' => Http::response([
                'error' => ['message' => 'Provider does not support response_format for Bearer secret-test-key'],
            ], 400),
        ]);

        try {
            app(UnificationAiSuggestionService::class)->suggestPlatformPair('app', 'hubspot', [$this->candidate()]);
            $this->fail('Expected the OpenRouter request to fail.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('OpenRouter HTTP 400', $exception->getMessage());
            $this->assertStringContainsString('Provider does not support response_format', $exception->getMessage());
            $this->assertStringNotContainsString('secret-test-key', $exception->getMessage());
        }
    }

    public function test_it_does_not_call_openrouter_when_the_selected_pair_has_no_local_candidates(): void
    {
        config()->set('services.openrouter.key', 'test-key');

        Http::fake();

        $suggestion = app(UnificationAiSuggestionService::class)->suggestPlatformPair('app', 'monday', []);

        $this->assertSame([], $suggestion['suggestions']);
        $this->assertFalse($suggestion['used_ai']);
        Http::assertNothingSent();
    }

    public function test_it_accepts_a_double_encoded_json_object_from_a_low_cost_provider(): void
    {
        config()->set('services.openrouter.key', 'test-key');
        config()->set('services.openrouter.url', 'https://openrouter.test/chat');

        Http::fake([
            'https://openrouter.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(json_encode([
                            'suggestions' => [[
                                'candidate_index' => 0,
                                'confidence' => 91,
                                'reason' => 'Coincidencia confirmada para revisión.',
                            ]],
                        ])),
                    ],
                ]],
            ]),
        ]);

        $suggestion = app(UnificationAiSuggestionService::class)->suggestPlatformPair('app', 'monday', [$this->candidate()]);

        $this->assertSame(91, $suggestion['suggestions'][0]['confidence']);
    }

    public function test_it_returns_a_sanitised_preview_when_the_model_returns_a_json_primitive(): void
    {
        config()->set('services.openrouter.key', 'test-key');
        config()->set('services.openrouter.url', 'https://openrouter.test/chat');

        Http::fake([
            'https://openrouter.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode('No puedo producir el objeto solicitado. Bearer test-key'),
                    ],
                ]],
            ]),
        ]);

        try {
            app(UnificationAiSuggestionService::class)->suggestPlatformPair('app', 'monday', [$this->candidate()]);
            $this->fail('Expected the invalid JSON shape to be reported.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('JSON de tipo string', $exception->getMessage());
            $this->assertStringContainsString('Vista previa:', $exception->getMessage());
            $this->assertStringNotContainsString('test-key', $exception->getMessage());
        }
    }

    public function test_it_never_sends_more_than_forty_candidates_even_if_the_environment_requests_more(): void
    {
        config()->set('services.openrouter.key', 'test-key');
        config()->set('services.openrouter.url', 'https://openrouter.test/chat');
        config()->set('services.openrouter.unification_max_candidates', 100);

        Http::fake([
            'https://openrouter.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['suggestions' => []]),
                    ],
                ]],
            ]),
        ]);

        $suggestion = app(UnificationAiSuggestionService::class)->suggestPlatformPair(
            'app',
            'monday',
            array_fill(0, 41, $this->candidate()),
        );

        $this->assertSame(40, $suggestion['candidate_limit']);
        Http::assertSent(function (ClientRequest $request): bool {
            $prompt = json_decode((string) data_get($request->data(), 'messages.1.content'), true);

            return count(data_get($prompt, 'candidate_pairs', [])) === 40;
        });
    }

    public function test_it_processes_an_explicit_batch_larger_than_forty_in_multiple_bounded_requests(): void
    {
        config()->set('services.openrouter.key', 'test-key');
        config()->set('services.openrouter.url', 'https://openrouter.test/chat');
        config()->set('services.openrouter.unification_max_candidates', 40);
        config()->set('services.openrouter.unification_max_batch_candidates', 200);

        Http::fake([
            'https://openrouter.test/chat' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['suggestions' => [[
                            'candidate_index' => 0,
                            'confidence' => 80,
                            'reason' => 'Propuesta para revisión humana.',
                        ]]]),
                    ],
                ]],
            ]),
        ]);
        $candidates = collect(range(0, 40))->map(function (int $index): array {
            $candidate = $this->candidate();
            $candidate['identity'] = "app|client|*|estado_{$index}↔monday|item|ventas|status_{$index}";
            $candidate['left']['key'] = "estado_{$index}";
            $candidate['right']['key'] = "status_{$index}";

            return $candidate;
        })->all();

        $suggestion = app(UnificationAiSuggestionService::class)->suggestPlatformPairBatch('app', 'monday', $candidates);

        $this->assertTrue($suggestion['used_ai']);
        $this->assertSame(41, $suggestion['candidate_count']);
        $this->assertSame(2, $suggestion['batch_count']);
        $this->assertCount(2, $suggestion['suggestions']);
        $this->assertSame('estado_40', $suggestion['suggestions'][1]['left']['key']);
        $this->assertCount(2, Http::recorded());
    }

    private function candidate(): array
    {
        return [
            'identity' => 'app|client|*|estado_documental↔monday|item|ventas|status',
            'left' => [
                'provider' => 'app', 'entity_type' => 'client', 'scope_key' => '*',
                'key' => 'estado_documental', 'label' => 'Estado documental',
            ],
            'right' => [
                'provider' => 'monday', 'entity_type' => 'item', 'scope_key' => 'ventas',
                'key' => 'status', 'label' => 'Estado documental',
            ],
            'confidence' => 100,
            'reason' => 'La clave o etiqueta coincide exactamente en ambos catálogos locales.',
        ];
    }
}

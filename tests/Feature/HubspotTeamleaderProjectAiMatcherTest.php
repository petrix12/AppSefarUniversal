<?php

namespace Tests\Feature;

use App\Services\HubspotTeamleaderProjectAiMatcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubspotTeamleaderProjectAiMatcherTest extends TestCase
{
    public function test_it_keeps_only_ai_matches_that_reference_a_sent_candidate(): void
    {
        config([
            'services.openrouter.key' => 'test-key',
            'services.openrouter.url' => 'https://ai.example.test/chat',
            'services.openrouter.unification_model' => 'test-model',
        ]);
        Http::fake([
            'https://ai.example.test/chat' => Http::response([
                'model' => 'test-model',
                'choices' => [[
                    'message' => ['content' => json_encode([
                        'matches' => [
                            ['candidate_index' => 0, 'confidence' => 91, 'reason' => 'Mismo servicio y monto.'],
                            ['candidate_index' => 99, 'confidence' => 100, 'reason' => 'No pertenece al lote.'],
                        ],
                    ])],
                ]],
            ]),
        ]);

        $result = app(HubspotTeamleaderProjectAiMatcher::class)->analyse([$this->candidate()]);

        $this->assertTrue($result['used_ai']);
        $this->assertSame('test-model', $result['model']);
        $this->assertCount(1, $result['matches']);
        $this->assertSame('deal-1:project-1', $result['matches'][0]['identity']);
        $this->assertSame(91, $result['matches'][0]['ai_confidence']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            return $request->hasHeader('Authorization', 'Bearer test-key')
                && data_get($payload, 'messages.1.content') !== null
                && ! str_contains((string) data_get($payload, 'messages.1.content'), 'pasaporte');
        });
    }

    private function candidate(): array
    {
        return [
            'identity' => 'deal-1:project-1',
            'negocio_id' => 1,
            'teamleader_project_id' => 'project-1',
            'deal_title' => 'Nacionalidad española',
            'project_title' => 'Nacionalidad española',
            'deal_amount' => 1000,
            'project_amount' => 1000,
            'deterministic_confidence' => 75,
            'evidence' => ['title_similarity' => 75],
        ];
    }
}

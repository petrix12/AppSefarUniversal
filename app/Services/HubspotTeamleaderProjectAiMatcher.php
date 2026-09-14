<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

/**
 * A bounded, reviewable AI pass for difficult HubSpot-deal / Teamleader-project
 * matches. It only receives candidate commercial titles, amounts and the
 * deterministic evidence already calculated locally; documents and contact
 * records are never sent to the model.
 */
class HubspotTeamleaderProjectAiMatcher
{
    private const MAX_CANDIDATES = 24;

    public function available(): bool
    {
        return filled(config('services.openrouter.key'));
    }

    /**
     * @param array<int, array{identity: string, negocio_id: int, teamleader_project_id: string, deal_title: string, project_title: string, deal_amount: mixed, project_amount: mixed, deterministic_confidence: int, evidence: array}> $candidates
     * @return array{matches: array<int, array>, model: string, used_ai: bool}
     */
    public function analyse(array $candidates): array
    {
        if (! $this->available()) {
            return ['matches' => [], 'model' => $this->model(), 'used_ai' => false];
        }

        $candidates = array_values(array_slice($candidates, 0, self::MAX_CANDIDATES));
        if ($candidates === []) {
            return ['matches' => [], 'model' => $this->model(), 'used_ai' => false];
        }

        $key = (string) config('services.openrouter.key');
        $response = Http::timeout((int) config('services.openrouter.unification_timeout', 30))
            ->retry(1, 250, throw: false)
            ->withHeaders([
                'Authorization' => "Bearer {$key}",
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name').' · Enlace HubSpot/Teamleader',
            ])
            ->post(config('services.openrouter.url'), [
                'model' => $this->model(),
                'messages' => $this->messages($candidates),
                'temperature' => 0,
                'max_tokens' => 700,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenRouter no pudo analizar las coincidencias de proyectos (HTTP '.$response->status().').');
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($content === '') {
            throw new RuntimeException('OpenRouter no devolvió un análisis de coincidencias de proyectos.');
        }

        return [
            'matches' => $this->normalise($content, $candidates),
            'model' => (string) data_get($response->json(), 'model', $this->model()),
            'used_ai' => true,
        ];
    }

    private function messages(array $candidates): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'Eres un analista de calidad de datos. Evalúas si un trato de HubSpot y un proyecto histórico de Teamleader describen el mismo encargo comercial. Sé conservador: nunca inventes coincidencias, no supongas que nombres de servicios similares son el mismo encargo y no propongas nada que no esté en la lista. Responde exclusivamente JSON válido, sin Markdown.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'candidate_pairs' => collect($candidates)->values()->map(fn (array $candidate, int $index) => [
                        'candidate_index' => $index,
                        'hubspot_deal' => [
                            'title' => $candidate['deal_title'],
                            'amount' => $candidate['deal_amount'],
                        ],
                        'teamleader_project' => [
                            'title' => $candidate['project_title'],
                            'amount' => $candidate['project_amount'],
                        ],
                        'deterministic_confidence' => $candidate['deterministic_confidence'],
                        'deterministic_evidence' => $candidate['evidence'],
                    ])->all(),
                    'output_contract' => [
                        'match' => '{"matches":[{"candidate_index":0,"confidence":88,"reason":"motivo breve"}]}',
                        'no_match' => '{"matches":[]}',
                        'rules' => 'Devuelve solamente pares con evidencia suficiente. candidate_index debe pertenecer a candidate_pairs y confidence debe ser entero de 0 a 100.',
                    ],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ],
        ];
    }

    private function normalise(string $content, array $candidates): array
    {
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/iu', '', $content) ?: '';
        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('OpenRouter devolvió un análisis de proyectos que no es JSON válido.');
        }

        return collect($decoded['matches'] ?? [])
            ->filter(fn (mixed $match) => is_array($match))
            ->map(function (array $match) use ($candidates): ?array {
                $index = filter_var($match['candidate_index'] ?? null, FILTER_VALIDATE_INT);
                if ($index === false || ! isset($candidates[$index])) {
                    return null;
                }

                return array_merge($candidates[$index], [
                    'ai_confidence' => max(0, min(100, (int) ($match['confidence'] ?? 0))),
                    'ai_reason' => Str::limit(trim((string) ($match['reason'] ?? '')), 500, ''),
                ]);
            })
            ->filter()
            ->unique('identity')
            ->values()
            ->all();
    }

    private function model(): string
    {
        return trim((string) config('services.openrouter.unification_model', 'qwen/qwen3-32b')) ?: 'qwen/qwen3-32b';
    }
}

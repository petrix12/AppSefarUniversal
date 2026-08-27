<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

/**
 * Suggests field relationships for human review. It never writes a mapping,
 * custom field or audit decision, and sends only field metadata to the model.
 */
class UnificationAiSuggestionService
{
    public function available(): bool
    {
        return filled(config('services.openrouter.key'));
    }

    /**
     * Evaluates only the candidate pairs for two administrator-selected
     * platforms. It never receives the complete cross-platform catalogue.
     */
    public function suggestPlatformPair(string $leftProvider, string $rightProvider, array $candidates): array
    {
        $apiKey = config('services.openrouter.key');

        if (blank($apiKey)) {
            throw new RuntimeException('Falta configurar OPENROUTER_API_KEY para solicitar una sugerencia.');
        }

        // The environment may reduce this threshold, but never increase it.
        // This cap keeps an accidental broad request from consuming credits.
        $candidateLimit = max(1, min(40, (int) config('services.openrouter.unification_max_candidates', 40)));
        $candidates = array_values(array_slice($candidates, 0, $candidateLimit));
        if ($candidates === []) {
            return [
                'suggestions' => [],
                'model' => config('services.openrouter.unification_model', 'qwen/qwen3.5-flash-02-23'),
                'candidate_limit' => $candidateLimit,
                'used_ai' => false,
            ];
        }

        $response = Http::timeout((int) config('services.openrouter.unification_timeout', 30))
            ->retry(1, 250, throw: false)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name').' · Mapa de unificación',
                'X-OpenRouter-Metadata' => 'enabled',
            ])
            ->post(config('services.openrouter.url'), [
                'model' => config('services.openrouter.unification_model', 'qwen/qwen3.5-flash-02-23'),
                'messages' => $this->pairMessages($leftProvider, $rightProvider, $candidates),
                'temperature' => 0.1,
                'max_tokens' => 700,
                // This rejects providers that cannot honor the requested JSON mode.
                'provider' => ['require_parameters' => true],
                'response_format' => $this->responseFormat(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->apiErrorMessage($response->status(), $response->json(), $response->body(), $apiKey));
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($content === '') {
            throw new RuntimeException('OpenRouter no devolvió una sugerencia.');
        }

        try {
            $suggestion = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('OpenRouter devolvió una respuesta que no cumple el formato esperado.');
        }

        if (! is_array($suggestion)) {
            throw new RuntimeException('OpenRouter devolvió una sugerencia inválida.');
        }

        return $this->normalisePairSuggestions($suggestion, $candidates, $candidateLimit);
    }

    private function pairMessages(string $leftProvider, string $rightProvider, array $candidates): array
    {
        return [
            [
                'role' => 'system',
                'content' => 'Eres un asistente de gobierno de datos. Evalúas pares candidatos de campos entre dos plataformas. Tu salida es exclusivamente una recomendación para auditoría humana: nunca afirmes que se ejecutó una sincronización ni que se debe activar un proceso. Solo puedes aprobar índices de la lista recibida; no inventes campos, valores, tipos ni reglas de negocio. Descarta pares si la evidencia semántica no basta. Las claves técnicas crípticas de Monday por sí solas no son evidencia suficiente. Responde únicamente con un objeto JSON válido, sin Markdown ni texto adicional.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'left_platform' => $leftProvider,
                    'right_platform' => $rightProvider,
                    'candidate_pairs' => collect($candidates)->values()->map(fn (array $candidate, int $index) => [
                        'candidate_index' => $index,
                        'left' => $candidate['left'],
                        'right' => $candidate['right'],
                        'deterministic_confidence' => $candidate['confidence'],
                        'deterministic_reason' => $candidate['reason'],
                    ])->all(),
                    'task' => 'Devuelve como máximo 20 índices que merecen convertirse en propuesta de auditoría. Omite los que no correspondan.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ],
        ];
    }

    private function responseFormat(): array
    {
        // Some low-cost routes accept JSON mode but reject a strict JSON
        // Schema even when the model catalogue advertises structured output.
        // Application-side validation still checks every returned index.
        if (config('services.openrouter.unification_response_format', 'json_object') !== 'json_schema') {
            return ['type' => 'json_object'];
        }

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'unification_field_suggestion',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['suggestions'],
                    'properties' => [
                        'suggestions' => [
                            'type' => 'array',
                            'maxItems' => 20,
                            'items' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['candidate_index', 'confidence', 'reason'],
                                'properties' => [
                                    'candidate_index' => ['type' => 'integer', 'minimum' => 0],
                                    'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                                    'reason' => ['type' => 'string', 'maxLength' => 500],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function normalisePairSuggestions(array $suggestion, array $candidates, int $candidateLimit): array
    {
        return [
            'suggestions' => collect($suggestion['suggestions'] ?? [])
                ->filter(fn (mixed $item) => is_array($item))
                ->map(function (array $item) use ($candidates): ?array {
                    $index = (int) ($item['candidate_index'] ?? -1);
                    if (! isset($candidates[$index])) {
                        return null;
                    }

                    return array_merge($candidates[$index], [
                        'confidence' => max(0, min(100, (int) ($item['confidence'] ?? 0))),
                        'reason' => Str::limit(trim((string) ($item['reason'] ?? '')), 500, ''),
                        'source' => 'openrouter',
                    ]);
                })
                ->filter()
                ->unique('identity')
                ->values()
                ->all(),
            'model' => config('services.openrouter.unification_model', 'qwen/qwen3.5-flash-02-23'),
            'candidate_limit' => $candidateLimit,
            'used_ai' => true,
        ];
    }

    private function apiErrorMessage(int $status, mixed $payload, string $body, string $apiKey): string
    {
        $providerMessage = is_array($payload)
            ? (string) (data_get($payload, 'error.message') ?: data_get($payload, 'message') ?: '')
            : '';
        $providerMessage = $providerMessage ?: $body;
        $providerMessage = str_ireplace($apiKey, '[clave oculta]', $providerMessage);
        $providerMessage = preg_replace('/Bearer\s+\S+/i', 'Bearer [clave oculta]', $providerMessage) ?: '';
        $providerMessage = Str::limit(trim($providerMessage), 900, '');

        $model = config('services.openrouter.unification_model', 'qwen/qwen3.5-flash-02-23');

        return "OpenRouter HTTP {$status} con {$model}: ".($providerMessage ?: 'sin detalle adicional del proveedor.');
    }
}

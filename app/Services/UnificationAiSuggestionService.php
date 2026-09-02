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
    private const ABSOLUTE_BATCH_CANDIDATE_LIMIT = 1000;

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

        $candidateLimit = $this->perRequestCandidateLimit();
        $candidates = array_values(array_slice($candidates, 0, $candidateLimit));
        if ($candidates === []) {
            return [
                'suggestions' => [],
                'model' => $this->primaryModel(),
                'candidate_limit' => $candidateLimit,
                'used_ai' => false,
            ];
        }

        $models = $this->models();
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
                // `models` enables OpenRouter's own failover: a 429 from the
                // primary model/provider tries the next configured model.
                'models' => $models,
                'messages' => $this->pairMessages($leftProvider, $rightProvider, $candidates),
                'temperature' => 0.1,
                'max_tokens' => 700,
                // This rejects providers that cannot honor the requested JSON mode.
                'provider' => ['require_parameters' => true],
                'response_format' => $this->responseFormat(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($this->apiErrorMessage($response->status(), $response->json(), $response->body(), $apiKey, $models));
        }

        $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
        if ($content === '') {
            $refusal = trim((string) data_get($response->json(), 'choices.0.message.refusal', ''));

            throw new RuntimeException('OpenRouter no devolvió contenido en choices[0].message.content.'
                .($refusal !== '' ? ' Motivo indicado: '.$this->contentPreview($refusal) : ''));
        }

        $suggestion = $this->decodeSuggestion($content);

        return $this->normalisePairSuggestions(
            $suggestion,
            $candidates,
            $candidateLimit,
            (string) data_get($response->json(), 'model', $this->primaryModel()),
        );
    }

    /**
     * Evaluates every explicitly selected pair in bounded model requests. The
     * per-request limit protects prompt size; it is not a limit on the batch
     * the administrator deliberately selected.
     */
    public function suggestPlatformPairBatch(string $leftProvider, string $rightProvider, array $candidates): array
    {
        $candidates = collect($candidates)
            ->filter(fn (mixed $candidate) => is_array($candidate))
            ->unique('identity')
            ->values()
            ->all();
        $candidateCount = count($candidates);
        $batchLimit = $this->batchCandidateLimit();

        if ($candidateCount > $batchLimit) {
            throw new RuntimeException("El lote contiene {$candidateCount} parejas y supera el máximo configurado de {$batchLimit}. Divide la revisión en lotes más pequeños o aumenta OPENROUTER_UNIFICATION_MAX_BATCH_CANDIDATES.");
        }
        if ($candidates === []) {
            return [
                'suggestions' => [],
                'model' => $this->primaryModel(),
                'candidate_limit' => $this->perRequestCandidateLimit(),
                'candidate_count' => 0,
                'batch_count' => 0,
                'used_ai' => false,
            ];
        }

        $suggestions = [];
        $chunks = array_chunk($candidates, $this->perRequestCandidateLimit());
        foreach ($chunks as $chunk) {
            $result = $this->suggestPlatformPair($leftProvider, $rightProvider, $chunk);
            $suggestions = array_merge($suggestions, $result['suggestions']);
        }

        return [
            'suggestions' => collect($suggestions)->unique('identity')->values()->all(),
            'model' => $this->primaryModel(),
            'candidate_limit' => $this->perRequestCandidateLimit(),
            'candidate_count' => $candidateCount,
            'batch_count' => count($chunks),
            'used_ai' => true,
        ];
    }

    public function batchCandidateLimit(): int
    {
        return max(
            $this->perRequestCandidateLimit(),
            min(self::ABSOLUTE_BATCH_CANDIDATE_LIMIT, (int) config('services.openrouter.unification_max_batch_candidates', 200)),
        );
    }

    private function perRequestCandidateLimit(): int
    {
        return max(1, min(40, (int) config('services.openrouter.unification_max_candidates', 40)));
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
                    'output_contract' => [
                        'required_object' => '{"suggestions":[{"candidate_index":0,"confidence":86,"reason":"motivo breve"}]}',
                        'empty_result' => '{"suggestions":[]}',
                        'rules' => 'suggestions debe ser una lista; cada candidate_index debe pertenecer a candidate_pairs.',
                    ],
                    'task' => 'Devuelve como máximo 20 índices que merecen convertirse en propuesta de auditoría. Omite los que no correspondan y usa empty_result si ninguno aplica.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ],
        ];
    }

    /**
     * Low-cost providers occasionally wrap valid JSON in a JSON string or
     * return the suggestions array directly. Both variants remain safe once
     * candidate indexes are validated by normalisePairSuggestions().
     */
    private function decodeSuggestion(string $content): array
    {
        $content = preg_replace('/^```(?:json)?\s*|\s*```$/iu', '', trim($content)) ?: '';
        $wasWrappedString = false;

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                if ($wasWrappedString) {
                    throw new RuntimeException('OpenRouter devolvió JSON de tipo string; su contenido no era un objeto JSON con la clave suggestions. Vista previa: '
                        .$this->contentPreview($content));
                }

                throw new RuntimeException('OpenRouter devolvió contenido que no es JSON válido. Vista previa: '
                    .$this->contentPreview($content));
            }

            if (is_string($decoded)) {
                $wasWrappedString = true;
                $content = trim($decoded);
                continue;
            }

            if (is_array($decoded)) {
                return array_is_list($decoded) ? ['suggestions' => $decoded] : $decoded;
            }

            throw new RuntimeException('OpenRouter devolvió JSON de tipo '.get_debug_type($decoded)
                .'; se esperaba un objeto con la clave suggestions. Vista previa: '
                .$this->contentPreview($content));
        }

        throw new RuntimeException('OpenRouter devolvió una cadena JSON doblemente codificada pero incompleta. Vista previa: '
            .$this->contentPreview($content));
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

    private function normalisePairSuggestions(array $suggestion, array $candidates, int $candidateLimit, string $model): array
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
            'model' => $model,
            'candidate_limit' => $candidateLimit,
            'used_ai' => true,
        ];
    }

    /** @return array<int, string> */
    private function models(): array
    {
        $fallbacks = config('services.openrouter.unification_fallback_models', []);
        if (is_string($fallbacks)) {
            $fallbacks = preg_split('/\s*,\s*/', trim($fallbacks)) ?: [];
        }

        return collect(array_merge([$this->primaryModel()], is_array($fallbacks) ? $fallbacks : []))
            ->map(fn (mixed $model) => trim((string) $model))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function primaryModel(): string
    {
        return trim((string) config('services.openrouter.unification_model', 'qwen/qwen3-32b')) ?: 'qwen/qwen3-32b';
    }

    private function apiErrorMessage(int $status, mixed $payload, string $body, string $apiKey, array $models): string
    {
        $providerMessage = is_array($payload)
            ? (string) (data_get($payload, 'error.message') ?: data_get($payload, 'message') ?: '')
            : '';
        $providerMessage = $providerMessage ?: $body;
        $providerMessage = str_ireplace($apiKey, '[clave oculta]', $providerMessage);
        $providerMessage = preg_replace('/Bearer\s+\S+/i', 'Bearer [clave oculta]', $providerMessage) ?: '';
        $providerMessage = Str::limit(trim($providerMessage), 900, '');

        $model = implode(' → ', $models ?: [$this->primaryModel()]);

        return "OpenRouter HTTP {$status} con {$model}: ".($providerMessage ?: 'sin detalle adicional del proveedor.');
    }

    /**
     * The endpoint is administrator-only and the prompt contains field
     * metadata, not client values. Still, limit and redact the diagnostic
     * preview before returning it to the browser.
     */
    private function contentPreview(string $content): string
    {
        $apiKey = (string) config('services.openrouter.key');
        $content = preg_replace('/Bearer\s+\S+/i', 'Bearer [clave oculta]', $content) ?: '';
        if ($apiKey !== '') {
            $content = str_ireplace($apiKey, '[clave oculta]', $content);
        }
        $content = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $content) ?: '';

        return Str::limit(trim($content), 1200, '…');
    }
}

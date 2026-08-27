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

    public function suggest(array $mapRow, array $appFields): array
    {
        $apiKey = config('services.openrouter.key');

        if (blank($apiKey)) {
            throw new RuntimeException('Falta configurar OPENROUTER_API_KEY para solicitar una sugerencia.');
        }

        $response = Http::timeout((int) config('services.openrouter.unification_timeout', 30))
            ->retry(1, 250, throw: false)
            ->withHeaders([
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name').' · Mapa de unificación',
            ])
            ->post(config('services.openrouter.url'), [
                'model' => config('services.openrouter.unification_model', 'qwen/qwen3.5-flash-02-23'),
                'messages' => $this->messages($mapRow, $appFields),
                'temperature' => 0.1,
                'max_tokens' => 700,
                // This rejects providers that cannot honor the JSON Schema.
                'provider' => ['require_parameters' => true],
                'response_format' => $this->responseFormat(),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('OpenRouter respondió con error '.$response->status().'.');
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

        return $this->normaliseSuggestion($suggestion);
    }

    private function messages(array $mapRow, array $appFields): array
    {
        $catalogue = collect($appFields)
            ->take(150)
            ->map(fn (array $field) => [
                'key' => $field['key'],
                'label' => $field['label'],
                'storage' => $field['storage'],
                'data_type' => $field['data_type'] ?? null,
            ])
            ->values()
            ->all();

        $selected = [
            'app' => $mapRow['app'] ? [
                'key' => $mapRow['app']['key'],
                'label' => $mapRow['app']['label'],
                'storage' => $mapRow['app']['storage'],
            ] : null,
            'hubspot' => $this->fieldsForPrompt($mapRow['hubspot'] ?? []),
            'teamleader' => $this->fieldsForPrompt($mapRow['teamleader'] ?? []),
            'monday' => $this->fieldsForPrompt($mapRow['monday_matches'] ?? []),
            'current_match_method' => $mapRow['match_method'] ?? 'unknown',
        ];

        return [
            [
                'role' => 'system',
                'content' => 'Eres un asistente de gobierno de datos. Evalúas posibles correspondencias de campos entre una App, HubSpot, Teamleader y Monday. Tu salida es exclusivamente una recomendación para auditoría humana: nunca afirmes que se ejecutó una sincronización ni que se debe activar un proceso. Usa únicamente las claves y etiquetas proporcionadas; no inventes campos, valores, tipos ni reglas de negocio. Si la evidencia semántica no basta, responde needs_information o no_match. Las claves técnicas crípticas de Monday por sí solas no son evidencia suficiente.',
            ],
            [
                'role' => 'user',
                'content' => json_encode([
                    'selected_relation' => $selected,
                    'app_field_catalogue' => $catalogue,
                    'task' => 'Indica si la relación seleccionada merece revisión, no coincide o requiere más información. Si falta un campo App, sugiere como máximo uno del catálogo. Explica brevemente qué etiquetas sustentan la decisión.',
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            ],
        ];
    }

    private function fieldsForPrompt(array $fields): array
    {
        return collect($fields)
            ->take(10)
            ->map(fn (array $field) => [
                'key' => $field['key'] ?? null,
                'label' => $field['label'] ?? null,
                'scope_key' => $field['scope_key'] ?? null,
                'type' => $field['type'] ?? null,
                'confidence_from_name' => $field['confidence'] ?? null,
            ])
            ->values()
            ->all();
    }

    private function responseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'unification_field_suggestion',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['recommendation', 'confidence', 'reason', 'suggested_app_field_key', 'suggested_app_field_label'],
                    'properties' => [
                        'recommendation' => ['type' => 'string', 'enum' => ['review_match', 'no_match', 'needs_information']],
                        'confidence' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                        'reason' => ['type' => 'string', 'maxLength' => 500],
                        'suggested_app_field_key' => ['type' => 'string', 'maxLength' => 191],
                        'suggested_app_field_label' => ['type' => 'string', 'maxLength' => 255],
                    ],
                ],
            ],
        ];
    }

    private function normaliseSuggestion(array $suggestion): array
    {
        $recommendation = $suggestion['recommendation'] ?? 'needs_information';
        if (! in_array($recommendation, ['review_match', 'no_match', 'needs_information'], true)) {
            $recommendation = 'needs_information';
        }

        return [
            'recommendation' => $recommendation,
            'confidence' => max(0, min(100, (int) ($suggestion['confidence'] ?? 0))),
            'reason' => Str::limit(trim((string) ($suggestion['reason'] ?? '')), 500, ''),
            'suggested_app_field_key' => Str::limit(trim((string) ($suggestion['suggested_app_field_key'] ?? '')), 191, ''),
            'suggested_app_field_label' => Str::limit(trim((string) ($suggestion['suggested_app_field_label'] ?? '')), 255, ''),
            'model' => config('services.openrouter.unification_model', 'qwen/qwen3.5-flash-02-23'),
        ];
    }
}

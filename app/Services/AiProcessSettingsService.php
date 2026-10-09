<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiProcessSettingsService
{
    private const DEFAULTS = [
        'teamleader_association' => [
            'label' => 'Asociación HubSpot ↔ Teamleader',
            'description' => 'Compara código, servicio e importes del trato para sugerir el proyecto Teamleader.',
            'model' => 'qwen/qwen3-8b',
            'fallback_models' => ['qwen/qwen3.8-flash'],
            'timeout_seconds' => 90,
            'max_tokens' => 600,
        ],
        'data_unification' => [
            'label' => 'Unificación de datos',
            'description' => 'Sugiere equivalencias de campos y asociaciones durante auditorías de datos.',
            'model' => 'qwen/qwen3-32b',
            'fallback_models' => ['qwen/qwen3.8-flash'],
            'timeout_seconds' => 90,
            'max_tokens' => 900,
        ],
        'systems_activity' => [
            'label' => 'Resumen de actividad',
            'description' => 'Resume eventos del sistema para reportes internos.',
            'model' => 'qwen/qwen3-8b',
            'fallback_models' => ['qwen/qwen3.8-flash'],
            'timeout_seconds' => 60,
            'max_tokens' => 500,
        ],
        'cos_classifier' => [
            'label' => 'Clasificación COS',
            'description' => 'Clasifica las etiquetas y el tablero del COS para inferir estados del proceso.',
            'model' => 'qwen/qwen3-8b',
            'fallback_models' => ['qwen/qwen3.8-flash'],
            'timeout_seconds' => 45,
            'max_tokens' => 500,
        ],
        'assistant_chat' => [
            'label' => 'Chat de asistente',
            'description' => 'Modelo para las respuestas del chat interno con IA.',
            'model' => 'qwen/qwen3.8-flash',
            'fallback_models' => ['qwen/qwen3-8b'],
            'timeout_seconds' => 60,
            'max_tokens' => 1200,
        ],
        'deployment_summary' => [
            'label' => 'Resumen de despliegue',
            'description' => 'Genera las notas de versión a partir de cambios publicados.',
            'model' => 'qwen/qwen3-8b',
            'fallback_models' => ['qwen/qwen3.8-flash'],
            'timeout_seconds' => 60,
            'max_tokens' => 800,
        ],
        'role_assistants' => [
            'label' => 'Asistentes por rol',
            'description' => 'Modelo predeterminado cuando un asistente no tiene un modelo propio.',
            'model' => 'openai/gpt-4o-mini',
            'fallback_models' => [],
            'timeout_seconds' => 60,
            'max_tokens' => 1200,
        ],
    ];

    public function definitions(): array
    {
        return self::DEFAULTS;
    }

    public function get(string $process): array
    {
        $defaults = self::DEFAULTS[$process] ?? self::DEFAULTS['data_unification'];
        $stored = Cache::remember('ai-process-settings:'.$process, 60, fn () =>
            DB::table('ai_process_settings')->where('process', $process)->first()
        );

        if (! $stored) {
            return $defaults;
        }

        $fallbackModels = is_array($stored->fallback_models)
            ? $stored->fallback_models
            : (json_decode((string) $stored->fallback_models, true) ?: []);

        return array_merge($defaults, [
            'model' => (string) $stored->model,
            'fallback_models' => array_values(array_filter(array_map('strval', $fallbackModels))),
            'timeout_seconds' => (int) $stored->timeout_seconds,
            'max_tokens' => (int) $stored->max_tokens,
        ]);
    }

    public function save(string $process, array $settings, ?int $userId = null): void
    {
        $values = [
            'model' => $settings['model'],
            'fallback_models' => json_encode(array_values($settings['fallback_models']), JSON_UNESCAPED_SLASHES),
            'timeout_seconds' => $settings['timeout_seconds'],
            'max_tokens' => $settings['max_tokens'],
            'updated_by' => $userId,
            'updated_at' => now(),
        ];
        $query = DB::table('ai_process_settings')->where('process', $process);
        if ($query->exists()) {
            $query->update($values);
        } else {
            DB::table('ai_process_settings')->insert(array_merge($values, [
                'process' => $process,
                'created_at' => now(),
            ]));
        }
        Cache::forget('ai-process-settings:'.$process);
    }
}

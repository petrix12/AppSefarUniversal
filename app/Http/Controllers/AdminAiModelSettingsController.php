<?php

namespace App\Http\Controllers;

use App\Services\AiProcessSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class AdminAiModelSettingsController extends Controller
{
    public function show(AiProcessSettingsService $settings)
    {
        $models = [];
        $catalogError = null;
        $apiKey = (string) config('services.openrouter.key');

        if ($apiKey !== '') {
            try {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout(12)
                    ->get('https://openrouter.ai/api/v1/models', [
                        'sort' => 'latency-low-to-high',
                        'supported_parameters' => 'response_format',
                    ]);

                if ($response->successful()) {
                    $models = collect($response->json('data', []))
                        ->filter(fn (array $model) => data_get($model, 'architecture.modality') === 'text->text')
                        ->take(150)
                        ->map(fn (array $model) => [
                            'id' => (string) ($model['id'] ?? ''),
                            'name' => (string) ($model['name'] ?? $model['id'] ?? ''),
                            'input_price' => (float) (data_get($model, 'pricing.prompt', 0)) * 1000000,
                            'output_price' => (float) (data_get($model, 'pricing.completion', 0)) * 1000000,
                        ])
                        ->filter(fn (array $model) => $model['id'] !== '')
                        ->values()
                        ->all();
                } else {
                    $catalogError = 'OpenRouter no devolvió el catálogo de modelos.';
                }
            } catch (\Throwable $exception) {
                $catalogError = 'No se pudo actualizar el catálogo ahora. Puedes introducir el ID del modelo manualmente.';
            }
        } else {
            $catalogError = 'Configura la clave API de OpenRouter para cargar el catálogo. También puedes introducir un ID manualmente.';
        }

        $processSettings = collect($settings->definitions())->mapWithKeys(
            fn (array $definition, string $process) => [$process => array_merge($definition, $settings->get($process))]
        )->all();

        return view('admin.integrations.ai-models', [
            'processSettings' => $processSettings,
            'models' => $models,
            'catalogError' => $catalogError,
        ]);
    }

    public function update(Request $request, AiProcessSettingsService $settings): RedirectResponse
    {
        $validProcesses = array_keys($settings->definitions());
        $data = $request->validate([
            'process' => ['required', 'string', Rule::in($validProcesses)],
            'model' => ['required', 'string', 'max:180', 'regex:/^[A-Za-z0-9._-]+\/[A-Za-z0-9._:-]+$/'],
            'fallback_models' => ['nullable', 'string', 'max:1000'],
            'timeout_seconds' => ['required', 'integer', 'min:15', 'max:180'],
            'max_tokens' => ['required', 'integer', 'min:64', 'max:4000'],
        ]);

        $fallbackModels = collect(preg_split('/[\s,;]+/', trim((string) ($data['fallback_models'] ?? ''))) ?: [])
            ->map(fn (string $model) => trim($model))
            ->filter()
            ->reject(fn (string $model) => $model === $data['model'])
            ->unique()
            ->values()
            ->all();

        $settings->save($data['process'], [
            'model' => $data['model'],
            'fallback_models' => $fallbackModels,
            'timeout_seconds' => (int) $data['timeout_seconds'],
            'max_tokens' => (int) $data['max_tokens'],
        ], (int) $request->user()->id);

        return back()->with('success', 'Configuración del modelo guardada.');
    }
}

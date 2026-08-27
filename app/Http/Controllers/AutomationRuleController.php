<?php

namespace App\Http\Controllers;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Services\AutomationEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;

class AutomationRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'can:administrador']);
    }

    public function index()
    {
        $rules = AutomationRule::query()
            ->withCount([
                'actions' => fn ($query) => $query->where('is_active', true),
                'runs',
            ])
            ->latest()
            ->paginate(20);
        $recentRuns = AutomationRun::query()
            ->with(['rule:id,name', 'action:id,action_type'])
            ->latest()
            ->limit(15)
            ->get();

        return view('automations.index', compact('rules', 'recentRuns'));
    }

    public function create()
    {
        return view('automations.form', [
            'rule' => new AutomationRule([
                'entity_type' => 'client',
                'trigger_type' => AutomationRule::TRIGGER_EVENT,
                'trigger_event' => AutomationEngine::EVENT_FIELD_CHANGED,
                'timezone' => config('app.timezone'),
                'is_active' => true,
            ]),
            'actionsJson' => $this->prettyJson([[
                'type' => AutomationAction::CREATE_TASK,
                'delay_minutes' => 0,
                'config' => [
                    'assignee' => 'owner',
                    'title' => 'Seguimiento: {{client.name}}',
                    'description' => 'Generada por automatización.',
                ],
            ]]),
            'conditionsJson' => $this->prettyJson([]),
            'triggerConfigJson' => $this->prettyJson([]),
        ]);
    }

    public function store(Request $request)
    {
        $payload = $this->validatedPayload($request);

        $rule = DB::transaction(function () use ($payload): AutomationRule {
            $rule = AutomationRule::create(array_merge($payload['rule'], [
                'created_by_user_id' => auth()->id(),
            ]));
            $this->replaceActions($rule, $payload['actions']);

            return $rule;
        });

        return redirect()
            ->route('automations.index')
            ->with('success', "Automatización “{$rule->name}” creada. Las acciones se ejecutarán según su programación.");
    }

    public function edit(AutomationRule $automation)
    {
        $automation->load(['actions' => fn ($query) => $query->where('is_active', true)]);

        return view('automations.form', [
            'rule' => $automation,
            'actionsJson' => $this->prettyJson($automation->actions->map(fn (AutomationAction $action) => [
                'type' => $action->action_type,
                'delay_minutes' => $action->delay_minutes,
                'config' => $action->config ?? [],
            ])->values()->all()),
            'conditionsJson' => $this->prettyJson($automation->conditions ?? []),
            'triggerConfigJson' => $this->prettyJson($automation->trigger_config ?? []),
        ]);
    }

    public function update(Request $request, AutomationRule $automation)
    {
        $payload = $this->validatedPayload($request);

        DB::transaction(function () use ($automation, $payload): void {
            $automation->update($payload['rule']);
            $this->replaceActions($automation, $payload['actions']);
        });

        return redirect()
            ->route('automations.index')
            ->with('success', 'Automatización actualizada.');
    }

    public function toggle(AutomationRule $automation)
    {
        $automation->update(['is_active' => ! $automation->is_active]);

        return back()->with('success', $automation->is_active ? 'Automatización activada.' : 'Automatización pausada.');
    }

    public function destroy(AutomationRule $automation)
    {
        $automation->delete();

        return back()->with('success', 'Automatización eliminada junto con sus acciones y ejecuciones.');
    }

    public function runDue(AutomationEngine $engine)
    {
        $result = $engine->processDueRuns(100);

        return back()->with(
            'success',
            "Acciones listas procesadas: {$result['completed']} completadas, {$result['skipped']} omitidas, {$result['failed']} fallidas."
        );
    }

    private function validatedPayload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'entity_type' => ['required', 'in:client,global'],
            'trigger_type' => ['required', 'in:' . implode(',', AutomationRule::TRIGGER_TYPES)],
            'trigger_event' => ['nullable', 'string', 'max:100'],
            'cron_expression' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'trigger_config_json' => ['nullable', 'string'],
            'conditions_json' => ['nullable', 'string'],
            'actions_json' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($data['trigger_type'] === AutomationRule::TRIGGER_EVENT && blank($data['trigger_event'])) {
            throw ValidationException::withMessages(['trigger_event' => 'Selecciona o escribe el evento que activa la regla.']);
        }

        if ($data['trigger_type'] === AutomationRule::TRIGGER_SCHEDULE && blank($data['cron_expression'])) {
            throw ValidationException::withMessages(['cron_expression' => 'Una regla programada requiere una expresión cron.']);
        }

        $triggerConfig = $this->decodeObject($data['trigger_config_json'] ?? '', 'trigger_config_json');
        $conditions = $this->decodeObject($data['conditions_json'] ?? '', 'conditions_json');
        $actions = $this->decodeActions($data['actions_json']);

        if ($data['trigger_type'] === AutomationRule::TRIGGER_DATE_FIELD && blank($triggerConfig['field_key'] ?? null)) {
            throw ValidationException::withMessages(['trigger_config_json' => 'Una regla por fecha requiere trigger_config.field_key.']);
        }

        if ($data['trigger_type'] === AutomationRule::TRIGGER_DATE_FIELD && $data['entity_type'] !== 'client') {
            throw ValidationException::withMessages(['entity_type' => 'Las reglas por fecha se aplican a clientes.']);
        }

        if ($data['trigger_type'] === AutomationRule::TRIGGER_SCHEDULE
            && $data['entity_type'] === 'client'
            && empty($triggerConfig['client_id'])) {
            throw ValidationException::withMessages([
                'trigger_config_json' => 'Una programación para cliente requiere trigger_config.client_id.',
            ]);
        }

        return [
            'rule' => [
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'entity_type' => $data['entity_type'],
                'trigger_type' => $data['trigger_type'],
                'trigger_event' => $data['trigger_type'] === AutomationRule::TRIGGER_EVENT ? $data['trigger_event'] : null,
                'cron_expression' => $data['trigger_type'] === AutomationRule::TRIGGER_SCHEDULE ? $data['cron_expression'] : null,
                'timezone' => $data['timezone'],
                'trigger_config' => $triggerConfig ?: null,
                'conditions' => $conditions ?: null,
                'is_active' => $request->boolean('is_active'),
            ],
            'actions' => $actions,
        ];
    }

    private function replaceActions(AutomationRule $rule, array $actions): void
    {
        // Do not delete actions: runs are the audit trail and must retain the
        // version that generated them. Replacing a rule simply retires the
        // former actions and creates a new active version.
        $rule->actions()->where('is_active', true)->update(['is_active' => false]);

        foreach ($actions as $position => $action) {
            $rule->actions()->create([
                'position' => $position,
                'action_type' => $action['type'],
                'delay_minutes' => $action['delay_minutes'],
                'config' => $action['config'],
                'is_active' => true,
            ]);
        }
    }

    private function decodeObject(string $json, string $field): array
    {
        if (blank(trim($json))) {
            return [];
        }

        try {
            $value = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([$field => 'El JSON no es válido.']);
        }

        if (! is_array($value)) {
            throw ValidationException::withMessages([$field => 'El valor debe ser un objeto o arreglo JSON.']);
        }

        return $value;
    }

    private function decodeActions(string $json): array
    {
        $actions = $this->decodeObject($json, 'actions_json');

        if ($actions === []) {
            throw ValidationException::withMessages(['actions_json' => 'Agrega al menos una acción.']);
        }

        foreach ($actions as $index => $action) {
            if (! is_array($action)
                || ! in_array($action['type'] ?? null, AutomationAction::TYPES, true)
                || ! is_array($action['config'] ?? null)) {
                throw ValidationException::withMessages([
                    'actions_json' => "La acción #" . ($index + 1) . ' no tiene un tipo o configuración válida.',
                ]);
            }

            $actions[$index]['delay_minutes'] = max(0, min((int) ($action['delay_minutes'] ?? 0), 525600));
        }

        return array_values($actions);
    }

    private function prettyJson(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }
}

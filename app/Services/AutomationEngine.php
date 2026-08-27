<?php

namespace App\Services;

use App\Models\AutomationAction;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowBoard;
use App\Models\WorkflowStage;
use App\Notifications\ClientAppNotification;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Cron\CronExpression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Configurable CRM-style automation engine.
 *
 * Rules only create auditable runs. A separate due-run processor executes the
 * action, avoiding external calls and long work during user-facing requests.
 */
class AutomationEngine
{
    public const EVENT_FIELD_CHANGED = 'client.field_changed';
    public const EVENT_WORKFLOW_TRANSITIONED = 'workflow.transitioned';
    public const EVENT_SCHEDULE = 'schedule';
    public const EVENT_DATE_FIELD_DUE = 'date_field_due';

    /**
     * @return int Number of action runs created (duplicates are ignored).
     */
    public function trigger(
        string $event,
        ?User $client = null,
        array $context = [],
        ?string $eventKey = null,
        ?CarbonInterface $occurredAt = null,
    ): int {
        $occurredAt ??= now();
        $eventKey ??= (string) Str::uuid();
        $context = $this->buildContext($event, $client, $context);

        return AutomationRule::query()
            ->active()
            ->where('trigger_type', AutomationRule::TRIGGER_EVENT)
            ->where('trigger_event', $event)
            ->where('entity_type', $client ? CustomFieldDefinition::ENTITY_CLIENT : 'global')
            ->with(['actions' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->sum(fn (AutomationRule $rule) => $this->queueRuleActions(
                $rule,
                $client,
                $event,
                $context,
                $eventKey,
                $occurredAt,
            ));
    }

    /**
     * Evaluates cron and date-field rules, then executes ready action runs.
     */
    public function runScheduler(int $limit = 100): array
    {
        $cronQueued = $this->scheduleCronRules();
        $dateQueued = $this->scheduleDateFieldRules();
        $processed = $this->processDueRuns($limit);

        return [
            'cron_queued' => $cronQueued,
            'date_queued' => $dateQueued,
            'processed' => $processed,
        ];
    }

    /**
     * @return array{completed:int,failed:int,skipped:int}
     */
    public function processDueRuns(int $limit = 100): array
    {
        $stats = ['completed' => 0, 'failed' => 0, 'skipped' => 0];
        $runIds = AutomationRun::query()
            ->due()
            ->orderBy('scheduled_for')
            ->limit(max(1, min($limit, 500)))
            ->pluck('id');

        foreach ($runIds as $runId) {
            $claimed = AutomationRun::query()
                ->whereKey($runId)
                ->where('status', AutomationRun::PENDING)
                ->update([
                    'status' => AutomationRun::RUNNING,
                    'started_at' => now(),
                    'attempts' => DB::raw('attempts + 1'),
                    'updated_at' => now(),
                ]);

            if ($claimed === 0) {
                continue;
            }

            $run = AutomationRun::query()->with(['rule', 'action'])->findOrFail($runId);

            try {
                $result = $this->execute($run);
                $status = $result['skipped'] ?? false ? AutomationRun::SKIPPED : AutomationRun::COMPLETED;
                $run->forceFill([
                    'status' => $status,
                    'finished_at' => now(),
                    'result' => $result,
                    'error_message' => null,
                ])->save();
                $stats[$status]++;
            } catch (Throwable $exception) {
                $run->forceFill([
                    'status' => AutomationRun::FAILED,
                    'finished_at' => now(),
                    'error_message' => Str::limit($exception->getMessage(), 65000, ''),
                ])->save();
                $stats['failed']++;

                Log::channel('daily')->error('Falló una automatización', [
                    'automation_run_id' => $run->id,
                    'automation_rule_id' => $run->automation_rule_id,
                    'automation_action_id' => $run->automation_action_id,
                    'action_type' => $run->action?->action_type,
                    'entity_type' => $run->entity_type,
                    'entity_id' => $run->entity_id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function scheduleCronRules(): int
    {
        $queued = 0;

        AutomationRule::query()
            ->active()
            ->where('trigger_type', AutomationRule::TRIGGER_SCHEDULE)
            ->with(['actions' => fn ($query) => $query->where('is_active', true)])
            ->each(function (AutomationRule $rule) use (&$queued): void {
                if (blank($rule->cron_expression)) {
                    return;
                }

                try {
                    $cron = new CronExpression($rule->cron_expression);
                    $timezone = $rule->timezone ?: config('app.timezone');

                    if (! $cron->isDue(now(), $timezone)) {
                        return;
                    }
                } catch (Throwable $exception) {
                    Log::channel('daily')->warning('Automatización con cron inválido', [
                        'automation_rule_id' => $rule->id,
                        'cron_expression' => $rule->cron_expression,
                        'error' => $exception->getMessage(),
                    ]);

                    return;
                }

                $client = $this->clientFromRuleConfig($rule);
                $now = now();
                $eventKey = 'cron:'.$rule->id.':'.$now->copy()->timezone($rule->timezone ?: config('app.timezone'))->format('YmdHi');

                $queued += $this->queueRuleActions(
                    $rule,
                    $client,
                    self::EVENT_SCHEDULE,
                    $this->buildContext(self::EVENT_SCHEDULE, $client, ['schedule' => ['cron' => $rule->cron_expression]]),
                    $eventKey,
                    $now,
                );

                $rule->forceFill(['last_scheduled_at' => $now])->save();
            });

        return $queued;
    }

    private function scheduleDateFieldRules(): int
    {
        $queued = 0;

        AutomationRule::query()
            ->active()
            ->where('trigger_type', AutomationRule::TRIGGER_DATE_FIELD)
            ->with(['actions' => fn ($query) => $query->where('is_active', true)])
            ->each(function (AutomationRule $rule) use (&$queued): void {
                $fieldKey = data_get($rule->trigger_config, 'field_key');

                if (blank($fieldKey)) {
                    return;
                }

                $definition = CustomFieldDefinition::query()
                    ->forEntity(CustomFieldDefinition::ENTITY_CLIENT)
                    ->where('key', $fieldKey)
                    ->first();

                if (! $definition) {
                    return;
                }

                $offsetMinutes = (int) data_get($rule->trigger_config, 'offset_minutes', 0);
                $catchUp = (bool) data_get($rule->trigger_config, 'catch_up', false);

                CustomFieldValue::query()
                    ->where('custom_field_definition_id', $definition->id)
                    ->where('entity_type', CustomFieldDefinition::ENTITY_CLIENT)
                    ->whereNotNull('value_text')
                    ->orderBy('id')
                    ->chunkById(250, function ($values) use ($rule, $definition, $offsetMinutes, $catchUp, &$queued): void {
                        foreach ($values as $fieldValue) {
                            try {
                                $scheduledFor = Carbon::parse($fieldValue->value_text)->addMinutes($offsetMinutes);
                            } catch (Throwable) {
                                continue;
                            }

                            if ($scheduledFor->isFuture()) {
                                continue;
                            }

                            if (! $catchUp && $rule->created_at && $scheduledFor->lessThan($rule->created_at)) {
                                continue;
                            }

                            $client = User::find($fieldValue->entity_id);

                            if (! $client) {
                                continue;
                            }

                            $eventKey = 'date:'.sha1(implode('|', [
                                $rule->id,
                                $fieldValue->id,
                                $scheduledFor->utc()->format('Y-m-d H:i:s'),
                            ]));

                            $queued += $this->queueRuleActions(
                                $rule,
                                $client,
                                self::EVENT_DATE_FIELD_DUE,
                                $this->buildContext(self::EVENT_DATE_FIELD_DUE, $client, [
                                    'field' => [
                                        'key' => $definition->key,
                                        'value' => $fieldValue->decoded_value,
                                        'due_at' => $scheduledFor->toIso8601String(),
                                    ],
                                ]),
                                $eventKey,
                                $scheduledFor,
                            );
                        }
                    });
            });

        return $queued;
    }

    private function queueRuleActions(
        AutomationRule $rule,
        ?User $client,
        string $event,
        array $context,
        string $eventKey,
        CarbonInterface $occurredAt,
    ): int {
        if (! $this->matchesConditions((array) $rule->conditions, $context)) {
            return 0;
        }

        $created = 0;

        foreach ($rule->actions as $action) {
            $run = AutomationRun::firstOrCreate(
                [
                    'automation_action_id' => $action->id,
                    'event_key' => $eventKey,
                ],
                [
                    'automation_rule_id' => $rule->id,
                    'entity_type' => $client ? CustomFieldDefinition::ENTITY_CLIENT : 'global',
                    'entity_id' => $client?->id,
                    'trigger_event' => $event,
                    'scheduled_for' => $occurredAt->copy()->addMinutes($action->delay_minutes),
                    'status' => AutomationRun::PENDING,
                    'context' => $context,
                ]
            );

            if ($run->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function execute(AutomationRun $run): array
    {
        $action = $run->action;

        if (! $action || ! $action->is_active || ! $run->rule?->is_active) {
            return ['skipped' => true, 'reason' => 'La regla o acción está desactivada.'];
        }

        $client = $run->entity_type === CustomFieldDefinition::ENTITY_CLIENT && $run->entity_id
            ? User::find($run->entity_id)
            : null;

        return match ($action->action_type) {
            AutomationAction::CREATE_TASK => $this->createTask($run, $client),
            AutomationAction::NOTIFY_USER => $this->notifyUser($run, $client),
            AutomationAction::SET_CUSTOM_FIELD => $this->setCustomField($run, $client),
            AutomationAction::MOVE_WORKFLOW => $this->moveWorkflow($run, $client),
            default => throw new InvalidArgumentException("Acción de automatización no soportada: {$action->action_type}"),
        };
    }

    private function createTask(AutomationRun $run, ?User $client): array
    {
        $client ??= throw new RuntimeException('La acción crear tarea requiere un cliente.');
        $config = (array) $run->action->config;
        $assignee = $this->resolveRecipient($config['assignee'] ?? 'owner', $client, $config);

        if (! $assignee) {
            throw new RuntimeException('No se pudo resolver el responsable de la tarea automática.');
        }

        $marker = "[automation_run:{$run->id}]";
        $existing = Task::query()->where('description', 'like', "%{$marker}%")->first();

        if ($existing) {
            return ['task_id' => $existing->id, 'reused' => true];
        }

        $dueDate = filled($config['due_date'] ?? null)
            ? Carbon::parse($this->interpolate((string) $config['due_date'], $run->context ?? []))->toDateString()
            : today()->addDays((int) ($config['due_in_days'] ?? 0))->toDateString();
        $description = trim($this->interpolate((string) ($config['description'] ?? ''), $run->context ?? []));
        $description = trim($description."\n\n{$marker}");

        $task = Task::create([
            'user_id' => $assignee->id,
            'contact_id' => $client->id,
            'title' => $this->interpolate((string) ($config['title'] ?? 'Tarea automática'), $run->context ?? []),
            'description' => $description,
            'due_date' => $dueDate,
            'status' => Task::STATUS_PENDING,
            'created_by_user_id' => $run->rule->created_by_user_id,
            'skip_hubspot_reassignment' => true,
        ]);

        return ['task_id' => $task->id, 'assignee_id' => $assignee->id];
    }

    private function notifyUser(AutomationRun $run, ?User $client): array
    {
        $config = (array) $run->action->config;
        $recipient = $this->resolveRecipient($config['recipient'] ?? 'owner', $client, $config);

        if (! $recipient) {
            throw new RuntimeException('No se pudo resolver el destinatario de la notificación automática.');
        }

        $recipient->notify(new ClientAppNotification(
            $this->interpolate((string) ($config['title'] ?? 'Notificación automática'), $run->context ?? []),
            $this->interpolate((string) ($config['body'] ?? ''), $run->context ?? []),
            filled($config['action_url'] ?? null) ? $this->interpolate((string) $config['action_url'], $run->context ?? []) : null,
            filled($config['action_text'] ?? null) ? $this->interpolate((string) $config['action_text'], $run->context ?? []) : null,
            (string) ($config['category'] ?? 'automation'),
            (bool) ($config['send_email'] ?? false),
            (bool) ($config['store_in_app'] ?? true),
        ));

        return ['notified_user_id' => $recipient->id];
    }

    private function setCustomField(AutomationRun $run, ?User $client): array
    {
        $client ??= throw new RuntimeException('La acción actualizar campo requiere un cliente.');
        $config = (array) $run->action->config;
        $fieldKey = (string) ($config['field_key'] ?? '');

        if ($fieldKey === '') {
            throw new InvalidArgumentException('La acción actualizar campo requiere field_key.');
        }

        $value = $config['value'] ?? null;
        if (is_string($value)) {
            $value = $this->interpolate($value, $run->context ?? []);
        }

        $fieldValue = app(UnifiedClientProfileService::class)->setValue(
            $client,
            $fieldKey,
            $value,
            'automation',
            now(),
        );

        return ['custom_field_value_id' => $fieldValue->id, 'field_key' => $fieldKey];
    }

    private function moveWorkflow(AutomationRun $run, ?User $client): array
    {
        $client ??= throw new RuntimeException('La acción mover flujo requiere un cliente.');
        $config = (array) $run->action->config;
        $targetBoard = WorkflowBoard::find($config['target_board_id'] ?? null);
        $targetStage = WorkflowStage::find($config['target_stage_id'] ?? null);

        if (! $targetBoard || ! $targetStage) {
            throw new InvalidArgumentException('La acción mover flujo requiere un tablero y etapa de destino válidos.');
        }

        $source = $client->workflowMemberships()
            ->where('status', 'active')
            ->when($config['source_board_id'] ?? null, fn ($query, $boardId) => $query->where('workflow_board_id', $boardId))
            ->first();

        if (! $source) {
            return ['skipped' => true, 'reason' => 'El cliente no tiene una membresía activa de origen.'];
        }

        $membership = app(WorkflowTransferService::class)->transfer(
            $client,
            $source,
            $targetBoard,
            $targetStage,
            null,
            $this->interpolate((string) ($config['reason'] ?? 'Movimiento automático'), $run->context ?? []),
            'automation',
        );

        return ['workflow_membership_id' => $membership->id];
    }

    private function resolveRecipient(string $selection, ?User $client, array $config): ?User
    {
        return match ($selection) {
            'client' => $client,
            'owner' => $client?->owner,
            'user' => isset($config['user_id']) ? User::find($config['user_id']) : null,
            default => null,
        };
    }

    private function clientFromRuleConfig(AutomationRule $rule): ?User
    {
        $clientId = data_get($rule->trigger_config, 'client_id');

        return $clientId ? User::find($clientId) : null;
    }

    private function buildContext(string $event, ?User $client, array $context): array
    {
        return array_replace_recursive([
            'event' => $event,
            'client' => $client ? [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'owner_id' => $client->owner_id,
            ] : null,
        ], $context);
    }

    private function matchesConditions(array $conditions, array $context): bool
    {
        $conditions = $conditions['all'] ?? $conditions;

        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition) || blank($condition['path'] ?? null)) {
                return false;
            }

            $actual = data_get($context, $condition['path']);
            $expected = $condition['value'] ?? null;

            if (! $this->matchesCondition($actual, (string) ($condition['operator'] ?? 'equals'), $expected)) {
                return false;
            }
        }

        return true;
    }

    private function matchesCondition(mixed $actual, string $operator, mixed $expected): bool
    {
        return match ($operator) {
            'equals' => $actual == $expected,
            'not_equals' => $actual != $expected,
            'exists' => filled($actual),
            'empty' => blank($actual),
            'in' => in_array($actual, (array) $expected, true),
            'not_in' => ! in_array($actual, (array) $expected, true),
            'greater_than' => $actual > $expected,
            'less_than' => $actual < $expected,
            default => false,
        };
    }

    private function interpolate(string $template, array $context): string
    {
        return preg_replace_callback('/{{\s*([a-zA-Z0-9_.]+)\s*}}/', function (array $matches) use ($context): string {
            $value = data_get($context, $matches[1]);

            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
            }

            return (string) ($value ?? '');
        }, $template) ?? $template;
    }
}

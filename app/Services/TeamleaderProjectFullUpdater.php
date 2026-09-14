<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Builds a complete legacy projects.update payload before changing custom
 * fields. Teamleader replaces custom_fields as a collection in this account.
 */
class TeamleaderProjectFullUpdater
{
    public function __construct(
        private readonly TeamleaderService $teamleader,
        private readonly TeamleaderProjectPaymentWriter $paymentWriter,
    ) {
    }

    public function update(string $projectId, array $project, array $customFields): void
    {
        if ($this->historicalMode()) {
            Log::channel('teamleader')->info('Actualización de proyecto omitida: Teamleader está en modo histórico local.', [
                'project_id' => $projectId,
            ]);

            return;
        }

        $this->teamleader->updateProject(
            $projectId,
            $this->paymentWriter->projectUpdatePayload($project, $customFields)
        );
    }

    /**
     * Updates a subset of custom fields without dropping the other values or
     * the editable project data already stored in Teamleader.
     */
    public function updateCustomFields(string $projectId, array $changes, ?array $project = null): void
    {
        if ($this->historicalMode()) {
            Log::channel('teamleader')->info('Actualización de campos omitida: Teamleader está en modo histórico local.', [
                'project_id' => $projectId,
            ]);

            return;
        }

        $project ??= $this->teamleader->getProjectDetails($projectId);

        if (! is_array($project) || empty($project['id'])) {
            throw new \RuntimeException('Teamleader no devolvió el proyecto que se iba a actualizar.');
        }

        $this->update(
            (string) $project['id'],
            $project,
            $this->mergeCustomFields($project['custom_fields'] ?? [], $changes)
        );
    }

    /**
     * Teamleader replaces custom_fields as a collection. Normalize both its
     * response shape (definition.id) and update shape (id), then overlay the
     * intended changes while retaining every unrelated field.
     */
    private function mergeCustomFields(array $currentFields, array $changes): array
    {
        $fieldsById = [];

        foreach (array_merge($currentFields, $changes) as $field) {
            if (! is_array($field)) {
                continue;
            }

            $id = $field['id'] ?? data_get($field, 'definition.id');
            if (! $id) {
                continue;
            }

            $fieldsById[(string) $id] = [
                'id' => $id,
                'value' => $field['value'] ?? null,
            ];
        }

        return array_values($fieldsById);
    }

    /** See TeamleaderProjectPaymentWriter::historicalMode(). */
    private function historicalMode(): bool
    {
        try {
            return app()->bound('config')
                ? (bool) app('config')->get('services.teamleader.historical_mode', true)
                : false;
        } catch (\Throwable) {
            return false;
        }
    }
}

<?php

namespace App\Services;

use App\Models\IntegrationOutbox;
use App\Models\User;
use App\Models\WorkflowBoard;
use App\Models\WorkflowMembership;
use App\Models\WorkflowStage;
use App\Models\WorkflowTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class WorkflowTransferService
{
    /**
     * Moves a client between app workflows and records the external operation
     * in the outbox. The remote provider is never called synchronously here.
     */
    public function transfer(
        User $client,
        WorkflowMembership $fromMembership,
        WorkflowBoard $targetBoard,
        WorkflowStage $targetStage,
        ?User $actor = null,
        ?string $reason = null,
        string $source = 'app',
    ): WorkflowMembership {
        if ($fromMembership->entity_type !== UnifiedClientProfileService::ENTITY_CLIENT
            || (int) $fromMembership->entity_id !== (int) $client->id) {
            throw new InvalidArgumentException('La membresía de origen no pertenece al cliente indicado.');
        }

        if ((int) $targetStage->workflow_board_id !== (int) $targetBoard->id) {
            throw new InvalidArgumentException('La etapa de destino no pertenece al tablero de destino.');
        }

        [$membership, $transition] = DB::transaction(function () use (
            $client,
            $fromMembership,
            $targetBoard,
            $targetStage,
            $actor,
            $reason,
            $source
        ): array {
            $fromMembership->refresh();
            $isSameBoard = (int) $fromMembership->workflow_board_id === (int) $targetBoard->id;
            $fromStageId = $fromMembership->workflow_stage_id;

            $targetMembership = $isSameBoard
                ? $fromMembership
                : WorkflowMembership::firstOrNew([
                    'entity_type' => UnifiedClientProfileService::ENTITY_CLIENT,
                    'entity_id' => $client->id,
                    'workflow_board_id' => $targetBoard->id,
                ]);

            if (! $isSameBoard) {
                $fromMembership->forceFill([
                    'status' => 'moved',
                    'left_at' => now(),
                ])->save();
            }

            $targetMembership->fill([
                'workflow_stage_id' => $targetStage->id,
                'status' => 'active',
                'source' => $source,
                'entered_at' => $targetMembership->entered_at ?? now(),
                'left_at' => null,
            ]);
            $targetMembership->save();

            $transition = WorkflowTransition::create([
                'workflow_membership_id' => $targetMembership->id,
                'entity_type' => UnifiedClientProfileService::ENTITY_CLIENT,
                'entity_id' => $client->id,
                'from_workflow_board_id' => $fromMembership->workflow_board_id,
                'from_workflow_stage_id' => $fromStageId,
                'to_workflow_board_id' => $targetBoard->id,
                'to_workflow_stage_id' => $targetStage->id,
                'actor_user_id' => $actor?->id,
                'source' => $source,
                'reason' => $reason,
            ]);

            if ($targetBoard->provider === 'monday') {
                $this->queueMondayTransfer(
                    $client,
                    $fromMembership,
                    $targetMembership,
                    $targetBoard,
                    $targetStage,
                    $isSameBoard,
                );
            }

            return [$targetMembership, $transition];
        });

        if ($source !== 'automation' && Schema::hasTable('automation_rules')) {
            app(AutomationEngine::class)->trigger(
                AutomationEngine::EVENT_WORKFLOW_TRANSITIONED,
                $client,
                [
                    'workflow' => [
                        'from_board_id' => $transition->from_workflow_board_id,
                        'from_stage_id' => $transition->from_workflow_stage_id,
                        'to_board_id' => $transition->to_workflow_board_id,
                        'to_stage_id' => $transition->to_workflow_stage_id,
                        'reason' => $transition->reason,
                    ],
                ],
                'workflow:'.$transition->id,
                $transition->created_at,
            );
        }

        return $membership;
    }

    private function queueMondayTransfer(
        User $client,
        WorkflowMembership $fromMembership,
        WorkflowMembership $targetMembership,
        WorkflowBoard $targetBoard,
        WorkflowStage $targetStage,
        bool $isSameBoard,
    ): void {
        $dedupeKey = 'workflow:'.sha1(implode('|', [
            $client->id,
            $fromMembership->id,
            $targetMembership->id,
            $targetStage->id,
        ]));

        IntegrationOutbox::updateOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'provider' => 'monday',
                'entity_type' => UnifiedClientProfileService::ENTITY_CLIENT,
                'entity_id' => $client->id,
                'operation' => $isSameBoard ? 'move_workflow_stage' : 'transfer_workflow',
                'payload' => [
                    'source_board_id' => $fromMembership->board->external_board_id,
                    'source_item_id' => $fromMembership->external_item_id,
                    'target_board_id' => $targetBoard->external_board_id,
                    'target_stage_id' => $targetStage->external_stage_id,
                    'target_item_id' => $targetMembership->external_item_id,
                ],
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => now(),
                'processed_at' => null,
                'last_error' => null,
            ]
        );
    }
}

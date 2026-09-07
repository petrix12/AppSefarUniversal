<?php

namespace App\Services;

/**
 * Compatibility wrapper for existing phase-payment flows. It converts a
 * fetched Teamleader project plus its complete custom field list into the
 * full mutable projects.update payload required by this Teamleader account.
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
        $this->teamleader->updateProject(
            $projectId,
            $this->paymentWriter->projectUpdatePayload($project, $customFields)
        );
    }
}

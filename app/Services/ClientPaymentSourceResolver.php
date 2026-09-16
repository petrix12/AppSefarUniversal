<?php

namespace App\Services;

/**
 * Chooses one financial source for a client COS without ever mixing a current
 * HubSpot deal with an unrelated Teamleader historical project.
 */
class ClientPaymentSourceResolver
{
    /**
     * @return array{source: string, reason: string, analysis: array}
     */
    public function resolve(array $hubspot, array $teamleader): array
    {
        $hasHubspot = (bool) ($hubspot['has_hubspot_deals'] ?? ! empty($hubspot['projects']));
        $hasTeamleader = ! empty($teamleader['projects']);
        $hasAssociation = (bool) ($hubspot['has_linked_deals'] ?? false);

        if ($hasHubspot && $hasTeamleader) {
            if ($hasAssociation) {
                return $this->decision('hubspot', 'tratos_asociados', $hubspot);
            }

            return $this->decision('teamleader', 'historico_sin_correlacion', $teamleader);
        }

        if ($hasHubspot) {
            return $this->decision('hubspot', 'solo_hubspot', $hubspot);
        }

        if ($hasTeamleader) {
            return $this->decision('teamleader', 'solo_teamleader', $teamleader);
        }

        return $this->decision('none', 'sin_datos_financieros', [
            'projects' => [],
            'totals' => [
                'projects' => 0,
                'preestab_amount' => 0.0,
                'paid_amount' => 0.0,
                'balance_amount' => 0.0,
                'overpaid_amount' => 0.0,
                'difference_amount' => 0.0,
                'projects_to_review' => 0,
            ],
        ]);
    }

    private function decision(string $source, string $reason, array $analysis): array
    {
        return compact('source', 'reason', 'analysis');
    }
}

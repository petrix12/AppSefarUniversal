<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HubspotDealLocalSyncService
{
    public function sync(User $user, array $deals): array
    {
        $columns = Schema::getColumnListing((new Negocio)->getTable());
        $excludedColumns = [
            'id',
            'created_at',
            'updated_at',
            'hubspot_id',
            'teamleader_id',
            'teamleader_comparisons',
            'teamleader_match_confidence',
            'teamleader_match_reason',
            'teamleader_matched_at',
            'user_id',
        ];
        $fillableColumns = array_diff($columns, $excludedColumns);

        $dealsById = [];
        foreach ($deals as $deal) {
            $id = $deal['id'] ?? null;
            if (! is_scalar($id) || trim((string) $id) === '') {
                continue;
            }

            // Association pages can repeat a deal. Its external ID, not its
            // title, identifies the local record across every refresh.
            $id = trim((string) $id);
            $dealsById[$id] = $deal;
        }

        $result = DB::transaction(function () use ($user, $deals, $dealsById, $fillableColumns): array {
            // All local deal syncs share this lock, including the legacy job.
            // Serialize the lookup + insert when refreshes overlap for a user.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existingDeals = Negocio::whereIn('hubspot_id', array_keys($dealsById))
                ->lockForUpdate()
                ->get()
                ->groupBy('hubspot_id');

            $inserted = 0;
            $dealsToUpdate = [];
            foreach ($dealsById as $hubspotId => $deal) {
                $data = $this->processDealData($deal, $fillableColumns);

                if ($existingDeals->has($hubspotId)) {
                    $clientDeals = $existingDeals->get($hubspotId)->where('user_id', $user->id);
                    if ($clientDeals->isEmpty()) {
                        // A remote deal already owned locally by another
                        // client requires review, not a copy or reassignment.
                        Log::warning('COS: trato HubSpot ya registrado para otro solicitante', [
                            'user_id' => $user->id,
                            'hubspot_id' => $hubspotId,
                        ]);
                        continue;
                    }
                    // Preserve every existing association if legacy duplicate
                    // rows already exist; refresh is not a deduplication tool.
                    foreach ($clientDeals as $existingDeal) {
                        $this->queueUpdateWhenChanged($existingDeal, $data, $dealsToUpdate);
                    }
                    continue;
                }

                // Cache a deal that already exists in HubSpot once. This
                // service never creates deals in either external platform.
                Negocio::create(array_merge($data, [
                    'hubspot_id' => (string) $hubspotId,
                    'user_id' => $user->id,
                ]));
                $inserted++;
            }

            foreach ($dealsToUpdate as $dealUpdate) {
                Negocio::where('id', $dealUpdate['id'])->update($dealUpdate['data']);
            }

            // Missing remote records are retained, including their local IDs
            // and Teamleader links, so their return updates the same rows.
            $preservedAssociations = Negocio::where('user_id', $user->id)
                ->whereNotNull('hubspot_id')
                ->whereNotIn('hubspot_id', array_keys($dealsById))
                ->whereNotNull('teamleader_id')
                ->where('teamleader_id', '!=', '')
                ->count();

            return [
                'received' => count($deals),
                'inserted' => $inserted,
                'updated' => count($dealsToUpdate),
                'deleted' => 0,
                'preserved_associations' => $preservedAssociations,
            ];
        }, 3);

        Log::info('HubSpot deals sincronizados localmente para COS/MCP', array_merge([
            'user_id' => $user->id,
        ], $result));

        return $result;
    }

    private function processDealData(array $deal, array $fillableColumns): array
    {
        $properties = $deal['properties'] ?? [];

        foreach (['argumento_de_ventas__new_', 'n2__antecedentes_penales', 'documentos'] as $property) {
            if (array_key_exists($property, $properties)) {
                $properties[$property] = $this->encodeHubspotListValue($properties[$property]);
            }
        }

        $data = [];

        foreach ($fillableColumns as $column) {
            // An omitted property is not a request to erase local data.
            if (array_key_exists($column, $properties)) {
                $data[$column] = $properties[$column];
            }
        }

        return $data;
    }

    private function encodeHubspotListValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $arrayData = strpos((string) $value, ';') !== false
            ? explode(';', (string) $value)
            : [$value];

        return json_encode($arrayData, JSON_UNESCAPED_UNICODE);
    }

    private function queueUpdateWhenChanged(Negocio $existingDeal, array $data, array &$dealsToUpdate): void
    {
        foreach ($data as $key => $value) {
            if ($existingDeal->{$key} != $value) {
                $dealsToUpdate[] = [
                    'id' => $existingDeal->id,
                    'data' => $data,
                ];

                return;
            }
        }
    }
}

<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HubspotDealLocalSyncService
{
    public function sync(User $user, array $deals): array
    {
        $columns = Schema::getColumnListing((new Negocio)->getTable());
        $excludedColumns = ['id', 'created_at', 'updated_at', 'hubspot_id', 'teamleader_id', 'user_id'];
        $fillableColumns = array_diff($columns, $excludedColumns);

        $hubspotIds = array_values(array_filter(array_column($deals, 'id')));

        $existingDeals = Negocio::where('user_id', $user->id)
            ->whereIn('hubspot_id', $hubspotIds)
            ->get()
            ->keyBy('hubspot_id');

        $newDeals = [];
        $dealsToUpdate = [];

        foreach ($deals as $deal) {
            if (empty($deal['id'])) {
                continue;
            }

            $data = $this->processDealData($deal, $fillableColumns);

            if ($existingDeals->has($deal['id'])) {
                $this->queueUpdateWhenChanged($existingDeals->get($deal['id']), $data, $dealsToUpdate);
                continue;
            }

            $newDeals[] = array_merge([
                'hubspot_id' => $deal['id'],
                'user_id' => $user->id,
            ], $data);
        }

        if (! empty($newDeals)) {
            Negocio::insert($newDeals);
        }

        foreach ($dealsToUpdate as $dealUpdate) {
            Negocio::where('id', $dealUpdate['id'])->update($dealUpdate['data']);
        }

        Log::info('HubSpot deals sincronizados localmente para COS/MCP', [
            'user_id' => $user->id,
            'received' => count($deals),
            'inserted' => count($newDeals),
            'updated' => count($dealsToUpdate),
        ]);

        return [
            'received' => count($deals),
            'inserted' => count($newDeals),
            'updated' => count($dealsToUpdate),
        ];
    }

    private function processDealData(array $deal, array $fillableColumns): array
    {
        $properties = $deal['properties'] ?? [];

        foreach (['argumento_de_ventas__new_', 'n2__antecedentes_penales', 'documentos'] as $property) {
            if (array_key_exists($property, $properties)) {
                $properties[$property] = $this->encodeHubspotListValue($properties[$property]);
            }
        }

        $data = ['dealname' => $properties['dealname'] ?? null];

        foreach ($fillableColumns as $column) {
            $data[$column] = $properties[$column] ?? null;
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

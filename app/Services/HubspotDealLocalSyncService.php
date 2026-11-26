<?php

namespace App\Services;

use App\Models\Negocio;
use App\Models\User;
use App\Models\Scopes\BlockedClientRecordScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HubspotDealLocalSyncService
{
    /** Import deals that are not yet connected to a qualified local client. */
    public function syncUnassigned(array $deals): array
    {
        $columns = Schema::getColumnListing((new Negocio)->getTable());
        $fillableColumns = array_diff($columns, [
            'id', 'created_at', 'updated_at', 'hubspot_id', 'teamleader_id',
            'teamleader_comparisons', 'teamleader_match_confidence', 'teamleader_match_reason',
            'teamleader_matched_at', 'user_id',
        ]);
        $dealsById = [];
        foreach ($deals as $deal) {
            $id = $deal['id'] ?? null;
            if (is_scalar($id) && trim((string) $id) !== '') $dealsById[trim((string) $id)] = $deal;
        }
        if ($dealsById === []) return ['received' => count($deals), 'inserted' => 0, 'updated' => 0];

        return HubspotSyncOrigin::fromHubspot(fn () => DB::transaction(function () use ($dealsById, $fillableColumns, $deals): array {
            $existing = Negocio::query()->withoutGlobalScope(BlockedClientRecordScope::class)->whereIn('hubspot_id', array_keys($dealsById))
                ->lockForUpdate()->get()->groupBy('hubspot_id');
            $inserted = 0;
            $updates = [];
            foreach ($dealsById as $id => $deal) {
                $data = $this->processDealData($deal, $fillableColumns);
                $remoteUpdatedAt = $this->remoteUpdatedAt($deal);
                if ($existing->has($id)) {
                    foreach ($existing->get($id)->whereNull('user_id') as $row) {
                        if ($this->remoteWins($remoteUpdatedAt, $row->updated_at)) {
                            $this->queueUpdateWhenChanged($row, $data + ['updated_at' => $remoteUpdatedAt ?? now()], $updates);
                        }
                    }
                    continue;
                }
                Negocio::query()->create($data + [
                    'hubspot_id' => $id, 'user_id' => null,
                    'updated_at' => $remoteUpdatedAt ?? now(),
                ]);
                $inserted++;
            }
            foreach ($updates as $update) Negocio::query()->whereKey($update['id'])->update($update['data']);
            return ['received' => count($deals), 'inserted' => $inserted, 'updated' => count($updates)];
        }, 3));
    }

    public function sync(User $user, array $deals, bool $hubspotPriority = false): array
    {
        if (! $hubspotPriority) {
            app(ClientRestrictionService::class)->detectHubspotRetirement($user, [], $deals);
        }
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

        $result = HubspotSyncOrigin::fromHubspot(fn () => DB::transaction(function () use ($user, $deals, $dealsById, $fillableColumns, $hubspotPriority): array {
            // All local deal syncs share this lock, including the legacy job.
            // Serialize the lookup + insert when refreshes overlap for a user.
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existingDeals = Negocio::query()->withoutGlobalScope(BlockedClientRecordScope::class)->whereIn('hubspot_id', array_keys($dealsById))
                ->lockForUpdate()
                ->get()
                ->groupBy('hubspot_id');

            $inserted = 0;
            $dealsToUpdate = [];
            foreach ($dealsById as $hubspotId => $deal) {
                $data = $this->processDealData($deal, $fillableColumns);
                $remoteUpdatedAt = $this->remoteUpdatedAt($deal);

                if ($existingDeals->has($hubspotId)) {
                    $clientDeals = $existingDeals->get($hubspotId)->where('user_id', $user->id);
                    if ($clientDeals->isEmpty()) {
                        $unassignedDeals = $existingDeals->get($hubspotId)->whereNull('user_id');
                        if ($unassignedDeals->isNotEmpty()) {
                            foreach ($unassignedDeals as $existingDeal) {
                                $dealData = ($hubspotPriority || $this->remoteWins($remoteUpdatedAt, $existingDeal->updated_at))
                                    ? array_merge($data, ['updated_at' => $remoteUpdatedAt ?? now()])
                                    : [];
                                $this->queueUpdateWhenChanged($existingDeal, array_merge($dealData, ['user_id' => $user->id]), $dealsToUpdate);
                            }
                            continue;
                        }
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
                        if ($hubspotPriority || $this->remoteWins($remoteUpdatedAt, $existingDeal->updated_at)) {
                            $this->queueUpdateWhenChanged($existingDeal, $data + ['updated_at' => $remoteUpdatedAt ?? now()], $dealsToUpdate);
                        }
                    }
                    continue;
                }

                // Cache a deal that already exists in HubSpot once. This
                // service never creates deals in either external platform.
                Negocio::create(array_merge($data, [
                    'hubspot_id' => (string) $hubspotId,
                    'user_id' => $user->id,
                    'updated_at' => $remoteUpdatedAt ?? now(),
                ]));
                $inserted++;
            }

            foreach ($dealsToUpdate as $dealUpdate) {
                Negocio::where('id', $dealUpdate['id'])->update($dealUpdate['data']);
            }

            // Missing remote records are retained, including their local IDs
            // and Teamleader links, so their return updates the same rows.
            $preservedAssociations = Negocio::query()->withoutGlobalScope(BlockedClientRecordScope::class)->where('user_id', $user->id)
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
        }, 3));

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

        // HubSpot uses dealname; the existing COS-facing label is proyecto.
        if (array_key_exists('dealname', $properties) && ! array_key_exists('proyecto', $properties)) {
            $properties['proyecto'] = $properties['dealname'];
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
            if ($key === 'updated_at' && $value instanceof \DateTimeInterface
                && $existingDeal->updated_at?->getTimestamp() === $value->getTimestamp()) {
                continue;
            }
            if ($existingDeal->{$key} != $value) {
                $dealsToUpdate[] = [
                    'id' => $existingDeal->id,
                    'data' => $data,
                ];

                return;
            }
        }
    }

    private function remoteUpdatedAt(array $deal): ?\Illuminate\Support\Carbon
    {
        // The version must belong to the same payload. A newer mirror
        // timestamp cannot make a cached response safe to apply.
        $value = $deal['updatedAt'] ?? data_get($deal, 'properties.hs_lastmodifieddate');
        if (! is_string($value) && ! $value instanceof \DateTimeInterface) return null;
        if (is_string($value) && trim($value) === '') return null;

        try {
            return \Illuminate\Support\Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    private function remoteWins(?\Illuminate\Support\Carbon $remoteUpdatedAt, mixed $localUpdatedAt): bool
    {
        if (! $remoteUpdatedAt) return ! $localUpdatedAt;
        if (! $localUpdatedAt) return true;
        try { return $remoteUpdatedAt->gt(\Illuminate\Support\Carbon::parse($localUpdatedAt)); }
        catch (\Throwable) { return true; }
    }
}

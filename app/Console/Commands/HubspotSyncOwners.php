<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\HubspotOwner;
use App\Services\HubspotService;


class HubspotSyncOwners extends Command
{
    protected $signature = 'hubspot:sync-owners
        {--dry-run : Solo muestra cambios, no escribe en BD}
        {--include-archived : Incluye owners archivados}';

    protected $description = 'Actualiza la copia local de responsables de HubSpot usando el limitador compartido.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeArchived = (bool) $this->option('include-archived');
        $hubspot = app(HubspotService::class);

        // HubspotService aplica el presupuesto y el limitador global.
        $owners = $hubspot->getAllOwners($includeArchived);
        if (empty($owners)) {
            $this->warn('No se encontraron owners en HubSpot.');
            return self::SUCCESS;
        }
        $this->info('Owners encontrados: ' . count($owners));

        $updated = 0;
        foreach ($owners as $ownerData) {
            $attributes = [
                'email' => $ownerData['email'] ?: null,
                'name' => $ownerData['name'] ?: null,
                'active' => $ownerData['active'],
                'hubspot_created_at' => $ownerData['createdAt'] ? \Carbon\Carbon::parse($ownerData['createdAt']) : null,
                'hubspot_updated_at' => $ownerData['updatedAt'] ? \Carbon\Carbon::parse($ownerData['updatedAt']) : null,
            ];
            $owner = HubspotOwner::query()->firstOrNew(['id' => (string) $ownerData['id']]);
            $owner->fill($attributes);
            if ($owner->isDirty()) {
                $updated++;
                if (! $dryRun) $owner->save();
            }
        }

        $this->info(($dryRun ? 'Cambios previstos' : 'Owners locales actualizados').": {$updated}".($dryRun ? ' (dry-run)' : '').' ✅');
        return self::SUCCESS;
    }

}

<?php

namespace App\Console\Commands;

use App\Models\CrmObjectMirror;
use App\Models\CrmObjectMirrorBackfill;
use App\Jobs\BackfillHubspotObjectPage;
use App\Services\CrmContactMirrorService;
use App\Services\CrmObjectMirrorService;
use App\Services\HubspotMigrationScope;
use App\Services\HubspotService;
use Illuminate\Console\Command;

class MirrorHubspotObjects extends Command
{
    protected $signature = 'crm:mirror-hubspot-objects
        {--type= : HubSpot object type, for example contacts, deals, invoices or quotes}
        {--all : Queue the standard CRM object types}
        {--initial-crm : Queue the agreed initial CRM migration scope, including archived records}
        {--include-archived : Also queue archived records}
        {--queue : Queue a resumable backfill in small pages}
        {--status : Show queued backfill progress}
        {--write : Persist the remote records in the local CRM object mirror}
        {--archived : Read archived records from HubSpot instead of active records}';

    protected $description = 'Inspect or copy a HubSpot object type into the local CRM mirror.';

    public function handle(HubspotService $hubspot, CrmObjectMirrorService $mirror, CrmContactMirrorService $contacts): int
    {
        if ($this->option('status')) {
            CrmObjectMirrorBackfill::query()->orderBy('object_type')->orderBy('archived')->get()
                ->each(fn (CrmObjectMirrorBackfill $run) => $this->line(
                    $run->object_type.($run->archived ? ' · archivados' : ' · activos').
                    ' · '.$run->status.' · '.$run->records_read.' registros'.
                    ($run->last_error_class ? ' · '.$run->last_error_class : '')
                ));
            return self::SUCCESS;
        }
        if ($this->option('initial-crm') && (! $this->option('queue') || ! $this->option('write'))) {
            $this->error('--initial-crm requires --queue --write. It only reads HubSpot and writes local snapshots.');
            return self::INVALID;
        }
        if ($this->option('initial-crm') && ($this->option('all') || filled($this->option('type')))) {
            $this->error('--initial-crm cannot be combined with --all or --type.');
            return self::INVALID;
        }
        if ($this->option('queue')) {
            return $this->queueBackfill($hubspot);
        }

        if ($this->option('all')) {
            $this->error('La opción --all requiere --queue para procesar la migración con límites de API.');
            return self::INVALID;
        }

        $type = trim((string) $this->option('type'));
        if (! preg_match('/^[a-zA-Z0-9_-]{2,80}$/', $type)) {
            $this->error('Indica un tipo válido con --type=contacts, deals, invoices, quotes, etc.');
            return self::INVALID;
        }

        $write = (bool) $this->option('write');
        $archived = (bool) $this->option('archived');
        $after = null;
        $seenCursors = [];
        $read = 0;
        $created = 0;
        $updated = 0;

        do {
            $page = $hubspot->listWebhookObjects($type, $after, 100, $archived);
            foreach ($page['results'] as $object) {
                $id = (string) ($object['id'] ?? '');
                if (! ctype_digit($id) || ! is_array($object['properties'] ?? null)) {
                    throw new \UnexpectedValueException('HubSpot devolvió un objeto sin identidad o propiedades válidas.');
                }
                $read++;
                if (! $write) continue;

                $existed = CrmObjectMirror::query()
                    ->where('provider', 'hubspot')->where('object_type', $type)->where('external_id', $id)->exists();
                $mirror->storeRemoteObject($type, $id, $object);
                if ($type === 'contacts' && ! $archived) $contacts->syncContact('hubspot', $id);
                $existed ? $updated++ : $created++;
            }

            $next = $page['next'];
            if ($next !== null && isset($seenCursors[(string) $next])) {
                throw new \UnexpectedValueException('HubSpot repitió un cursor de paginación.');
            }
            if ($next !== null) $seenCursors[(string) $next] = true;
            $after = $next;
        } while ($after !== null);

        $mode = $write ? 'Copia local' : 'Solo lectura';
        $this->info("{$mode}: {$read} objetos HubSpot de tipo {$type}".($archived ? ' (archivados)' : '').'.');
        if ($write) $this->line("Nuevos: {$created} · Actualizados: {$updated}.");
        $this->comment('No se modificaron registros en HubSpot ni se generaron cobros.');

        return self::SUCCESS;
    }

    private function queueBackfill(HubspotService $hubspot): int
    {
        if (! $this->option('write')) {
            $this->error('Confirma la copia local con --write; --queue no modifica HubSpot.');
            return self::INVALID;
        }

        $types = $this->option('initial-crm')
            ? HubspotMigrationScope::OBJECT_TYPES
            : ($this->option('all')
                ? HubspotMigrationScope::OBJECT_TYPES
                : [trim((string) $this->option('type'))]);
        if (in_array('', $types, true) || collect($types)->contains(fn ($type) => ! preg_match('/^[a-zA-Z0-9_-]{2,80}$/', $type))) {
            $this->error('Indica --type válido o --all.');
            return self::INVALID;
        }

        $archivedStates = $this->option('initial-crm') || $this->option('include-archived')
            ? [false, true]
            : [(bool) $this->option('archived')];
        $queued = 0;
        foreach ($types as $type) {
            foreach ($archivedStates as $archived) {
                $backfill = CrmObjectMirrorBackfill::query()->firstOrCreate([
                    'provider' => 'hubspot', 'object_type' => $type, 'archived' => $archived,
                ]);
                if ($backfill->status === 'completed') {
                    $this->line("Ya completado: {$type}".($archived ? ' (archivados)' : '')." · {$backfill->records_read}");
                    continue;
                }
                if ($backfill->status === 'running') {
                    $this->line("Ya está en curso: {$type}".($archived ? ' (archivados)' : '').'.');
                    continue;
                }
                $backfill->update(['status' => 'queued', 'last_error_class' => null]);
                BackfillHubspotObjectPage::dispatch($backfill->id);
                $queued++;
            }
        }

        $this->info("Backfills puestos en la cola crm-sync: {$queued}.");
        if ($this->option('initial-crm')) {
            $this->line('Alcance: '.implode(', ', HubspotMigrationScope::OBJECT_TYPES).'.');
            $this->line('No incluye empresas, productos ni objetos personalizados.');
        }
        $this->comment('Se leen 25 registros por página, se reanudan desde el cursor guardado y se aplican límites por segundo y por día.');
        $this->warn('El estado completado confirma snapshots de objetos; no confirma paridad de todas las asociaciones ni sincronización saliente de todos los tipos.');
        return self::SUCCESS;
    }
}

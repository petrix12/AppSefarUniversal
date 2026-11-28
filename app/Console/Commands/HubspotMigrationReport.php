<?php

namespace App\Console\Commands;

use App\Models\CrmObjectMirrorBackfill;
use App\Services\HubspotObjectRecordStore;
use App\Services\HubspotMigrationScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HubspotMigrationReport extends Command
{
    protected $signature = 'crm:hubspot-migration-report';

    protected $description = 'Muestra el avance local de la carga inicial de HubSpot sin llamar a APIs externas.';

    public function handle(): int
    {
        foreach (['crm_object_mirrors', 'crm_object_mirror_backfills'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Falta la tabla {$table}; aplica las migraciones de CRM antes de consultar el avance.");
                return self::FAILURE;
            }
        }

        $mirrors = collect();
        $usingLegacyMirror = [];
        foreach (HubspotMigrationScope::OBJECT_TYPES as $type) {
            $table = HubspotObjectRecordStore::tableFor($type);
            if ($table === null || ! Schema::hasTable($table)) {
                $usingLegacyMirror[] = $type;
                $query = DB::table('crm_object_mirrors')->where('object_type', $type);
            } else {
                $query = DB::table($table);
            }
            $query->where('provider', 'hubspot')
                ->select('archived', DB::raw('COUNT(*) AS total'))
                ->groupBy('archived')->get()
                ->each(fn ($row) => $mirrors->put($type.':'.(int) $row->archived, $row));
        }

        $backfills = CrmObjectMirrorBackfill::query()
            ->where('provider', 'hubspot')
            ->whereIn('object_type', HubspotMigrationScope::OBJECT_TYPES)
            ->get()
            ->keyBy(fn ($row) => $row->object_type.':'.(int) $row->archived);

        $rows = [];
        foreach (HubspotMigrationScope::OBJECT_TYPES as $type) {
            $values = [$type];
            foreach ([false, true] as $archived) {
                $key = $type.':'.(int) $archived;
                $backfill = $backfills->get($key);
                $mirrorCount = (int) ($mirrors->get($key)->total ?? 0);
                $values[] = $mirrorCount;
                $values[] = $backfill?->status ?? 'sin iniciar';
                $values[] = (int) ($backfill?->records_read ?? 0);
                $values[] = $backfill
                    ? ($mirrorCount - (int) $backfill->records_read)
                    : '—';
                $values[] = $backfill?->last_error_class ?? '—';
            }
            $rows[] = $values;
        }

        $this->info('Migración inicial HubSpot → App · estado guardado localmente');
        if ($usingLegacyMirror !== []) {
            $this->line('Conteos del espejo original hasta crear las tablas de: '.implode(', ', $usingLegacyMirror).'.');
        }
        $this->table(
            ['Tipo', 'Espejo activo', 'Estado activo', 'Leídos activo', 'Δ activo', 'Error activo', 'Espejo archivado', 'Estado archivado', 'Leídos archivado', 'Δ archivado', 'Error archivado'],
            $rows,
        );

        if (Schema::hasTable('crm_object_associations')) {
            $associationCount = DB::table('crm_object_associations')->where('provider', 'hubspot')->where('active', true)->count();
            $this->line("Asociaciones HubSpot conocidas en la App: {$associationCount}");
        }
        $this->newLine();
        $this->info('Proyección a módulos locales');
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'hs_id')) {
            $clients = DB::table('users')->whereIn('id', \App\Models\User::clients()->select('users.id'));
            $this->line('Cuentas vinculadas por hs_id: '.(clone $clients)->whereNotNull('hs_id')->where('hs_id', '!=', '')->count());
            $this->line('Cuentas representado sin hs_id: '.(clone $clients)->where(function ($query): void {
                $query->whereNull('hs_id')->orWhere('hs_id', '');
            })->count());
        }
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'hubspot_id')) {
            $leads = DB::table('leads')->whereNull('converted_at');
            $this->line('Leads vinculados por hubspot_id: '.(clone $leads)->whereNotNull('hubspot_id')->where('hubspot_id', '!=', '')->count());
            $this->line('Leads sin hubspot_id: '.(clone $leads)->where(function ($query): void {
                $query->whereNull('hubspot_id')->orWhere('hubspot_id', '');
            })->count());
        }
        if (Schema::hasTable('negocios') && Schema::hasColumn('negocios', 'hubspot_id')) {
            $deals = DB::table('negocios')->whereNotNull('hubspot_id')->where('hubspot_id', '!=', '');
            $this->line('Negocios vinculados por hubspot_id: '.$deals->count());
            $this->line('Negocios sin hubspot_id: '.DB::table('negocios')->whereNull('hubspot_id')->orWhere('hubspot_id', '')->count());
        }
        if (Schema::hasTable('crm_contact_snapshots')) {
            $classifications = DB::table('crm_contact_snapshots')->where('provider', 'hubspot')
                ->select('classification', DB::raw('COUNT(*) AS total'))->groupBy('classification')->get();
            $this->line('Contactos clasificados: '.($classifications->isEmpty()
                ? 'ninguno'
                : $classifications->map(fn ($row) => $row->classification.': '.$row->total)->implode(' · ')));
        }
        if (Schema::hasTable('crm_webhook_events')) {
            $events = DB::table('crm_webhook_events')->where('provider', 'hubspot')
                ->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->get();
            $this->line('Eventos HubSpot recibidos: '.($events->isEmpty()
                ? 'ninguno'
                : $events->map(fn ($row) => $row->status.': '.$row->total)->implode(' · ')));
        }

        if (Schema::hasTable('crm_identity_reviews')) {
            $reviews = DB::table('crm_identity_reviews')->where('provider', 'hubspot')
                ->select('status', DB::raw('COUNT(*) AS total'))->groupBy('status')->get();
            $this->line('Revisión de identidad: '.($reviews->isEmpty()
                ? 'ninguna'
                : $reviews->map(fn ($row) => $row->status.': '.$row->total)->implode(' · ')));

            $pendingReasons = DB::table('crm_identity_reviews')->where('provider', 'hubspot')->where('status', 'pending')
                ->select('reason_code', DB::raw('COUNT(*) AS total'))->groupBy('reason_code')->orderBy('reason_code')->get();
            if ($pendingReasons->isNotEmpty()) {
                $this->table(['Motivo pendiente', 'Registros'], $pendingReasons->map(fn ($row) => [
                    $row->reason_code,
                    $row->total,
                ])->all());
            }
        }

        if (Schema::hasTable('crm_object_association_backfills')) {
            $associationRuns = DB::table('crm_object_association_backfills')
                ->select('status', DB::raw('COUNT(*) AS total'))
                ->groupBy('status')->get();
            $this->line('Backfill de asociaciones: '.($associationRuns->isEmpty()
                ? 'sin iniciar'
                : $associationRuns->map(fn ($row) => $row->status.': '.$row->total)->implode(' · ')));
            $associationRows = DB::table('crm_object_association_backfills')
                ->orderBy('from_object_type')->orderBy('to_object_type')->get();
            if ($associationRows->isNotEmpty()) {
                $this->table(['Desde', 'Hacia', 'Estado', 'Leídos', 'Relaciones', 'Error'], $associationRows->map(fn ($row) => [
                    $row->from_object_type,
                    $row->to_object_type,
                    $row->status,
                    $row->records_read,
                    $row->associations_read,
                    $row->last_error_class ?? '—',
                ])->all());
            }
        }

        if (Schema::hasTable('crm_sync_conflicts')) {
            $pendingConflicts = DB::table('crm_sync_conflicts')->where('status', 'pending')->count();
            $this->line("Conflictos de campos pendientes de revisión: {$pendingConflicts}");
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'hs_id')) {
            $duplicateIds = DB::table('users')->whereNotNull('hs_id')->where('hs_id', '!=', '')
                ->select('hs_id')->groupBy('hs_id')->havingRaw('COUNT(*) > 1')->count();
            $this->line("IDs HubSpot repetidos en usuarios locales: {$duplicateIds} grupos");
        }
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'hubspot_id')) {
            $duplicateLeadIds = DB::table('leads')->whereNotNull('hubspot_id')->where('hubspot_id', '!=', '')
                ->select('hubspot_id')->groupBy('hubspot_id')->havingRaw('COUNT(*) > 1')->count();
            $this->line("IDs HubSpot repetidos en leads locales: {$duplicateLeadIds} grupos");
        }
        $crossTableIds = $this->crossTableHubspotIdGroups();
        if ($crossTableIds !== null) {
            $this->line("IDs HubSpot compartidos entre users y leads (revisar): {$crossTableIds} grupos");
        }
        if (Schema::hasTable('negocios') && Schema::hasColumn('negocios', 'hubspot_id')) {
            $duplicateDealIds = DB::table('negocios')->whereNotNull('hubspot_id')->where('hubspot_id', '!=', '')
                ->select('hubspot_id')->groupBy('hubspot_id')->havingRaw('COUNT(*) > 1')->count();
            $this->line("IDs de negocio HubSpot repetidos localmente: {$duplicateDealIds} grupos");
        }
        $this->line('Correos repetidos entre usuarios/leads (incluye correos secundarios): '.$this->duplicateEmailGroups().' grupos');

        $this->newLine();
        $this->warn('Δ = filas del espejo menos registros leídos por el backfill. Es una comprobación local de conteo, no una comparación en vivo de IDs contra HubSpot.');
        $this->warn('Este informe refleja el estado local de la cola y los espejos; no consulta conteos actuales de HubSpot ni certifica paridad de asociaciones.');
        $this->warn('Facturas, presupuestos y actividad histórica todavía se importan como snapshots; falta vincularlas con sus módulos operativos.');
        $this->warn('La carga de asociaciones es una cola separada; ejecuta crm:hubspot-backfill-associations solo después de completar los objetos.');
        $this->warn('Los conteos de usuarios, leads y negocios son referencias locales; aún no son una comparación exacta por ID contra HubSpot.');
        return self::SUCCESS;
    }

    private function crossTableHubspotIdGroups(): ?int
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'hs_id')
            || ! Schema::hasTable('leads') || ! Schema::hasColumn('leads', 'hubspot_id')) {
            return null;
        }

        $ids = DB::table('users as users')
            ->join('leads as leads', 'leads.hubspot_id', '=', 'users.hs_id')
            ->whereNotNull('users.hs_id')->where('users.hs_id', '!=', '')
            ->select('users.hs_id')->distinct();

        return DB::query()->fromSub($ids, 'shared_hubspot_ids')->count();
    }

    private function duplicateEmailGroups(): int
    {
        $queries = [];
        if (Schema::hasTable('users')) {
            foreach (['email', 'email_2', 'email_alternativo'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    continue;
                }
                $queries[] = DB::table('users')->selectRaw(
                    "'user' AS entity_type, id AS entity_id, LOWER(TRIM({$column})) AS normalized_email",
                )->whereNotNull($column)->whereRaw("TRIM({$column}) != ''");
            }
        }
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'email')) {
            $queries[] = DB::table('leads')->selectRaw(
                "'lead' AS entity_type, id AS entity_id, LOWER(TRIM(email)) AS normalized_email",
            )->whereNotNull('email')->whereRaw("TRIM(email) != ''");
        }
        if (count($queries) < 2) {
            return 0;
        }

        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }

        $uniqueCandidates = DB::query()->fromSub($union, 'email_candidates')
            ->select('entity_type', 'entity_id', 'normalized_email')->distinct();

        return DB::query()->fromSub($uniqueCandidates, 'unique_candidates')
            ->select('normalized_email')
            ->groupBy('normalized_email')
            ->havingRaw('COUNT(*) > 1')
            ->count();
    }
}

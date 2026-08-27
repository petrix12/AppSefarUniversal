<?php

namespace App\Console\Commands;

use App\Models\CustomFieldDefinition;
use App\Models\IntegrationFieldMapping;
use App\Models\User;
use App\Services\UnifiedClientProfileService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ImportLegacyUnifiedFields extends Command
{
    protected $signature = 'unified:import-legacy-fields
        {--write : Crea definiciones y borradores de mapeo inactivos. Sin esta opción solo genera el reporte.}
        {--copy-values : Copia también los valores existentes de users; requiere --write.}';

    protected $description = 'Convierte el catálogo assoc_tl_hs en campos configurables y mapeos explícitos.';

    public function handle(UnifiedClientProfileService $profiles): int
    {
        if (! Schema::hasTable('assoc_tl_hs')) {
            $this->error('No existe la tabla assoc_tl_hs; no hay catálogo legado para importar.');

            return self::FAILURE;
        }

        $write = (bool) $this->option('write');
        $copyValues = (bool) $this->option('copy-values');

        if ($copyValues && ! $write) {
            $this->error('--copy-values requiere --write.');

            return self::FAILURE;
        }

        $rows = DB::table('assoc_tl_hs')
            ->select(['tl_id', 'hs_id'])
            ->whereNotNull('hs_id')
            ->whereNotNull('tl_id')
            ->get()
            ->filter(fn ($row) => filled($row->hs_id) && filled($row->tl_id))
            ->values();

        $fieldKeys = $rows->pluck('hs_id')->unique()->values();
        $stats = [
            'definitions' => $fieldKeys->count(),
            'hubspot_mappings' => $fieldKeys->count(),
            'teamleader_mappings' => $rows->count(),
            'copied_values' => 0,
        ];

        if ($write) {
            $definitions = [];

            foreach ($fieldKeys as $fieldKey) {
                $definition = $profiles->defineField([
                    'entity_type' => UnifiedClientProfileService::ENTITY_CLIENT,
                    'key' => $fieldKey,
                    'label' => $this->labelFor($fieldKey),
                    'data_type' => $this->dataTypeForColumn($fieldKey),
                    'group' => 'Migrado de HubSpot y Teamleader',
                ]);
                $definitions[$fieldKey] = $definition;

                IntegrationFieldMapping::updateOrCreate(
                    [
                        'provider' => 'hubspot',
                        'external_entity_type' => 'contact',
                        'scope_key' => '*',
                        'external_field_key' => $fieldKey,
                    ],
                    [
                        'entity_type' => UnifiedClientProfileService::ENTITY_CLIENT,
                        'custom_field_definition_id' => $definition->id,
                        'local_attribute' => null,
                        'direction' => 'bidirectional',
                        'conflict_policy' => 'manual',
                        // Importing a catalogue must never turn on a sync.
                        // Activation happens only through a later, audited
                        // promotion flow, field by field.
                        'is_active' => false,
                    ]
                );
            }

            foreach ($rows as $row) {
                IntegrationFieldMapping::updateOrCreate(
                    [
                        'provider' => 'teamleader',
                        'external_entity_type' => 'contact',
                        'scope_key' => '*',
                        'external_field_key' => $row->tl_id,
                    ],
                    [
                        'entity_type' => UnifiedClientProfileService::ENTITY_CLIENT,
                        'custom_field_definition_id' => $definitions[$row->hs_id]->id,
                        'local_attribute' => null,
                        // Teamleader is currently a consultation source.
                        'direction' => 'pull',
                        'conflict_policy' => 'manual',
                        'is_active' => false,
                    ]
                );
            }

            if ($copyValues) {
                foreach ($definitions as $fieldKey => $definition) {
                    // Some legacy mappings never received a users column. They
                    // still become configurable fields and mappings, but there
                    // is no local value to copy from the old schema.
                    if (! Schema::hasColumn('users', $fieldKey)) {
                        continue;
                    }

                    User::query()
                        ->select(['id', $fieldKey])
                        ->whereNotNull($fieldKey)
                        ->orderBy('id')
                        ->chunkById(500, function ($users) use ($profiles, $definition, $fieldKey, &$stats): void {
                            foreach ($users as $user) {
                                if ($user->{$fieldKey} === '') {
                                    continue;
                                }

                                $profiles->setValue(
                                    $user,
                                    $definition,
                                    $user->{$fieldKey},
                                    'legacy_app',
                                    $user->updated_at,
                                    false,
                                );
                                $stats['copied_values']++;
                            }
                        });
                }
            }
        }

        $this->table(['Elemento', $write ? 'Procesados' : 'Detectados'], [
            ['Definiciones de campo', $stats['definitions']],
            ['Mapeos HubSpot', $stats['hubspot_mappings']],
            ['Mapeos Teamleader', $stats['teamleader_mappings']],
            ['Valores copiados', $stats['copied_values']],
        ]);

        if (! $write) {
            $this->comment('Modo seguro: no se escribió ningún dato.');
        } else {
            $this->comment('Los mapeos creados quedaron inactivos; este comando no activa sincronizaciones.');
        }

        return self::SUCCESS;
    }

    private function labelFor(string $fieldKey): string
    {
        return Str::of($fieldKey)
            ->replace('__', ' ')
            ->replace('_', ' ')
            ->squish()
            ->ucfirst()
            ->toString();
    }

    private function dataTypeForColumn(string $column): string
    {
        if (! Schema::hasColumn('users', $column)) {
            return 'text';
        }

        return match (Schema::getColumnType('users', $column)) {
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'integer', 'bigint', 'smallint', 'tinyint' => 'number',
            'decimal', 'float', 'double' => 'decimal',
            default => 'text',
        };
    }
}

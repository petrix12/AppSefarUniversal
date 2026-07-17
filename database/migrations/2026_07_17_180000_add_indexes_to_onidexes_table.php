<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTION = 'onidex';
    private const TABLE = 'onidexes';

    public function up(): void
    {
        $existing = $this->existingIndexes();
        $clauses = [];

        if (! in_array('PRIMARY', $existing, true)) {
            $clauses[] = 'ADD PRIMARY KEY (`id`)';
        }

        foreach ($this->indexes() as $name => $column) {
            if (! in_array($name, $existing, true)) {
                $clauses[] = "ADD INDEX `{$name}` (`{$column}`)";
            }
        }

        if ($clauses !== []) {
            DB::connection(self::CONNECTION)->statement(
                'ALTER TABLE `'.self::TABLE.'` '.implode(', ', $clauses)
            );
        }
    }

    public function down(): void
    {
        $existing = $this->existingIndexes();
        $clauses = [];

        foreach (array_keys($this->indexes()) as $name) {
            if (in_array($name, $existing, true)) {
                $clauses[] = "DROP INDEX `{$name}`";
            }
        }

        if (in_array('PRIMARY', $existing, true)) {
            $clauses[] = 'DROP PRIMARY KEY';
        }

        if ($clauses !== []) {
            DB::connection(self::CONNECTION)->statement(
                'ALTER TABLE `'.self::TABLE.'` '.implode(', ', $clauses)
            );
        }
    }

    private function existingIndexes(): array
    {
        return collect(DB::connection(self::CONNECTION)->select('SHOW INDEX FROM `'.self::TABLE.'`'))
            ->pluck('Key_name')
            ->unique()
            ->values()
            ->all();
    }

    private function indexes(): array
    {
        return [
            'idx_onidexes_cedula' => 'cedula',
            'idx_onidexes_apellido1' => 'apellido1',
            'idx_onidexes_apellido2' => 'apellido2',
            'idx_onidexes_nombre1' => 'nombre1',
            'idx_onidexes_nombre2' => 'nombre2',
            'idx_onidexes_nacion' => 'nacion',
            'idx_onidexes_fec_nac' => 'fec_nac',
        ];
    }
};

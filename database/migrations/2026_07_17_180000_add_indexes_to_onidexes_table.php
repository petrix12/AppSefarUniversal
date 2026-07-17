<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const CONNECTION = 'onidex';
    private const TABLE = 'onidexes';
    private const NON_BLOCKING_MYSQL_ERRORS = [
        1030, // InnoDB/storage engine failed while rebuilding this large table.
        1142, // ALTER denied for the configured database user.
    ];

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

        $this->runAlterTable($clauses);
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

        $this->runAlterTable($clauses);
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

    private function runAlterTable(array $clauses): void
    {
        if ($clauses === []) {
            return;
        }

        try {
            DB::connection(self::CONNECTION)->statement(
                'ALTER TABLE `'.self::TABLE.'` '.implode(', ', $clauses)
            );
        } catch (QueryException $exception) {
            $mysqlErrorCode = (int) ($exception->errorInfo[1] ?? 0);

            if (! in_array($mysqlErrorCode, self::NON_BLOCKING_MYSQL_ERRORS, true)) {
                throw $exception;
            }

            Log::warning('No se pudieron actualizar los indices de Onidex desde la migracion.', [
                'connection' => self::CONNECTION,
                'table' => self::TABLE,
                'mysql_error_code' => $mysqlErrorCode,
                'message' => $exception->getMessage(),
                'sql' => 'ALTER TABLE `'.self::TABLE.'` '.implode(', ', $clauses),
            ]);
        }
    }
};

<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MondayFieldMapping;
use Monday;

class GenerateMondayMappings extends Command
{
    protected $signature = 'monday:compare-boards';

    protected $description = 'Compara un board target con el board base por title, para asociar monday_column_id a local_field_key.';

    private $baseBoardId = 878831315;
    private $targetBoardIds = [
        6524058079, 3950637564, 815474056, 3639222742, 3469085450,
        2213224176, 1910043474, 1845710504, 1845706367, 1845701215,
        1016436921, 1026956491, 815474056, 815471640, 807173414,
        803542982, 765394861, 742896377, 708128239, 708123651,
        669590637, 625187241
    ];

    //6524058079,

    private $allowedBaseFields = [
        'texto_largo88', 'texto30', 'texto64', 'texto51', 'text43', 'text06', 'texto6', 'texto_largo58',
        'texto_largo', 'texto_largo0', 'cliente_solicitud', 'ubicaci_n4', 'ubicaci_n', 'texto37', 'texto2',
        'long_text', 'long_text6', 'long_text2', 'estado54', 'color3', 'color9', 'men__desplegable',
        'men__desplegable2', 'status', 'estado05', 'personas2', 'dup__of_etiquetador', 'estado46',
        'estado5', 'estado8', 'personas_1', 'person', 'estado7', 'estado90', 'fecha87', 'fecha5',
        'fecha3', 'fecha88', 'fecha89', 'fecha0', 'fecha06', 'fecha7', 'fecha71', 'fecha9',
        'fecha32', 'fecha86'
    ];

    public function handle()
    {
        $baseCols = $this->getBoardColumns($this->baseBoardId);

        if (empty($baseCols)) {
            $this->error("No se pudieron obtener columnas del board base ({$this->baseBoardId}).");
            return;
        }

        $baseCols = array_filter($baseCols, function ($column) {
            return in_array($column['id'], $this->allowedBaseFields);
        });

        foreach ($this->targetBoardIds as $targetBoardId) {
            $this->info("Procesando board target: $targetBoardId");

            $targetCols = $this->getBoardColumns($targetBoardId);

            if (empty($targetCols)) {
                $this->warn("No se pudieron obtener columnas del board $targetBoardId.");
                continue;
            }

            $this->compareColumnsInteractive($baseCols, $targetCols, $targetBoardId);
        }

        $this->info("Procesamiento completado.");
    }

    private function compareColumnsInteractive(&$baseCols, &$targetCols, $targetBoardId)
    {
        foreach ($baseCols as $baseIndex => $baseColumn) {
            $this->info("Base: {$baseColumn['title']} ({$baseColumn['id']})");

            foreach ($targetCols as $targetIndex => $targetColumn) {
                $this->line("[T$targetIndex] {$targetColumn['title']} ({$targetColumn['id']})");
            }

            $targetSelection = $this->ask("Seleccione el número correspondiente a la columna del board target para '{$baseColumn['title']}' (o 's' para saltar)");

            if (strtolower($targetSelection) === 's') {
                $this->info("Saltado: {$baseColumn['title']}");
                unset($baseCols[$baseIndex]);
                continue;
            }

            $targetIndex = str_replace('T', '', $targetSelection);

            if (isset($targetCols[$targetIndex])) {
                $targetColumn = $targetCols[$targetIndex];

                $mappingBase = MondayFieldMapping::where('board_id', $this->baseBoardId)
                    ->where('monday_column_id', $baseColumn['id'])
                    ->first();

                if ($mappingBase) {
                    MondayFieldMapping::create([
                        'board_id'         => $targetBoardId,
                        'local_field_key'  => $mappingBase->local_field_key,
                        'monday_column_id' => $targetColumn['id']
                    ]);
                    $this->info("Mapping manual creado: [Board $targetBoardId] {$mappingBase->local_field_key} => {$targetColumn['id']}");
                } else {
                    $this->warn("No existe mapping base en DB local para la columna seleccionada.");
                }

                unset($baseCols[$baseIndex]);
                unset($targetCols[$targetIndex]);
            } else {
                $this->warn("Selección inválida. No se realizó ninguna acción para '{$baseColumn['title']}'.");
            }
        }
    }

    private function getBoardColumns($boardId)
    {
        $query = "
            boards (ids: [$boardId]) {
                columns {
                    id
                    title
                }
            }
        ";

        $result = Monday::customQuery($query);
        $data = json_decode(json_encode($result), true);
        return $data['boards'][0]['columns'] ?? [];
    }
}

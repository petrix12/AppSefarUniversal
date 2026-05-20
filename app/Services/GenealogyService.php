<?php

namespace App\Services;

use App\Models\Agcliente;
use Illuminate\Support\Facades\Cache;

class GenealogyService
{
    /**
     * Obtiene y procesa el árbol genealógico
     * Cache por 30 minutos
     */
    public function getProcessedTree(string $passport): array
    {
        $cacheKey = "genealogy_tree_{$passport}";

        return Cache::remember($cacheKey, 1800, function () use ($passport) {
            $people = Agcliente::where("IDCliente", trim($passport))->get()->toArray();

            if (empty($people)) {
                return [
                    'columnasparatabla' => [],
                    'hayTatarabuelo' => false,
                    'people' => []
                ];
            }

            $tree = $this->buildGenerationsTree($people);

            return [
                'columnasparatabla' => $tree['columns'],
                'hayTatarabuelo' => $tree['hayTatarabuelo'],
                'people' => $people
            ];
        });
    }

    /**
     * Construye el árbol de generaciones optimizado
     */
    private function buildGenerationsTree(array $people): array
    {
        $peopleMap = array_column($people, null, 'id');
        $generaciones = $this->calculateGenerations($people);
        $maxGeneraciones = max($generaciones) + 1;
        $columnasparatabla = [];

        for ($i = 0; $i < $maxGeneraciones; $i++) {
            $columnasparatabla[$i] = [];

            if ($i == 0) {
                $people[0]['showbtn'] = 2;
                $columnasparatabla[$i][] = $people[0];
                continue;
            }

            foreach ($columnasparatabla[$i-1] as $personaAnterior) {
                $this->processParent($columnasparatabla[$i], $personaAnterior, 'idPadreNew', 'm', $peopleMap);
                $this->processParent($columnasparatabla[$i], $personaAnterior, 'idMadreNew', 'f', $peopleMap);
            }
        }

        $this->assignParentescos($columnasparatabla, $maxGeneraciones);

        return [
            'columns' => $columnasparatabla,
            'hayTatarabuelo' => isset($columnasparatabla[4]) && count($columnasparatabla[4]) > 0
        ];
    }

    private function calculateGenerations(array $people): array
    {
        $generaciones = [];
        $queue = [];

        // Identificar raíces
        foreach ($people as $person) {
            if ($person['idPadreNew'] === null && $person['idMadreNew'] === null) {
                $generaciones[$person['id']] = 1;
                $queue[] = $person['id'];
            }
        }

        // BFS
        while (!empty($queue)) {
            $currentId = array_shift($queue);
            $currentGeneration = $generaciones[$currentId];

            foreach ($people as $person) {
                if ($person['idPadreNew'] == $currentId || $person['idMadreNew'] == $currentId) {
                    if (!isset($generaciones[$person['id']]) || $generaciones[$person['id']] < $currentGeneration + 1) {
                        $generaciones[$person['id']] = $currentGeneration + 1;
                        $queue[] = $person['id'];
                    }
                }
            }
        }

        return empty($generaciones) ? [1] : $generaciones;
    }

    private function processParent(&$columna, $personaAnterior, $parentField, $gender, $peopleMap): void
    {
        if (isset($personaAnterior[$parentField]) && isset($peopleMap[$personaAnterior[$parentField]])) {
            $parent = $peopleMap[$personaAnterior[$parentField]];
            $parent['showbtn'] = 1;
            $parent['genero'] = $gender;
            $columna[] = $parent;
        } else {
            $columna[] = [
                'showbtn' => 0,
                'genero' => $gender,
            ];
        }
    }

    private function assignParentescos(&$columnasparatabla, $maxGeneraciones): void
    {
        $parentescos = $this->getParentescos($maxGeneraciones);

        foreach ($columnasparatabla as $key => $generacion) {
            foreach ($generacion as $idx => $persona) {
                $columnasparatabla[$key][$idx]['parentesco'] = $parentescos[$key][$idx] ?? 'Desconocido';
            }
        }
    }

    private function getParentescos($maxGeneraciones): array
    {
        $parentescos = [
            0 => ["Cliente"],
            1 => ["Padre", "Madre"]
        ];

        $parentescos_post_padres = [
            "Abuel",
            "Bisabuel",
            "Tatarabuel",
            "Trastatarabuel",
            "Retatarabuel",
            "Sestarabuel",
            "Setatarabuel",
            "Octatarabuel",
            "Nonatarabuel",
            "Decatarabuel",
            "Undecatarabuel",
            "Duodecatarabuel",
            "Trececatarabuel",
            "Catorcatarabuel",
            "Quincecatarabuel",
            "Deciseiscatarabuel",
            "Decisietecatarabuel",
            "Deciochocatarabuel",
            "Decinuevecatarabuel",
            "Vigecatarabuel",
            "Vigecimoprimocatarabuel",
            "Vigecimosegundocatarabuel",
            "Vigecimotercercatarabuel",
            "Vigecimocuartocatarabuel",
            "Vigecimoquintocatarabuel",
            "Vigecimosextocatarabuel",
            "Vigecimoseptimocatarabuel",
            "Vigecimooctavocatarabuel",
            "Vigecimonovenocatarabuel",
            "Trigecatarabuel",
            "Trigecimoprimocatarabuel",
            "Trigecimosegundocatarabuel",
            "Trigecimotercercatarabuel",
            "Trigecimocuartocatarabuel",
            "Trigecimoquintocatarabuel",
            "Trigecimosextocatarabuel",
            "Trigecimoseptimocatarabuel",
            "Trigecimooctavocatarabuel",
            "Trigecimonovenocatarabuel",
            "Cuarentacatarabuel",
            "Cuarentaprimocatarabuel",
            "Cuarentasegundocatarabuel",
            "Cuarentatercercatarabuel",
        ];

        $prepar = 4;
        foreach ($parentescos_post_padres as $key => $parentesco) {
            if ($key + 2 < $maxGeneraciones) {
                $parentescos[$key + 2] = [];
                for ($i = 0; $i < $prepar; $i++) {
                    $suffix = ($i % 2 == 0 ? "o" : "a");
                    $text = $this->generateRelationshipText($i, $key);
                    $parentescos[$key + 2][] = $parentesco . $suffix . " " . $text;
                }
                $prepar *= 2;
            }
        }

        return $parentescos;
    }

    private function generateRelationshipText($i, $key): string
    {
        // Determinar si es línea paterna (índice par) o materna (índice impar)
        $isPaternal = ($i % 2 == 0);

        // Para abuelos y bisabuelos, usar descriptores simples
        if ($key <= 1) {
            return $isPaternal ? "paterno" : "materno";
        }

        // Para generaciones más lejanas (tatarabuelos en adelante), usar "por línea..."
        return $isPaternal ? "por línea paterna" : "por línea materna";
    }
}

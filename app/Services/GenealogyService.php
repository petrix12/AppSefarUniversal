<?php

namespace App\Services;

use App\Models\Agcliente;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GenealogyService
{
    private const CLIENT_VISIBLE_GENERATIONS = 5;
    private const MAX_SAFE_GENERATIONS = 44;
    private const DEFAULT_LINEAGE_COLOR = '#B08A43';

    public function getProcessedTree(string $passport): array
    {
        $cacheKey = $this->cacheKey($passport);

        return Cache::remember($cacheKey, 1800, function () use ($passport) {
            return $this->buildTree(
                $passport,
                null,
                self::CLIENT_VISIBLE_GENERATIONS,
                0,
                0,
                false
            );
        });
    }

    public function forgetProcessedTree(string $passport): void
    {
        Cache::forget($this->cacheKey($passport));
    }

    public function buildTree(
        string $passport,
        ?int $rootId = null,
        ?int $maxGenerations = null,
        int $baseGeneration = 0,
        int $rootSlot = 0,
        bool $includeAddButtons = true,
        ?string $rootLineColor = null
    ): array {
        $passport = trim($passport);
        $this->migratePrimaryParents($passport);

        $people = $this->normalizePeople(
            Agcliente::where('IDCliente', $passport)
                ->orderBy('IDPersona')
                ->orderBy('id')
                ->get()
                ->toArray()
        );

        if (empty($people)) {
            return $this->emptyTree();
        }

        $people = $this->sanitizeParentLinks($people);
        $peopleById = array_column($people, null, 'id');
        $root = $this->findRoot($people, $rootId);

        if ($root === null) {
            return $this->emptyTree($people);
        }

        $tree = $this->buildCompactAncestorColumns(
            $peopleById,
            $root,
            $maxGenerations,
            $baseGeneration,
            $rootSlot,
            $includeAddButtons,
            $rootLineColor
        );

        $this->syncPersonaSlots($tree['columnasparatabla']);

        return [
            'columnasparatabla' => $tree['columnasparatabla'],
            'parentescos' => [],
            'people' => $people,
            'root' => $root,
            'hayTatarabuelo' => $this->hasRealPersonAtGeneration($tree['columnasparatabla'], 4),
            'warnings' => $tree['warnings'],
            'stats' => [
                'generations' => count($tree['columnasparatabla']),
                'people' => count($people),
                'visible_nodes' => $tree['visibleNodes'],
                'truncated' => $tree['truncated'],
            ],
        ];
    }

    private function cacheKey(string $passport): string
    {
        return 'genealogy_tree_' . trim($passport);
    }

    private function emptyTree(array $people = []): array
    {
        return [
            'columnasparatabla' => [],
            'parentescos' => [],
            'people' => $people,
            'root' => null,
            'hayTatarabuelo' => false,
            'warnings' => [],
            'stats' => [
                'generations' => 0,
                'people' => count($people),
                'visible_nodes' => 0,
                'truncated' => false,
            ],
        ];
    }

    private function migratePrimaryParents(string $passport): void
    {
        $client = Agcliente::where('IDCliente', $passport)
            ->where('IDPersona', 1)
            ->first();

        if (!$client) {
            return;
        }

        $father = Agcliente::where('IDCliente', $passport)
            ->where('IDPersona', 2)
            ->first();

        $mother = Agcliente::where('IDCliente', $passport)
            ->where('IDPersona', 3)
            ->first();

        if (!$father && !$mother) {
            return;
        }

        $data = ['migradoNuevoID' => 1];

        if ($father) {
            $data['idPadreNew'] = $father->id;
            $data['IDPadre'] = 2;
        }

        if ($mother) {
            $data['idMadreNew'] = $mother->id;
            $data['IDMadre'] = 3;
        }

        DB::table('agclientes')->where('id', $client->id)->update($data);
    }

    private function normalizePeople(array $people): array
    {
        if (count($people) > 2 && empty($people[0]['IDMadre'])) {
            if (($people[1]['Sexo'] ?? null) === 'F') {
                $people[0]['IDMadre'] = $people[1]['IDPersona'] ?? null;
                $people[0]['IDPadre'] = $people[2]['IDPersona'] ?? null;
            } else {
                $people[0]['IDMadre'] = $people[2]['IDPersona'] ?? null;
                $people[0]['IDPadre'] = $people[1]['IDPersona'] ?? null;
            }
        }

        foreach ($people as $key => $person) {
            if ((int) ($person['IDMadre'] ?? 0) < 1) {
                $people[$key]['IDMadre'] = null;
            }

            if ((int) ($person['IDPadre'] ?? 0) < 1) {
                $people[$key]['IDPadre'] = null;
            }
        }

        $idPersonaToIdMap = [];
        foreach ($people as $person) {
            if (!empty($person['IDPersona'])) {
                $idPersonaToIdMap[$person['IDPersona']] = $person['id'];
            }
        }

        foreach ($people as $key => $person) {
            if ((int) ($person['migradoNuevoID'] ?? 0) !== 0) {
                continue;
            }

            $people[$key]['idPadreNew'] = isset($person['IDPadre'], $idPersonaToIdMap[$person['IDPadre']])
                ? $idPersonaToIdMap[$person['IDPadre']]
                : null;

            $people[$key]['idMadreNew'] = isset($person['IDMadre'], $idPersonaToIdMap[$person['IDMadre']])
                ? $idPersonaToIdMap[$person['IDMadre']]
                : null;

            DB::table('agclientes')
                ->where('id', $person['id'])
                ->update([
                    'idPadreNew' => $people[$key]['idPadreNew'],
                    'idMadreNew' => $people[$key]['idMadreNew'],
                    'migradoNuevoID' => 1,
                ]);
        }

        return $people;
    }

    private function sanitizeParentLinks(array $people): array
    {
        $peopleById = array_column($people, null, 'id');

        foreach ($people as $key => $person) {
            foreach (['idPadreNew', 'idMadreNew'] as $field) {
                $parentId = $person[$field] ?? null;

                if (empty($parentId)) {
                    $people[$key][$field] = null;
                    continue;
                }

                if ((int) $parentId === (int) $person['id'] || !isset($peopleById[$parentId])) {
                    $people[$key][$field] = null;
                }
            }
        }

        return $people;
    }

    private function findRoot(array $people, ?int $rootId = null): ?array
    {
        if ($rootId !== null) {
            foreach ($people as $person) {
                if ((int) $person['id'] === $rootId) {
                    return $person;
                }
            }

            return null;
        }

        foreach ($people as $person) {
            if ((int) ($person['IDPersona'] ?? 0) === 1) {
                return $person;
            }
        }

        return $people[0] ?? null;
    }

    private function buildCompactAncestorColumns(
        array $peopleById,
        array $root,
        ?int $maxGenerations,
        int $baseGeneration,
        int $rootSlot,
        bool $includeAddButtons,
        ?string $rootLineColor = null
    ): array {
        $effectiveMaxGenerations = min(
            $maxGenerations ?? self::MAX_SAFE_GENERATIONS,
            self::MAX_SAFE_GENERATIONS,
            max(1, count($peopleById) + 1)
        );

        $columns = [];
        $warnings = [];
        $queue = [[
            'person' => $root,
            'generation' => 0,
            'slot' => max(0, $rootSlot),
            'layoutSlot' => 0,
            'lineColor' => $this->sanitizeColor($rootLineColor) ?? self::DEFAULT_LINEAGE_COLOR,
            'path' => [(int) $root['id'] => true],
        ]];
        $queued = [];
        $truncated = false;

        while (!empty($queue)) {
            $entry = array_shift($queue);
            $person = $entry['person'];
            $generation = $entry['generation'];
            $slot = $entry['slot'];
            $layoutSlot = $entry['layoutSlot'];
            $inheritedLineColor = $this->sanitizeColor($entry['lineColor'] ?? null) ?? self::DEFAULT_LINEAGE_COLOR;

            if ($generation >= $effectiveMaxGenerations) {
                $truncated = true;
                continue;
            }

            $nodeKey = $this->makeNodeKey($person['id'], $generation, $slot);
            if (isset($queued[$nodeKey])) {
                continue;
            }
            $queued[$nodeKey] = true;

            $node = $person;
            $node['showbtn'] = 2;
            $node['tree_slot'] = $slot;
            $node['tree_layout_generation'] = $generation;
            $node['tree_layout_slot'] = $layoutSlot;
            $node['PersonaIDNew'] = $slot;
            $node['tree_node_key'] = $nodeKey;
            $node['tree_generation'] = $baseGeneration + $generation;
            $node['parentesco'] = $this->relationshipLabel($baseGeneration + $generation, $slot);
            $node['tree_has_more'] = false;
            $node['tree_more_parent_count'] = 0;
            $node['tree_more_parent_sides'] = [];
            $node['tree_inherited_color'] = $inheritedLineColor;
            $node['tree_color_padre'] = $this->resolveLineageColor($person['colorLineaPadre'] ?? null, $inheritedLineColor);
            $node['tree_color_madre'] = $this->resolveLineageColor($person['colorLineaMadre'] ?? null, $inheritedLineColor);

            $parents = [
                'idPadreNew' => ['key' => 'tree_padre_key', 'sex' => 'm', 'offset' => 0],
                'idMadreNew' => ['key' => 'tree_madre_key', 'sex' => 'f', 'offset' => 1],
            ];

            foreach ($parents as $field => $meta) {
                $parentId = $person[$field] ?? null;
                $parentSlot = ($slot * 2) + $meta['offset'];
                $parentLayoutSlot = ($layoutSlot * 2) + $meta['offset'];
                $nextGeneration = $generation + 1;
                $parentLineColor = $meta['sex'] === 'm'
                    ? $node['tree_color_padre']
                    : $node['tree_color_madre'];

                if (!empty($parentId) && isset($peopleById[$parentId])) {
                    if (isset($entry['path'][(int) $parentId])) {
                        $warnings[] = 'Ciclo omitido entre ' . $person['id'] . ' y ' . $parentId . '.';
                        continue;
                    }

                    if ($nextGeneration < $effectiveMaxGenerations) {
                        $parentKey = $this->makeNodeKey($parentId, $nextGeneration, $parentSlot);
                        $node[$meta['key']] = $parentKey;
                        $queue[] = [
                            'person' => $peopleById[$parentId],
                            'generation' => $nextGeneration,
                            'slot' => $parentSlot,
                            'layoutSlot' => $parentLayoutSlot,
                            'lineColor' => $parentLineColor,
                            'path' => $entry['path'] + [(int) $parentId => true],
                        ];
                    } else {
                        $truncated = true;
                        $node['tree_has_more'] = true;
                        $node['tree_more_parent_count']++;
                        $node['tree_more_parent_sides'][] = $meta['sex'];
                    }

                    continue;
                }

                if ($includeAddButtons && $nextGeneration < $effectiveMaxGenerations) {
                    $addNode = $this->makeAddButtonNode(
                        $person,
                        $nodeKey,
                        $nextGeneration,
                        $parentSlot,
                        $parentLayoutSlot,
                        $meta['sex'],
                        $baseGeneration,
                        $parentLineColor
                    );

                    $node[$meta['key']] = $addNode['tree_node_key'];
                    $columns[$nextGeneration][] = $addNode;
                }
            }

            $columns[$generation][] = $node;
        }

        ksort($columns);
        foreach ($columns as $generation => $nodes) {
            usort($nodes, function (array $a, array $b) {
                return [$a['tree_layout_slot'] ?? 0, $a['showbtn'] ?? 0, $a['tree_node_key'] ?? '']
                    <=> [$b['tree_layout_slot'] ?? 0, $b['showbtn'] ?? 0, $b['tree_node_key'] ?? ''];
            });

            $columns[$generation] = array_values($nodes);
        }

        $columns = $this->assignVisualRows($columns);

        return [
            'columnasparatabla' => array_values($columns),
            'warnings' => array_values(array_unique($warnings)),
            'visibleNodes' => array_sum(array_map('count', $columns)),
            'truncated' => $truncated,
        ];
    }

    private function assignVisualRows(array $columns): array
    {
        $nodeMeta = [];

        foreach ($columns as $generation => $nodes) {
            foreach ($nodes as $index => $node) {
                $key = $node['tree_node_key'] ?? null;
                if (!$key) {
                    continue;
                }

                $parents = [];
                if (($node['showbtn'] ?? null) === 2) {
                    foreach (['tree_padre_key', 'tree_madre_key'] as $field) {
                        if (!empty($node[$field])) {
                            $parents[] = $node[$field];
                        }
                    }
                }

                $nodeMeta[$key] = [
                    'generation' => $generation,
                    'index' => $index,
                    'showbtn' => $node['showbtn'] ?? null,
                    'parents' => $parents,
                    'child' => $node['tree_child_key'] ?? null,
                    'sex' => $node['showbtnsex'] ?? null,
                    'row' => null,
                    'state' => 'new',
                ];
            }
        }

        foreach ($nodeMeta as $key => $meta) {
            if (($meta['showbtn'] ?? null) !== 2) {
                continue;
            }

            $nodeMeta[$key]['parents'] = array_values(array_filter(
                $meta['parents'],
                fn (string $parentKey): bool => isset($nodeMeta[$parentKey])
                    && ($nodeMeta[$parentKey]['showbtn'] ?? null) === 2
            ));
        }

        $nextRow = 0;
        $assign = function (string $key) use (&$assign, &$nodeMeta, &$nextRow): float {
            if (!isset($nodeMeta[$key])) {
                return (float) $nextRow++;
            }

            if ($nodeMeta[$key]['row'] !== null) {
                return (float) $nodeMeta[$key]['row'];
            }

            if ($nodeMeta[$key]['state'] === 'visiting') {
                $nodeMeta[$key]['row'] = (float) $nextRow++;
                $nodeMeta[$key]['state'] = 'done';
                return (float) $nodeMeta[$key]['row'];
            }

            $nodeMeta[$key]['state'] = 'visiting';
            $parentRows = [];

            if (($nodeMeta[$key]['showbtn'] ?? null) === 1) {
                $childKey = $nodeMeta[$key]['child'];
                $childRow = $childKey && isset($nodeMeta[$childKey])
                    ? $assign($childKey)
                    : (float) $nextRow++;
                $offset = ($nodeMeta[$key]['sex'] ?? null) === 'm' ? -0.22 : 0.22;

                $nodeMeta[$key]['row'] = max(0, $childRow + $offset);
                $nodeMeta[$key]['state'] = 'done';

                return (float) $nodeMeta[$key]['row'];
            }

            foreach ($nodeMeta[$key]['parents'] as $parentKey) {
                if (isset($nodeMeta[$parentKey])) {
                    $parentRows[] = $assign($parentKey);
                }
            }

            $nodeMeta[$key]['row'] = count($parentRows) > 0
                ? array_sum($parentRows) / count($parentRows)
                : (float) $nextRow++;
            $nodeMeta[$key]['state'] = 'done';

            return (float) $nodeMeta[$key]['row'];
        };

        foreach ($columns[0] ?? [] as $node) {
            if (!empty($node['tree_node_key'])) {
                $assign($node['tree_node_key']);
            }
        }

        foreach (array_keys($nodeMeta) as $key) {
            $assign($key);
        }

        foreach ($columns as $generation => $nodes) {
            foreach ($nodes as $index => $node) {
                $key = $node['tree_node_key'] ?? null;
                $columns[$generation][$index]['tree_row'] = $key && isset($nodeMeta[$key])
                    ? $nodeMeta[$key]['row']
                    : (float) $index;
            }

            $columns[$generation] = $this->separateColumnRows($columns[$generation]);

            usort($columns[$generation], function (array $a, array $b) {
                return [$a['tree_row'] ?? 0, $a['tree_layout_slot'] ?? 0]
                    <=> [$b['tree_row'] ?? 0, $b['tree_layout_slot'] ?? 0];
            });
        }

        return $columns;
    }

    private function separateColumnRows(array $nodes): array
    {
        $realNodes = [];
        $supportNodes = [];

        foreach ($nodes as $node) {
            if (($node['showbtn'] ?? null) === 2) {
                $realNodes[] = $node;
            } else {
                $supportNodes[] = $node;
            }
        }

        $sortByDesiredRow = function (array $a, array $b): int {
            return [$a['tree_row'] ?? 0, $a['tree_layout_slot'] ?? 0]
                <=> [$b['tree_row'] ?? 0, $b['tree_layout_slot'] ?? 0];
        };

        usort($realNodes, $sortByDesiredRow);
        usort($supportNodes, $sortByDesiredRow);

        $occupied = [];
        $placed = [];

        foreach ($realNodes as $node) {
            $node['tree_row'] = $this->nextAvailableRow((float) ($node['tree_row'] ?? 0), 0.64, $occupied);
            $occupied[] = [$node['tree_row'] - 0.64, $node['tree_row'] + 0.64];
            $placed[] = $node;
        }

        foreach ($supportNodes as $node) {
            $halfHeight = ($node['showbtn'] ?? null) === 1 ? 0.34 : 0.48;
            $node['tree_row'] = $this->nextAvailableRow((float) ($node['tree_row'] ?? 0), $halfHeight, $occupied);
            $occupied[] = [$node['tree_row'] - $halfHeight, $node['tree_row'] + $halfHeight];
            $placed[] = $node;
        }

        return $placed;
    }

    private function nextAvailableRow(float $desiredRow, float $halfHeight, array $occupied): float
    {
        $row = max(0.0, $desiredRow);
        usort($occupied, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        for ($attempts = 0; $attempts < 200; $attempts++) {
            $moved = false;

            foreach ($occupied as $range) {
                if (($row + $halfHeight) <= $range[0] || ($row - $halfHeight) >= $range[1]) {
                    continue;
                }

                $row = $range[1] + $halfHeight;
                $moved = true;
            }

            if (!$moved) {
                break;
            }
        }

        return $row;
    }

    private function makeAddButtonNode(
        array $child,
        string $childNodeKey,
        int $generation,
        int $slot,
        int $layoutSlot,
        string $sex,
        int $baseGeneration,
        string $lineColor
    ): array {
        return [
            'showbtn' => 1,
            'showbtnsex' => $sex,
            'id_hijo' => $child['id'],
            'IDCliente' => $child['IDCliente'],
            'tree_slot' => $slot,
            'tree_layout_generation' => $generation,
            'tree_layout_slot' => $layoutSlot,
            'PersonaIDNew' => $slot,
            'tree_node_key' => 'add_' . $sex . '_' . $child['id'] . '_' . $generation . '_' . $slot,
            'tree_child_key' => $childNodeKey,
            'tree_generation' => $baseGeneration + $generation,
            'tree_inherited_color' => $lineColor,
            'tree_line_color' => $lineColor,
            'parentesco' => $sex === 'm' ? 'Agregar Padre' : 'Agregar Madre',
        ];
    }

    private function resolveLineageColor(?string $storedColor, string $fallbackColor): string
    {
        return $this->sanitizeColor($storedColor)
            ?? $this->sanitizeColor($fallbackColor)
            ?? self::DEFAULT_LINEAGE_COLOR;
    }

    private function sanitizeColor(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        $color = strtoupper(trim($color));

        return preg_match('/^#[0-9A-F]{6}$/', $color) === 1
            ? $color
            : null;
    }

    private function makeNodeKey(int|string $id, int $generation, int $slot): string
    {
        return 'person_' . $id . '_' . $generation . '_' . $slot;
    }

    private function relationshipLabel(int $absoluteGeneration, int $slot): string
    {
        if ($absoluteGeneration <= 0) {
            return 'Cliente';
        }

        if ($absoluteGeneration === 1) {
            return $slot % 2 === 0 ? 'Padre' : 'Madre';
        }

        $stems = [
            'Abuel', 'Bisabuel', 'Tatarabuel', 'Trastatarabuel',
            'Retatarabuel', 'Sestarabuel', 'Setatarabuel', 'Octatarabuel',
            'Nonatarabuel', 'Decatarabuel', 'Undecatarabuel', 'Duodecatarabuel',
            'Trececatarabuel', 'Catorcatarabuel', 'Quincecatarabuel',
            'Deciseiscatarabuel', 'Decisietecatarabuel', 'Deciochocatarabuel',
            'Decinuevecatarabuel', 'Vigecatarabuel', 'Vigecimoprimocatarabuel',
            'Vigecimosegundocatarabuel', 'Vigecimotercercatarabuel',
            'Vigecimocuartocatarabuel', 'Vigecimoquintocatarabuel',
            'Vigecimosextocatarabuel', 'Vigecimoseptimocatarabuel',
            'Vigecimooctavocatarabuel', 'Vigecimonovenocatarabuel',
            'Trigecatarabuel', 'Trigecimoprimocatarabuel',
            'Trigecimosegundocatarabuel', 'Trigecimotercercatarabuel',
            'Trigecimocuartocatarabuel', 'Trigecimoquintocatarabuel',
            'Trigecimosextocatarabuel', 'Trigecimoseptimocatarabuel',
            'Trigecimooctavocatarabuel', 'Trigecimonovenocatarabuel',
            'Cuarentacatarabuel',
        ];

        $level = $absoluteGeneration - 2;
        $stem = $stems[$level] ?? ('Generacion ' . ($absoluteGeneration + 1));
        $suffix = $slot % 2 === 0 ? 'o' : 'a';

        return trim($stem . $suffix . ' ' . $this->relationshipPathText($slot, $level));
    }

    private function relationshipPathText(int $slot, int $level): string
    {
        $text = '';
        $multiplier = 4;

        for ($j = 1; $j <= $level; $j++) {
            $text .= (($slot % $multiplier) < ($multiplier / 2) ? 'P ' : 'M ');
            $multiplier *= 2;
        }

        $text .= ($slot < 2 * ($level + 1) ? 'P' : 'M');

        return $text;
    }

    private function hasRealPersonAtGeneration(array $columns, int $generation): bool
    {
        foreach ($columns[$generation] ?? [] as $node) {
            if (($node['showbtn'] ?? null) === 2) {
                return true;
            }
        }

        return false;
    }

    private function syncPersonaSlots(array $columns): void
    {
        foreach ($columns as $column) {
            foreach ($column as $node) {
                if (($node['showbtn'] ?? null) !== 2 || !isset($node['id'], $node['PersonaIDNew'])) {
                    continue;
                }

                if ((string) ($node['PersonaIDNew'] ?? '') === (string) ($node['tree_slot'] ?? '')) {
                    DB::table('agclientes')
                        ->where('id', $node['id'])
                        ->where(function ($query) use ($node) {
                            $query->whereNull('PersonaIDNew')
                                ->orWhere('PersonaIDNew', '!=', $node['tree_slot']);
                        })
                        ->update(['PersonaIDNew' => $node['tree_slot']]);
                }
            }
        }
    }
}

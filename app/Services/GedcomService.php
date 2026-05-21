<?php

namespace App\Services;

use App\Models\Agcliente;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GedcomService
{
    private const MONTHS = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AUG',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DEC',
    ];

    private const MONTH_NUMBERS = [
        'JAN' => 1,
        'FEB' => 2,
        'MAR' => 3,
        'APR' => 4,
        'MAY' => 5,
        'JUN' => 6,
        'JUL' => 7,
        'AUG' => 8,
        'SEP' => 9,
        'OCT' => 10,
        'NOV' => 11,
        'DEC' => 12,
    ];

    public function exportClientByAgclienteId(int|string $id): string
    {
        $root = Agcliente::findOrFail($id);
        $people = Agcliente::where('IDCliente', $root->IDCliente)
            ->orderBy('IDPersona')
            ->orderBy('id')
            ->get();

        return $this->exportPeople($people, 'ARBOL CLIENTE ' . $root->IDCliente . '.ged');
    }

    public function exportGlobal(): string
    {
        $people = Agcliente::whereNotNull('IDCliente')
            ->where('IDCliente', '<>', '')
            ->where('IDCliente', '<>', '0')
            ->orderBy('IDCliente')
            ->orderBy('IDPersona')
            ->orderBy('id')
            ->get();

        return $this->exportPeople($people, 'AppSefarGlobal.ged');
    }

    public function exportGlobalToFile(string $path): void
    {
        @set_time_limit(0);

        $query = Agcliente::query()
            ->whereNotNull('IDCliente')
            ->where('IDCliente', '<>', '')
            ->where('IDCliente', '<>', '0');

        $relations = $this->buildExportRelationsFromQuery(clone $query);

        $handle = fopen($path, 'wb');

        if (!$handle) {
            throw new RuntimeException('No se pudo crear el archivo GEDCOM temporal.');
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            $this->writeLines($handle, $this->headerLines('AppSefarGlobal.ged'));

            (clone $query)
                ->select($this->exportColumns())
                ->orderBy('id')
                ->chunkById(1000, function (Collection $people) use ($handle, $relations): void {
                    foreach ($people as $person) {
                        $this->writeIndividual($handle, $person, $relations);
                    }
                });

            foreach ($relations['families'] as $familyId => $family) {
                $this->writeFamily($handle, $familyId, $family);
            }

            fwrite($handle, "0 TRLR\n");
        } finally {
            fclose($handle);
        }
    }

    public function validate(string $gedcom): array
    {
        $parsed = $this->parse($gedcom);
        $summary = $this->summary($parsed);

        return [
            ...$summary,
            'can_import' => empty($parsed['errors']) && count($parsed['individuals']) > 0,
            'preview' => $this->previewRows($parsed),
            'errors' => $parsed['errors'],
            'warnings' => $parsed['warnings'],
        ];
    }

    public function import(string $gedcom, string $idCliente, bool $replaceExisting = false): array
    {
        $idCliente = trim($idCliente);

        if ($idCliente === '') {
            throw new RuntimeException('Debes indicar el IDCliente destino.');
        }

        $parsed = $this->parse($gedcom);

        if (!empty($parsed['errors'])) {
            throw new RuntimeException('El GEDCOM tiene errores y no se puede importar.');
        }

        if (count($parsed['individuals']) === 0) {
            throw new RuntimeException('El GEDCOM no contiene individuos para importar.');
        }

        if (!$replaceExisting && Agcliente::where('IDCliente', $idCliente)->exists()) {
            throw new RuntimeException('Ese IDCliente ya tiene un arbol. Marca reemplazar si quieres regenerarlo desde el GEDCOM.');
        }

        $mapped = $this->mapImportRows($parsed, $idCliente);
        $created = 0;
        $updatedParents = 0;

        DB::transaction(function () use ($idCliente, $replaceExisting, $mapped, &$created, &$updatedParents) {
            if ($replaceExisting) {
                Agcliente::where('IDCliente', $idCliente)->delete();
            }

            $xrefToId = [];
            $xrefToIdPersona = [];

            foreach ($mapped['people'] as $xref => $row) {
                $person = Agcliente::create($row);
                $xrefToId[$xref] = $person->id;
                $xrefToIdPersona[$xref] = $person->IDPersona;
                $created++;
            }

            foreach ($mapped['parents'] as $xref => $parents) {
                $updates = [];

                if (!empty($parents['father']) && isset($xrefToId[$parents['father']])) {
                    $updates['idPadreNew'] = $xrefToId[$parents['father']];
                    $updates['IDPadre'] = $xrefToIdPersona[$parents['father']] ?? null;
                }

                if (!empty($parents['mother']) && isset($xrefToId[$parents['mother']])) {
                    $updates['idMadreNew'] = $xrefToId[$parents['mother']];
                    $updates['IDMadre'] = $xrefToIdPersona[$parents['mother']] ?? null;
                }

                if (!empty($updates) && isset($xrefToId[$xref])) {
                    Agcliente::where('id', $xrefToId[$xref])->update($updates);
                    $updatedParents++;
                }
            }
        });

        app(GenealogyService::class)->forgetProcessedTree($idCliente);

        return [
            ...$this->summary($parsed),
            'IDCliente' => $idCliente,
            'created' => $created,
            'updated_parents' => $updatedParents,
            'replaced' => $replaceExisting,
            'warnings' => $parsed['warnings'],
            'errors' => [],
        ];
    }

    private function exportPeople(Collection $people, string $filename): string
    {
        $people = $people->values();
        $relations = $this->buildExportRelations($people);
        $lines = $this->headerLines($filename);

        foreach ($people as $person) {
            $lines = array_merge($lines, $this->individualLines($person, $relations));
        }

        foreach ($relations['families'] as $familyId => $family) {
            $marriage = $this->firstMarriageData($people, $family['father'], $family['mother']);
            $family['marriage'] = $marriage;
            $lines = array_merge($lines, $this->familyLines($familyId, $family));
        }

        $lines[] = '0 TRLR';

        return "\xEF\xBB\xBF" . implode("\n", $lines) . "\n";
    }

    private function individualLines(object $person, array $relations): array
    {
        $personId = (int) $person->id;
        $lines = $this->recordLine(0, $this->xref('I', $personId), 'INDI');
        $lines = array_merge($lines, $this->line(1, 'NAME', $this->gedcomName($person)));
        $lines = array_merge($lines, $this->line(2, 'GIVN', $person->Nombres));
        $lines = array_merge($lines, $this->line(2, 'SURN', $person->Apellidos));
        $lines = array_merge($lines, $this->line(1, 'SEX', $this->sex($person->Sexo)));
        $lines = array_merge($lines, $this->eventLines(1, 'BIRT', $person->AnhoNac, $person->MesNac, $person->DiaNac, $person->LugarNac, $person->PaisNac));
        $lines = array_merge($lines, $this->eventLines(1, 'BAPM', $person->AnhoBtzo, $person->MesBtzo, $person->DiaBtzo, $person->LugarBtzo, $person->PaisBtzo));
        $lines = array_merge($lines, $this->eventLines(1, 'DEAT', $person->AnhoDef, $person->MesDef, $person->DiaDef, $person->LugarDef, $person->PaisDef));

        if ($this->filled($person->NPasaporte)) {
            $lines = array_merge($lines, $this->line(1, 'IDNO', $person->NPasaporte));
            $lines = array_merge($lines, $this->line(2, 'TYPE', 'Passport'));
        }

        if ($this->filled($person->NDocIdent)) {
            $lines = array_merge($lines, $this->line(1, 'IDNO', $person->NDocIdent));
            $lines = array_merge($lines, $this->line(2, 'TYPE', 'Identity document'));
        }

        if ($this->filled($person->Observaciones)) {
            $lines = array_merge($lines, $this->line(1, 'NOTE', $person->Observaciones));
        }

        foreach ($relations['child_families'][$personId] ?? [] as $familyId) {
            $lines = array_merge($lines, $this->line(1, 'FAMC', $this->xref('F', $familyId), false));
        }

        foreach ($relations['parent_families'][$personId] ?? [] as $familyId) {
            $lines = array_merge($lines, $this->line(1, 'FAMS', $this->xref('F', $familyId), false));
        }

        return $lines;
    }

    private function familyLines(int $familyId, array $family): array
    {
        $lines = $this->recordLine(0, $this->xref('F', $familyId), 'FAM');

        if ($family['father']) {
            $lines = array_merge($lines, $this->line(1, 'HUSB', $this->xref('I', $family['father']), false));
        }

        if ($family['mother']) {
            $lines = array_merge($lines, $this->line(1, 'WIFE', $this->xref('I', $family['mother']), false));
        }

        foreach ($family['children'] as $childId) {
            $lines = array_merge($lines, $this->line(1, 'CHIL', $this->xref('I', $childId), false));
        }

        $marriage = $family['marriage'] ?? null;
        if ($marriage) {
            $lines = array_merge($lines, $this->eventLines(1, 'MARR', $marriage['year'], $marriage['month'], $marriage['day'], $marriage['place'], $marriage['country']));
        }

        return $lines;
    }

    private function writeIndividual($handle, object $person, array $relations): void
    {
        $this->writeLines($handle, $this->individualLines($person, $relations));
    }

    private function writeFamily($handle, int $familyId, array $family): void
    {
        $this->writeLines($handle, $this->familyLines($familyId, $family));
    }

    private function writeLines($handle, array $lines): void
    {
        foreach ($lines as $line) {
            fwrite($handle, $line . "\n");
        }
    }

    private function buildExportRelations(Collection $people): array
    {
        $peopleById = $people->keyBy('id');
        $idPersonaMaps = [];

        foreach ($people as $person) {
            if ($this->filled($person->IDCliente) && $this->filled($person->IDPersona)) {
                $idPersonaMaps[$person->IDCliente][(int) $person->IDPersona] = (int) $person->id;
            }
        }

        $families = [];
        $familyKeys = [];
        $childFamilies = [];
        $parentFamilies = [];
        $nextFamilyId = 1;

        foreach ($people as $person) {
            $childId = (int) $person->id;
            $fatherId = $this->resolveExportParentId($person, 'idPadreNew', 'IDPadre', $peopleById, $idPersonaMaps);
            $motherId = $this->resolveExportParentId($person, 'idMadreNew', 'IDMadre', $peopleById, $idPersonaMaps);

            if (!$fatherId && !$motherId) {
                continue;
            }

            $key = ($fatherId ?: 0) . ':' . ($motherId ?: 0);
            if (!isset($familyKeys[$key])) {
                $familyKeys[$key] = $nextFamilyId++;
                $families[$familyKeys[$key]] = [
                    'father' => $fatherId,
                    'mother' => $motherId,
                    'children' => [],
                ];
            }

            $familyId = $familyKeys[$key];
            $families[$familyId]['children'][] = $childId;
            $childFamilies[$childId][] = $familyId;

            if ($fatherId) {
                $parentFamilies[$fatherId][] = $familyId;
            }

            if ($motherId) {
                $parentFamilies[$motherId][] = $familyId;
            }
        }

        foreach ($families as $familyId => $family) {
            $families[$familyId]['children'] = array_values(array_unique($family['children']));
        }

        foreach ($parentFamilies as $personId => $familyIds) {
            $parentFamilies[$personId] = array_values(array_unique($familyIds));
        }

        return [
            'families' => $families,
            'child_families' => $childFamilies,
            'parent_families' => $parentFamilies,
        ];
    }

    private function buildExportRelationsFromQuery($query): array
    {
        $people = $query
            ->select([
                'id',
                'IDCliente',
                'IDPersona',
                'idPadreNew',
                'idMadreNew',
                'IDPadre',
                'IDMadre',
                'AnhoMatr',
                'MesMatr',
                'DiaMatr',
                'LugarMatr',
                'PaisMatr',
            ])
            ->orderBy('id')
            ->get();

        $relations = $this->buildExportRelations($people);
        $marriages = [];

        foreach ($people as $person) {
            if ($this->filled($person->AnhoMatr) || $this->filled($person->PaisMatr) || $this->filled($person->LugarMatr)) {
                $marriages[(int) $person->id] = [
                    'year' => $person->AnhoMatr,
                    'month' => $person->MesMatr,
                    'day' => $person->DiaMatr,
                    'place' => $person->LugarMatr,
                    'country' => $person->PaisMatr,
                ];
            }
        }

        foreach ($relations['families'] as $familyId => $family) {
            $relations['families'][$familyId]['marriage'] = null;

            foreach (array_filter([$family['father'], $family['mother']]) as $parentId) {
                if (isset($marriages[$parentId])) {
                    $relations['families'][$familyId]['marriage'] = $marriages[$parentId];
                    break;
                }
            }
        }

        unset($people);

        return $relations;
    }

    private function exportColumns(): array
    {
        return [
            'id',
            'IDCliente',
            'IDPersona',
            'idPadreNew',
            'idMadreNew',
            'IDPadre',
            'IDMadre',
            'Nombres',
            'Apellidos',
            'Sexo',
            'NPasaporte',
            'NDocIdent',
            'AnhoNac',
            'MesNac',
            'DiaNac',
            'LugarNac',
            'PaisNac',
            'AnhoBtzo',
            'MesBtzo',
            'DiaBtzo',
            'LugarBtzo',
            'PaisBtzo',
            'AnhoDef',
            'MesDef',
            'DiaDef',
            'LugarDef',
            'PaisDef',
            'AnhoMatr',
            'MesMatr',
            'DiaMatr',
            'LugarMatr',
            'PaisMatr',
            'Observaciones',
        ];
    }

    private function parse(string $gedcom): array
    {
        $gedcom = preg_replace('/^\xEF\xBB\xBF/', '', $gedcom) ?? $gedcom;
        $rawLines = preg_split('/\R/', $gedcom) ?: [];
        $records = [];
        $stack = [];
        $lastNode = null;
        $errors = [];
        $warnings = [];

        foreach ($rawLines as $index => $rawLine) {
            $rawLine = rtrim($rawLine, "\r\n");
            if (trim($rawLine) === '') {
                continue;
            }

            if (!preg_match('/^(\d+)\s+(?:(@[^@]+@)\s+)?([A-Za-z0-9_]+)(?:\s+(.*))?$/', $rawLine, $matches)) {
                $errors[] = 'Linea ' . ($index + 1) . ': formato GEDCOM invalido.';
                continue;
            }

            $level = (int) $matches[1];
            $xref = isset($matches[2]) ? trim($matches[2]) : null;
            $tag = strtoupper($matches[3]);
            $value = $matches[4] ?? '';

            if (($tag === 'CONC' || $tag === 'CONT') && $lastNode) {
                $lastNode->value .= ($tag === 'CONT' ? "\n" : '') . $value;
                continue;
            }

            $node = (object) [
                'line' => $index + 1,
                'level' => $level,
                'xref' => $xref,
                'tag' => $tag,
                'value' => $value,
                'children' => [],
            ];

            if ($level === 0) {
                $records[] = $node;
                $stack = [$level => $node];
            } else {
                while (!empty($stack) && max(array_keys($stack)) >= $level) {
                    unset($stack[max(array_keys($stack))]);
                }

                if (empty($stack)) {
                    $errors[] = 'Linea ' . ($index + 1) . ': registro sin padre.';
                    continue;
                }

                $parent = $stack[max(array_keys($stack))];
                $parent->children[] = $node;
                $stack[$level] = $node;
            }

            $lastNode = $node;
        }

        $xrefSeen = [];
        $individuals = [];
        $families = [];
        $hasHead = false;
        $hasTrailer = false;

        foreach ($records as $record) {
            $hasHead = $hasHead || $record->tag === 'HEAD';
            $hasTrailer = $hasTrailer || $record->tag === 'TRLR';

            if ($record->xref) {
                if (isset($xrefSeen[$record->xref])) {
                    $errors[] = 'XREF duplicado: ' . $record->xref;
                }
                $xrefSeen[$record->xref] = true;
            }

            if ($record->tag === 'INDI' && $record->xref) {
                $individuals[$record->xref] = $this->parseIndividual($record);
            }

            if ($record->tag === 'FAM' && $record->xref) {
                $families[$record->xref] = $this->parseFamily($record);
            }
        }

        if (!$hasHead) {
            $errors[] = 'Falta el registro HEAD.';
        }

        if (!$hasTrailer) {
            $errors[] = 'Falta el registro TRLR.';
        }

        if (count($individuals) === 0) {
            $errors[] = 'No se encontraron registros INDI.';
        }

        foreach ($families as $xref => $family) {
            foreach (array_filter([$family['husband'], $family['wife'], ...$family['children']]) as $personXref) {
                if (!isset($individuals[$personXref])) {
                    $errors[] = "La familia {$xref} referencia un individuo inexistente: {$personXref}.";
                }
            }
        }

        foreach ($individuals as $xref => $individual) {
            foreach ($individual['famc'] as $familyXref) {
                if (!isset($families[$familyXref])) {
                    $warnings[] = "El individuo {$xref} apunta a una familia inexistente: {$familyXref}.";
                }
            }
        }

        return compact('records', 'individuals', 'families', 'errors', 'warnings');
    }

    private function parseIndividual(object $record): array
    {
        $individual = [
            'xref' => $record->xref,
            'names' => '',
            'surnames' => '',
            'sex' => null,
            'birth' => [],
            'baptism' => [],
            'death' => [],
            'documents' => [],
            'notes' => [],
            'famc' => [],
            'fams' => [],
        ];

        foreach ($record->children as $child) {
            match ($child->tag) {
                'NAME' => $individual = $this->mergeName($individual, $child),
                'SEX' => $individual['sex'] = $this->sex($child->value),
                'BIRT' => $individual['birth'] = $this->parseEvent($child),
                'BAPM', 'CHR' => $individual['baptism'] = $this->parseEvent($child),
                'DEAT' => $individual['death'] = $this->parseEvent($child),
                'IDNO' => $individual['documents'][] = $this->parseDocument($child),
                'NOTE' => $individual['notes'][] = trim($child->value),
                'FAMC' => $individual['famc'][] = trim($child->value),
                'FAMS' => $individual['fams'][] = trim($child->value),
                default => null,
            };
        }

        return $individual;
    }

    private function parseFamily(object $record): array
    {
        $family = [
            'xref' => $record->xref,
            'husband' => null,
            'wife' => null,
            'children' => [],
            'marriage' => [],
        ];

        foreach ($record->children as $child) {
            match ($child->tag) {
                'HUSB' => $family['husband'] = trim($child->value),
                'WIFE' => $family['wife'] = trim($child->value),
                'CHIL' => $family['children'][] = trim($child->value),
                'MARR' => $family['marriage'] = $this->parseEvent($child),
                default => null,
            };
        }

        $family['children'] = array_values(array_unique(array_filter($family['children'])));

        return $family;
    }

    private function mapImportRows(array $parsed, string $idCliente): array
    {
        $slots = $this->assignImportSlots($parsed);
        $nextFreeIdPersona = max(array_map(fn ($slot) => $slot['IDPersona'], $slots ?: [['IDPersona' => 0]])) + 1;
        $people = [];
        $parents = [];
        $now = now();
        $usuario = Auth::user()?->email ?? 'gedcom-import';

        foreach ($parsed['individuals'] as $xref => $individual) {
            $slot = $slots[$xref] ?? null;
            $birth = $individual['birth'] ?? [];
            $baptism = $individual['baptism'] ?? [];
            $death = $individual['death'] ?? [];
            $passport = $this->firstDocument($individual['documents'], 'passport');
            $identityDocument = $this->firstDocument($individual['documents'], 'identity');

            $people[$xref] = [
                'IDCliente' => $idCliente,
                'IDPersona' => $slot['IDPersona'] ?? $nextFreeIdPersona++,
                'PersonaIDNew' => $slot['slot'] ?? null,
                'Generacion' => isset($slot['generation']) ? $slot['generation'] + 1 : null,
                'Nombres' => $individual['names'] ?: 'Sin nombre',
                'Apellidos' => $individual['surnames'] ?: '',
                'Sexo' => $individual['sex'] ?: 'U',
                'NPasaporte' => $passport,
                'NDocIdent' => $identityDocument,
                'AnhoNac' => $birth['year'] ?? null,
                'MesNac' => $birth['month'] ?? null,
                'DiaNac' => $birth['day'] ?? null,
                'LugarNac' => $birth['place'] ?? null,
                'PaisNac' => $birth['country'] ?? null,
                'AnhoBtzo' => $baptism['year'] ?? null,
                'MesBtzo' => $baptism['month'] ?? null,
                'DiaBtzo' => $baptism['day'] ?? null,
                'LugarBtzo' => $baptism['place'] ?? null,
                'PaisBtzo' => $baptism['country'] ?? null,
                'AnhoDef' => $death['year'] ?? null,
                'MesDef' => $death['month'] ?? null,
                'DiaDef' => $death['day'] ?? null,
                'LugarDef' => $death['place'] ?? null,
                'PaisDef' => $death['country'] ?? null,
                'Observaciones' => implode("\n", array_filter($individual['notes'])),
                'FRegistro' => $now,
                'FUpdate' => $now,
                'Usuario' => $usuario,
                'migradoNuevoID' => 1,
            ];

            $parents[$xref] = $this->parentsForImportedIndividual($individual, $parsed['families']);
        }

        foreach ($parsed['families'] as $family) {
            $marriage = $family['marriage'] ?? [];
            if (empty($marriage)) {
                continue;
            }

            foreach (array_filter([$family['husband'], $family['wife']]) as $spouseXref) {
                if (!isset($people[$spouseXref])) {
                    continue;
                }

                $people[$spouseXref]['AnhoMatr'] = $marriage['year'] ?? null;
                $people[$spouseXref]['MesMatr'] = $marriage['month'] ?? null;
                $people[$spouseXref]['DiaMatr'] = $marriage['day'] ?? null;
                $people[$spouseXref]['LugarMatr'] = $marriage['place'] ?? null;
                $people[$spouseXref]['PaisMatr'] = $marriage['country'] ?? null;
            }
        }

        return compact('people', 'parents');
    }

    private function assignImportSlots(array $parsed): array
    {
        $parentXrefs = [];
        foreach ($parsed['families'] as $family) {
            foreach (array_filter([$family['husband'], $family['wife']]) as $xref) {
                $parentXrefs[$xref] = true;
            }
        }

        $root = null;
        foreach (array_keys($parsed['individuals']) as $xref) {
            if (!isset($parentXrefs[$xref])) {
                $root = $xref;
                break;
            }
        }

        $root ??= array_key_first($parsed['individuals']);
        $slots = [];
        $visiting = [];

        $assign = function (string $xref, int $generation, int $slot) use (&$assign, &$slots, &$visiting, $parsed): void {
            if (isset($visiting[$xref])) {
                return;
            }

            $visiting[$xref] = true;
            $slots[$xref] = [
                'generation' => $generation,
                'slot' => $slot,
                'IDPersona' => (2 ** $generation) + $slot,
            ];

            $parents = $this->parentsForImportedIndividual($parsed['individuals'][$xref], $parsed['families']);

            if ($parents['father'] && isset($parsed['individuals'][$parents['father']])) {
                $assign($parents['father'], $generation + 1, $slot * 2);
            }

            if ($parents['mother'] && isset($parsed['individuals'][$parents['mother']])) {
                $assign($parents['mother'], $generation + 1, $slot * 2 + 1);
            }

            unset($visiting[$xref]);
        };

        if ($root) {
            $assign($root, 0, 0);
        }

        return $slots;
    }

    private function parentsForImportedIndividual(array $individual, array $families): array
    {
        $familyXref = $individual['famc'][0] ?? null;
        $family = $familyXref ? ($families[$familyXref] ?? null) : null;

        return [
            'father' => $family['husband'] ?? null,
            'mother' => $family['wife'] ?? null,
        ];
    }

    private function previewRows(array $parsed): array
    {
        return collect($parsed['individuals'])
            ->take(20)
            ->map(function (array $individual): array {
                return [
                    'xref' => $individual['xref'],
                    'name' => trim($individual['names'] . ' ' . $individual['surnames']),
                    'sex' => $individual['sex'] ?: 'U',
                    'birth' => $this->dateLabel($individual['birth'] ?? []),
                ];
            })
            ->values()
            ->all();
    }

    private function summary(array $parsed): array
    {
        return [
            'individuals_count' => count($parsed['individuals']),
            'families_count' => count($parsed['families']),
            'errors_count' => count($parsed['errors']),
            'warnings_count' => count($parsed['warnings']),
        ];
    }

    private function parseEvent(object $event): array
    {
        $date = [];
        $place = [];

        foreach ($event->children as $child) {
            if ($child->tag === 'DATE') {
                $date = $this->parseGedcomDate($child->value);
            }

            if ($child->tag === 'PLAC') {
                $place = $this->parsePlace($child->value);
            }
        }

        return [...$date, ...$place];
    }

    private function mergeName(array $individual, object $nameNode): array
    {
        $parsedName = $this->splitGedcomName($nameNode->value);

        foreach ($nameNode->children as $child) {
            if ($child->tag === 'GIVN') {
                $parsedName['names'] = trim($child->value);
            }

            if ($child->tag === 'SURN') {
                $parsedName['surnames'] = trim($child->value);
            }
        }

        $individual['names'] = $individual['names'] ?: $parsedName['names'];
        $individual['surnames'] = $individual['surnames'] ?: $parsedName['surnames'];

        return $individual;
    }

    private function parseDocument(object $node): array
    {
        $type = '';
        foreach ($node->children as $child) {
            if ($child->tag === 'TYPE') {
                $type = trim($child->value);
            }
        }

        return [
            'value' => trim($node->value),
            'type' => $type,
        ];
    }

    private function resolveExportParentId(object $person, string $newField, string $oldField, Collection $peopleById, array $idPersonaMaps): ?int
    {
        $parentId = (int) ($person->{$newField} ?? 0);
        if ($parentId > 0 && $peopleById->has($parentId) && $parentId !== (int) $person->id) {
            return $parentId;
        }

        $oldParentId = (int) ($person->{$oldField} ?? 0);
        $resolved = $idPersonaMaps[$person->IDCliente][$oldParentId] ?? null;

        return $resolved && $resolved !== (int) $person->id ? (int) $resolved : null;
    }

    private function firstMarriageData(Collection $people, ?int $fatherId, ?int $motherId): ?array
    {
        foreach (array_filter([$fatherId, $motherId]) as $id) {
            $person = $people->firstWhere('id', $id);
            if (!$person) {
                continue;
            }

            if ($this->filled($person->AnhoMatr) || $this->filled($person->PaisMatr) || $this->filled($person->LugarMatr)) {
                return [
                    'year' => $person->AnhoMatr,
                    'month' => $person->MesMatr,
                    'day' => $person->DiaMatr,
                    'place' => $person->LugarMatr,
                    'country' => $person->PaisMatr,
                ];
            }
        }

        return null;
    }

    private function headerLines(string $filename): array
    {
        $now = now();

        return [
            '0 HEAD',
            '1 GEDC',
            '2 VERS 5.5.5',
            '2 FORM LINEAGE-LINKED',
            '3 VERS 5.5.5',
            '1 DEST AppSefarUniversal',
            '1 SOUR AppSefarUniversal',
            '2 VERS 1.0.0',
            '2 NAME App Sefar Universal',
            '2 CORP Sefar Universal',
            '1 DATE ' . strtoupper($now->format('j M Y')),
            '2 TIME ' . $now->format('H:i:s'),
            '1 LANG Spanish',
            '1 CHAR UTF-8',
            '1 FILE ' . $filename,
            '1 SUBM @U1@',
            '0 @U1@ SUBM',
            '1 NAME Sefar Universal',
        ];
    }

    private function recordLine(int $level, string $xref, string $tag): array
    {
        return ["{$level} {$xref} {$tag}"];
    }

    private function line(int $level, string $tag, mixed $value = null, bool $escapeAtSigns = true): array
    {
        $value = $this->clean($value);

        if ($value === '') {
            return ["{$level} {$tag}"];
        }

        if ($escapeAtSigns) {
            $value = str_replace('@', '@@', $value);
        }
        $base = "{$level} {$tag} ";
        $line = $base . $value;

        if (strlen($line) <= 240) {
            return [$line];
        }

        $lines = [substr($line, 0, 240)];
        $remaining = substr($line, 240);

        while ($remaining !== '') {
            $chunk = substr($remaining, 0, 220);
            $lines[] = ($level + 1) . ' CONC ' . $chunk;
            $remaining = substr($remaining, strlen($chunk));
        }

        return $lines;
    }

    private function eventLines(int $level, string $tag, mixed $year, mixed $month, mixed $day, mixed $place, mixed $country): array
    {
        $date = $this->gedcomDate($year, $month, $day);
        $placeText = $this->place($place, $country);

        if ($date === '' && $placeText === '') {
            return [];
        }

        $lines = $this->line($level, $tag);

        if ($date !== '') {
            $lines = array_merge($lines, $this->line($level + 1, 'DATE', $date));
        }

        if ($placeText !== '') {
            $lines = array_merge($lines, $this->line($level + 1, 'PLAC', $placeText));
        }

        return $lines;
    }

    private function gedcomDate(mixed $year, mixed $month, mixed $day): string
    {
        $year = (int) $year;
        $month = (int) $month;
        $day = (int) $day;

        if ($year <= 0) {
            return '';
        }

        if ($month <= 0 || !isset(self::MONTHS[$month])) {
            return (string) $year;
        }

        if ($day <= 0) {
            return self::MONTHS[$month] . ' ' . $year;
        }

        return $day . ' ' . self::MONTHS[$month] . ' ' . $year;
    }

    private function parseGedcomDate(string $value): array
    {
        $value = strtoupper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
        $value = preg_replace('/^(ABT|ABOUT|AFT|AFTER|BEF|BEFORE|CAL|EST)\s+/', '', $value) ?? $value;

        if (preg_match('/(\d{1,2})\s+([A-Z]{3})\s+(\d{3,4})/', $value, $matches)) {
            return [
                'day' => (int) $matches[1],
                'month' => self::MONTH_NUMBERS[$matches[2]] ?? null,
                'year' => (int) $matches[3],
            ];
        }

        if (preg_match('/([A-Z]{3})\s+(\d{3,4})/', $value, $matches)) {
            return [
                'day' => null,
                'month' => self::MONTH_NUMBERS[$matches[1]] ?? null,
                'year' => (int) $matches[2],
            ];
        }

        if (preg_match('/\b(\d{3,4})\b/', $value, $matches)) {
            return [
                'day' => null,
                'month' => null,
                'year' => (int) $matches[1],
            ];
        }

        return [];
    }

    private function parsePlace(string $value): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $value))));

        if (empty($parts)) {
            return [];
        }

        $country = count($parts) > 1 ? array_pop($parts) : null;

        return [
            'place' => implode(', ', $parts),
            'country' => $country ?: null,
        ];
    }

    private function place(mixed $place, mixed $country): string
    {
        return implode(', ', array_filter([$this->clean($place), $this->clean($country)]));
    }

    private function gedcomName(object $person): string
    {
        $names = $this->clean($person->Nombres);
        $surnames = $this->clean($person->Apellidos);

        return trim($names . ' /' . $surnames . '/');
    }

    private function splitGedcomName(string $value): array
    {
        if (preg_match('/^(.*?)\/(.*?)\/(.*)$/', trim($value), $matches)) {
            return [
                'names' => trim($matches[1] . ' ' . $matches[3]),
                'surnames' => trim($matches[2]),
            ];
        }

        $parts = preg_split('/\s+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $surname = count($parts) > 1 ? array_pop($parts) : '';

        return [
            'names' => implode(' ', $parts),
            'surnames' => $surname,
        ];
    }

    private function sex(mixed $value): string
    {
        $value = strtoupper(trim((string) $value));

        return in_array($value, ['M', 'F', 'U', 'X', 'N'], true) ? $value : 'U';
    }

    private function xref(string $prefix, int $id): string
    {
        return '@' . $prefix . $id . '@';
    }

    private function firstDocument(array $documents, string $kind): ?string
    {
        foreach ($documents as $document) {
            $type = strtoupper($document['type'] ?? '');

            if ($kind === 'passport' && str_contains($type, 'PASS')) {
                return $document['value'];
            }

            if ($kind === 'identity' && !str_contains($type, 'PASS')) {
                return $document['value'];
            }
        }

        return null;
    }

    private function dateLabel(array $event): string
    {
        return $this->gedcomDate($event['year'] ?? null, $event['month'] ?? null, $event['day'] ?? null);
    }

    private function filled(mixed $value): bool
    {
        return $this->clean($value) !== '';
    }

    private function clean(mixed $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }
}

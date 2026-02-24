<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\TFile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    // ════════════════════════════════════════════════════════════════════════
    // HELPER: Encuentra la raíz visual del árbol
    // El nodo raíz es el que NO aparece como idPadreNew ni idMadreNew
    // de ningún otro nodo — es decir, nadie lo tiene como padre/madre.
    // ════════════════════════════════════════════════════════════════════════

    private function encontrarRaiz(array $people): ?array
    {
        $ids_referenciados = [];

        foreach ($people as $persona) {
            if (!empty($persona['idPadreNew'])) {
                $ids_referenciados[] = $persona['idPadreNew'];
            }
            if (!empty($persona['idMadreNew'])) {
                $ids_referenciados[] = $persona['idMadreNew'];
            }
        }

        // El nodo raíz es el que no está referenciado por nadie
        foreach ($people as $persona) {
            if (!in_array($persona['id'], $ids_referenciados)) {
                return $persona;
            }
        }

        // Fallback: si hay inconsistencia, usar IDPersona == 1
        foreach ($people as $persona) {
            if ($persona['IDPersona'] == 1) {
                return $persona;
            }
        }

        return null;
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPER: Verifica autorización por rol
    // ════════════════════════════════════════════════════════════════════════

    private function verificarAutorizacion(string $IDCliente): bool
    {
        $roles = [
            'Traviesoevans'   => 'Travieso Evans',
            'Vargassequera'   => 'Patricia Vargas Sequera',
            'BadellLaw'       => 'Badell Law',
            'P&V-Abogados'    => 'P & V Abogados',
            'Mujica-Coto'     => 'Mujica y Coto Abogados',
            'German-Fleitas'  => 'German Fleitas',
            'Soma-Consultores' => 'Soma Consultores',
            'MG-Tours'        => 'MG Tours',
        ];

        foreach ($roles as $rol => $referido) {
            if (Auth()->user()->hasRole($rol)) {
                $autorizado = Agcliente::where('referido', 'LIKE', $referido)
                    ->where('IDCliente', 'LIKE', $IDCliente)
                    ->count();

                if ($autorizado == 0) {
                    return false;
                }
            }
        }

        return true;
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPER: Migra padres al nuevo sistema de IDs si aún no está migrado
    // ════════════════════════════════════════════════════════════════════════

    private function migrarPadresNuevoID(string $IDCliente): void
    {
        $searchCliente = Agcliente::where('IDCliente', 'LIKE', $IDCliente)
            ->where('IDPersona', 1)
            ->first();

        if (!$searchCliente) {
            return;
        }

        $padreQuery = Agcliente::where('IDCliente', 'LIKE', $IDCliente)
            ->where('IDPersona', 2)
            ->first();

        $madreQuery = Agcliente::where('IDCliente', 'LIKE', $IDCliente)
            ->where('IDPersona', 3)
            ->first();

        if ($padreQuery || $madreQuery) {
            $data = ['migradoNuevoID' => 1];

            if ($padreQuery) {
                $data['idPadreNew'] = $padreQuery->id;
                $data['IDPadre']    = 2;
            }

            if ($madreQuery) {
                $data['idMadreNew'] = $madreQuery->id;
                $data['IDMadre']    = 3;
            }

            DB::table('agclientes')
                ->where('id', $searchCliente->id)
                ->update($data);
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPER: Normaliza el array $people
    // - Asigna IDMadre/IDPadre al nodo 0 si faltan
    // - Limpia IDs negativos
    // - Migra a idPadreNew/idMadreNew si no está migrado
    // ════════════════════════════════════════════════════════════════════════

    private function normalizarPeople(array $people): array
    {
        // Asignar ids de padres al nodo 0 si faltan
        if (count($people) > 2 && !isset($people[0]['IDMadre'])) {
            if ($people[1]['Sexo'] == 'F') {
                $people[0]['IDMadre'] = $people[1]['IDPersona'];
                $people[0]['IDPadre'] = $people[2]['IDPersona'];
            } else {
                $people[0]['IDMadre'] = $people[2]['IDPersona'];
                $people[0]['IDPadre'] = $people[1]['IDPersona'];
            }
        }

        // Limpiar IDs negativos
        foreach ($people as $key => $person) {
            if ($person['IDMadre'] < 1) {
                $people[$key]['IDMadre'] = null;
            }
            if ($person['IDPadre'] < 1) {
                $people[$key]['IDPadre'] = null;
            }
        }

        // Construir mapa IDPersona → id
        $idPersonaToIdMap = [];
        foreach ($people as $item) {
            $idPersonaToIdMap[$item['IDPersona']] = $item['id'];
        }

        // Migrar a nuevo sistema de IDs si no está migrado
        foreach ($people as &$item) {
            if ($item['migradoNuevoID'] == 0) {
                $item['idPadreNew'] = isset($item['IDPadre'], $idPersonaToIdMap[$item['IDPadre']])
                    ? $idPersonaToIdMap[$item['IDPadre']]
                    : null;

                $item['idMadreNew'] = isset($item['IDMadre'], $idPersonaToIdMap[$item['IDMadre']])
                    ? $idPersonaToIdMap[$item['IDMadre']]
                    : null;
            }
        }
        unset($item);

        // Persistir migración
        foreach ($people as $person) {
            if ($person['migradoNuevoID'] == 0) {
                DB::table('agclientes')
                    ->where('id', $person['id'])
                    ->update([
                        'idPadreNew'      => $person['idPadreNew'],
                        'idMadreNew'      => $person['idMadreNew'],
                        'migradoNuevoID'  => 1,
                    ]);
            }
        }

        return $people;
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPER: Construye columnasparatabla desde una persona de inicio
    // ════════════════════════════════════════════════════════════════════════

    private function construirColumnas(array $arreglo, array $personaInicio): array
    {
        // ── Calcular generaciones desde el individuo hacia arriba ─────────
        $generaciones = [$personaInicio['id'] => 1];
        $porRevisar   = [$personaInicio];

        while (!empty($porRevisar)) {
            $siguientes = [];

            foreach ($porRevisar as $persona) {
                foreach (['idPadreNew', 'idMadreNew'] as $campo) {
                    if (empty($persona[$campo])) {
                        continue;
                    }

                    foreach ($arreglo as $candidato) {
                        if ($candidato['id'] == $persona[$campo]) {
                            if (!isset($generaciones[$candidato['id']])) {
                                $generaciones[$candidato['id']] = $generaciones[$persona['id']] + 1;
                                $siguientes[] = $candidato;
                            }
                            break;
                        }
                    }
                }
            }

            $porRevisar = $siguientes;
        }

        // ── Recalcular hasta estabilizar ──────────────────────────────────
        $cambio = true;
        while ($cambio) {
            $cambio = false;
            foreach ($arreglo as $persona) {
                if (!isset($generaciones[$persona['id']])) {
                    continue;
                }

                $genPadre  = isset($generaciones[$persona['idPadreNew']]) ? $generaciones[$persona['idPadreNew']] : 0;
                $genMadre  = isset($generaciones[$persona['idMadreNew']]) ? $generaciones[$persona['idMadreNew']] : 0;
                $genActual = max($genPadre, $genMadre) + 1;

                if ($generaciones[$persona['id']] !== $genActual) {
                    $generaciones[$persona['id']] = $genActual;
                    $cambio = true;
                }
            }
        }

        $maxGeneraciones = !empty($generaciones) ? max($generaciones) + 1 : 1;

        // ── Construir columnas ────────────────────────────────────────────
        $columnasparatabla    = [];
        $columnasparatabla[0] = [$personaInicio];
        $columnasparatabla[0][0]['showbtn'] = 2;

        for ($i = 1; $i < $maxGeneraciones; $i++) {
            foreach ($columnasparatabla[$i - 1] as $persona2) {
                if (!isset($columnasparatabla[$i])) {
                    $columnasparatabla[$i] = [];
                }

                $j = count($columnasparatabla[$i]);

                // ── Padre ─────────────────────────────────────────────────
                if (empty($persona2['idPadreNew'])) {
                    $columnasparatabla[$i][$j]['showbtn'] = match (true) {
                        $persona2['showbtn'] == 0 => 0,
                        $persona2['showbtn'] == 1 => 0,
                        default                   => 1,
                    };

                    if ($columnasparatabla[$i][$j]['showbtn'] === 1) {
                        $columnasparatabla[$i][$j]['showbtnsex'] = 'm';
                        $columnasparatabla[$i][$j]['id_hijo']    = $persona2['id'];
                    }
                } else {
                    foreach ($arreglo as $candidato) {
                        if ($candidato['id'] == $persona2['idPadreNew']) {
                            $columnasparatabla[$i][$j]            = $candidato;
                            $columnasparatabla[$i][$j]['showbtn'] = 2;
                            break;
                        }
                    }
                }

                $j++;

                // ── Madre ─────────────────────────────────────────────────
                if (empty($persona2['idMadreNew'])) {
                    $columnasparatabla[$i][$j]['showbtn'] = match (true) {
                        $persona2['showbtn'] == 0 => 0,
                        $persona2['showbtn'] == 1 => 0,
                        default                   => 1,
                    };

                    if ($columnasparatabla[$i][$j]['showbtn'] === 1) {
                        $columnasparatabla[$i][$j]['showbtnsex'] = 'f';
                        $columnasparatabla[$i][$j]['id_hijo']    = $persona2['id'];
                    }
                } else {
                    foreach ($arreglo as $candidato) {
                        if ($candidato['id'] == $persona2['idMadreNew']) {
                            $columnasparatabla[$i][$j]            = $candidato;
                            $columnasparatabla[$i][$j]['showbtn'] = 2;
                            break;
                        }
                    }
                }
            }
        }

        return $columnasparatabla;
    }

    // ════════════════════════════════════════════════════════════════════════
    // HELPER: Genera los parentescos
    // ════════════════════════════════════════════════════════════════════════

    private function generarParentescos(array $columnasparatabla): array
    {
        $parentescos_post_padres = [
            "Abuel", "Bisabuel", "Tatarabuel", "Trastatarabuel",
            "Retatarabuel", "Sestarabuel", "Setatarabuel", "Octatarabuel",
            "Nonatarabuel", "Decatarabuel", "Undecatarabuel", "Duodecatarabuel",
            "Trececatarabuel", "Catorcatarabuel", "Quincecatarabuel",
            "Deciseiscatarabuel", "Decisietecatarabuel", "Deciochocatarabuel",
            "Decinuevecatarabuel", "Vigecatarabuel", "Vigecimoprimocatarabuel",
            "Vigecimosegundocatarabuel", "Vigecimotercercatarabuel",
            "Vigecimocuartocatarabuel", "Vigecimoquintocatarabuel",
            "Vigecimosextocatarabuel", "Vigecimoseptimocatarabuel",
            "Vigecimooctavocatarabuel", "Vigecimonovenocatarabuel",
            "Trigecatarabuel", "Trigecimoprimocatarabuel",
            "Trigecimosegundocatarabuel", "Trigecimotercercatarabuel",
            "Trigecimocuartocatarabuel", "Trigecimoquintocatarabuel",
            "Trigecimosextocatarabuel", "Trigecimoseptimocatarabuel",
            "Trigecimooctavocatarabuel", "Trigecimonovenocatarabuel",
            "Cuarentacatarabuel", "Cuarentaprimocatarabuel",
            "Cuarentasegundocatarabuel", "Cuarentatercercatarabuel",
        ];

        $parentescos = [];
        $prepar      = 4;

        foreach ($parentescos_post_padres as $key => $parentesco) {
            if ($key <= count($columnasparatabla)) {
                $parentescos[$key] = [];

                for ($i = 0; $i < $prepar; $i++) {
                    $textparentesco      = $parentesco . ($i % 2 == 0 ? 'o' : 'a');
                    $text                = $this->generarTexto($i, $key);
                    $parentescos[$key][] = $textparentesco . ' ' . $text;
                }

                $prepar *= 2;
            }
        }

        return $parentescos;
    }

    private function generarTexto(int $i, int $key): string
    {
        $text          = '';
        $multiplicador = 4;

        for ($j = 1; $j <= $key; $j++) {
            $text         .= (($i % $multiplicador) < ($multiplicador / 2) ? 'P ' : 'M ');
            $multiplicador *= 2;
        }

        $text .= ($i < 2 * ($key + 1) ? 'P' : 'M');

        return $text;
    }

    // ════════════════════════════════════════════════════════════════════════
    // tree() — Vista principal del árbol desde el cliente
    // ════════════════════════════════════════════════════════════════════════

    public function tree(string $IDCliente)
    {
        if (!$this->verificarAutorizacion($IDCliente)) {
            return view('crud.agclientes.index');
        }

        $existe = Agcliente::where('IDCliente', 'LIKE', $IDCliente)
            ->where('IDPersona', 1)
            ->get();

        if (!$existe->count()) {
            return redirect()->route('crud.agclientes.index')
                ->with('info', 'IDCliente: ' . $IDCliente . ' no encontrado');
        }

        $this->migrarPadresNuevoID($IDCliente);

        $people = json_decode(
            json_encode(Agcliente::where('IDCliente', $IDCliente)->get()),
            true
        );

        $people = $this->normalizarPeople($people);

        // ── Encontrar raíz: nodo que nadie referencia como padre/madre ────
        $personaInicio = $this->encontrarRaiz($people);

        if ($personaInicio === null) {
            return redirect()->route('crud.agclientes.index')
                ->with('info', 'IDCliente: ' . $IDCliente . ' — no se pudo determinar el nodo raíz');
        }

        $columnasparatabla = $this->construirColumnas($people, $personaInicio);

        // ── Actualizar PersonaIDNew ───────────────────────────────────────
        foreach ($columnasparatabla as $key => $columna) {
            foreach ($columna as $key2 => $persona) {
                if (
                    $persona['showbtn'] == 2 &&
                    (is_null($persona['PersonaIDNew']) || $persona['PersonaIDNew'] == 'null')
                ) {
                    DB::table('agclientes')
                        ->where('id', $persona['id'])
                        ->update(['PersonaIDNew' => $key2]);

                    $columnasparatabla[$key][$key2]['PersonaIDNew'] = $key2;
                }
            }
        }

        $parentescos = $this->generarParentescos($columnasparatabla);

        // ── Recortar a 5 generaciones para la vista ───────────────────────
        $temparr = [];
        foreach ($columnasparatabla as $key => $columna) {
            if ($key < 5) {
                $temparr[] = $columna;
            }
        }
        $columnasparatabla = $temparr;

        $tipoarchivos  = TFile::all();
        $cliente       = json_decode(json_encode(User::where('passport', $IDCliente)->get()), true);
        $user          = User::where('passport', $IDCliente)->first();
        $generacionBase = 0;
        $checkBtn       = 'no';
        $parentnumber   = 0;

        $htmlGenerado = view('arboles.vistatree', compact(
            'generacionBase', 'columnasparatabla', 'parentescos', 'checkBtn', 'parentnumber'
        ))->render();

        return view('arboles.tree', compact(
            'generacionBase', 'user', 'IDCliente', 'people',
            'columnasparatabla', 'cliente', 'tipoarchivos',
            'parentescos', 'htmlGenerado', 'checkBtn', 'parentnumber'
        ));
    }

    // ════════════════════════════════════════════════════════════════════════
    // treepart() — Vista del árbol desde un ancestro específico
    // ════════════════════════════════════════════════════════════════════════

    public function treepart(string $IDCliente, int $idToCheck, int $gentocheck, int $parenttocheck)
    {
        if (!$this->verificarAutorizacion($IDCliente)) {
            return view('crud.agclientes.index');
        }

        $existe = Agcliente::where('IDCliente', 'LIKE', $IDCliente)
            ->where('IDPersona', 1)
            ->get();

        if (!$existe->count()) {
            return redirect()->route('crud.agclientes.index')
                ->with('info', 'IDCliente: ' . $IDCliente . ' no encontrado');
        }

        $this->migrarPadresNuevoID($IDCliente);

        $people = json_decode(
            json_encode(Agcliente::where('IDCliente', $IDCliente)->get()),
            true
        );

        $people = $this->normalizarPeople($people);

        // ── Encontrar la persona de inicio por idToCheck ──────────────────
        $personaInicio = null;
        foreach ($people as $persona) {
            if ($persona['id'] == $idToCheck) {
                $personaInicio = $persona;
                break;
            }
        }

        if ($personaInicio === null) {
            return redirect()->route('crud.agclientes.index')
                ->with('info', 'No se encontró la persona con id: ' . $idToCheck);
        }

        $columnasparatabla = $this->construirColumnas($people, $personaInicio);

        $parentescos = $this->generarParentescos($columnasparatabla);

        // ── Recortar a 5 generaciones para la vista ───────────────────────
        $temparr = [];
        foreach ($columnasparatabla as $key => $columna) {
            if ($key < 5) {
                $temparr[] = $columna;
            }
        }
        $columnasparatabla = $temparr;

        $tipoarchivos   = TFile::all();
        $cliente        = json_decode(json_encode(User::where('passport', $IDCliente)->get()), true);
        $user           = User::where('passport', $IDCliente)->first();
        $generacionBase = $gentocheck;
        $checkBtn       = 'si';
        $parentnumber   = $parenttocheck;

        $htmlGenerado = view('arboles.vistatree', compact(
            'generacionBase', 'columnasparatabla', 'parentescos', 'checkBtn', 'parentnumber'
        ))->render();

        return view('arboles.tree', compact(
            'generacionBase', 'user', 'IDCliente', 'people',
            'columnasparatabla', 'cliente', 'tipoarchivos',
            'parentescos', 'htmlGenerado', 'checkBtn', 'parentnumber'
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\TFile;
use App\Models\User;
use App\Services\GenealogyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    private const VISIBLE_GENERATIONS = 6;

    public function __construct(private GenealogyService $genealogyService)
    {
    }

    private function verificarAutorizacion(string $IDCliente): bool
    {
        $roles = [
            'Traviesoevans' => 'Travieso Evans',
            'Vargassequera' => 'Patricia Vargas Sequera',
            'BadellLaw' => 'Badell Law',
            'P&V-Abogados' => 'P & V Abogados',
            'Mujica-Coto' => 'Mujica y Coto Abogados',
            'German-Fleitas' => 'German Fleitas',
            'Soma-Consultores' => 'Soma Consultores',
            'MG-Tours' => 'MG Tours',
        ];

        foreach ($roles as $rol => $referido) {
            if (!auth()->user()->hasRole($rol)) {
                continue;
            }

            $autorizado = Agcliente::where('referido', 'LIKE', $referido)
                ->where('IDCliente', 'LIKE', $IDCliente)
                ->count();

            if ($autorizado === 0) {
                return false;
            }
        }

        return true;
    }

    public function tree(string $IDCliente)
    {
        return $this->renderTree($IDCliente);
    }

    public function treepart(string $IDCliente, int $idToCheck, int $gentocheck, int $parenttocheck)
    {
        return $this->renderTree($IDCliente, $idToCheck, $gentocheck, $parenttocheck, 'si');
    }

    public function branch(Request $request, string $IDCliente, int $idToCheck, int $gentocheck, int $parenttocheck): JsonResponse
    {
        if (!$this->verificarAutorizacion($IDCliente)) {
            abort(403);
        }

        $treeData = $this->genealogyService->buildTree(
            $IDCliente,
            $idToCheck,
            self::VISIBLE_GENERATIONS,
            $gentocheck,
            $parenttocheck,
            true,
            $request->query('lineColor')
        );

        if (empty($treeData['columnasparatabla'])) {
            abort(404);
        }

        return response()->json([
            'columnasparatabla' => $treeData['columnasparatabla'],
            'stats' => $treeData['stats'],
            'warnings' => $treeData['warnings'],
            'root' => [
                'id' => $treeData['root']['id'] ?? null,
                'generation' => $gentocheck,
                'slot' => $parenttocheck,
                'lineColor' => $request->query('lineColor'),
            ],
        ]);
    }

    public function updateLineColor(Request $request, string $IDCliente, int $id): JsonResponse
    {
        if (!$this->verificarAutorizacion($IDCliente)) {
            abort(403);
        }

        if (!auth()->user()->hasRole(['Administrador', 'Genealogista', 'Documentalista'])) {
            abort(403);
        }

        $validated = $request->validate([
            'side' => 'required|in:padre,madre',
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $person = Agcliente::where('IDCliente', $IDCliente)->findOrFail($id);
        $column = $validated['side'] === 'padre' ? 'colorLineaPadre' : 'colorLineaMadre';
        $person->{$column} = strtoupper($validated['color']);
        $person->save();

        $this->genealogyService->forgetProcessedTree($IDCliente);

        return response()->json([
            'status' => 'ok',
            'id' => $person->id,
            'side' => $validated['side'],
            'color' => $person->{$column},
        ]);
    }

    private function renderTree(
        string $IDCliente,
        ?int $rootId = null,
        int $generacionBase = 0,
        int $parentnumber = 0,
        string $checkBtn = 'no'
    ) {
        if (!$this->verificarAutorizacion($IDCliente)) {
            return view('crud.agclientes.index');
        }

        $existe = Agcliente::where('IDCliente', 'LIKE', $IDCliente)
            ->where('IDPersona', 1)
            ->exists();

        if (!$existe) {
            return redirect()->route('crud.agclientes.index')
                ->with('info', 'IDCliente: ' . $IDCliente . ' no encontrado');
        }

        $treeData = $this->genealogyService->buildTree(
            $IDCliente,
            $rootId,
            self::VISIBLE_GENERATIONS,
            $generacionBase,
            $parentnumber,
            true
        );

        if (empty($treeData['columnasparatabla'])) {
            $message = $rootId
                ? 'No se encontro la persona con id: ' . $rootId
                : 'IDCliente: ' . $IDCliente . ' - no se pudo determinar el nodo raiz';

            return redirect()->route('crud.agclientes.index')->with('info', $message);
        }

        $columnasparatabla = $treeData['columnasparatabla'];
        $people = $treeData['people'];
        $parentescos = $treeData['parentescos'];
        $treeWarnings = $treeData['warnings'];
        $treeStats = $treeData['stats'];
        $tipoarchivos = TFile::all();
        $cliente = json_decode(json_encode(User::where('passport', $IDCliente)->get()), true);
        $user = User::where('passport', $IDCliente)->first();
        $htmlGenerado = '';

        return view('arboles.tree', compact(
            'generacionBase',
            'user',
            'IDCliente',
            'people',
            'columnasparatabla',
            'cliente',
            'tipoarchivos',
            'parentescos',
            'htmlGenerado',
            'checkBtn',
            'parentnumber',
            'treeWarnings',
            'treeStats'
        ));
    }
}

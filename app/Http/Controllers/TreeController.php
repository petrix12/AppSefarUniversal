<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\DocumentRequest;
use App\Models\File as ClientFile;
use App\Models\GenealogyUnion;
use App\Models\TFile;
use App\Models\User;
use App\Services\GenealogyService;
use App\Services\GenealogyDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TreeController extends Controller
{
    private const VISIBLE_GENERATIONS = 6;

    public function __construct(
        private GenealogyService $genealogyService,
        private GenealogyDocumentService $documents,
    )
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

    public function personDetail(string $IDCliente, int $id): JsonResponse
    {
        if (!$this->verificarAutorizacion($IDCliente)) {
            abort(403);
        }

        if (!auth()->user()->hasRole(['Administrador', 'Genealogista', 'Documentalista'])) {
            abort(403);
        }

        $person = Agcliente::where('IDCliente', $IDCliente)->findOrFail($id);
        $client = User::where('passport', $IDCliente)->first();

        return response()->json([
            'person' => $person,
            'files' => $this->filesForPerson($person),
            'tree_files' => $this->unassignedFilesForClient($IDCliente),
            'document_kinds' => GenealogyDocumentService::kinds(),
            // The checklist is intentionally derived instead of creating requests
            // automatically. Internal users explicitly decide which records to ask for.
            'allowed_document_kinds' => GenealogyDocumentService::allowedKindsForPerson($person),
            'document_requests' => $this->documentRequestsForPerson($client, $person),
            'reusable_files' => [],
            'possible_spouses' => Agcliente::where('IDCliente', $IDCliente)
                ->whereKeyNot($person->id)
                ->orderBy('Nombres')
                ->get(['id', 'Nombres', 'Apellidos'])
                ->map(fn (Agcliente $candidate) => [
                    'id' => $candidate->id,
                    'name' => trim($candidate->Nombres . ' ' . $candidate->Apellidos) ?: 'Sin nombre',
                ])
                ->values(),
        ]);
    }

    /** Documents shown from the client-facing tree. Never return CRM/internal files here. */
    public function clientPersonDetail(int $id): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user && $user->hasRole('Cliente'), 403);

        $person = Agcliente::where('IDCliente', $user->passport)->findOrFail($id);

        return response()->json([
            'person' => $person,
            'files' => $this->filesForPerson($person, true),
            'tree_files' => $this->unassignedFilesForClient($user->passport, true),
            'document_kinds' => GenealogyDocumentService::kinds(),
            'allowed_document_kinds' => GenealogyDocumentService::allowedKindsForPerson($person),
            'document_requests' => $this->documentRequestsForPerson($user, $person),
            // Only documents the customer uploaded through the application can be
            // re-used. HubSpot and Teamleader files never enter this collection.
            'reusable_files' => $this->reusableFilesForClient($user),
            'possible_spouses' => [],
        ]);
    }

    private function filesForPerson(Agcliente $person, bool $clientSafe = false): array
    {
        return $this->documents->filesForPerson($person, $clientSafe);
    }

    private function unassignedFilesForClient(string $IDCliente, bool $clientSafe = false): array
    {
        $query = ClientFile::where('IDCliente', $IDCliente)
            ->where(function ($query) {
                $query->whereNull('IDPersonaNew')
                    ->orWhere('IDPersonaNew', 0)
                    ->orWhere('IDPersonaNew', '');
            })
            ->where(function ($query) {
                $query->whereNull('IDPersona')
                    ->orWhere('IDPersona', 0)
                    ->orWhere('IDPersona', '');
            });

        if ($clientSafe) {
            $query->where('client_visible', true)
                ->whereIn('document_kind', array_keys(GenealogyDocumentService::kinds()));
        }

        return $query->orderBy('tipo')->orderBy('file')->get()
            ->map(fn (ClientFile $file): array => $this->formatTreeFile($file))
            ->values()
            ->all();
    }

    /**
     * Return the latest request for each document slot of a person. Marriage
     * requests are also returned from the other spouse's node through the union.
     */
    private function documentRequestsForPerson(?User $user, Agcliente $person): array
    {
        if (! $user) {
            return [];
        }

        $unionIds = GenealogyUnion::query()
            ->where('IDCliente', $person->IDCliente)
            ->where(function ($query) use ($person) {
                $query->where('spouse_one_id', $person->id)
                    ->orWhere('spouse_two_id', $person->id);
            })
            ->pluck('id');

        return DocumentRequest::query()
            ->where('user_id', $user->id)
            ->where('document_type', 'genealogico')
            ->whereIn('document_kind', array_keys(GenealogyDocumentService::kinds()))
            ->where(function ($query) use ($person, $unionIds) {
                $query->where('person_id', $person->id);

                if ($unionIds->isNotEmpty()) {
                    $query->orWhereIn('genealogy_union_id', $unionIds);
                }
            })
            ->latest('id')
            ->get()
            ->unique('document_kind')
            ->map(fn (DocumentRequest $documentRequest): array => [
                'id' => $documentRequest->id,
                'document_kind' => $documentRequest->document_kind,
                'document_label' => GenealogyDocumentService::label($documentRequest->document_kind),
                'status' => $documentRequest->status,
                'person_id' => $documentRequest->person_id,
                'genealogy_union_id' => $documentRequest->genealogy_union_id,
            ])
            ->values()
            ->all();
    }

    /** Files from the client-owned library that may be attached to a request. */
    private function reusableFilesForClient(User $user): array
    {
        return $this->documents->reusableForClient($user)
            ->get()
            ->map(fn (ClientFile $file): array => $this->formatTreeFile($file))
            ->values()
            ->all();
    }

    private function formatTreeFile(ClientFile $file): array
    {
        return $this->documents->present($file);
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
        $documentCatalog = $this->documents->catalog($IDCliente);
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
            ,'documentCatalog'
        ));
    }
}

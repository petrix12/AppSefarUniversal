<?php

namespace App\Http\Controllers;

use App\Models\FamilyGroup;
use App\Models\FamilyGroupMember;
use App\Services\FamilyGroupService;
use Illuminate\Http\Request;
use RealRashid\SweetAlert\Facades\Alert;

class FamilyGroupController extends Controller
{
    public function __construct(private FamilyGroupService $familyGroupService)
    {
    }

    public function index()
    {
        $request = request();
        $search = trim((string) $request->query('search', ''));

        $groups = FamilyGroup::withCount('members')
            ->when($search !== '', function ($query) use ($search) {
                $term = '%' . $search . '%';
                $query->where('name', 'LIKE', $term)
                    ->orWhere('primary_id_cliente', 'LIKE', $term)
                    ->orWhere('match_key', 'LIKE', $term);
            })
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('family_groups.index', compact('groups', 'search'));
    }

    public function create()
    {
        return view('family_groups.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'IDCliente' => 'required|string|max:175',
            'name' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $passport = trim($validated['IDCliente']);

        if (!$this->familyGroupService->clientExists($passport)) {
            return back()
                ->withInput()
                ->withErrors(['IDCliente' => 'No encontre un cliente o arbol con ese IDCliente.']);
        }

        $group = $this->familyGroupService->createCalculatedGroup(
            $passport,
            $validated['name'] ?? null,
            $validated['notes'] ?? null
        );

        Alert::success('Grupo familiar creado', 'Se creo el grupo y se calcularon coincidencias iniciales.');

        return redirect()->route('family-groups.show', $group);
    }

    public function show(Request $request, FamilyGroup $familyGroup)
    {
        $familyGroup->load(['members.user', 'members.anchorPerson']);
        $candidateSearch = trim((string) $request->query('candidate_search', ''));
        $candidates = $this->familyGroupService->candidatesForGroup($familyGroup, $candidateSearch ?: null);

        return view('family_groups.show', compact('familyGroup', 'candidates', 'candidateSearch'));
    }

    public function destroy(FamilyGroup $familyGroup)
    {
        $name = $familyGroup->name;
        $familyGroup->delete();

        Alert::info('Grupo familiar eliminado', 'Se elimino el grupo familiar: ' . $name);

        return redirect()->route('family-groups.index');
    }

    public function recalculate(FamilyGroup $familyGroup)
    {
        $added = $this->familyGroupService->recalculateMembers($familyGroup);

        Alert::success('Coincidencias recalculadas', "Se agregaron {$added} cliente(s) al grupo.");

        return redirect()->route('family-groups.show', $familyGroup);
    }

    public function addMember(Request $request, FamilyGroup $familyGroup)
    {
        $validated = $request->validate([
            'IDCliente' => 'required|string|max:175',
        ]);

        $passport = trim($validated['IDCliente']);

        if (!$this->familyGroupService->clientExists($passport)) {
            return back()
                ->withInput()
                ->withErrors(['IDCliente' => 'No encontre un cliente o arbol con ese IDCliente.']);
        }

        $this->familyGroupService->addClientToGroup($familyGroup, $passport, 'manual');

        Alert::success('Cliente agregado', 'El cliente se agrego al grupo familiar.');

        return redirect()->route('family-groups.show', $familyGroup);
    }

    public function removeMember(FamilyGroup $familyGroup, FamilyGroupMember $member)
    {
        abort_unless((int) $member->family_group_id === (int) $familyGroup->id, 404);

        $name = $member->display_name ?: $member->IDCliente;
        $member->delete();

        Alert::info('Cliente removido', 'Se saco del grupo familiar a: ' . $name);

        return redirect()->route('family-groups.show', $familyGroup);
    }
}

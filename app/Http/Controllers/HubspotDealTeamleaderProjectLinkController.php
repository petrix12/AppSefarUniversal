<?php

namespace App\Http\Controllers;

use App\Models\Negocio;
use App\Models\TlProject;
use App\Models\User;
use App\Services\HubspotDealTeamleaderProjectLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HubspotDealTeamleaderProjectLinkController extends Controller
{
    public function __construct(private readonly HubspotDealTeamleaderProjectLinkService $links)
    {
    }

    public function detect(User $user): RedirectResponse
    {
        $result = $this->links->detectAndLink($user, auth()->id());

        if ($result['unavailable'] ?? false) {
            return back()->with('error', 'El histórico de Teamleader todavía no está disponible localmente.');
        }

        return back()->with('success', "Se asociaron {$result['linked']} trato(s) por coincidencia exacta o similar de alta confianza. Quedan {$result['review']} para revisión.");
    }

    public function store(Request $request, User $user, Negocio $negocio): RedirectResponse
    {
        $data = $request->validate([
            'teamleader_project_id' => ['required', 'string', 'exists:tl_projects,id'],
        ]);

        abort_unless((int) $negocio->user_id === (int) $user->id, 404);
        $project = TlProject::findOrFail($data['teamleader_project_id']);

        $this->links->link(
            $user,
            $negocio,
            $project,
            'manual',
            100,
            ['selected_by' => 'internal_user'],
            auth()->id(),
        );

        return back()->with('success', 'Trato de HubSpot asociado al proyecto histórico de Teamleader.');
    }

    public function destroy(User $user, Negocio $negocio): RedirectResponse
    {
        abort_unless((int) $negocio->user_id === (int) $user->id, 404);
        $this->links->unlink($user, $negocio);

        return back()->with('success', 'Asociación eliminada. El proyecto histórico de Teamleader no fue modificado.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;

class AdminIntegrationTokenController extends Controller
{
    private const API_ABILITIES = [
        'read' => 'Lectura',
        'create' => 'Crear',
        'update' => 'Actualizar',
        'delete' => 'Eliminar',
    ];

    public function apiTokens()
    {
        return view('admin.integrations.api-tokens', [
            'tokens' => $this->tokensQuery(false)->paginate(20),
            'users' => $this->internalUsers(),
            'abilities' => self::API_ABILITIES,
        ]);
    }

    public function mcp()
    {
        return view('admin.integrations.mcp', [
            'tokens' => $this->tokensQuery(true)->paginate(20),
            'users' => $this->internalUsers(),
        ]);
    }

    public function storeApiToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in(array_keys(self::API_ABILITIES))],
        ]);

        $user = $this->findInternalUser((int) $data['user_id']);
        $abilities = array_values(array_unique($data['abilities']));
        $token = $user->createToken($data['name'], $abilities);

        return back()
            ->with('success', 'Token API creado correctamente.')
            ->with('created_token', $token->plainTextToken)
            ->with('created_token_name', $data['name']);
    }

    public function storeMcpToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['nullable', 'string', 'max:80'],
        ]);

        $user = $this->findInternalUser((int) $data['user_id']);
        $name = trim((string) ($data['name'] ?? ''));
        $tokenName = $name !== '' ? $name : 'MCP privado - ' . now()->format('Y-m-d H:i');
        $token = $user->createToken($tokenName, ['mcp:read']);

        return back()
            ->with('success', 'Token MCP creado correctamente.')
            ->with('created_token', $token->plainTextToken)
            ->with('created_token_name', $tokenName);
    }

    public function destroy(PersonalAccessToken $token): RedirectResponse
    {
        $token->delete();

        return back()->with('success', 'Token revocado correctamente.');
    }

    private function tokensQuery(bool $mcp)
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->when(
                $mcp,
                fn ($query) => $query->where('abilities', 'like', '%"mcp:read"%'),
                fn ($query) => $query->where(function ($nested) {
                    $nested->whereNull('abilities')
                        ->orWhere('abilities', 'not like', '%"mcp:read"%');
                })
            )
            ->with('tokenable')
            ->latest();
    }

    private function internalUsers()
    {
        return User::query()
            ->select(['id', 'name', 'email'])
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Cliente'))
            ->orderBy('name')
            ->get();
    }

    private function findInternalUser(int $userId): User
    {
        $user = User::with('roles')->findOrFail($userId);

        abort_if($user->hasRole('Cliente'), 422, 'No se pueden crear tokens para usuarios con rol Cliente.');

        return $user;
    }
}

<?php

namespace App\Services;

use App\Models\User;

class ClientTreeAccess
{
    public function authorize(User $user, ?string $treeId): void
    {
        if (! $user->hasRole('Cliente')) {
            return;
        }

        $assignedTree = app(GenealogyTreeResolver::class)->resolveFor($user);
        abort_unless($assignedTree !== null && $assignedTree === trim((string) $treeId), 403,
            'Solo puedes acceder a tu propio árbol genealógico y sus documentos.');
    }
}

<?php

namespace App\Services;

use App\Models\User;

class ClientTreeAccess
{
    public function isClientScoped(User $user): bool
    {
        return $user->hasRole('Cliente') && ! $user->hasRole('Genealogista');
    }

    public function authorize(User $user, ?string $treeId): void
    {
        if (! $this->isClientScoped($user)) {
            return;
        }

        $assignedTree = app(GenealogyTreeResolver::class)->resolveFor($user);
        abort_unless($assignedTree !== null && $assignedTree === trim((string) $treeId), 403,
            'Solo puedes acceder a tu propio árbol genealógico y sus documentos.');
    }
}

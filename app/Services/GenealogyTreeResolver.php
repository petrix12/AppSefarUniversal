<?php

namespace App\Services;

use App\Models\Agcliente;
use App\Models\User;

class GenealogyTreeResolver
{
    /**
     * Resolve the tree assigned to a user. The passport remains the canonical
     * association; the secondary identifier is only considered as a fallback.
     */
    public function resolveFor(User $user): ?string
    {
        return $this->resolve($user->passport, $user->genealogy_tree_id);
    }

    public function resolve(?string $passport, ?string $secondaryTreeId = null): ?string
    {
        foreach ([$passport, $secondaryTreeId] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '' && $this->treeExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function treeExists(string $treeId): bool
    {
        return Agcliente::query()
            ->where('IDCliente', trim($treeId))
            ->where('IDPersona', 1)
            ->exists();
    }
}

<?php

namespace App\Services;

use App\Models\Agcliente;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class GenealogyTreeResolver
{
    /**
     * Resolve the tree assigned to a user. The passport remains the canonical
     * association, followed by the assigned identifier and previous passports.
     */
    public function resolveFor(User $user): ?string
    {
        $treeId = $this->resolve($user->passport, $user->genealogy_tree_id);
        if ($treeId !== null || ! Schema::hasTable('user_passport_histories')) {
            return $treeId;
        }

        foreach ($user->passportHistories()->pluck('passport')->unique() as $passport) {
            if ($this->treeExists($passport)) {
                return $passport;
            }
        }

        return null;
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

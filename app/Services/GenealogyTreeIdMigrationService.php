<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GenealogyTreeIdMigrationService
{
    /**
     * Shows the exact local records that would move from one tree identifier
     * to another, without modifying any data.
     */
    public function preview(string $oldId, string $newId): array
    {
        [$oldId, $newId] = $this->normalizeIdentifiers($oldId, $newId);

        if (! Schema::hasTable('agclientes')) {
            throw new DomainException('La tabla de árboles genealógicos no está disponible.');
        }

        $treePeople = DB::table('agclientes')->where('IDCliente', $oldId)->count();
        $hasRoot = DB::table('agclientes')
            ->where('IDCliente', $oldId)
            ->where('IDPersona', 1)
            ->exists();

        if ($treePeople === 0) {
            throw new DomainException("No existe un árbol con el IDCliente {$oldId}.");
        }

        if (! $hasRoot) {
            throw new DomainException("El IDCliente {$oldId} no tiene una persona raíz y no se puede migrar como árbol completo.");
        }

        $counts = [
            'usuarios' => $this->countRecords('users', 'passport', $oldId),
            'personas_arbol' => $treePeople,
            'archivos' => $this->countRecords('files', 'IDCliente', $oldId),
            'familias' => $this->countRecords('families', 'IDCliente', $oldId),
            'grupos_familiares' => $this->countRecords('family_groups', 'primary_id_cliente', $oldId),
            'miembros_grupo_familiar' => $this->countRecords('family_group_members', 'IDCliente', $oldId),
            'enlaces_secundarios_de_arbol' => $this->countRecords('user_genealogy_tree_links', 'tree_id', $oldId),
        ];

        $conflicts = [];
        if (DB::table('agclientes')->where('IDCliente', $newId)->exists()) {
            $conflicts[] = "El IDCliente destino {$newId} ya tiene registros de árbol.";
        }

        if ($counts['usuarios'] > 0 && $this->countRecords('users', 'passport', $newId) > 0) {
            $conflicts[] = "El pasaporte destino {$newId} ya pertenece a otro usuario.";
        }

        $conflicts = array_merge($conflicts, $this->familyConflicts($oldId, $newId));
        $conflicts = array_merge($conflicts, $this->familyGroupMemberConflicts($oldId, $newId));

        return [
            'old_id' => $oldId,
            'new_id' => $newId,
            'counts' => $counts,
            'conflicts' => $conflicts,
            'can_migrate' => $conflicts === [],
        ];
    }

    /**
     * Moves every local reference that identifies the complete genealogy tree.
     * The preview is calculated again inside the transaction to keep the
     * confirmation screen from becoming stale.
     */
    public function migrate(string $oldId, string $newId): array
    {
        [$oldId, $newId] = $this->normalizeIdentifiers($oldId, $newId);

        return DB::transaction(function () use ($oldId, $newId): array {
            $this->lockRelevantRecords($oldId, $newId);
            $preview = $this->preview($oldId, $newId);

            if (! $preview['can_migrate']) {
                throw new DomainException('No se migró el árbol porque existen conflictos: '.implode(' ', $preview['conflicts']));
            }

            $updated = [
                'personas_arbol' => DB::table('agclientes')->where('IDCliente', $oldId)->update(['IDCliente' => $newId]),
                'archivos' => $this->replaceIdentifier('files', 'IDCliente', $oldId, $newId),
                'grupos_familiares' => $this->replaceIdentifier('family_groups', 'primary_id_cliente', $oldId, $newId),
                'miembros_grupo_familiar' => $this->replaceIdentifier('family_group_members', 'IDCliente', $oldId, $newId),
                'enlaces_secundarios_de_arbol' => $this->replaceIdentifier('user_genealogy_tree_links', 'tree_id', $oldId, $newId),
            ];

            $updated['familias'] = $this->migrateFamilies($oldId, $newId);
            $updated['usuarios'] = $this->migrateUsers($oldId, $newId);

            return [
                'old_id' => $oldId,
                'new_id' => $newId,
                'updated' => $updated,
            ];
        });
    }

    private function normalizeIdentifiers(string $oldId, string $newId): array
    {
        $oldId = trim($oldId);
        $newId = trim($newId);

        if ($oldId === '' || $newId === '') {
            throw new DomainException('Debes indicar el IDCliente actual y el IDCliente nuevo.');
        }

        if ($oldId === $newId) {
            throw new DomainException('El IDCliente actual y el nuevo deben ser distintos.');
        }

        return [$oldId, $newId];
    }

    private function countRecords(string $table, string $column, string $value): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->where($column, $value)->count();
    }

    private function replaceIdentifier(string $table, string $column, string $oldId, string $newId): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return DB::table($table)->where($column, $oldId)->update([$column => $newId]);
    }

    private function familyConflicts(string $oldId, string $newId): array
    {
        if (! Schema::hasTable('families')) {
            return [];
        }

        $conflicts = [];
        foreach (DB::table('families')->where('IDCliente', $oldId)->get(['id', 'IDFamiliar']) as $family) {
            $targetCombinedId = $newId.'-'.$family->IDFamiliar;

            if (DB::table('families')->where('IDCombinado', $targetCombinedId)->where('id', '!=', $family->id)->exists()) {
                $conflicts[] = "La relación familiar {$targetCombinedId} ya existe.";
            }
        }

        return $conflicts;
    }

    private function familyGroupMemberConflicts(string $oldId, string $newId): array
    {
        if (! Schema::hasTable('family_group_members')) {
            return [];
        }

        $conflicts = [];
        foreach (DB::table('family_group_members')->where('IDCliente', $oldId)->get(['id', 'family_group_id']) as $member) {
            if (DB::table('family_group_members')
                ->where('family_group_id', $member->family_group_id)
                ->where('IDCliente', $newId)
                ->where('id', '!=', $member->id)
                ->exists()) {
                $conflicts[] = "El grupo familiar #{$member->family_group_id} ya contiene el IDCliente destino.";
            }
        }

        return $conflicts;
    }

    private function migrateFamilies(string $oldId, string $newId): int
    {
        if (! Schema::hasTable('families')) {
            return 0;
        }

        $updated = 0;
        foreach (DB::table('families')->where('IDCliente', $oldId)->get(['id', 'IDFamiliar']) as $family) {
            $updated += DB::table('families')->where('id', $family->id)->update([
                'IDCliente' => $newId,
                'IDCombinado' => $newId.'-'.$family->IDFamiliar,
            ]);
        }

        return $updated;
    }

    private function migrateUsers(string $oldId, string $newId): int
    {
        if (! Schema::hasTable('users')) {
            return 0;
        }

        $updated = 0;
        foreach (User::query()->where('passport', $oldId)->lockForUpdate()->get() as $user) {
            $user->passport = $newId;
            $user->save();
            $updated++;
        }

        return $updated;
    }

    private function lockRelevantRecords(string $oldId, string $newId): void
    {
        foreach ([
            ['agclientes', 'IDCliente'],
            ['users', 'passport'],
            ['files', 'IDCliente'],
            ['families', 'IDCliente'],
            ['family_groups', 'primary_id_cliente'],
            ['family_group_members', 'IDCliente'],
            ['user_genealogy_tree_links', 'tree_id'],
        ] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->whereIn($column, [$oldId, $newId])->lockForUpdate()->get();
            }
        }
    }
}

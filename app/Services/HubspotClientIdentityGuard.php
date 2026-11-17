<?php

namespace App\Services;

use App\Exceptions\CosIdentityConflictException;
use App\Models\User;
use Throwable;

class HubspotClientIdentityGuard
{
    public function assertForUser(User $user, array $contact, ?Throwable $previous = null): void
    {
        $conflicts = [];
        $properties = $contact['properties'] ?? [];
        // A merged HubSpot ID can resolve to another canonical ID. Check both,
        // plus its known aliases, without relinking any local account.
        $ids = array_values(array_unique(array_filter(array_map(
            fn ($value) => $this->normalize($value),
            array_merge([$user->hs_id, $contact['id'] ?? null],
                explode(';', (string) ($properties['hs_merged_object_ids'] ?? ''))),
        ), fn ($value) => $value !== '')));
        if ($ids !== []) {
            $owners = User::where('id', '!=', $user->id)->whereIn('hs_id', $ids)->pluck('id')->all();
            if ($owners !== []) {
                $conflicts['hs_id'] = $owners;
            }
        }

        foreach (['email' => 'email', 'passport' => 'numero_de_pasaporte'] as $field => $property) {
            $value = $this->normalize($properties[$property] ?? null);
            if ($value === '') {
                continue;
            }
            $owners = User::where('id', '!=', $user->id)
                ->whereRaw("LOWER(TRIM({$field})) = ?", [$value])->pluck('id')->all();
            if ($owners !== []) {
                $conflicts[$field] = $owners;
            }
        }

        if ($conflicts !== []) {
            throw new CosIdentityConflictException($conflicts, $previous);
        }
    }

    private function normalize(mixed $value): string
    {
        return is_scalar($value) ? mb_strtolower(trim((string) $value)) : '';
    }
}

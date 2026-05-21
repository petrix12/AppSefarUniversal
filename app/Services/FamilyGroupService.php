<?php

namespace App\Services;

use App\Models\Agcliente;
use App\Models\FamilyGroup;
use App\Models\FamilyGroupMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FamilyGroupService
{
    private const AUTO_ADD_THRESHOLD = 42;
    private const CANDIDATE_THRESHOLD = 36;
    private const SCAN_LIMIT = 8000;

    private const STOP_WORDS = [
        'DE', 'DEL', 'LA', 'LAS', 'LOS', 'Y', 'E', 'EL', 'DA', 'DAS', 'DO', 'DOS',
        'VAN', 'VON', 'DI', 'DU', 'LE', 'SAN', 'SANTA', 'BEN', 'IBN',
    ];

    public function createCalculatedGroup(string $passport, ?string $name = null, ?string $notes = null): FamilyGroup
    {
        $passport = trim($passport);
        $client = $this->clientRecord($passport);
        $matchKey = implode(' ', array_slice($this->anchorSurnameTokens([$passport]), 0, 4));

        $group = FamilyGroup::create([
            'name' => $name ?: 'Grupo familiar ' . ($client['name'] ?: $passport),
            'primary_id_cliente' => $passport,
            'match_key' => $matchKey ?: null,
            'status' => 'calculated',
            'notes' => $notes,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        $this->addClientToGroup($group, $passport, 'principal', [
            'confidence' => 100,
            'match_type' => 'principal',
            'match_reasons' => ['Cliente base del grupo familiar'],
            'evidence' => [],
        ]);

        $this->recalculateMembers($group);

        return $group->fresh(['members']);
    }

    public function recalculateMembers(FamilyGroup $group): int
    {
        $added = 0;
        $existing = $group->members()->pluck('IDCliente')->map(fn ($value) => trim((string) $value))->all();
        $anchors = $existing ?: array_filter([(string) $group->primary_id_cliente]);
        $candidates = $this->findCandidateClients($anchors, $existing, null, 60, self::AUTO_ADD_THRESHOLD);

        foreach ($candidates as $candidate) {
            $member = $this->addClientToGroup($group, $candidate['IDCliente'], 'calculated', [
                'confidence' => $candidate['score'],
                'match_type' => implode(', ', $candidate['match_types']),
                'match_reasons' => $candidate['reasons'],
                'evidence' => $candidate['evidence'],
            ]);

            if ($member->wasRecentlyCreated) {
                $added++;
            }
        }

        $group->update([
            'status' => 'calculated',
            'updated_by' => Auth::id(),
        ]);

        return $added;
    }

    public function addClientToGroup(FamilyGroup $group, string $passport, string $source = 'manual', array $match = []): FamilyGroupMember
    {
        $passport = trim($passport);
        $client = $this->clientRecord($passport);

        return FamilyGroupMember::updateOrCreate(
            [
                'family_group_id' => $group->id,
                'IDCliente' => $passport,
            ],
            [
                'user_id' => $client['user']?->id,
                'anchor_agcliente_id' => $client['person']?->id,
                'display_name' => $client['name'] ?: $passport,
                'source' => $source,
                'confidence' => (int) ($match['confidence'] ?? ($source === 'manual' ? 100 : 0)),
                'match_type' => $match['match_type'] ?? $source,
                'match_reasons' => $match['match_reasons'] ?? [],
                'evidence' => $match['evidence'] ?? [],
                'added_by' => Auth::id(),
            ]
        );
    }

    public function candidatesForGroup(FamilyGroup $group, ?string $search = null): Collection
    {
        $members = $group->members()->pluck('IDCliente')->map(fn ($value) => trim((string) $value))->all();
        $anchors = $members ?: array_filter([(string) $group->primary_id_cliente]);

        return $this->findCandidateClients($anchors, $members, $search, 100, self::CANDIDATE_THRESHOLD);
    }

    public function clientExists(string $passport): bool
    {
        $passport = trim($passport);

        return User::where('passport', $passport)->exists()
            || Agcliente::where('IDCliente', $passport)->exists();
    }

    private function findCandidateClients(array $anchorPassports, array $excludedPassports = [], ?string $search = null, int $limit = 80, int $threshold = 36): Collection
    {
        $anchorPassports = array_values(array_unique(array_filter(array_map('trim', $anchorPassports))));
        $excluded = array_flip(array_values(array_unique(array_filter(array_map('trim', $excludedPassports)))));

        if (empty($anchorPassports)) {
            return collect();
        }

        $fingerprint = $this->anchorFingerprint($anchorPassports);

        if (empty($fingerprint['document_tokens']) && empty($fingerprint['name_keys']) && empty($fingerprint['surname_tokens'])) {
            return collect();
        }

        $query = Agcliente::query()
            ->select([
                'id', 'IDCliente', 'IDPersona', 'Nombres', 'Apellidos', 'NPasaporte',
                'NDocIdent', 'PaisPasaporte', 'PaisDocIdent', 'AnhoNac', 'LugarNac',
            ])
            ->whereNotNull('IDCliente');

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $query->where(function ($searchQuery) use ($term) {
                $searchQuery->where('IDCliente', 'LIKE', $term)
                    ->orWhere('Nombres', 'LIKE', $term)
                    ->orWhere('Apellidos', 'LIKE', $term)
                    ->orWhere('NPasaporte', 'LIKE', $term)
                    ->orWhere('NDocIdent', 'LIKE', $term);
            });
        }

        $candidates = [];
        $people = $query->limit(self::SCAN_LIMIT)->get();

        foreach ($people as $person) {
            $passport = trim((string) $person->IDCliente);

            if ($passport === '' || isset($excluded[$passport]) || in_array($passport, $anchorPassports, true)) {
                continue;
            }

            $match = $this->scorePersonAgainstFingerprint($person, $fingerprint);

            if ($match['score'] < $threshold) {
                continue;
            }

            if (!isset($candidates[$passport])) {
                $candidates[$passport] = [
                    'IDCliente' => $passport,
                    'name' => $passport,
                    'score' => 0,
                    'reasons' => [],
                    'match_types' => [],
                    'matched_people_count' => 0,
                    'evidence' => [],
                ];
            }

            $candidates[$passport]['score'] = max($candidates[$passport]['score'], $match['score']);
            $candidates[$passport]['reasons'] = array_values(array_unique(array_merge($candidates[$passport]['reasons'], $match['reasons'])));
            $candidates[$passport]['match_types'] = array_values(array_unique(array_merge($candidates[$passport]['match_types'], $match['types'])));
            $candidates[$passport]['matched_people_count']++;

            if (count($candidates[$passport]['evidence']) < 5) {
                $candidates[$passport]['evidence'][] = [
                    'agcliente_id' => $person->id,
                    'IDPersona' => $person->IDPersona,
                    'name' => trim(($person->Nombres ?? '') . ' ' . ($person->Apellidos ?? '')),
                    'passport' => $person->NPasaporte ?: $person->NDocIdent,
                    'score' => $match['score'],
                    'reasons' => $match['reasons'],
                ];
            }
        }

        $clientRecords = $this->clientRecordsForPassports(array_keys($candidates));

        foreach ($candidates as $passport => $candidate) {
            $candidates[$passport]['name'] = $clientRecords[$passport]['name'] ?: $passport;
        }

        return collect($candidates)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function anchorFingerprint(array $passports): array
    {
        $people = Agcliente::whereIn('IDCliente', $passports)
            ->select(['id', 'IDCliente', 'Nombres', 'Apellidos', 'NPasaporte', 'NDocIdent'])
            ->get();
        $users = User::whereIn('passport', $passports)->get();

        $documentTokens = [];
        $nameKeys = [];
        $surnameTokens = [];

        foreach ($people as $person) {
            foreach ($this->documentTokens($person->NPasaporte, $person->NDocIdent) as $token) {
                $documentTokens[$token] = true;
            }

            $nameKey = $this->nameKey($person->Nombres, $person->Apellidos);
            if ($nameKey !== '') {
                $nameKeys[$nameKey] = true;
            }

            foreach ($this->surnameTokens((string) $person->Apellidos) as $token) {
                $surnameTokens[$token] = true;
            }
        }

        foreach ($users as $user) {
            foreach ($this->documentTokens($user->passport) as $token) {
                $documentTokens[$token] = true;
            }

            $nameKey = $this->normalizeText($user->name ?? '');
            if ($nameKey !== '') {
                $nameKeys[$nameKey] = true;
            }

            foreach ($this->surnameTokens(trim(($user->apellidos ?? '') . ' ' . ($user->name ?? ''))) as $token) {
                $surnameTokens[$token] = true;
            }
        }

        return [
            'document_tokens' => $documentTokens,
            'name_keys' => $nameKeys,
            'surname_tokens' => $surnameTokens,
        ];
    }

    private function scorePersonAgainstFingerprint(Agcliente $person, array $fingerprint): array
    {
        $score = 0;
        $reasons = [];
        $types = [];

        $documentMatches = array_intersect_key(
            array_flip($this->documentTokens($person->NPasaporte, $person->NDocIdent)),
            $fingerprint['document_tokens']
        );

        if (!empty($documentMatches)) {
            $score += 100;
            $types[] = 'documento';
            $reasons[] = 'Pasaporte o documento coincide';
        }

        $nameKey = $this->nameKey($person->Nombres, $person->Apellidos);
        if ($nameKey !== '' && isset($fingerprint['name_keys'][$nameKey])) {
            $score += 85;
            $types[] = 'nombre';
            $reasons[] = 'Nombre completo coincide';
        }

        $surnameMatches = array_values(array_intersect(
            $this->surnameTokens((string) $person->Apellidos),
            array_keys($fingerprint['surname_tokens'])
        ));

        if (!empty($surnameMatches)) {
            $surnameScore = count($surnameMatches) >= 2 ? 58 : 42;
            $score += $surnameScore;
            $types[] = 'apellido';
            $reasons[] = 'Apellido compatible: ' . implode(', ', array_slice($surnameMatches, 0, 3));
        }

        return [
            'score' => min(100, $score),
            'reasons' => $reasons,
            'types' => $types,
        ];
    }

    private function clientRecord(string $passport): array
    {
        $passport = trim($passport);

        return $this->clientRecordsForPassports([$passport])[$passport] ?? [
            'user' => null,
            'person' => null,
            'name' => '',
        ];
    }

    private function clientRecordsForPassports(array $passports): array
    {
        $passports = array_values(array_unique(array_filter(array_map('trim', $passports))));

        if (empty($passports)) {
            return [];
        }

        $users = User::whereIn('passport', $passports)
            ->get()
            ->keyBy('passport');

        $people = Agcliente::whereIn('IDCliente', $passports)
            ->select(['id', 'IDCliente', 'IDPersona', 'Nombres', 'Apellidos'])
            ->orderByRaw('CASE WHEN IDPersona = 1 THEN 0 ELSE 1 END')
            ->orderBy('IDPersona')
            ->get()
            ->groupBy('IDCliente')
            ->map(fn ($items) => $items->first());

        $records = [];

        foreach ($passports as $passport) {
            $user = $users->get($passport);
            $person = $people->get($passport);
            $name = $user?->name ?: trim(($person->Nombres ?? '') . ' ' . ($person->Apellidos ?? ''));

            $records[$passport] = [
                'user' => $user,
                'person' => $person,
                'name' => trim((string) $name),
            ];
        }

        return $records;
    }

    private function anchorSurnameTokens(array $passports): array
    {
        $people = Agcliente::whereIn('IDCliente', $passports)->pluck('Apellidos')->all();
        $tokens = [];

        foreach ($people as $surname) {
            foreach ($this->surnameTokens((string) $surname) as $token) {
                $tokens[$token] = true;
            }
        }

        return array_keys($tokens);
    }

    private function nameKey(?string $names, ?string $surnames): string
    {
        return $this->normalizeText(trim((string) $names . ' ' . (string) $surnames));
    }

    private function documentTokens(?string ...$values): array
    {
        $tokens = [];

        foreach ($values as $value) {
            $token = preg_replace('/[^A-Z0-9]/', '', $this->normalizeText((string) $value));
            if (strlen($token) >= 5) {
                $tokens[] = $token;
            }
        }

        return array_values(array_unique($tokens));
    }

    private function surnameTokens(string $value): array
    {
        $normalized = $this->normalizeText($value);
        $parts = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($parts, function (string $token): bool {
            return strlen($token) >= 4 && !in_array($token, self::STOP_WORDS, true);
        })));
    }

    private function normalizeText(string $value): string
    {
        $value = Str::ascii(Str::upper(trim($value)));
        $value = preg_replace('/[^A-Z0-9\s]/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}

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

        if (
            empty($fingerprint['document_tokens'])
            && empty($fingerprint['name_keys'])
            && empty($fingerprint['sorted_name_keys'])
            && empty($fingerprint['given_name_tokens'])
            && empty($fingerprint['surname_tokens'])
        ) {
            return collect();
        }

        $query = Agcliente::query()
            ->select([
                'id', 'IDCliente', 'IDPersona', 'Nombres', 'Apellidos', 'NPasaporte',
                'NDocIdent', 'PaisPasaporte', 'PaisDocIdent',
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
            ->select([
                'id', 'IDCliente', 'Nombres', 'Apellidos', 'NPasaporte', 'NDocIdent',
            ])
            ->get();
        $users = User::whereIn('passport', $passports)->get();

        $documentTokens = [];
        $nameKeys = [];
        $sortedNameKeys = [];
        $givenNameTokens = [];
        $surnameTokens = [];
        $nameSurnamePairs = [];

        foreach ($people as $person) {
            foreach ($this->documentTokens($person->NPasaporte, $person->NDocIdent) as $token) {
                $documentTokens[$token] = true;
            }

            $signature = $this->personNameSignature(
                $person->Nombres,
                $person->Apellidos
            );

            $this->mergeSignatureIntoFingerprint(
                $signature,
                $nameKeys,
                $sortedNameKeys,
                $givenNameTokens,
                $surnameTokens,
                $nameSurnamePairs
            );
        }

        foreach ($users as $user) {
            foreach ($this->documentTokens($user->passport) as $token) {
                $documentTokens[$token] = true;
            }

            $signature = $this->personNameSignature(
                $user->name ?? '',
                $user->apellidos ?? ''
            );

            $this->mergeSignatureIntoFingerprint(
                $signature,
                $nameKeys,
                $sortedNameKeys,
                $givenNameTokens,
                $surnameTokens,
                $nameSurnamePairs
            );
        }

        return [
            'document_tokens' => $documentTokens,
            'name_keys' => $nameKeys,
            'sorted_name_keys' => $sortedNameKeys,
            'given_name_tokens' => $givenNameTokens,
            'surname_tokens' => $surnameTokens,
            'name_surname_pairs' => $nameSurnamePairs,
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

        $signature = $this->personNameSignature(
            $person->Nombres,
            $person->Apellidos
        );

        if ($signature['full_key'] !== '' && isset($fingerprint['name_keys'][$signature['full_key']])) {
            $score += 90;
            $types[] = 'nombre+apellido';
            $reasons[] = 'Nombre completo coincide';
        } elseif ($signature['sorted_key'] !== '' && isset($fingerprint['sorted_name_keys'][$signature['sorted_key']])) {
            $score += 82;
            $types[] = 'nombre+apellido';
            $reasons[] = 'Nombre completo coincide aunque cambie el orden';
        }

        $surnameReferenceTokens = array_keys($fingerprint['surname_tokens']);
        $givenNameReferenceTokens = array_keys($fingerprint['given_name_tokens']);

        $surnameMatches = array_values(array_intersect(
            $signature['surname_tokens'],
            $surnameReferenceTokens
        ));

        $givenNameMatches = array_values(array_intersect(
            $signature['given_tokens'],
            $givenNameReferenceTokens
        ));

        $pairMatches = $this->nameSurnamePairMatches($signature, $fingerprint['name_surname_pairs']);
        $fuzzySurnameMatches = empty($surnameMatches)
            ? $this->fuzzyTokenMatches($signature['surname_tokens'], $surnameReferenceTokens)
            : [];
        $fuzzyGivenNameMatches = empty($givenNameMatches)
            ? $this->fuzzyTokenMatches($signature['given_tokens'], $givenNameReferenceTokens)
            : [];

        if (!empty($pairMatches)) {
            $score += count($surnameMatches) >= 2 ? 82 : 74;
            $types[] = 'nombre+apellido';
            $reasons[] = 'Nombre y apellido coinciden: ' . $this->tokensLabel($pairMatches);
        } elseif (!empty($givenNameMatches) && !empty($surnameMatches)) {
            $score += count($surnameMatches) >= 2 ? 74 : 66;
            $types[] = 'nombre+apellido';
            $reasons[] = 'Nombre y apellido compatibles: ' . $this->tokensLabel(array_merge($givenNameMatches, $surnameMatches));
        } elseif ((!empty($givenNameMatches) && !empty($fuzzySurnameMatches)) || (!empty($fuzzyGivenNameMatches) && !empty($surnameMatches))) {
            $score += 56;
            $types[] = 'nombre+apellido similar';
            $reasons[] = 'Nombre y apellido similares: ' . $this->tokensLabel(array_merge(
                $givenNameMatches,
                $surnameMatches,
                $fuzzyGivenNameMatches,
                $fuzzySurnameMatches
            ));
        }

        if (!empty($surnameMatches)) {
            $surnameScore = count($surnameMatches) >= 2 ? 44 : 24;
            $score += $surnameScore;
            $types[] = 'apellido';
            $reasons[] = 'Apellido compatible: ' . $this->tokensLabel($surnameMatches);
        } elseif (!empty($fuzzySurnameMatches)) {
            $score += 14;
            $types[] = 'apellido similar';
            $reasons[] = 'Apellido similar: ' . $this->tokensLabel($fuzzySurnameMatches);
        }

        if (!empty($givenNameMatches)) {
            $score += 12;
            $types[] = 'nombre';
            $reasons[] = 'Nombre compatible: ' . $this->tokensLabel($givenNameMatches);
        } elseif (!empty($fuzzyGivenNameMatches)) {
            $score += 6;
            $types[] = 'nombre similar';
            $reasons[] = 'Nombre similar: ' . $this->tokensLabel($fuzzyGivenNameMatches);
        }

        return [
            'score' => min(100, $score),
            'reasons' => array_values(array_unique($reasons)),
            'types' => array_values(array_unique($types)),
        ];
    }

    private function mergeSignatureIntoFingerprint(
        array $signature,
        array &$nameKeys,
        array &$sortedNameKeys,
        array &$givenNameTokens,
        array &$surnameTokens,
        array &$nameSurnamePairs
    ): void {
        if ($signature['full_key'] !== '') {
            $nameKeys[$signature['full_key']] = true;
        }

        if ($signature['sorted_key'] !== '') {
            $sortedNameKeys[$signature['sorted_key']] = true;
        }

        foreach ($signature['given_tokens'] as $token) {
            $givenNameTokens[$token] = true;
        }

        foreach ($signature['surname_tokens'] as $token) {
            $surnameTokens[$token] = true;
        }

        foreach ($signature['name_surname_pairs'] as $pair) {
            $nameSurnamePairs[$pair] = true;
        }
    }

    private function personNameSignature(?string $names, ?string $surnames): array
    {
        $givenTokens = $this->nameTokens((string) $names);
        $surnameTokens = $this->surnameTokens((string) $surnames);
        $allTokens = array_values(array_unique(array_merge($givenTokens, $surnameTokens)));
        $sortedTokens = $allTokens;
        sort($sortedTokens, SORT_STRING);

        $pairs = [];
        foreach ($givenTokens as $givenToken) {
            foreach ($surnameTokens as $surnameToken) {
                $pairs[] = $givenToken . '|' . $surnameToken;
            }
        }

        return [
            'full_key' => $this->nameKey($names, $surnames),
            'sorted_key' => !empty($givenTokens) && !empty($surnameTokens) ? implode(' ', $sortedTokens) : '',
            'given_tokens' => $givenTokens,
            'surname_tokens' => $surnameTokens,
            'all_tokens' => $allTokens,
            'name_surname_pairs' => array_values(array_unique($pairs)),
        ];
    }

    private function nameSurnamePairMatches(array $signature, array $referencePairs): array
    {
        $matches = [];

        foreach ($signature['name_surname_pairs'] as $pair) {
            if (isset($referencePairs[$pair])) {
                $matches[] = str_replace('|', ' ', $pair);
            }
        }

        return array_values(array_unique($matches));
    }

    private function fuzzyTokenMatches(array $candidateTokens, array $referenceTokens): array
    {
        $matches = [];
        $referenceTokens = array_values(array_unique($referenceTokens));

        foreach (array_values(array_unique($candidateTokens)) as $candidateToken) {
            if (strlen($candidateToken) < 5) {
                continue;
            }

            foreach ($referenceTokens as $referenceToken) {
                if (
                    $candidateToken === $referenceToken
                    || strlen($referenceToken) < 5
                    || $candidateToken[0] !== $referenceToken[0]
                    || abs(strlen($candidateToken) - strlen($referenceToken)) > 2
                ) {
                    continue;
                }

                $allowedDistance = max(strlen($candidateToken), strlen($referenceToken)) >= 8 ? 2 : 1;

                if (levenshtein($candidateToken, $referenceToken) <= $allowedDistance) {
                    $matches[] = $candidateToken . ' ~ ' . $referenceToken;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
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

    private function nameTokens(string $value): array
    {
        $normalized = $this->normalizeText($value);
        $parts = preg_split('/\s+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter($parts, function (string $token): bool {
            return strlen($token) >= 3 && !in_array($token, self::STOP_WORDS, true);
        })));
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

    private function tokensLabel(array $tokens): string
    {
        return implode(', ', array_slice(array_values(array_unique(array_filter($tokens))), 0, 4));
    }

    private function normalizeText(string $value): string
    {
        $value = Str::ascii(Str::upper(trim($value)));
        $value = preg_replace('/[^A-Z0-9\s]/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}

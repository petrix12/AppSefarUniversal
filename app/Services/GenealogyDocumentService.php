<?php

namespace App\Services;

use App\Models\Agcliente;
use App\Models\File;
use App\Models\GenealogyUnion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GenealogyDocumentService
{
    public const KIND_PASSPORT = 'passport';
    public const KIND_BIRTH = 'birth_certificate';
    public const KIND_MARRIAGE = 'marriage_certificate';
    public const KIND_DEATH = 'death_certificate';

    public const CLIENT_SOURCES = ['app_cliente', 'solicitud_cliente'];

    public static function kinds(): array
    {
        return [
            self::KIND_PASSPORT => 'Pasaporte',
            self::KIND_BIRTH => 'Acta de nacimiento',
            self::KIND_MARRIAGE => 'Acta de matrimonio',
            self::KIND_DEATH => 'Acta de defunción',
        ];
    }

    public static function label(?string $kind): string
    {
        return self::kinds()[$kind] ?? 'Documento sin clasificar';
    }

    public static function isAllowedKind(?string $kind): bool
    {
        return is_string($kind) && array_key_exists($kind, self::kinds());
    }

    /** The client is not asked for a death certificate; it is an ancestor-only requirement. */
    public static function allowedKindsForPerson(Agcliente $person): array
    {
        $kinds = self::kinds();

        if ((string) $person->IDPersona === '1') {
            unset($kinds[self::KIND_DEATH]);
        }

        return $kinds;
    }

    public static function inferKind(?string $legacyType): ?string
    {
        $value = Str::ascii(Str::lower(trim((string) $legacyType)));

        return match (true) {
            Str::contains($value, 'pasaporte'), Str::contains($value, 'identificacion') => self::KIND_PASSPORT,
            Str::contains($value, 'nacimiento') => self::KIND_BIRTH,
            Str::contains($value, 'matrimonio') => self::KIND_MARRIAGE,
            Str::contains($value, 'defuncion') => self::KIND_DEATH,
            default => null,
        };
    }

    public function visibleToClient(string $passport): Builder
    {
        return File::query()
            ->where('IDCliente', $passport)
            ->where('client_visible', true)
            ->whereIn('document_kind', array_keys(self::kinds()))
            ->where(function (Builder $query) {
                $query->whereNull('source')
                    ->orWhere('source', '!=', 'hubspot')
                    ->orWhere(function (Builder $hubspot) {
                        $hubspot->where('source', 'hubspot')
                            ->where(function (Builder $eligible) {
                                foreach (array_keys(HubspotService::clientEligibleContactFileProperties()) as $property) {
                                    $eligible->orWhere('source_reference', 'like', 'hubspot:' . $property . ':%');
                                }
                            });
                    });
            })
            ->orderBy('document_kind')
            ->orderByDesc('created_at');
    }

    public function canView(User $user, File $file): bool
    {
        if ($this->isInternalUser($user)) {
            return true;
        }

        return (string) $file->IDCliente === (string) $user->passport
            && (bool) $file->client_visible
            && self::isAllowedKind($file->document_kind)
            && HubspotService::isClientEligibleFileSource($file->source, $file->source_reference);
    }

    public function canReuseForRequest(User $user, File $file): bool
    {
        return (string) $file->IDCliente === (string) $user->passport
            && in_array($file->source, self::CLIENT_SOURCES, true)
            && self::isAllowedKind($file->document_kind);
    }

    public function isInternalUser(User $user): bool
    {
        return method_exists($user, 'hasAnyRole')
            && $user->hasAnyRole(['Administrador', 'Genealogista', 'Documentalista', 'Produccion']);
    }

    public function temporaryUrl(File $file): string
    {
        $path = trim((string) $file->location, '/') . '/' . ltrim((string) $file->file, '/');

        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(10));
        } catch (\Throwable) {
            return Storage::disk('s3')->url($path);
        }
    }

    public function associatePerson(File $file, Agcliente $person, string $relationship = 'subject'): void
    {
        $file->people()->syncWithoutDetaching([
            $person->id => ['relationship' => $relationship],
        ]);

        $file->forceFill(['IDPersonaNew' => $person->id])->save();
    }

    public function associateUnion(File $file, GenealogyUnion $union): void
    {
        $file->genealogyUnions()->syncWithoutDetaching([$union->id]);

        foreach ([$union->spouse_one_id, $union->spouse_two_id] as $personId) {
            if ($personId) {
                $file->people()->syncWithoutDetaching([
                    $personId => ['relationship' => 'spouse'],
                ]);
            }
        }
    }

    public function findOrCreateUnion(Agcliente $person, Agcliente $spouse): GenealogyUnion
    {
        abort_unless(
            (string) $person->IDCliente === (string) $spouse->IDCliente,
            422,
            'Las dos personas deben pertenecer al mismo árbol.'
        );

        $first = min((int) $person->id, (int) $spouse->id);
        $second = max((int) $person->id, (int) $spouse->id);

        return GenealogyUnion::firstOrCreate(
            [
                'IDCliente' => $person->IDCliente,
                'spouse_one_id' => $first,
                'spouse_two_id' => $second,
            ],
            [
                'marriage_date' => $this->treeDate($person, 'Matr') ?: $this->treeDate($spouse, 'Matr'),
                'marriage_place' => $person->LugarMatr ?: $spouse->LugarMatr,
                'marriage_country' => $person->PaisMatr ?: $spouse->PaisMatr,
                'created_by' => auth()->id(),
            ]
        );
    }

    public function filesForPerson(Agcliente $person, bool $clientSafe = false): array
    {
        $ids = $this->fileIdsForPerson($person);
        $query = File::query()->where('IDCliente', $person->IDCliente)->whereIn('id', $ids);

        if ($clientSafe) {
            $query->where('client_visible', true)->whereIn('document_kind', array_keys(self::kinds()));
        }

        return $query->orderBy('document_kind')->orderBy('file')->get()
            ->map(fn (File $file) => $this->present($file))
            ->values()
            ->all();
    }

    public function catalog(string $passport, bool $clientSafe = false): array
    {
        $people = Agcliente::where('IDCliente', $passport)
            ->orderBy('Generacion')
            ->orderBy('IDPersona')
            ->get();

        $files = $clientSafe
            ? $this->visibleToClient($passport)->get()
            : File::where('IDCliente', $passport)->orderBy('document_kind')->orderBy('file')->get();

        $idsByPerson = [];
        foreach ($people as $person) {
            $idsByPerson[$person->id] = array_flip($this->fileIdsForPerson($person));
        }

        $assigned = [];
        $groups = $people->map(function (Agcliente $person) use ($files, $idsByPerson, &$assigned) {
            $personFiles = $files->filter(function (File $file) use ($person, $idsByPerson, &$assigned) {
                $matches = isset($idsByPerson[$person->id][$file->id]);
                if ($matches) {
                    $assigned[$file->id] = true;
                }
                return $matches;
            })->map(fn (File $file) => $this->present($file))->values()->all();

            return [
                'person' => [
                    'id' => $person->id,
                    'name' => trim($person->Nombres . ' ' . $person->Apellidos) ?: 'Sin nombre',
                    'relationship' => $person->parentesco ?? $person->Familiaridad,
                    'generation' => $person->Generacion,
                ],
                'files' => $personFiles,
            ];
        })->filter(fn (array $group) => count($group['files']) > 0)->values()->all();

        $unassigned = $files->reject(fn (File $file) => isset($assigned[$file->id]))
            ->map(fn (File $file) => $this->present($file))
            ->values()
            ->all();

        return ['people' => $groups, 'unassigned' => $unassigned];
    }

    public function present(File $file): array
    {
        $name = (string) $file->file;
        $extension = Str::lower(pathinfo($name, PATHINFO_EXTENSION));
        $mime = Str::lower((string) $file->mime_type);

        return [
            'id' => $file->id,
            'file' => $name,
            'tipo' => $file->tipo,
            'document_kind' => $file->document_kind,
            'document_label' => self::label($file->document_kind),
            'source' => $file->source,
            'client_visible' => (bool) $file->client_visible,
            'notas' => $file->notas,
            'created_at' => optional($file->created_at)->format('d/m/Y'),
            'preview_url' => route('viewfile', $file->id),
            'is_pdf' => $extension === 'pdf' || Str::contains($mime, 'pdf'),
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || Str::startsWith($mime, 'image/'),
        ];
    }

    private function fileIdsForPerson(Agcliente $person): array
    {
        $legacyId = $person->IDPersona;
        $directIds = File::query()
            ->where('IDCliente', $person->IDCliente)
            ->where(function (Builder $query) use ($person, $legacyId) {
                $query->where('IDPersonaNew', $person->id);
                if ($legacyId !== null && $legacyId !== '') {
                    $query->orWhere(function (Builder $legacy) use ($legacyId) {
                        $legacy->where('IDPersona', $legacyId)
                            ->where(function (Builder $unmigrated) {
                                $unmigrated->whereNull('IDPersonaNew')
                                    ->orWhere('IDPersonaNew', 0)
                                    ->orWhere('IDPersonaNew', '');
                            });
                    });
                }
            })
            ->pluck('id')
            ->all();

        $linkedIds = DB::table('genealogy_document_person')
            ->where('person_id', $person->id)
            ->pluck('file_id')
            ->all();

        $unionIds = GenealogyUnion::query()
            ->where('IDCliente', $person->IDCliente)
            ->where(fn (Builder $query) => $query->where('spouse_one_id', $person->id)->orWhere('spouse_two_id', $person->id))
            ->pluck('id')
            ->all();

        $unionFileIds = empty($unionIds)
            ? []
            : DB::table('genealogy_document_union')->whereIn('genealogy_union_id', $unionIds)->pluck('file_id')->all();

        return array_values(array_unique(array_map('intval', array_merge($directIds, $linkedIds, $unionFileIds))));
    }

    private function treeDate(Agcliente $person, string $suffix): ?string
    {
        $parts = array_filter([
            $person->{'Dia' . $suffix} ?? null,
            $person->{'Mes' . $suffix} ?? null,
            $person->{'Anho' . $suffix} ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        return empty($parts) ? null : implode('/', $parts);
    }

}

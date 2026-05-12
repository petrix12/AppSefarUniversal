<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\AssocTlHs;
use App\Models\TlContact;
use App\Models\User;
use App\Services\HubspotService;
use App\Services\TeamleaderService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class ExternalClientImportController extends Controller
{
    public function showImportForm(Request $request, HubspotService $hubspot, TeamleaderService $teamleader)
    {
        $candidate = null;
        $existingUser = null;
        $searchError = null;

        if ($request->filled('search')) {
            try {
                $candidate = $this->resolveCandidate(
                    $request->input('source', 'hubspot'),
                    $request->input('search_by', 'email'),
                    trim((string) $request->input('search')),
                    $hubspot,
                    $teamleader
                );

                if ($candidate) {
                    $existingUser = $this->findExistingUser($candidate);
                }
            } catch (\Throwable $e) {
                $searchError = $e->getMessage();
            }
        }

        return view('client-import.index', compact('candidate', 'existingUser', 'searchError'));
    }

    public function importClient(Request $request, HubspotService $hubspot, TeamleaderService $teamleader)
    {
        $data = $request->validate([
            'source' => 'required|in:hubspot,teamleader',
            'search_by' => 'required|in:email,passport,id',
            'search' => 'required|string|max:255',
        ]);

        $candidate = $this->resolveCandidate(
            $data['source'],
            $data['search_by'],
            trim($data['search']),
            $hubspot,
            $teamleader
        );

        if (!$candidate) {
            return back()
                ->withInput()
                ->with('error', 'No se encontro el contacto en la fuente seleccionada.');
        }

        $existingUser = $this->findExistingUser($candidate);

        if (empty($candidate['email']) && !$existingUser) {
            return back()
                ->withInput()
                ->with('error', 'No se puede migrar el contacto porque no tiene email.');
        }

        $user = $existingUser ?: $this->createUserFromCandidate($candidate);

        $updated = $this->fillMissingUserData($user, $candidate);
        $this->ensureClientRole($user);
        $this->ensureAgclienteRecord($user);

        return redirect()
            ->route('crud.users.edit', $user)
            ->with(
                'success',
                $existingUser
                    ? 'El cliente ya existia en la App. Se vincularon/actualizaron los datos disponibles.'
                    : 'Cliente migrado a la App correctamente. No se envio correo automatico al cliente.'
            );
    }

    private function resolveCandidate(
        string $source,
        string $searchBy,
        string $search,
        HubspotService $hubspot,
        TeamleaderService $teamleader
    ): ?array {
        return $source === 'hubspot'
            ? $this->resolveHubspotCandidate($searchBy, $search, $hubspot)
            : $this->resolveTeamleaderCandidate($searchBy, $search, $teamleader);
    }

    private function resolveHubspotCandidate(string $searchBy, string $search, HubspotService $hubspot): ?array
    {
        $contact = match ($searchBy) {
            'id' => $hubspot->getContactById($search),
            'passport' => $hubspot->searchContactByPassport($search),
            default => $hubspot->searchContactByEmail($search),
        };

        if (!$contact) {
            return null;
        }

        if ($searchBy !== 'id') {
            $contact = $hubspot->getContactById($contact['id']);
        }

        return $this->normalizeHubspotContact($contact);
    }

    private function resolveTeamleaderCandidate(string $searchBy, string $search, TeamleaderService $teamleader): ?array
    {
        $contact = null;

        if ($searchBy === 'id') {
            $contact = $this->localTeamleaderContact($search) ?: $teamleader->getContactById($search);
        } elseif ($searchBy === 'passport') {
            $contact = $this->localTeamleaderContactByPassport($search) ?: $teamleader->searchContactByPassport($search);
        } else {
            $contact = $this->localTeamleaderContactByEmail($search) ?: $teamleader->searchContactByEmail($search);
        }

        if (!$contact) {
            return null;
        }

        if (!empty($contact['id'])) {
            $fullContact = $this->localTeamleaderContact($contact['id']) ?: $teamleader->getContactById($contact['id']);
            $contact = $fullContact ?: $contact;
        }

        return $this->normalizeTeamleaderContact($contact);
    }

    private function normalizeHubspotContact(array $contact): array
    {
        $props = $contact['properties'] ?? [];
        $firstName = $this->value($props, ['firstname', 'nombres']);
        $lastName = $this->value($props, ['lastname', 'apellidos']);
        $name = trim("{$firstName} {$lastName}") ?: $this->value($props, ['name', 'nombre']) ?: 'Cliente HubSpot ' . $contact['id'];

        return [
            'source' => 'hubspot',
            'source_label' => 'HubSpot',
            'external_id' => (string) $contact['id'],
            'name' => $name,
            'nombres' => $firstName ?: $name,
            'apellidos' => $lastName,
            'email' => $this->value($props, ['email']),
            'phone' => $this->value($props, ['phone', 'mobilephone']),
            'passport' => $this->value($props, ['numero_de_pasaporte', 'passport']),
            'servicio' => $this->value($props, ['servicio_solicitado', 'servicio']),
            'pais_de_nacimiento' => $this->value($props, ['pais_de_nacimiento']),
            'ciudad_de_nacimiento' => $this->value($props, ['ciudad_de_nacimiento']),
            'referido_por' => $this->value($props, ['n000__referido_por__clonado_', 'referido_por']),
            'date_of_birth' => $this->parseDate($this->value($props, ['date_of_birth', 'fecha_nac'])),
            'raw' => $contact,
        ];
    }

    private function normalizeTeamleaderContact(array $contact): array
    {
        $firstName = $contact['first_name'] ?? null;
        $lastName = $contact['last_name'] ?? null;
        $name = trim("{$firstName} {$lastName}") ?: 'Cliente Teamleader ' . ($contact['id'] ?? '');

        return [
            'source' => 'teamleader',
            'source_label' => 'Teamleader',
            'external_id' => (string) ($contact['id'] ?? ''),
            'name' => $name,
            'nombres' => $firstName ?: $name,
            'apellidos' => $lastName,
            'email' => $this->primaryEmail($contact),
            'phone' => $this->primaryPhone($contact),
            'passport' => $contact['passport'] ?? $this->teamleaderCustomValue($contact, ['numero_de_pasaporte', 'passport']),
            'servicio' => $this->teamleaderCustomValue($contact, ['servicio_solicitado', 'servicio']),
            'pais_de_nacimiento' => $this->teamleaderCustomValue($contact, ['pais_de_nacimiento']),
            'ciudad_de_nacimiento' => $this->teamleaderCustomValue($contact, ['ciudad_de_nacimiento']),
            'referido_por' => $this->teamleaderCustomValue($contact, ['n000__referido_por__clonado_', 'referido_por']),
            'date_of_birth' => $this->parseDate($this->teamleaderCustomValue($contact, ['date_of_birth', 'fecha_nac'])),
            'raw' => $contact,
        ];
    }

    private function createUserFromCandidate(array $candidate): User
    {
        $password = Str::random(32);

        $payload = [
            'name' => $candidate['name'],
            'email' => $candidate['email'],
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'nombres' => $candidate['nombres'],
            'apellidos' => $candidate['apellidos'],
            'passport' => $candidate['passport'],
            'phone' => $candidate['phone'],
            'servicio' => $candidate['servicio'],
            'pais_de_nacimiento' => $candidate['pais_de_nacimiento'],
            'ciudad_de_nacimiento' => $candidate['ciudad_de_nacimiento'],
            'date_of_birth' => $candidate['date_of_birth'],
            'referido_por' => $candidate['referido_por'],
            'cosready' => 1,
        ];

        $payload[$candidate['source'] === 'hubspot' ? 'hs_id' : 'tl_id'] = $candidate['external_id'];

        return User::create($this->onlyExistingUserColumns($payload));
    }

    private function fillMissingUserData(User $user, array $candidate): bool
    {
        $updates = [
            'name' => $user->name ?: $candidate['name'],
            'nombres' => $user->nombres ?: $candidate['nombres'],
            'apellidos' => $user->apellidos ?: $candidate['apellidos'],
            'phone' => $user->phone ?: $candidate['phone'],
            'servicio' => $user->servicio ?: $candidate['servicio'],
            'pais_de_nacimiento' => $user->pais_de_nacimiento ?: $candidate['pais_de_nacimiento'],
            'ciudad_de_nacimiento' => $user->ciudad_de_nacimiento ?: $candidate['ciudad_de_nacimiento'],
            'date_of_birth' => $user->date_of_birth ?: $candidate['date_of_birth'],
            'referido_por' => $user->referido_por ?: $candidate['referido_por'],
        ];

        if (blank($user->passport) && filled($candidate['passport'] ?? null)) {
            $passportInUse = User::query()
                ->where('passport', $candidate['passport'])
                ->whereKeyNot($user->id)
                ->exists();

            if (!$passportInUse) {
                $updates['passport'] = $candidate['passport'];
            }
        }

        $sourceColumn = $candidate['source'] === 'hubspot' ? 'hs_id' : 'tl_id';

        if (blank($user->{$sourceColumn} ?? null)) {
            $updates[$sourceColumn] = $candidate['external_id'];
        }

        $updates = array_filter(
            $this->onlyExistingUserColumns($updates),
            fn ($value) => !is_null($value) && $value !== ''
        );

        if (empty($updates)) {
            return false;
        }

        $user->fill($updates);
        return $user->isDirty() && $user->save();
    }

    private function findExistingUser(array $candidate): ?User
    {
        $query = User::query();
        $sourceColumn = $candidate['source'] === 'hubspot' ? 'hs_id' : 'tl_id';
        $hasMatch = false;

        $query->where(function ($q) use ($candidate, $sourceColumn, &$hasMatch) {
            if ($this->hasUserColumn($sourceColumn) && !empty($candidate['external_id'])) {
                $q->orWhere($sourceColumn, $candidate['external_id']);
                $hasMatch = true;
            }

            if (!empty($candidate['email'])) {
                $q->orWhere('email', $candidate['email']);
                $hasMatch = true;
            }

            if (!empty($candidate['passport']) && $this->hasUserColumn('passport')) {
                $q->orWhere('passport', $candidate['passport']);
                $hasMatch = true;
            }
        });

        if (!$hasMatch) {
            return null;
        }

        return $query->first();
    }

    private function ensureClientRole(User $user): void
    {
        if (!$user->hasRole('Cliente')) {
            $user->assignRole('Cliente');
        }

        $permissions = Permission::query()
            ->whereIn('name', ['pay.services', 'finish.register'])
            ->pluck('name')
            ->all();

        if (!empty($permissions)) {
            $user->givePermissionTo($permissions);
        }
    }

    private function ensureAgclienteRecord(User $user): void
    {
        if (empty($user->passport)) {
            return;
        }

        Agcliente::firstOrCreate(
            [
                'IDCliente' => trim($user->passport),
                'IDPersona' => 1,
            ],
            [
                'Nombres' => trim((string) $user->nombres),
                'Apellidos' => trim((string) $user->apellidos),
                'NPasaporte' => trim((string) $user->passport),
                'PNacimiento' => trim((string) $user->pais_de_nacimiento),
                'PaisNac' => trim((string) $user->pais_de_nacimiento),
                'referido' => trim((string) $user->referido_por),
                'FRegistro' => now(),
                'FUpdate' => now(),
                'Usuario' => trim((string) $user->email),
            ]
        );
    }

    private function localTeamleaderContact(string $id): ?array
    {
        $contact = TlContact::find($id);
        return $contact ? $this->tlContactModelToArray($contact) : null;
    }

    private function localTeamleaderContactByEmail(string $email): ?array
    {
        $contact = TlContact::query()->where('email', $email)->first();
        return $contact ? $this->tlContactModelToArray($contact) : null;
    }

    private function localTeamleaderContactByPassport(string $passport): ?array
    {
        $contact = TlContact::query()->where('passport', $passport)->first();
        return $contact ? $this->tlContactModelToArray($contact) : null;
    }

    private function tlContactModelToArray(TlContact $contact): array
    {
        return array_merge($contact->raw_data ?? [], [
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'emails' => $contact->emails ?: [['type' => 'primary', 'email' => $contact->email]],
            'telephones' => $contact->telephones ?: [['type' => 'mobile', 'number' => $contact->phone]],
            'custom_fields' => $contact->custom_fields ?: [],
            'passport' => $contact->passport,
            'status' => $contact->status,
        ]);
    }

    private function teamleaderCustomValue(array $contact, array $hubspotFields): ?string
    {
        $tlIds = AssocTlHs::query()
            ->whereIn('hs_id', $hubspotFields)
            ->pluck('tl_id')
            ->filter()
            ->values()
            ->all();

        foreach ($contact['custom_fields'] ?? [] as $field) {
            $id = $field['definition']['id'] ?? $field['id'] ?? null;

            if ($id && in_array($id, $tlIds, true) && filled($field['value'] ?? null)) {
                return is_array($field['value']) ? json_encode($field['value']) : (string) $field['value'];
            }
        }

        return null;
    }

    private function primaryEmail(array $contact): ?string
    {
        foreach ($contact['emails'] ?? [] as $email) {
            if (($email['type'] ?? null) === 'primary' && !empty($email['email'])) {
                return $email['email'];
            }
        }

        return $contact['email'] ?? null;
    }

    private function primaryPhone(array $contact): ?string
    {
        foreach ($contact['telephones'] ?? [] as $phone) {
            if (!empty($phone['number'])) {
                return $phone['number'];
            }
        }

        return $contact['phone'] ?? null;
    }

    private function value(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (filled($data[$key] ?? null)) {
                return (string) $data[$key];
            }
        }

        return null;
    }

    private function parseDate($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            if (is_numeric($value)) {
                $timestamp = ((int) $value) > 9999999999 ? ((int) $value) / 1000 : (int) $value;
                return Carbon::createFromTimestamp((int) $timestamp)->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function onlyExistingUserColumns(array $payload): array
    {
        return array_intersect_key($payload, array_flip(Schema::getColumnListing('users')));
    }

    private function hasUserColumn(string $column): bool
    {
        return Schema::hasColumn('users', $column);
    }
}

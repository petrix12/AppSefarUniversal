<?php

namespace App\Services;

use App\Models\MarketingContact;
use App\Models\MarketingContactSource;
use App\Models\MarketingList;
use App\Models\MarketingListMember;
use App\Models\TlContact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketingAudienceImporter
{
    public function __construct(private readonly HubspotService $hubspot)
    {
    }

    public function import(MarketingList $list, string $source, array $config, ?UploadedFile $file = null): int
    {
        $records = match ($source) {
            'app' => $this->fromApp($config),
            'teamleader' => $this->fromTeamleader($config),
            'hubspot' => $this->fromHubspot($config),
            'csv' => $this->fromCsv($file),
            default => throw new \InvalidArgumentException('Fuente de contactos no admitida.'),
        };

        return DB::transaction(function () use ($list, $source, $config, $records): int {
            $list->members()->delete();
            $imported = 0;

            foreach ($records as $record) {
                $email = strtolower(trim((string) ($record['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                $contact = MarketingContact::firstOrCreate(['email' => $email]);
                $changes = array_filter([
                    'first_name' => $record['first_name'] ?? null,
                    'last_name' => $record['last_name'] ?? null,
                    'phone' => $record['phone'] ?? null,
                ], fn ($value) => filled($value));
                $attributes = array_filter((array) ($record['attributes'] ?? []), fn ($value) => $value !== null && $value !== '');
                if ($attributes) {
                    $changes['attributes'] = array_replace((array) $contact->getAttribute('attributes'), $attributes);
                }
                if ($changes) {
                    $contact->update($changes);
                }

                $sourceId = (string) ($record['source_id'] ?? $email);
                MarketingContactSource::updateOrCreate(
                    ['marketing_contact_id' => $contact->id, 'source_type' => $source, 'source_id' => $sourceId],
                    ['source_data' => $record['source_data'] ?? []]
                );
                MarketingListMember::firstOrCreate([
                    'marketing_list_id' => $list->id,
                    'marketing_contact_id' => $contact->id,
                ], ['source_data' => $record['source_data'] ?? []]);
                $imported++;
            }

            $list->update([
                'source_type' => $source,
                'source_config' => $config,
                'last_imported_at' => now(),
            ]);

            return $imported;
        });
    }

    private function fromApp(array $config): iterable
    {
        $query = User::query()->whereNotNull('email')->where('email', '!=', '');
        $segment = $config['app_segment'] ?? 'all';

        if ($segment === 'clients') {
            $query->role('Cliente');
        } elseif ($segment === 'verified') {
            $query->whereNotNull('email_verified_at');
        }

        return $query->orderBy('id')->cursor()->map(fn (User $user) => [
            'email' => $user->email,
            'first_name' => $user->nombres ?: Str::before($user->name, ' '),
            'last_name' => $user->apellidos ?: Str::after($user->name, ' '),
            'phone' => $user->phone,
            'attributes' => ['app_user_id' => $user->id, 'service' => $user->servicio],
            'source_id' => $user->id,
            'source_data' => ['user_id' => $user->id, 'segment' => $segment],
        ]);
    }

    private function fromTeamleader(array $config): iterable
    {
        $query = TlContact::query()->where(function ($query) {
            $query->whereNull('status')->orWhere('status', '!=', 'deleted');
        })->whereNotNull('email')->where('email', '!=', '');
        $tags = array_filter(array_map('trim', explode(',', (string) ($config['teamleader_tags'] ?? ''))));
        return $query->orderBy('id')->cursor()
            ->filter(function (TlContact $contact) use ($tags): bool {
                if (!$tags) {
                    return true;
                }
                $contactTags = collect($contact->tags ?? [])->map(function ($tag): string {
                    if (is_array($tag)) {
                        return strtolower(trim((string) ($tag['name'] ?? $tag['label'] ?? $tag['id'] ?? '')));
                    }
                    return strtolower(trim((string) $tag));
                });
                return collect($tags)->every(fn ($tag) => $contactTags->contains(strtolower($tag)));
            })
            ->map(fn (TlContact $contact) => [
            'email' => $contact->primary_email,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'phone' => $contact->phone,
            'attributes' => ['teamleader_id' => $contact->id, 'tags' => $contact->tags],
            'source_id' => $contact->id,
            'source_data' => ['teamleader_id' => $contact->id, 'tags' => $contact->tags],
        ]);
    }

    private function fromHubspot(array $config): iterable
    {
        $listId = trim((string) ($config['hubspot_list_id'] ?? ''));
        if ($listId === '') {
            throw new \InvalidArgumentException('Indica el ID de la lista de HubSpot.');
        }

        return collect($this->hubspot->getMarketingListMembers($listId))->map(fn (array $contact) => [
            'email' => data_get($contact, 'properties.email'),
            'first_name' => data_get($contact, 'properties.firstname'),
            'last_name' => data_get($contact, 'properties.lastname'),
            'phone' => data_get($contact, 'properties.phone'),
            'attributes' => data_get($contact, 'properties', []),
            'source_id' => $contact['id'],
            'source_data' => ['hubspot_contact_id' => $contact['id'], 'hubspot_list_id' => $listId],
        ]);
    }

    private function fromCsv(?UploadedFile $file): iterable
    {
        if (!$file || !$file->isValid()) {
            throw new \InvalidArgumentException('Selecciona un archivo CSV válido.');
        }

        $handle = fopen($file->getRealPath(), 'rb');
        if (!$handle) {
            throw new \RuntimeException('No fue posible leer el CSV.');
        }

        $firstLine = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
        $headers = fgetcsv($handle, 0, $delimiter) ?: [];
        $headers = array_map(fn ($value) => Str::snake(trim((string) $value)), $headers);
        if ($headers) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
        }
        $aliases = ['correo' => 'email', 'e_mail' => 'email', 'nombre' => 'first_name', 'apellido' => 'last_name', 'telefono' => 'phone'];
        $headers = array_map(fn ($header) => $aliases[$header] ?? $header, $headers);

        $records = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = array_pad($row, count($headers), null);
            $data = array_combine($headers, array_slice($row, 0, count($headers))) ?: [];
            $records[] = [
                'email' => $data['email'] ?? null,
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'phone' => $data['phone'] ?? null,
                'attributes' => Arr::except($data, ['email', 'first_name', 'last_name', 'phone']),
                'source_id' => strtolower(trim((string) ($data['email'] ?? ''))),
                'source_data' => ['csv_row' => count($records) + 2],
            ];
        }
        fclose($handle);

        return $records;
    }
}

<?php

namespace App\Services;

use App\Models\ClientRestrictionScan;
use App\Models\CustomFieldValue;
use App\Models\TlContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TestClientRestrictionScanner
{
    private const PRIVATE_FIELDS = ['password', 'password_md5', 'remember_token', 'two_factor_secret',
        'two_factor_recovery_codes', 'stripe_cus_id', 'arraycos', 'profile_photo_path'];

    public function eligibleUsers()
    {
        // Never bulk-disable an employee, administrator or service account.
        return User::role('Cliente')->whereDoesntHave('roles', fn ($q) => $q->where('name', '<>', 'Cliente'));
    }

    public function matches(User $client, bool $partial = false, ?array $related = null): array
    {
        $values = collect($client->getAttributes())->except(self::PRIVATE_FIELDS)->all();
        if ($related !== null) {
            $values = array_merge($values, $related);
        } elseif (Schema::hasTable('custom_field_values')) {
            foreach (CustomFieldValue::forEntity('client', $client->id)->get() as $field) {
                $values['campo_adicional.'.$field->custom_field_definition_id] = $field->decoded_value;
            }
        }
        if ($related === null && filled($client->tl_id) && Schema::hasTable('tl_contacts')) {
            $contact = TlContact::find($client->tl_id);
            if ($contact) {
                $values['teamleader'] = $contact->getAttributes();
            }
        }
        $matches = [];
        $this->findMatches($values, '', $partial, $matches);

        return $matches;
    }

    public function preview(User $actor, bool $partial = false): ClientRestrictionScan
    {
        abort_unless($actor->hasRole('Administrador'), 403);
        $candidates = [];
        $hasCustomFields = Schema::hasTable('custom_field_values');
        $hasContacts = Schema::hasTable('tl_contacts');
        $this->eligibleUsers()->orderBy('users.id')->chunkById(200, function ($clients) use (&$candidates, $partial, $hasCustomFields, $hasContacts): void {
            $extra = [];
            if ($hasCustomFields) {
                foreach (CustomFieldValue::where('entity_type', 'client')->whereIn('entity_id', $clients->pluck('id'))->get() as $field) {
                    $extra[$field->entity_id]['campo_adicional.'.$field->custom_field_definition_id] = $field->decoded_value;
                }
            }
            $contacts = $hasContacts ? TlContact::whereIn('id', $clients->pluck('tl_id')->filter())->get()->keyBy('id') : collect();
            foreach ($clients as $client) {
                $related = $extra[$client->id] ?? [];
                if ($contacts->has($client->tl_id)) {
                    $related['teamleader'] = $contacts[$client->tl_id]->getAttributes();
                }
                $matches = $this->matches($client, $partial, $related);
                if ($matches !== []) {
                    $candidates[] = ['id' => $client->id, 'name' => $client->name, 'email' => $client->email,
                        'matches' => $matches, 'fingerprint' => $this->fingerprint($client, $matches)];
                }
            }
        }, 'users.id', 'id');

        return ClientRestrictionScan::create(['id' => (string) Str::uuid(), 'actor_id' => $actor->id,
            'partial' => $partial, 'candidates' => $candidates, 'expires_at' => now()->addMinutes(30)]);
    }

    public function apply(ClientRestrictionScan $scan, User $actor, array $ids): int
    {
        abort_unless($actor->hasRole('Administrador') && $scan->actor_id === $actor->id, 403);

        return DB::transaction(function () use ($scan, $actor, $ids): int {
            $scan = ClientRestrictionScan::whereKey($scan->id)->lockForUpdate()->firstOrFail();
            if ($scan->applied_at || $scan->expires_at->isPast()) {
                throw ValidationException::withMessages(['scan' => 'Esta vista previa ya fue aplicada o venció. Genera una nueva.']);
            }
            $preview = collect($scan->candidates)->keyBy('id');
            $clients = [];
            foreach (array_unique($ids) as $id) {
                $client = $this->eligibleUsers()->whereKey($id)->lockForUpdate()->first();
                $matches = $client ? $this->matches($client, $scan->partial) : [];
                if (! $client || ! $preview->has($id) || $matches === [] ||
                    ! hash_equals($preview[$id]['fingerprint'], $this->fingerprint($client, $matches))) {
                    throw ValidationException::withMessages(['scan' => 'Un registro cambió o no pertenece a esta vista previa. No se aplicó el lote; vuelve a revisarlo.']);
                }
                $clients[] = $client;
            }
            $count = 0;
            foreach ($clients as $client) {
                $count += (int) app(ClientRestrictionService::class)->block($client, 'test_data', null,
                    'test_scan', $scan->id, 'Campos coincidentes: '.implode(', ', array_keys($preview[$client->id]['matches'])), $actor);
            }
            $scan->update(['applied_at' => now()]);

            return $count;
        });
    }

    private function fingerprint(User $client, array $matches): string
    {
        return hash('sha256', json_encode([$client->getAttributes(), $matches], JSON_UNESCAPED_UNICODE));
    }

    private function findMatches(mixed $value, string $path, bool $partial, array &$matches): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                if (! in_array((string) $key, self::PRIVATE_FIELDS, true)) {
                    $this->findMatches($child, ltrim($path.'.'.$key, '.'), $partial, $matches);
                }
            }
            return;
        }
        if (! is_string($value)) {
            return;
        }
        $pattern = $partial ? '/prueba|test/iu' : '/(?<!\pL)(?:pruebas?|tests?)\d*(?!\pL)/iu';
        if (preg_match($pattern, $value)) {
            $matches[$path] = Str::limit($value, 160);
        }
    }
}

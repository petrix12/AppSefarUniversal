<?php

namespace App\Services;

use App\Models\ClientRestrictionEvent;
use App\Models\ClientRestrictionSync;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientRestrictionService
{
    public function canReview(?User $actor): bool
    {
        return $actor && ($actor->hasRole('Administrador') ||
            ($actor->getRoleNames()->contains(fn ($role) => $role !== 'Cliente')
                && $actor->getAllPermissions()->contains('name', 'clients.blocks.review')));
    }

    public function scopeVisible(Builder $query, ?User $actor): Builder
    {
        return ! Schema::hasTable('user_access_blocks') || $this->canReview($actor)
            ? $query : $query->whereDoesntHave('accessBlock');
    }

    public function assertReadable(User $client, ?User $actor): void
    {
        abort_if($this->isRestricted($client) && ! $this->canReview($actor), 403,
            'Expediente bloqueado. Solo el personal autorizado puede revisar este caso.');
    }

    public function assertDeliverable(User $client): void
    {
        abort_if($this->isRestricted($client), 423,
            'Entregas suspendidas: este expediente está bloqueado hasta que la empresa resuelva su situación.');
    }

    public function isRestricted(User $client): bool
    {
        return Schema::hasTable('user_access_blocks') && $client->accessBlock()->exists();
    }

    public function assertEmailDeliverable(string $email): void
    {
        if (Schema::hasTable('user_access_blocks') && User::query()
            ->where(function ($query) use ($email): void {
                $query->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))]);
                if (Schema::hasColumn('users', 'email_2')) {
                    $query->orWhereRaw('LOWER(email_2) = ?', [mb_strtolower(trim($email))]);
                }
            })->whereHas('accessBlock')->exists()) {
            abort(423, 'No se permite enviar información a un cliente bloqueado.');
        }
    }

    public function assertContactDeliverable(array $contact): void
    {
        if (filled($contact['email'] ?? null)) {
            $this->assertEmailDeliverable((string) $contact['email']);
        }
        $phone = preg_replace('/\D/', '', (string) ($contact['phone'] ?? ''));
        if ($phone !== '' && Schema::hasTable('user_access_blocks')) {
            $blocked = User::whereHas('accessBlock')->whereNotNull('phone')->get(['id', 'phone']);
            abort_if($blocked->contains(fn ($client) => preg_replace('/\D/', '', (string) $client->phone) === $phone), 423,
                'No se permite enviar información a un cliente bloqueado.');
        }
    }

    public function block(User $client, string $reason, ?string $publicExplanation = null,
        string $source = 'manual', ?string $reference = null, ?string $internalNote = null, ?User $actor = null): bool
    {
        abort_unless(Schema::hasTable('client_restriction_events'), 503, 'Aplica primero la migración de restricciones de clientes.');
        abort_unless($client->isCliente(), 422, 'El registro debe corresponder a un cliente.');

        return DB::transaction(function () use ($client, $reason, $publicExplanation, $source, $reference, $internalNote, $actor): bool {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            // Automatic detection never replaces the reason for a manual hold.
            if ($client->accessBlock()->exists()) {
                return false;
            }

            $client->accessBlock()->create([
                'reason' => $reason, 'reason_other' => $publicExplanation,
                'source' => $source, 'source_reference' => $reference, 'internal_note' => $internalNote,
                'blocked_at' => now(), 'blocked_by_user_id' => $actor?->id,
            ]);
            $client->unsetRelation('accessBlock');
            $this->record($client, $actor, 'blocked', $source, ['reason' => $reason, 'reference' => $reference, 'internal_note' => $internalNote]);
            $this->queuePropagation($client, true);

            return true;
        });
    }

    public function unblock(User $client, User $actor, string $explanation): void
    {
        abort_unless($actor->hasRole('Administrador'), 403);
        if (mb_strlen(trim($explanation)) < 10) {
            throw ValidationException::withMessages(['explanation' => 'Explica cómo se resolvió la situación (al menos 10 caracteres).']);
        }

        DB::transaction(function () use ($client, $actor, $explanation): void {
            User::whereKey($client->id)->lockForUpdate()->firstOrFail();
            if (! $client->accessBlock()->exists()) {
                return;
            }
            $previous = $client->accessBlock()->first()->toArray();
            $client->accessBlock()->delete();
            $client->unsetRelation('accessBlock');
            if (filled($client->hs_id)) {
                \Illuminate\Support\Facades\Cache::forget('hubspot_data_'.$client->hs_id);
            }
            $this->record($client, $actor, 'unblocked', 'manual', ['explanation' => trim($explanation), 'previous' => $previous]);
            $this->queuePropagation($client, false);
        });
    }

    public function detectHubspotRetirement(User $client, array $contact, array $deals): bool
    {
        if (! Schema::hasTable('client_restriction_events')) {
            return false;
        }
        $retired = collect($deals)->filter(fn ($deal) => $this->isAffirmative(data_get($deal, 'properties.el_cliente_se_encuentra_retirado')));
        $contactRetired = $this->isAffirmative(data_get($contact, 'properties.retirado'));
        if (! $contactRetired && $retired->isEmpty()) {
            // A stale/empty response never lifts an existing restriction.
            return false;
        }

        return $this->block($client, 'retired', null, 'hubspot',
            $retired->map(fn ($deal) => (string) data_get($deal, 'id'))->implode(','),
            $retired->map(fn ($deal) => (string) data_get($deal, 'properties.motivo_del_retiro'))->filter()->unique()->implode(' | '));
    }

    public function isAffirmative(mixed $value): bool
    {
        return is_scalar($value) && in_array(Str::lower(Str::ascii(trim((string) $value))), ['true', '1', 'si', 'yes', 'retirado', 'retirada'], true);
    }

    private function record(User $client, ?User $actor, string $action, string $source, array $details): void
    {
        ClientRestrictionEvent::create(['user_id' => $client->id, 'actor_id' => $actor?->id,
            'action' => $action, 'source' => $source, 'details' => $details]);
    }

    private function queuePropagation(User $client, bool $blocked): void
    {
        foreach (['hubspot', 'monday', 'teamleader'] as $platform) {
            ClientRestrictionSync::updateOrCreate(['user_id' => $client->id, 'platform' => $platform], [
                'version' => (string) Str::uuid(), 'blocked' => $blocked, 'status' => 'pending',
                'attempts' => 0, 'last_error' => null, 'synced_at' => null, 'retry_at' => now(),
            ]);
        }
    }
}

<?php

namespace App\Services;

use App\Models\TlContact;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TeamleaderContactResolver
{
    public function resolve(User $user, TeamleaderService $teamleaderService): ?string
    {
        $user->refresh();

        if (filled($user->tl_id)) {
            $contact = TlContact::query()->find((string) $user->tl_id);

            if ($contact) {
                return (string) $contact->id;
            }

            $apiContact = $teamleaderService->getContactById((string) $user->tl_id);

            if ($apiContact && filled($apiContact['id'] ?? null)) {
                $this->rememberApiContact($apiContact, $teamleaderService);

                return (string) $apiContact['id'];
            }
        }

        $localContact = $this->findLocalContactByEmail($user);

        if ($localContact) {
            return $this->linkUser($user, (string) $localContact->id, 'local_backup');
        }

        foreach ($this->candidateEmails($user) as $email) {
            try {
                $apiContact = $teamleaderService->searchContactByEmail($email);
            } catch (\Throwable $e) {
                Log::channel('teamleader')->warning('Teamleader: no se pudo buscar contacto por email', [
                    'user_id' => $user->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            if ($apiContact && filled($apiContact['id'] ?? null)) {
                $this->rememberApiContact($apiContact, $teamleaderService);

                return $this->linkUser($user, (string) $apiContact['id'], 'api_email');
            }
        }

        Log::channel('teamleader')->info('Teamleader: contacto no encontrado; se omite sincronizacion TL', [
            'user_id' => $user->id,
            'emails' => $this->candidateEmails($user),
        ]);

        return null;
    }

    private function findLocalContactByEmail(User $user): ?TlContact
    {
        foreach ($this->candidateEmails($user) as $email) {
            $contact = TlContact::query()
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', 'deleted'))
                ->where(function ($query) use ($email) {
                    $query
                        ->whereRaw('LOWER(TRIM(email)) = ?', [$email])
                        ->orWhereRaw('LOWER(CAST(emails AS CHAR)) LIKE ?', ['%' . $email . '%'])
                        ->orWhereRaw('LOWER(CAST(raw_data AS CHAR)) LIKE ?', ['%' . $email . '%']);
                })
                ->orderByDesc('tl_updated_at')
                ->first();

            if ($contact) {
                return $contact;
            }
        }

        return null;
    }

    private function candidateEmails(User $user): array
    {
        return collect([
            $user->email,
            $user->email_2 ?? null,
            $user->email_alternativo ?? null,
        ])
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function linkUser(User $user, string $teamleaderId, string $source): string
    {
        if ((string) $user->tl_id !== $teamleaderId) {
            $user->forceFill(['tl_id' => $teamleaderId])->save();
            Cache::forget("teamleader_data_{$user->id}");

            Log::channel('teamleader')->info('Teamleader: tl_id rellenado en usuario', [
                'user_id' => $user->id,
                'tl_id' => $teamleaderId,
                'source' => $source,
            ]);
        }

        return $teamleaderId;
    }

    private function rememberApiContact(array $contact, TeamleaderService $teamleaderService): void
    {
        $id = $contact['id'] ?? null;

        if (! $id) {
            return;
        }

        $details = $teamleaderService->getContactById((string) $id) ?: $contact;

        try {
            TlContact::fromTeamleader($details);
        } catch (\Throwable $e) {
            Log::channel('teamleader')->warning('Teamleader: no se pudo guardar contacto API en respaldo local', [
                'tl_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

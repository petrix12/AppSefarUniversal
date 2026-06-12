<?php

namespace App\Services;

use App\Models\HubspotOwner;
use App\Models\HubspotOwnerUser;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HubspotUserProvisioningService
{
    public function __construct(
        private readonly HubspotService $hubspot
    ) {}

    public function provisionCoordinator(User $user): array
    {
        if (! config('services.hubspot.coordinator_user_provisioning.enabled', true)) {
            return ['status' => 'disabled'];
        }

        $email = strtolower(trim((string) $user->email));

        if ($email === '') {
            throw new \InvalidArgumentException('El coordinador no tiene email para provisionar en HubSpot.');
        }

        $existingOwner = $this->trySyncOwnerLinkIfAvailable($user, $email);

        if ($existingOwner) {
            $this->markProvisioned($user, null);

            return [
                'status' => 'already_owner',
                'hubspot_owner_id' => (string) $existingOwner['id'],
            ];
        }

        try {
            $existingHubspotUser = $this->hubspot->findUserByEmail($email);
        } catch (\RuntimeException $e) {
            Log::warning('No se pudo verificar usuario existente en HubSpot antes de provisionar.', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            $existingHubspotUser = null;
        }

        if ($existingHubspotUser) {
            $owner = $this->trySyncOwnerLinkIfAvailable($user, $email);
            $this->markProvisioned($user, $existingHubspotUser['id'] ?? null);

            return [
                'status' => 'already_user',
                'hubspot_user_id' => $existingHubspotUser['id'] ?? null,
                'hubspot_owner_id' => $owner['id'] ?? null,
            ];
        }

        try {
            $createdUser = $this->hubspot->createUser($this->payloadForCoordinator($user, $email));
            $hubspotUserId = (string) ($createdUser['id'] ?? $createdUser['userId'] ?? '');

            $this->markProvisioned($user, $hubspotUserId !== '' ? $hubspotUserId : null);

            $owner = $this->trySyncOwnerLinkIfAvailable($user, $email);

            return [
                'status' => 'created',
                'hubspot_user_id' => $hubspotUserId !== '' ? $hubspotUserId : null,
                'hubspot_owner_id' => $owner['id'] ?? null,
            ];
        } catch (\RuntimeException $e) {
            if ((int) $e->getCode() === 409) {
                try {
                    $existingHubspotUser = $this->hubspot->findUserByEmail($email);
                } catch (\RuntimeException $lookupException) {
                    Log::warning('Usuario HubSpot ya existia, pero no se pudo leer por email.', [
                        'user_id' => $user->id,
                        'email' => $email,
                        'error' => $lookupException->getMessage(),
                    ]);
                    $existingHubspotUser = null;
                }

                $owner = $this->trySyncOwnerLinkIfAvailable($user, $email);
                $this->markProvisioned($user, $existingHubspotUser['id'] ?? null);

                return [
                    'status' => 'already_user',
                    'hubspot_user_id' => $existingHubspotUser['id'] ?? null,
                    'hubspot_owner_id' => $owner['id'] ?? null,
                ];
            }

            $this->markProvisioningError($user, $e->getMessage());
            throw $e;
        }
    }

    private function payloadForCoordinator(User $user, string $email): array
    {
        $config = config('services.hubspot.coordinator_user_provisioning', []);

        $payload = [
            'email' => $email,
            'sendWelcomeEmail' => (bool) ($config['send_welcome_email'] ?? true),
        ];

        if (! empty($config['role_id'])) {
            $payload['roleId'] = (string) $config['role_id'];
        }

        if (! empty($config['primary_team_id'])) {
            $payload['primaryTeamId'] = (string) $config['primary_team_id'];
        }

        if (! empty($config['secondary_team_ids'])) {
            $payload['secondaryTeamIds'] = array_values(array_map('strval', $config['secondary_team_ids']));
        }

        Log::channel('tasks')->info('Payload de provisioning HubSpot para coordinador preparado', [
            'user_id' => $user->id,
            'email' => $email,
            'has_role_id' => ! empty($payload['roleId']),
            'has_primary_team_id' => ! empty($payload['primaryTeamId']),
            'secondary_team_ids_count' => count($payload['secondaryTeamIds'] ?? []),
        ]);

        return $payload;
    }

    private function trySyncOwnerLinkIfAvailable(User $user, string $email): ?array
    {
        try {
            return $this->syncOwnerLinkIfAvailable($user, $email);
        } catch (\RuntimeException $e) {
            Log::warning('No se pudo enlazar owner HubSpot para coordinador.', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function syncOwnerLinkIfAvailable(User $user, string $email): ?array
    {
        $owner = $this->hubspot->findOwnerByEmail($email);

        if (! $owner || empty($owner['id'])) {
            return null;
        }

        $ownerName = trim(
            ($owner['firstName'] ?? '') . ' ' . ($owner['lastName'] ?? '')
        );

        if ($ownerName === '') {
            $ownerName = $owner['name'] ?? $user->name;
        }

        HubspotOwner::updateOrCreate(
            ['id' => (string) $owner['id']],
            [
                'email' => $owner['email'] ?? $email,
                'name' => $ownerName,
                'active' => $owner['active'] ?? true,
                'hubspot_created_at' => ! empty($owner['createdAt']) ? \Carbon\Carbon::parse($owner['createdAt']) : null,
                'hubspot_updated_at' => ! empty($owner['updatedAt']) ? \Carbon\Carbon::parse($owner['updatedAt']) : null,
            ]
        );

        HubspotOwnerUser::where('hubspot_owner_id', (string) $owner['id'])
            ->where('user_id', '!=', $user->id)
            ->delete();

        HubspotOwnerUser::updateOrCreate(
            ['user_id' => $user->id],
            [
                'hubspot_owner_id' => (string) $owner['id'],
                'hubspot_owner_name' => $ownerName,
            ]
        );

        $updates = [];

        if (Schema::hasColumn('users', 'hubspot_owner_id')) {
            $updates['hubspot_owner_id'] = (string) $owner['id'];
        }

        if (! empty($updates)) {
            $user->forceFill($updates)->save();
        }

        return $owner;
    }

    private function markProvisioned(User $user, ?string $hubspotUserId): void
    {
        $updates = [];

        if ($hubspotUserId && Schema::hasColumn('users', 'hubspot_user_id')) {
            $updates['hubspot_user_id'] = $hubspotUserId;
        }

        if (Schema::hasColumn('users', 'hubspot_user_provisioned_at')) {
            $updates['hubspot_user_provisioned_at'] = now();
        }

        if (Schema::hasColumn('users', 'hubspot_user_provisioning_error')) {
            $updates['hubspot_user_provisioning_error'] = null;
        }

        if (! empty($updates)) {
            $user->forceFill($updates)->save();
        }
    }

    private function markProvisioningError(User $user, string $error): void
    {
        if (! Schema::hasColumn('users', 'hubspot_user_provisioning_error')) {
            return;
        }

        $user->forceFill([
            'hubspot_user_provisioning_error' => mb_substr($error, 0, 4000),
        ])->save();
    }
}

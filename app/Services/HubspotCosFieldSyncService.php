<?php

namespace App\Services;

use App\Exceptions\CosIdentityConflictException;
use App\Models\Negocio;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/** Synchronizes only HubSpot contacts already linked to App clients and their deals. */
class HubspotCosFieldSyncService
{
    private const CONTACT_FIELDS = [
        'firstname' => 'nombres', 'lastname' => 'apellidos', 'email' => 'email',
        'phone' => 'phone', 'numero_de_pasaporte' => 'passport', 'fecha_nac' => 'date_of_birth',
        'servicio_solicitado' => 'servicio', 'n000__referido_por__clonado_' => 'referido_por',
        'email_2' => 'email_2', 'address' => 'address', 'city' => 'city',
        'pais_de_residencia' => 'pais_de_residencia',
    ];

    public function __construct(
        private HubspotService $hubspot,
        private HubspotDealLocalSyncService $dealSync,
    ) {
    }

    /** @return array{status: string, deals: int, cosready?: bool} */
    public function syncUser(User $user, bool $includeDeals = true, ?string $dealId = null): array
    {
        if (! $user->hasRole('Cliente') || ! is_string($user->hs_id) || preg_match('/^[0-9]{1,64}$/D', $user->hs_id) !== 1) {
            return ['status' => 'unlinked', 'deals' => 0];
        }

        $linkedUsers = User::query()->where('hs_id', $user->hs_id)->limit(2)->get();
        if ($linkedUsers->count() !== 1 || (int) $linkedUsers->first()->getKey() !== (int) $user->getKey()) {
            Log::warning('HubSpot COS sync skipped an ambiguous contact ID.', ['user_id' => $user->getKey()]);
            return ['status' => 'conflict', 'deals' => 0];
        }

        $contact = $this->hubspot->webhookObject('contacts', $user->hs_id, [...array_keys(self::CONTACT_FIELDS), 'hs_merged_object_ids']);
        if ($contact === null || ($contact['archived'] ?? false)) {
            return ['status' => 'missing', 'deals' => 0];
        }
        if ((string) ($contact['id'] ?? '') !== $user->hs_id) {
            throw new \UnexpectedValueException('HubSpot returned a different contact ID.');
        }

        try {
            app(HubspotClientIdentityGuard::class)->assertForUser($user, $contact);
        } catch (CosIdentityConflictException) {
            Log::warning('HubSpot COS sync skipped a contact with a conflicting identity.', ['user_id' => $user->getKey()]);
            return ['status' => 'conflict', 'deals' => 0];
        }
        $properties = (array) ($contact['properties'] ?? []);
        if ($this->hasSecondaryEmailConflict($user, $properties)) {
            Log::warning('HubSpot COS: correo secundario en conflicto.', ['user_id' => $user->getKey()]);

            return ['status' => 'conflict', 'deals' => 0];
        }

        $deals = [];
        if ($includeDeals) {
            $dealIds = $dealId !== null ? [$dealId] : $this->hubspot->webhookAssociations('contacts', $user->hs_id, 'deals');
            if ($dealIds !== []) {
                $deals = $this->hubspot->getDealsByIds($dealIds, [...self::localDealColumns(), 'dealname']);
            }
            if (count($deals) !== count($dealIds)) {
                return ['status' => 'missing_deal', 'deals' => 0];
            }
        }

        HubspotSyncOrigin::fromHubspot(fn () => $this->applyContactProperties($user, $properties));
        if ($deals !== []) {
            $this->dealSync->sync($user, $deals, true);
        }
        Cache::forget('hubspot_data_'.$user->hs_id);
        Cache::forget('cos.portfolio.v2.all');
        Cache::forget('cos.portfolio.v2.'.$user->owner_id);
        $ready = app(ClientCosSnapshotService::class)->rebuildLocal($user);

        return ['status' => 'synced', 'deals' => count($deals), 'cosready' => $ready];
    }

    public function ingestEvent(array $event): array
    {
        if (! self::supportsEvent($event)) {
            return ['status' => 'unsupported', 'users' => 0];
        }

        $type = (string) ($event['subscriptionType'] ?? $event['type'] ?? '');
        $objectId = (string) ($event['objectId'] ?? '');
        $contactIds = [];
        $dealId = null;
        if (preg_match('/\.(deletion|archived|merge)$/', $type)) {
            Log::info('HubSpot COS: evento de archivo o fusión recibido para revisión.', ['type' => $type, 'hubspot_id' => $objectId]);
            return ['status' => 'review', 'users' => 0];
        }
        if ($event['associationRemoved'] ?? false) {
            return ['status' => 'review', 'users' => 0];
        }

        if (str_ends_with($type, '.associationChange') && ! ($event['genericWebhook'] ?? false)) {
            $contactIsSource = strtoupper((string) ($event['associationType'] ?? '')) === 'CONTACT_TO_DEAL';
            $contactId = (string) ($event[$contactIsSource ? 'fromObjectId' : 'toObjectId'] ?? '');
            $dealId = (string) ($event[$contactIsSource ? 'toObjectId' : 'fromObjectId'] ?? '');
            $contactIds = array_intersect([$contactId], $this->hubspot->webhookAssociations('deals', $dealId, 'contacts'));
        } elseif (str_starts_with($type, 'contact.')) {
            $contactId = $objectId;
            if (ctype_digit($contactId)) {
                $contactIds[] = $contactId;
            }
        } elseif (ctype_digit($objectId)) {
            $dealId = $objectId;
            $contactIds = $this->hubspot->webhookAssociations('deals', $dealId, 'contacts');
        }

        $contactIds = array_values(array_unique(array_filter(array_map('strval', $contactIds), 'ctype_digit')));
        $users = User::query()->clients()->whereIn('hs_id', $contactIds)->get();
        if ($dealId !== null && $users->count() > 1) {
            $owners = Negocio::where('hubspot_id', $dealId)->whereNotNull('user_id')->distinct()->pluck('user_id');
            $users = $owners->count() === 1 ? $users->where('id', $owners->first()) : collect();
            if ($users->isEmpty()) {
                Log::warning('HubSpot COS: negocio con varios representados, asociación pendiente de revisión.', ['hubspot_id' => $dealId]);
                return ['status' => 'review', 'users' => 0];
            }
        }
        $synced = 0;
        $conflicts = 0;
        foreach ($users as $user) {
            $includeDeals = $type !== 'contact.propertyChange';
            $result = $this->syncUser($user, $includeDeals, $dealId);
            $synced += $result['status'] === 'synced' ? 1 : 0;
            $conflicts += in_array($result['status'], ['conflict', 'missing', 'missing_deal'], true) ? 1 : 0;
        }

        return ['status' => $conflicts > 0 ? 'review' : 'processed', 'users' => $synced, 'conflicts' => $conflicts];
    }

    public static function supportsEvent(array $event): bool
    {
        $type = (string) ($event['subscriptionType'] ?? $event['type'] ?? '');

        if (preg_match('/^(contact|deal)\.(creation|propertyChange|associationChange|merge|deletion|archived|restore)$/', $type) !== 1) {
            return false;
        }
        if ($type === 'contact.propertyChange') {
            $property = $event['propertyName'] ?? '';
            return is_string($property) && isset(self::CONTACT_FIELDS[$property]);
        }
        if ($type === 'deal.propertyChange') {
            $property = $event['propertyName'] ?? '';
            return is_string($property) && ($property === 'amount'
                || in_array($property, CosCrmSyncFields::HUBSPOT_DEAL, true)
                || in_array($property, self::localDealColumns(), true));
        }
        if (! str_ends_with($type, '.associationChange')) {
            return true;
        }
        if ($event['genericWebhook'] ?? false) {
            return in_array([
                HubspotWebhookEventNormalizer::objectType($event['fromObjectTypeId'] ?? null),
                HubspotWebhookEventNormalizer::objectType($event['toObjectTypeId'] ?? null),
            ], [['contact', 'deal'], ['deal', 'contact']], true);
        }

        return in_array(strtoupper((string) ($event['associationType'] ?? '')), ['CONTACT_TO_DEAL', 'DEAL_TO_CONTACT'], true);
    }

    private function applyContactProperties(User $user, array $properties): void
    {
        $columns = array_flip(Schema::getColumnListing($user->getTable()));
        $updates = [];

        foreach (self::CONTACT_FIELDS as $property => $column) {
            $value = $properties[$property] ?? null;
            if (isset($columns[$column]) && is_scalar($value) && trim((string) $value) !== '') {
                $updates[$column] = $column === 'date_of_birth' ? substr((string) $value, 0, 10) : $value;
            }
        }

        $user->forceFill($updates);
        if ($user->isDirty()) {
            $user->save();
        }
    }

    private static function localDealColumns(): array
    {
        return Cache::remember('crm.hubspot.cos-fields.deal-columns', 600, fn () => Schema::getColumnListing((new Negocio)->getTable()));
    }

    private function hasSecondaryEmailConflict(User $user, array $properties): bool
    {
        $emails = array_values(array_filter(array_map(
            fn ($value) => is_string($value) ? mb_strtolower(trim($value)) : '',
            [$properties['email'] ?? null, $properties['email_2'] ?? null],
        )));
        if ($emails === []) {
            return false;
        }

        $columns = array_intersect(['email', 'email_2', 'email_alternativo'], Schema::getColumnListing($user->getTable()));
        return User::query()->whereKeyNot($user->getKey())->where(function ($query) use ($columns, $emails): void {
            foreach ($columns as $column) {
                $query->orWhereIn(\Illuminate\Support\Facades\DB::raw("LOWER(TRIM({$column}))"), $emails);
            }
        })->exists();
    }
}

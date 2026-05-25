<?php

namespace App\Jobs;

use App\Models\Negocio;
use App\Models\Task;
use App\Models\User;
use App\Services\HubspotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BulkReassignContactsToAdvisorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const ALERT_EMAIL = 'sistemasccs@gmail.com';

    public int $tries = 1;
    public int $timeout = 1800;
    private ?bool $userReassignmentColumnExists = null;

    public function __construct(
        private array $contactIds,
        private int $advisorUserId,
        private string $hubspotOwnerId,
        private array $options = [],
        private ?int $adminUserId = null
    ) {
        $this->contactIds = array_values(array_unique(array_map('intval', $this->contactIds)));
    }

    public function handle(HubspotService $hubspot): void
    {
        $contacts = User::query()
            ->whereIn('id', $this->contactIds)
            ->get(['id', 'name', 'email', 'hs_id', 'owner_id']);

        if ($contacts->isEmpty()) {
            Log::channel('tasks')->warning('Reasignacion masiva sin contactos encontrados', [
                'admin_user_id' => $this->adminUserId,
                'advisor_user_id' => $this->advisorUserId,
                'input_contact_ids' => $this->contactIds,
            ]);

            return;
        }

        $contactIds = $contacts->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $dryRun = (bool) ($this->options['dry_run'] ?? false);
        $cancelPendingTasks = (bool) ($this->options['cancel_pending_tasks'] ?? true);
        $updateHubspot = (bool) ($this->options['update_hubspot'] ?? true);
        $updateDeals = (bool) ($this->options['update_deals'] ?? true);
        $respectNoHubspotLists = (bool) ($this->options['respect_no_hubspot_lists'] ?? true);

        $blockedForHubspot = $respectNoHubspotLists
            ? $this->contactsBlockedForHubspot($contactIds)
            : [];

        $hubspotContactIds = $contacts
            ->reject(fn (User $contact) => in_array((int) $contact->id, $blockedForHubspot, true))
            ->pluck('hs_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        $result = [
            'admin_user_id' => $this->adminUserId,
            'advisor_user_id' => $this->advisorUserId,
            'hubspot_owner_id' => $this->hubspotOwnerId,
            'dry_run' => $dryRun,
            'contacts_found' => count($contactIds),
            'hubspot_contact_ids' => count($hubspotContactIds),
            'hubspot_blocked_by_list' => count($blockedForHubspot),
            'hubspot_missing_id' => $contacts->filter(fn (User $contact) => empty($contact->hs_id))->count(),
            'local_users_updated' => 0,
            'pending_tasks_canceled' => 0,
            'hubspot_contacts_updated' => 0,
            'hubspot_deals_updated' => 0,
        ];

        if ($dryRun) {
            Log::channel('tasks')->info('Dry-run de reasignacion masiva de contactos', $result);
            return;
        }

        DB::transaction(function () use ($contactIds, $cancelPendingTasks, $updateDeals, &$result) {
            $result['local_users_updated'] = User::query()
                ->whereIn('id', $contactIds)
                ->update($this->ownerUpdateAttributes($this->advisorUserId));

            if ($cancelPendingTasks) {
                $result['pending_tasks_canceled'] = Task::query()
                    ->whereIn('contact_id', $contactIds)
                    ->where('status', Task::STATUS_PENDING)
                    ->where('user_id', '!=', $this->advisorUserId)
                    ->update(['status' => Task::STATUS_CANCELED]);
            }

            if (
                $updateDeals
                &&
                Schema::hasTable('negocios')
                && Schema::hasColumn('negocios', 'hubspot_owner_id')
            ) {
                Negocio::query()
                    ->whereIn('user_id', $contactIds)
                    ->update(['hubspot_owner_id' => $this->hubspotOwnerId]);
            }
        });

        if ($updateHubspot && ! empty($hubspotContactIds)) {
            $result['hubspot_contacts_updated'] = $hubspot->batchUpdateContactOwners(
                $hubspotContactIds,
                $this->hubspotOwnerId
            );

            if ($updateDeals) {
                $dealIds = $hubspot->getDealIdsByContactIds($hubspotContactIds);
                $result['hubspot_deals_updated'] = $hubspot->batchUpdateDealOwners(
                    $dealIds,
                    $this->hubspotOwnerId
                );
            }
        }

        Log::channel('tasks')->info('Reasignacion masiva de contactos completada', $result);
    }

    public function failed(Throwable $e): void
    {
        $key = 'tasks-bulk-reassign-alert:' . md5($this->advisorUserId . '|' . json_encode($this->contactIds) . '|' . $e->getMessage());

        if (! Cache::add($key, true, now()->addDay())) {
            return;
        }

        Log::channel('tasks')->error('Fallo job de reasignacion masiva de contactos', [
            'admin_user_id' => $this->adminUserId,
            'advisor_user_id' => $this->advisorUserId,
            'hubspot_owner_id' => $this->hubspotOwnerId,
            'contact_ids_count' => count($this->contactIds),
            'options' => $this->options,
            'error' => $e->getMessage(),
        ]);

        $body = implode("\n", [
            'Fallo el job de reasignacion masiva de contactos.',
            '',
            "Asesor destino user_id: {$this->advisorUserId}",
            "HubSpot owner id: {$this->hubspotOwnerId}",
            'Admin que lo lanzo: ' . ($this->adminUserId ?: 'N/A'),
            'Contactos: ' . count($this->contactIds),
            'Fecha: ' . now()->toDateTimeString(),
            '',
            'Error:',
            get_class($e) . ': ' . $e->getMessage(),
            '',
            'Opciones:',
            json_encode($this->options, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            '',
            'Primeros IDs:',
            implode(', ', array_slice($this->contactIds, 0, 100)),
        ]);

        try {
            Mail::raw($body, function ($message) {
                $message->to(self::ALERT_EMAIL)->subject('[App Sefar] Fallo reasignacion masiva');
            });
        } catch (Throwable $mailError) {
            Log::channel('tasks')->error('No se pudo enviar alerta de fallo de reasignacion masiva', [
                'recipient' => self::ALERT_EMAIL,
                'mail_error' => $mailError->getMessage(),
                'original_error' => $e->getMessage(),
            ]);
        }
    }

    private function contactsBlockedForHubspot(array $contactIds): array
    {
        if (! Schema::hasTable('list_user') || ! Schema::hasTable('lists')) {
            return [];
        }

        return DB::table('list_user as lu')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->whereIn('lu.user_id', $contactIds)
            ->where('l.disable_hubspot_reassignment', true)
            ->pluck('lu.user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function ownerUpdateAttributes(int $advisorId): array
    {
        $attributes = ['owner_id' => $advisorId];

        if ($this->userReassignmentColumnExists()) {
            $attributes['last_task_reassigned_at'] = now();
        }

        return $attributes;
    }

    private function userReassignmentColumnExists(): bool
    {
        if ($this->userReassignmentColumnExists === null) {
            $this->userReassignmentColumnExists = Schema::hasColumn('users', 'last_task_reassigned_at');
        }

        return $this->userReassignmentColumnExists;
    }
}

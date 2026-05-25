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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class BulkReassignContactsToAdvisorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

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
            Log::warning('Reasignacion masiva sin contactos encontrados', [
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
            Log::info('Dry-run de reasignacion masiva de contactos', $result);
            return;
        }

        DB::transaction(function () use ($contactIds, $cancelPendingTasks, $updateDeals, &$result) {
            $result['local_users_updated'] = User::query()
                ->whereIn('id', $contactIds)
                ->update(['owner_id' => $this->advisorUserId]);

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

        Log::info('Reasignacion masiva de contactos completada', $result);
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
}

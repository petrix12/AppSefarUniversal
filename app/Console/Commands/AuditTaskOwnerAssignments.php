<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\HubspotService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditTaskOwnerAssignments extends Command
{
    protected $signature = 'tasks:audit-owner-assignments
        {--date= : Filtra tareas por due_date YYYY-MM-DD}
        {--status=open : open|pending|in_progress|completed|canceled|all}
        {--limit=200 : Maximo de contactos a revisar}';

    protected $description = 'Audita tareas contra owner local y hubspot_owner_id sin escribir cambios.';

    public function handle(HubspotService $hubspot): int
    {
        $status = (string) $this->option('status');
        $allowedStatuses = ['open', 'pending', 'in_progress', 'completed', 'canceled', 'all'];

        if (! in_array($status, $allowedStatuses, true)) {
            $this->error('Status invalido. Usa open|pending|in_progress|completed|canceled|all.');
            return self::FAILURE;
        }

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : null;

        $limit = max(1, (int) $this->option('limit'));

        $tasks = Task::query()
            ->with([
                'assignee:id,name,email',
                'contact:id,name,email,hs_id,owner_id',
            ])
            ->whereNotNull('contact_id')
            ->notAssignedToSystems()
            ->when($date, fn ($query) => $query->whereDate('due_date', $date))
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'open') {
                    $query->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS]);
                } else {
                    $query->where('status', $status);
                }
            })
            ->orderByDesc('id')
            ->get();

        $contacts = $tasks
            ->pluck('contact')
            ->filter()
            ->unique('id')
            ->take($limit)
            ->values();

        if ($contacts->isEmpty()) {
            $this->info('No hay tareas para auditar.');
            return self::SUCCESS;
        }

        $ownerMap = DB::table('hubspot_owner_user as hou')
            ->join('users as u', 'u.id', '=', 'hou.user_id')
            ->join('hubspot_owners as ho', 'ho.id', '=', 'hou.hubspot_owner_id')
            ->where('ho.active', true)
            ->select([
                'hou.hubspot_owner_id as hs_owner_id',
                'u.id as user_id',
                'u.name as user_name',
            ])
            ->get()
            ->keyBy(fn ($row) => (string) $row->hs_owner_id);

        $listPolicy = $this->taskPoolListPolicy($contacts->pluck('id')->map(fn ($id) => (int) $id)->all());

        $stats = [
            'contacts_reviewed' => 0,
            'tasks_reviewed' => $tasks->count(),
            'hubspot_not_found' => 0,
            'hubspot_missing_owner' => 0,
            'hubspot_owner_unmapped' => 0,
            'task_assignee_mismatch_hubspot' => 0,
            'local_owner_mismatch_hubspot' => 0,
            'api_errors' => 0,
        ];

        $examples = [];

        foreach ($contacts as $contact) {
            $stats['contacts_reviewed']++;

            $contactTasks = $tasks->where('contact_id', $contact->id)->values();
            $policy = $listPolicy[(int) $contact->id] ?? [
                'lists' => '',
                'disable_hubspot_reassignment' => false,
            ];

            try {
                $hubspotContact = $this->hubspotContactFor($hubspot, $contact);
            } catch (\Throwable $e) {
                $stats['api_errors']++;
                $this->pushExample($examples, $contact, $contactTasks, $policy, 'api_error', $e->getMessage());
                continue;
            }

            if (! $hubspotContact) {
                $stats['hubspot_not_found']++;
                $this->pushExample($examples, $contact, $contactTasks, $policy, 'hubspot_not_found');
                continue;
            }

            $hubspotOwnerId = $hubspotContact['properties']['hubspot_owner_id'] ?? null;

            if (blank($hubspotOwnerId)) {
                $stats['hubspot_missing_owner']++;
                $this->pushExample($examples, $contact, $contactTasks, $policy, 'hubspot_missing_owner', null, $hubspotContact['id'] ?? null);
                continue;
            }

            $mappedOwner = $ownerMap->get((string) $hubspotOwnerId);

            if (! $mappedOwner) {
                $stats['hubspot_owner_unmapped']++;
                $this->pushExample($examples, $contact, $contactTasks, $policy, 'hubspot_owner_unmapped', (string) $hubspotOwnerId, $hubspotContact['id'] ?? null);
                continue;
            }

            $taskMismatch = $contactTasks->contains(
                fn (Task $task) => (int) $task->user_id !== (int) $mappedOwner->user_id
            );

            if ($taskMismatch) {
                $stats['task_assignee_mismatch_hubspot']++;
                $this->pushExample($examples, $contact, $contactTasks, $policy, 'task_assignee_mismatch_hubspot', $mappedOwner->user_name, $hubspotContact['id'] ?? null);
            }

            if ((int) ($contact->owner_id ?? 0) !== (int) $mappedOwner->user_id) {
                $stats['local_owner_mismatch_hubspot']++;
                $this->pushExample($examples, $contact, $contactTasks, $policy, 'local_owner_mismatch_hubspot', $mappedOwner->user_name, $hubspotContact['id'] ?? null);
            }
        }

        $this->info('Resumen de auditoria de owners');
        $this->table(['metrica', 'total'], collect($stats)->map(fn ($value, $key) => [$key, $value])->values()->all());

        if (! empty($examples)) {
            $this->warn('Ejemplos relevantes');
            $this->table(
                ['contact_id', 'contacto', 'task_ids', 'asesores_tarea', 'listas', 'tipo', 'detalle', 'hs_id'],
                $examples
            );
        }

        return self::SUCCESS;
    }

    private function hubspotContactFor(HubspotService $hubspot, object $contact): ?array
    {
        if (! empty($contact->hs_id)) {
            return $hubspot->getContactOwnerById((string) $contact->hs_id);
        }

        if (blank($contact->email)) {
            return null;
        }

        return $hubspot->searchContactOwnerByEmail((string) $contact->email);
    }

    private function taskPoolListPolicy(array $contactIds): array
    {
        if (empty($contactIds)) {
            return [];
        }

        return DB::table('list_user as lu')
            ->join('lists as l', 'l.id', '=', 'lu.list_id')
            ->whereIn('lu.user_id', $contactIds)
            ->where('l.include_in_task_pool', true)
            ->where('lu.contacted', false)
            ->select('lu.user_id', 'l.name', 'l.disable_hubspot_reassignment')
            ->get()
            ->groupBy('user_id')
            ->map(function ($rows) {
                return [
                    'lists' => $rows->pluck('name')->filter()->unique()->implode(', '),
                    'disable_hubspot_reassignment' => $rows->contains(
                        fn ($row) => (bool) $row->disable_hubspot_reassignment
                    ),
                ];
            })
            ->all();
    }

    private function pushExample(
        array &$examples,
        object $contact,
        $tasks,
        array $policy,
        string $type,
        ?string $detail = null,
        ?string $hubspotContactId = null
    ): void {
        if (count($examples) >= 20) {
            return;
        }

        $examples[] = [
            (int) $contact->id,
            (string) $contact->name,
            $tasks->pluck('id')->implode(', '),
            $tasks->map(fn (Task $task) => $task->assignee?->name ?: 'Sin asesor')->unique()->implode(', '),
            $policy['lists'] ?? '',
            $type,
            $detail ?: '',
            $hubspotContactId ?: (string) ($contact->hs_id ?? ''),
        ];
    }
}

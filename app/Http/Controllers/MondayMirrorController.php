<?php

namespace App\Http\Controllers;

use App\Models\MondayBoard;
use App\Models\MondayColumn;
use App\Models\MondayItem;
use App\Models\MondayItemMovement;
use App\Models\MondayItemValue;
use App\Models\MondaySyncRun;
use App\Models\MondayWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MondayMirrorController extends Controller
{
    public function dashboard(Request $request): View
    {
        $installed = Schema::hasTable('monday_boards');
        if (! $installed) {
            return view('admin.monday.index', [
                'installed' => false,
                'boards' => null,
                'metrics' => [],
                'recentRuns' => collect(),
            ]);
        }

        $search = trim((string) $request->query('q'));
        $boards = MondayBoard::query()
            ->available()
            ->withCount([
                'groups',
                'columns',
                'items as items_total' => fn ($query) => $query->whereNull('parent_item_id'),
                'items as subitems_total' => fn ($query) => $query->whereNotNull('parent_item_id'),
            ])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhere('workspace_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('workspace_name')
            ->orderBy('name')
            ->paginate(18)
            ->withQueryString();

        $metrics = [
            'boards' => MondayBoard::query()->available()->count(),
            'items' => MondayItem::query()->whereNull('parent_item_id')->count(),
            'subitems' => MondayItem::query()->whereNotNull('parent_item_id')->count(),
            'values' => MondayItemValue::query()->count(),
            'webhook_pending' => MondayWebhookEvent::query()->whereIn('status', ['pending', 'failed'])->count(),
        ];

        $recentRuns = MondaySyncRun::query()->latest('id')->limit(8)->get();

        return view('admin.monday.index', compact('installed', 'boards', 'metrics', 'recentRuns'));
    }

    public function showBoard(Request $request, MondayBoard $board): View
    {
        $columns = MondayColumn::query()
            ->where('board_id', $board->id)
            ->orderBy('id')
            ->get();
        $groups = $board->groups()->orderBy('id')->get();

        $search = trim((string) $request->query('q'));
        $groupId = trim((string) $request->query('group'));
        $state = (string) $request->query('state', 'active');
        $perPage = in_array((int) $request->query('per_page', 25), [25, 50, 100], true)
            ? (int) $request->query('per_page', 25)
            : 25;

        $itemsQuery = MondayItem::query();
        if (in_array($state, ['all', 'archived', 'deleted'], true)) {
            $itemsQuery->withTrashed();
        }
        if (in_array($state, ['archived', 'deleted'], true)) {
            $itemsQuery->where('state', $state);
        } elseif ($state !== 'all') {
            $itemsQuery->where(function ($query): void {
                $query->whereNull('state')->orWhere('state', 'active');
            });
        }

        $items = $itemsQuery
            ->where('board_id', $board->id)
            ->whereNull('parent_item_id')
            ->when($groupId !== '', fn ($query) => $query->where('group_id', $groupId))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('id', 'like', "%{$search}%")
                        ->orWhereHas('values', fn ($query) => $query->where('text', 'like', "%{$search}%"))
                        ->orWhereHas('subitems', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%")
                                ->orWhereHas('values', fn ($query) => $query->where('text', 'like', "%{$search}%"));
                        });
                });
            })
            ->with([
                'values',
                'subitems' => fn ($query) => $query->orderBy('id'),
                'subitems.values',
            ])
            ->orderBy('group_id')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $groupCounts = MondayItem::query()
            ->select(['group_id', DB::raw('COUNT(*) as total')])
            ->where('board_id', $board->id)
            ->whereNull('parent_item_id')
            ->groupBy('group_id')
            ->pluck('total', 'group_id');

        return view('admin.monday.board', compact(
            'board',
            'columns',
            'groups',
            'items',
            'groupCounts',
            'search',
            'groupId',
            'state',
            'perPage'
        ));
    }

    public function showItem(string $item): View
    {
        $item = MondayItem::withTrashed()
            ->with(['board', 'values', 'parent', 'subitems.values', 'movements'])
            ->findOrFail($item);
        $columns = MondayColumn::withTrashed()
            ->where('board_id', $item->board_id)
            ->orderBy('id')
            ->get()
            ->keyBy('monday_id');
        $boardsById = MondayBoard::query()->pluck('name', 'id');

        return view('admin.monday.item', compact('item', 'columns', 'boardsById'));
    }

    public function activity(Request $request): View
    {
        $eventStatus = trim((string) $request->query('event_status'));
        $eventType = trim((string) $request->query('event_type'));

        $events = MondayWebhookEvent::query()
            ->when($eventStatus !== '', fn ($query) => $query->where('status', $eventStatus))
            ->when($eventType !== '', fn ($query) => $query->where('event_type', $eventType))
            ->latest('id')
            ->paginate(25, ['*'], 'events_page')
            ->withQueryString();
        $eventTypes = MondayWebhookEvent::query()
            ->whereNotNull('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');
        $runs = MondaySyncRun::query()
            ->latest('id')
            ->paginate(15, ['*'], 'runs_page')
            ->withQueryString();
        $movements = MondayItemMovement::query()
            ->with('item')
            ->latest('moved_at')
            ->paginate(20, ['*'], 'movements_page')
            ->withQueryString();
        $boardsById = MondayBoard::query()->pluck('name', 'id');

        return view('admin.monday.activity', compact(
            'events',
            'eventTypes',
            'runs',
            'movements',
            'boardsById',
            'eventStatus',
            'eventType'
        ));
    }
}

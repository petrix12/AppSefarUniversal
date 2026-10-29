<?php

namespace App\Http\Controllers;

use App\Models\MondayBoard;
use App\Models\MondayColumn;
use App\Models\MondayItem;
use App\Models\MondayItemMovement;
use App\Models\MondayItemValue;
use App\Models\MondaySyncRun;
use App\Models\MondayWebhookEvent;
use Illuminate\Http\JsonResponse;
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

    public function showBoard(Request $request, MondayBoard $board): View|JsonResponse
    {
        $columns = MondayColumn::query()
            ->where('board_id', $board->id)
            ->orderBy('id')
            ->get(['id', 'monday_id', 'title', 'type']);
        $groups = $board->groups()->orderBy('id')->get(['id', 'monday_id', 'title', 'color']);

        $search = trim((string) $request->query('q'));
        $groupId = trim((string) $request->query('group'));
        $state = (string) $request->query('state', 'active');

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

        // Exclude raw_data: each item stores a full remote payload, including
        // values and subitems that would otherwise be loaded repeatedly.
        $itemFields = ['id', 'board_id', 'group_id', 'parent_item_id', 'name', 'monday_updated_at'];
        $valueFields = ['id', 'item_id', 'monday_column_id', 'type', 'text', 'value'];
        $items = $itemsQuery
            ->select($itemFields)
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
            ->withCount('subitems')
            ->with([
                'values' => fn ($query) => $query->select($valueFields),
                'subitems' => fn ($query) => $query->select($itemFields)->orderBy('id')->limit(5),
                'subitems.values' => fn ($query) => $query->select($valueFields),
            ])
            ->orderBy('group_id')
            ->orderBy('id')
            ->paginate(100)
            ->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'groups' => $items->getCollection()
                    ->groupBy(fn ($item) => (string) ($item->group_id ?? ''))
                    ->map(fn ($groupItems, $groupId) => [
                        'id' => (string) $groupId,
                        'html' => view('admin.monday.partials.board-item-rows', ['items' => $groupItems, 'columns' => $columns])->render(),
                    ])->values(),
                'next_page_url' => $items->nextPageUrl(),
                'loaded' => $items->lastItem() ?? 0,
                'total' => $items->total(),
            ]);
        }

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
            'state'
        ));
    }

    public function showItem(string $item): View
    {
        $item = MondayItem::withTrashed()
            ->with(['board:id,name', 'values:id,item_id,monday_column_id,type,text', 'parent:id,name', 'movements'])
            ->findOrFail($item);
        $subitems = $item->subitems()
            ->select(['id', 'parent_item_id', 'name'])
            ->withCount(['values as populated_values_count' => fn ($query) => $query->whereNotNull('text')])
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();
        $columns = MondayColumn::withTrashed()
            ->where('board_id', $item->board_id)
            ->orderBy('id')
            ->get()
            ->keyBy('monday_id');
        $boardsById = MondayBoard::query()->pluck('name', 'id');

        return view('admin.monday.item', compact('item', 'columns', 'boardsById', 'subitems'));
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

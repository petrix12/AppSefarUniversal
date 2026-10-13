<?php

namespace App\Http\Controllers;

use App\Models\KanbanBoard;
use App\Models\KanbanCard;
use App\Models\KanbanColumn;
use App\Models\OrgReportingLine;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KanbanBoardController extends Controller
{
    private ?array $reportingChildrenByManager = null;
    private ?array $visibleUserIdsCache = null;

    public function index()
    {
        $visibleUserIds = $this->visibleUserIds();
        $boards = KanbanBoard::query()
            ->whereIn('created_by_user_id', $visibleUserIds)
            ->withCount('columns')
            ->with([
                'columns.cards' => fn ($query) => $query
                    ->where(fn ($cards) => $cards->whereIn('assigned_to_user_id', $visibleUserIds)
                        ->orWhereNull('assigned_to_user_id'))
                    ->orderBy('sort_order')->orderBy('id'),
            ])
            ->with('creator:id,name')
            ->latest('updated_at')
            ->get();

        return view('kanban.index', compact('boards'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $board = DB::transaction(function () use ($data, $request): KanbanBoard {
            $board = KanbanBoard::create([
                ...$data,
                'created_by_user_id' => $request->user()->id,
            ]);

            foreach (['Por hacer', 'En curso', 'Hecho'] as $index => $name) {
                $board->columns()->create(['name' => $name, 'sort_order' => $index]);
            }

            return $board;
        });

        return redirect()->route('kanban.show', $board)->with('success', 'Tablero creado.');
    }

    public function show(KanbanBoard $board)
    {
        $this->authorizeBoard($board);
        $visibleUserIds = $this->visibleUserIds();
        $board->load([
            'columns' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'columns.cards' => fn ($query) => $query
                ->where(fn ($cards) => $cards->whereIn('assigned_to_user_id', $visibleUserIds)
                    ->orWhereNull('assigned_to_user_id'))
                ->with('assignee:id,name')->orderBy('sort_order')->orderBy('id'),
            'creator:id,name',
        ]);

        $members = User::query()
            ->select('users.id', 'users.name')
            ->whereIn('users.id', $visibleUserIds)
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Cliente'))
            ->orderBy('users.name')
            ->get();

        return view('kanban.show', compact('board', 'members'));
    }

    public function update(Request $request, KanbanBoard $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $board->update($request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]));

        return response()->json(['ok' => true, 'name' => $board->name]);
    }

    public function destroy(KanbanBoard $board)
    {
        $this->authorizeBoard($board);
        abort_unless(
            (int) $board->created_by_user_id === (int) request()->user()->id || request()->user()->can('administrador'),
            403
        );

        $board->delete();

        return redirect()->route('kanban.index')->with('success', 'Tablero eliminado.');
    }

    public function storeColumn(Request $request, KanbanBoard $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $column = $board->columns()->create([
            'name' => $data['name'],
            'sort_order' => (int) $board->columns()->max('sort_order') + 1,
        ]);

        return response()->json(['ok' => true, 'id' => $column->id, 'name' => $column->name]);
    }

    public function updateColumn(Request $request, KanbanBoard $board, KanbanColumn $column): JsonResponse
    {
        $this->authorizeBoard($board);
        abort_unless((int) $column->kanban_board_id === (int) $board->id, 404);
        $column->update($request->validate(['name' => ['required', 'string', 'max:80']]));

        return response()->json(['ok' => true, 'name' => $column->name]);
    }

    public function destroyColumn(KanbanBoard $board, KanbanColumn $column): JsonResponse
    {
        $this->authorizeBoard($board);
        abort_unless((int) $column->kanban_board_id === (int) $board->id, 404);
        abort_if($board->columns()->count() <= 1, 422, 'El tablero debe conservar al menos una columna.');
        $column->delete();

        return response()->json(['ok' => true]);
    }

    public function reorderColumns(Request $request, KanbanBoard $board): JsonResponse
    {
        $this->authorizeBoard($board);
        $data = $request->validate(['column_ids' => ['required', 'array'], 'column_ids.*' => ['integer']]);
        $ids = array_map('intval', $data['column_ids']);
        $currentIds = $board->columns()->pluck('id')->map(fn ($id) => (int) $id)->all();
        abort_unless(count($ids) === count($currentIds)
            && count(array_unique($ids)) === count($ids)
            && count(array_diff($ids, $currentIds)) === 0, 422);

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                KanbanColumn::whereKey($id)->update(['sort_order' => $index]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function storeCard(Request $request, KanbanBoard $board, KanbanColumn $column): JsonResponse
    {
        $this->authorizeBoard($board);
        abort_unless((int) $column->kanban_board_id === (int) $board->id, 404);
        $data = $this->validatedCard($request);
        $this->ensureAssignableToTeam($data['assigned_to_user_id'] ?? null);

        $card = $column->cards()->create([
            ...$data,
            'created_by_user_id' => $request->user()->id,
            'sort_order' => (int) $column->cards()->max('sort_order') + 1,
        ]);
        $card->load('assignee:id,name');

        return response()->json(['ok' => true, 'card' => $this->cardPayload($card)]);
    }

    public function updateCard(Request $request, KanbanBoard $board, KanbanCard $card): JsonResponse
    {
        $this->authorizeBoard($board);
        $this->ensureCardOnBoard($board, $card);
        $data = $this->validatedCard($request);
        $this->ensureAssignableToTeam($data['assigned_to_user_id'] ?? null);
        $card->update($data);
        $card->load('assignee:id,name');

        return response()->json(['ok' => true, 'card' => $this->cardPayload($card)]);
    }

    public function destroyCard(KanbanBoard $board, KanbanCard $card): JsonResponse
    {
        $this->authorizeBoard($board);
        $this->ensureCardOnBoard($board, $card);
        $card->delete();

        return response()->json(['ok' => true]);
    }

    public function moveCard(Request $request, KanbanBoard $board, KanbanCard $card): JsonResponse
    {
        $this->authorizeBoard($board);
        $this->ensureCardOnBoard($board, $card);
        $data = $request->validate([
            'column_id' => ['required', 'integer'],
            'card_ids' => ['required', 'array'],
            'card_ids.*' => ['integer'],
        ]);

        $target = $board->columns()->whereKey($data['column_id'])->firstOrFail();
        $orderedIds = array_map('intval', $data['card_ids']);
        abort_unless(in_array((int) $card->id, $orderedIds, true), 422);

        DB::transaction(function () use ($card, $target, $orderedIds): void {
            $sourceId = (int) $card->kanban_column_id;
            $visibleUserIds = $this->visibleUserIds();
            $validDestinationIds = KanbanCard::query()
                ->where('kanban_column_id', $target->id)
                ->where(fn ($query) => $query->whereIn('assigned_to_user_id', $visibleUserIds)
                    ->orWhereNull('assigned_to_user_id'))
                ->when($sourceId !== (int) $target->id, fn ($query) => $query->where('id', '!=', $card->id))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $expectedIds = $sourceId === (int) $target->id
                ? $validDestinationIds
                : [...$validDestinationIds, (int) $card->id];
            abort_unless(count($orderedIds) === count($expectedIds)
                && count(array_unique($orderedIds)) === count($orderedIds)
                && count(array_diff($orderedIds, $expectedIds)) === 0, 422);

            $card->update(['kanban_column_id' => $target->id]);
            $hiddenDestinationIds = KanbanCard::query()->where('kanban_column_id', $target->id)
                ->whereNotIn('id', $orderedIds)->orderBy('sort_order')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ([...$orderedIds, ...$hiddenDestinationIds] as $index => $id) {
                KanbanCard::whereKey($id)->update(['sort_order' => $index]);
            }

            if ($sourceId !== (int) $target->id) {
                KanbanCard::query()->where('kanban_column_id', $sourceId)
                    ->orderBy('sort_order')->orderBy('id')->get()
                    ->values()->each(fn (KanbanCard $remaining, int $index) => $remaining->update(['sort_order' => $index]));
            }
        });

        return response()->json(['ok' => true]);
    }

    private function validatedCard(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['nullable', 'date'],
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $data['assigned_to_user_id'] = $data['assigned_to_user_id'] ?: null;

        return $data;
    }

    private function ensureInternalAssignee(?int $userId): void
    {
        if ($userId && User::whereKey($userId)->whereHas('roles', fn ($query) => $query->where('name', 'Cliente'))->exists()) {
            throw ValidationException::withMessages(['assigned_to_user_id' => 'Selecciona un miembro del equipo.']);
        }
    }

    private function ensureAssignableToTeam(?int $userId): void
    {
        $this->ensureInternalAssignee($userId);
        if ($userId && ! in_array($userId, $this->visibleUserIds(), true)) {
            throw ValidationException::withMessages(['assigned_to_user_id' => 'Solo puedes asignar tarjetas a ti o a miembros de tu equipo.']);
        }
    }

    public function orgChart()
    {
        $users = User::query()
            ->select('users.id', 'users.name', 'users.email')
            ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Cliente'))
            ->orderBy('users.name')->get();
        $reportingLines = OrgReportingLine::query()->with(['manager:id,name', 'subordinate:id,name'])
            ->join('users as subordinates', 'subordinates.id', '=', 'org_reporting_lines.subordinate_user_id')
            ->orderBy('subordinates.name')
            ->select('org_reporting_lines.*')->get();

        return view('kanban.org-chart', compact('users', 'reportingLines'));
    }

    public function storeReportingLine(Request $request)
    {
        $data = $request->validate([
            'manager_user_id' => ['required', 'integer', 'exists:users,id'],
            'subordinate_user_id' => ['required', 'integer', 'different:manager_user_id', 'exists:users,id'],
        ]);
        $this->ensureInternalAssignee((int) $data['manager_user_id']);
        $this->ensureInternalAssignee((int) $data['subordinate_user_id']);

        $descendants = $this->descendantUserIds((int) $data['subordinate_user_id']);
        if (in_array((int) $data['manager_user_id'], $descendants, true)) {
            throw ValidationException::withMessages(['manager_user_id' => 'Esta relación crearía un ciclo en el organigrama.']);
        }

        OrgReportingLine::updateOrCreate(
            ['subordinate_user_id' => $data['subordinate_user_id']],
            ['manager_user_id' => $data['manager_user_id']],
        );

        return redirect()->route('kanban.org-chart')->with('success', 'Relación del organigrama guardada.');
    }

    public function destroyReportingLine(OrgReportingLine $line)
    {
        $line->delete();

        return redirect()->route('kanban.org-chart')->with('success', 'Relación eliminada.');
    }

    private function authorizeBoard(KanbanBoard $board): void
    {
        abort_unless(in_array((int) $board->created_by_user_id, $this->visibleUserIds(), true), 404);
    }

    private function ensureCardOnBoard(KanbanBoard $board, KanbanCard $card): void
    {
        abort_unless((int) $card->column?->kanban_board_id === (int) $board->id, 404);
        $visibleUserIds = $this->visibleUserIds();
        $visible = $card->assigned_to_user_id === null
            || in_array((int) $card->assigned_to_user_id, $visibleUserIds, true);
        abort_unless($visible, 404);
    }

    private function visibleUserIds(): array
    {
        if ($this->visibleUserIdsCache !== null) {
            return $this->visibleUserIdsCache;
        }

        $userId = (int) request()->user()->id;

        return $this->visibleUserIdsCache = array_values(array_unique([$userId, ...$this->descendantUserIds($userId)]));
    }

    private function descendantUserIds(int $managerId): array
    {
        $this->reportingChildrenByManager ??= DB::table('org_reporting_lines')->get()
            ->groupBy('manager_user_id')
            ->map(fn ($rows) => $rows->pluck('subordinate_user_id')->map(fn ($id) => (int) $id)->all())
            ->all();

        $visited = [];
        $pending = $this->reportingChildrenByManager[$managerId] ?? [];
        while ($pending !== []) {
            $childId = array_pop($pending);
            if (in_array($childId, $visited, true)) {
                continue;
            }
            $visited[] = $childId;
            array_push($pending, ...($this->reportingChildrenByManager[$childId] ?? []));
        }

        return $visited;
    }

    private function cardPayload(KanbanCard $card): array
    {
        return [
            'id' => $card->id,
            'title' => $card->title,
            'description' => $card->description,
            'due_date' => $card->due_date?->toDateString(),
            'assigned_to_user_id' => $card->assigned_to_user_id,
            'assignee_name' => $card->assignee?->name,
        ];
    }
}

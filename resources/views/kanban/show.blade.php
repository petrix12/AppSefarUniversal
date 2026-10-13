@extends('adminlte::page')

@section('title', $board->name)

@section('content_header')
    <div class="d-flex align-items-start justify-content-between flex-wrap">
        <div>
            <a href="{{ route('kanban.index') }}" class="text-muted small"><i class="fas fa-arrow-left mr-1"></i>Todos los tableros</a>
            <h1 class="mt-2 mb-1">
                <span id="board-title">{{ $board->name }}</span>
                <button class="btn btn-link btn-sm text-muted align-baseline" id="edit-board" title="Editar tablero"><i class="fas fa-pen"></i></button>
            </h1>
            @if($board->description)<p class="text-muted mb-0">{{ $board->description }}</p>@endif
        </div>
        <div class="d-flex mt-2 mt-md-4">
            <button class="btn btn-outline-primary mr-2" id="add-column"><i class="fas fa-plus mr-1"></i>Añadir columna</button>
            <form method="POST" action="{{ route('kanban.destroy', $board) }}" onsubmit="return confirm('¿Eliminar este tablero y todas sus tarjetas?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger" title="Eliminar tablero"><i class="far fa-trash-alt"></i></button>
            </form>
        </div>
    </div>
@stop

@section('content')
    <div id="kanban-board"
        data-board-id="{{ $board->id }}"
        data-csrf="{{ csrf_token() }}"
        data-column-create-url="{{ route('kanban.columns.store', $board) }}"
        data-column-order-url="{{ route('kanban.columns.reorder', $board) }}"
        data-board-update-url="{{ route('kanban.update', $board) }}"
        data-card-update-template="{{ route('kanban.cards.update', [$board, '__CARD__']) }}"
        data-card-delete-template="{{ route('kanban.cards.destroy', [$board, '__CARD__']) }}"
        data-card-move-template="{{ route('kanban.cards.move', [$board, '__CARD__']) }}">
        <div class="kanban-scroll">
            <div class="kanban-columns" id="kanban-columns">
                @foreach($board->columns as $column)
                    <section class="kanban-column" data-column-id="{{ $column->id }}"
                        data-column-update-url="{{ route('kanban.columns.update', [$board, $column]) }}"
                        data-column-delete-url="{{ route('kanban.columns.destroy', [$board, $column]) }}"
                        data-card-create-url="{{ route('kanban.cards.store', [$board, $column]) }}">
                        <header class="kanban-column-header" draggable="true">
                            <span class="kanban-column-name">{{ $column->name }}</span>
                            <span class="badge badge-light kanban-count">{{ $column->cards->count() }}</span>
                            <div class="dropdown ml-auto">
                                <button class="btn btn-sm btn-link text-muted p-1" data-toggle="dropdown" aria-label="Opciones de columna"><i class="fas fa-ellipsis-h"></i></button>
                                <div class="dropdown-menu dropdown-menu-right">
                                    <button class="dropdown-item rename-column"><i class="fas fa-pen mr-2"></i>Renombrar</button>
                                    <button class="dropdown-item text-danger delete-column"><i class="far fa-trash-alt mr-2"></i>Eliminar columna</button>
                                </div>
                            </div>
                        </header>
                        <div class="kanban-card-list" data-column-id="{{ $column->id }}">
                            @foreach($column->cards as $card)
                                <article class="kanban-card" draggable="true" data-card-id="{{ $card->id }}"
                                    data-title="{{ $card->title }}" data-description="{{ $card->description }}"
                                    data-due-date="{{ $card->due_date?->toDateString() }}"
                                    data-assigned-id="{{ $card->assigned_to_user_id }}">
                                    <div class="kanban-card-title">{{ $card->title }}</div>
                                    @if($card->description)<div class="kanban-card-description"><i class="fas fa-align-left"></i></div>@endif
                                    <div class="kanban-card-meta">
                                        @if($card->due_date)<span class="kanban-due {{ $card->due_date->isPast() && !$card->due_date->isToday() ? 'text-danger' : '' }}"><i class="far fa-clock mr-1"></i>{{ $card->due_date->format('d/m') }}</span>@endif
                                        @if($card->assignee)<span class="kanban-assignee" title="{{ $card->assignee->name }}">{{ collect(explode(' ', $card->assignee->name))->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span>@endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                        <form class="kanban-add-card-form" action="{{ route('kanban.cards.store', [$board, $column]) }}">
                            @csrf
                            <input class="form-control form-control-sm" name="title" maxlength="180" placeholder="Añadir una tarjeta…" required>
                            <button class="btn btn-sm btn-primary" title="Añadir tarjeta"><i class="fas fa-plus"></i></button>
                        </form>
                    </section>
                @endforeach
            </div>
        </div>
        <div class="kanban-toast" id="kanban-toast" role="status" aria-live="polite"></div>
    </div>

    <div class="modal fade" id="cardModal" tabindex="-1" role="dialog" aria-labelledby="cardModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form id="card-form" class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="cardModalLabel">Editar tarjeta</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <input type="hidden" id="card-id">
                    <div class="form-group"><label for="card-title">Título</label><input class="form-control" id="card-title" name="title" maxlength="180" required></div>
                    <div class="form-group"><label for="card-description">Descripción</label><textarea class="form-control" id="card-description" name="description" rows="4" maxlength="5000"></textarea></div>
                    <div class="form-row">
                        <div class="form-group col-md-6"><label for="card-assignee">Responsable</label><select class="form-control" id="card-assignee" name="assigned_to_user_id"><option value="">Sin asignar</option>@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach</select></div>
                        <div class="form-group col-md-6"><label for="card-due-date">Fecha límite</label><input class="form-control" id="card-due-date" name="due_date" type="date"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-outline-danger" id="delete-card"><i class="far fa-trash-alt mr-1"></i>Eliminar</button>
                    <div><button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button><button class="btn btn-primary ml-1">Guardar</button></div>
                </div>
            </form>
        </div>
    </div>
@stop

@section('css')
<style>
    .kanban-scroll { overflow-x: auto; padding: .25rem .1rem 1rem; }
    .kanban-columns { display: flex; align-items: flex-start; gap: 1rem; min-height: calc(100vh - 260px); width: max-content; min-width: 100%; }
    .kanban-column { background: #edf1f5; border-radius: .55rem; width: 292px; max-height: calc(100vh - 250px); display: flex; flex-direction: column; padding: .7rem; transition: background .15s ease, opacity .15s ease; }
    .kanban-column.drag-over { background: #dcebf2; }
    .kanban-column-header { display: flex; align-items: center; gap: .5rem; padding: .15rem .15rem .65rem; cursor: grab; font-weight: 700; }
    .kanban-column-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .kanban-card-list { display: flex; flex-direction: column; gap: .55rem; overflow-y: auto; min-height: 45px; padding: .05rem .05rem .4rem; }
    .kanban-card { background: #fff; border-radius: .4rem; box-shadow: 0 1px 3px rgba(20,40,60,.13); padding: .7rem .75rem; cursor: grab; border: 1px solid rgba(0,0,0,.04); }
    .kanban-card:hover { box-shadow: 0 3px 9px rgba(20,40,60,.16); }
    .kanban-card.dragging { opacity: .4; }
    .kanban-card-title { font-weight: 600; overflow-wrap: anywhere; }
    .kanban-card-description { color: #8b98a5; font-size: .8rem; margin-top: .4rem; }
    .kanban-card-meta { display: flex; justify-content: flex-end; align-items: center; gap: .55rem; margin-top: .55rem; min-height: 24px; }
    .kanban-due { color: #687785; font-size: .75rem; margin-right: auto; }
    .kanban-assignee { display: inline-flex; align-items: center; justify-content: center; background: #176b87; color: white; width: 25px; height: 25px; border-radius: 50%; font-size: .67rem; font-weight: 700; }
    .kanban-add-card-form { display: flex; gap: .4rem; padding-top: .45rem; }
    .kanban-add-card-form .form-control { min-width: 0; }
    .kanban-toast { position: fixed; right: 1.5rem; bottom: 1.5rem; z-index: 1100; color: white; background: #253746; border-radius: .4rem; padding: .7rem 1rem; opacity: 0; transform: translateY(8px); pointer-events: none; transition: .2s; }
    .kanban-toast.show { opacity: 1; transform: translateY(0); }
    @media (max-width: 767.98px) { .kanban-columns { min-height: calc(100vh - 300px); } .kanban-column { max-height: calc(100vh - 230px); width: 84vw; } }
</style>
@stop

@section('js')
<script>
(() => {
    const root = document.getElementById('kanban-board');
    if (!root) return;
    const csrf = root.dataset.csrf;
    const columnsRoot = document.getElementById('kanban-columns');
    const toast = document.getElementById('kanban-toast');
    const modal = $('#cardModal');
    let draggedCard = null;
    let draggedColumn = null;
    let toastTimer;

    const showToast = (message) => {
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2600);
    };

    const request = async (url, method, body = {}) => {
        const response = await fetch(url, {
            method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            body: method === 'GET' ? undefined : JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'No se pudo guardar el cambio.');
        return data;
    };

    const cardUrl = (template, id) => template.replace('__CARD__', id);
    const updateCounts = () => document.querySelectorAll('.kanban-column').forEach((column) => {
        column.querySelector('.kanban-count').textContent = column.querySelectorAll('.kanban-card').length;
    });

    const applyCardData = (element, card) => {
        element.dataset.title = card.title || '';
        element.dataset.description = card.description || '';
        element.dataset.dueDate = card.due_date || '';
        element.dataset.assignedId = card.assigned_to_user_id || '';
        element.querySelector('.kanban-card-title').textContent = card.title;
        const description = element.querySelector('.kanban-card-description');
        if (card.description) {
            if (description) description.innerHTML = '<i class="fas fa-align-left"></i>';
            else { const indicator = document.createElement('div'); indicator.className = 'kanban-card-description'; indicator.innerHTML = '<i class="fas fa-align-left"></i>'; element.insertBefore(indicator, element.querySelector('.kanban-card-meta')); }
        } else if (description) description.remove();
        const meta = element.querySelector('.kanban-card-meta');
        let due = meta.querySelector('.kanban-due');
        if (card.due_date) {
            if (!due) { due = document.createElement('span'); due.className = 'kanban-due'; meta.prepend(due); }
            const [, month, day] = card.due_date.match(/^(\d{4})-(\d{2})-(\d{2})$/) || [];
            due.innerHTML = `<i class="far fa-clock mr-1"></i>${day}/${month}`;
        } else if (due) due.remove();
        let assignee = meta.querySelector('.kanban-assignee');
        if (card.assignee_name) {
            const initials = card.assignee_name.split(/\s+/).slice(0, 2).map((part) => part.slice(0, 1)).join('');
            if (!assignee) { assignee = document.createElement('span'); assignee.className = 'kanban-assignee'; meta.append(assignee); }
            assignee.textContent = initials;
            assignee.title = card.assignee_name;
        } else if (assignee) assignee.remove();
    };

    document.querySelectorAll('.kanban-add-card-form').forEach((form) => form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = form.elements.title;
        const title = input.value.trim();
        if (!title) return;
        try {
            const result = await request(form.action, 'POST', { title });
            const element = document.createElement('article');
            element.className = 'kanban-card';
            element.draggable = true;
            element.dataset.cardId = result.card.id;
            element.innerHTML = '<div class="kanban-card-title"></div><div class="kanban-card-meta"></div>';
            applyCardData(element, result.card);
            form.closest('.kanban-column').querySelector('.kanban-card-list').append(element);
            input.value = '';
            updateCounts();
        } catch (error) { showToast(error.message); }
    }));

    document.getElementById('add-column').addEventListener('click', async () => {
        const name = prompt('Nombre de la columna:');
        if (!name?.trim()) return;
        try {
            await request(root.dataset.columnCreateUrl, 'POST', { name: name.trim() });
            window.location.reload();
        } catch (error) { showToast(error.message); }
    });

    document.getElementById('edit-board').addEventListener('click', async () => {
        const current = document.getElementById('board-title').textContent.trim();
        const name = prompt('Nombre del tablero:', current);
        if (!name?.trim() || name.trim() === current) return;
        try {
            const result = await request(root.dataset.boardUpdateUrl, 'PATCH', { name: name.trim(), description: @json($board->description) });
            document.getElementById('board-title').textContent = result.name;
            document.title = `${result.name} | Sefar Universal`;
        } catch (error) { showToast(error.message); }
    });

    document.querySelectorAll('.rename-column').forEach((button) => button.addEventListener('click', async () => {
        const column = button.closest('.kanban-column');
        const name = prompt('Nombre de la columna:', column.querySelector('.kanban-column-name').textContent.trim());
        if (!name?.trim()) return;
        try {
            await request(column.dataset.columnUpdateUrl, 'PATCH', { name: name.trim() });
            column.querySelector('.kanban-column-name').textContent = name.trim();
        } catch (error) { showToast(error.message); }
    }));

    document.querySelectorAll('.delete-column').forEach((button) => button.addEventListener('click', async () => {
        const column = button.closest('.kanban-column');
        if (!confirm('¿Eliminar esta columna y sus tarjetas?')) return;
        try {
            await request(column.dataset.columnDeleteUrl, 'DELETE');
            column.remove();
        } catch (error) { showToast(error.message); }
    }));

    const openCard = (card) => {
        document.getElementById('card-id').value = card.dataset.cardId;
        document.getElementById('card-title').value = card.dataset.title || '';
        document.getElementById('card-description').value = card.dataset.description || '';
        document.getElementById('card-due-date').value = card.dataset.dueDate || '';
        document.getElementById('card-assignee').value = card.dataset.assignedId || '';
        modal.modal('show');
    };

    columnsRoot.addEventListener('click', (event) => {
        const card = event.target.closest('.kanban-card');
        if (card && !draggedCard) openCard(card);
    });

    document.getElementById('card-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        const id = document.getElementById('card-id').value;
        const card = document.querySelector(`.kanban-card[data-card-id="${id}"]`);
        const body = {
            title: document.getElementById('card-title').value.trim(),
            description: document.getElementById('card-description').value,
            due_date: document.getElementById('card-due-date').value || null,
            assigned_to_user_id: document.getElementById('card-assignee').value || null,
        };
        try {
            const result = await request(cardUrl(root.dataset.cardUpdateTemplate, id), 'PATCH', body);
            applyCardData(card, result.card);
            modal.modal('hide');
            showToast('Tarjeta guardada.');
        } catch (error) { showToast(error.message); }
    });

    document.getElementById('delete-card').addEventListener('click', async () => {
        const id = document.getElementById('card-id').value;
        if (!confirm('¿Eliminar esta tarjeta?')) return;
        try {
            await request(cardUrl(root.dataset.cardDeleteTemplate, id), 'DELETE');
            document.querySelector(`.kanban-card[data-card-id="${id}"]`)?.remove();
            updateCounts();
            modal.modal('hide');
        } catch (error) { showToast(error.message); }
    });

    columnsRoot.addEventListener('dragstart', (event) => {
        const card = event.target.closest('.kanban-card');
        const header = event.target.closest('.kanban-column-header');
        if (card) {
            draggedCard = card;
            card.classList.add('dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', card.dataset.cardId);
        } else if (header) {
            draggedColumn = header.closest('.kanban-column');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('application/x-kanban-column', draggedColumn.dataset.columnId);
        }
    });

    columnsRoot.addEventListener('dragend', () => {
        draggedCard?.classList.remove('dragging');
        draggedCard = null;
        draggedColumn = null;
        document.querySelectorAll('.kanban-column.drag-over').forEach((column) => column.classList.remove('drag-over'));
    });

    columnsRoot.addEventListener('dragover', (event) => {
        const list = event.target.closest('.kanban-card-list');
        const column = event.target.closest('.kanban-column');
        if (draggedCard && list) {
            event.preventDefault();
            const after = [...list.querySelectorAll('.kanban-card:not(.dragging)')].find((item) => event.clientY <= item.getBoundingClientRect().top + item.offsetHeight / 2);
            list.insertBefore(draggedCard, after || null);
            column.classList.add('drag-over');
        } else if (draggedColumn && column && draggedColumn !== column) {
            event.preventDefault();
            const before = event.clientX < column.getBoundingClientRect().left + column.offsetWidth / 2;
            columnsRoot.insertBefore(draggedColumn, before ? column : column.nextSibling);
        }
    });

    columnsRoot.addEventListener('dragleave', (event) => event.target.closest('.kanban-column')?.classList.remove('drag-over'));

    columnsRoot.addEventListener('drop', async (event) => {
        const list = event.target.closest('.kanban-card-list');
        if (draggedColumn) {
            event.preventDefault();
            try {
                await request(root.dataset.columnOrderUrl, 'PATCH', { column_ids: [...columnsRoot.querySelectorAll('.kanban-column')].map((column) => Number(column.dataset.columnId)) });
            } catch (error) { showToast(error.message); window.location.reload(); }
            return;
        }
        if (!draggedCard || !list) return;
        event.preventDefault();
        const column = list.closest('.kanban-column');
        const cardId = Number(draggedCard.dataset.cardId);
        try {
            await request(cardUrl(root.dataset.cardMoveTemplate, cardId), 'PATCH', {
                column_id: Number(column.dataset.columnId),
                card_ids: [...list.querySelectorAll('.kanban-card')].map((item) => Number(item.dataset.cardId)),
            });
            updateCounts();
        } catch (error) { showToast(error.message); window.location.reload(); }
    });
})();
</script>
@stop

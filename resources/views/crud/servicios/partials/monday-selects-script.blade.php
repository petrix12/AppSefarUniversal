<script>
    document.addEventListener('DOMContentLoaded', function () {
        const boardSelect = document.getElementById('monday_board_id');
        const groupSelect = document.getElementById('monday_group_id');
        const enabledInput = document.getElementById('monday_sync_enabled');
        const status = document.getElementById('monday-catalog-status');

        if (!boardSelect || !groupSelect) {
            return;
        }

        const selectedBoard = boardSelect.dataset.selected || '';
        const selectedGroup = groupSelect.dataset.selected || '';

        function setStatus(message, isError = false) {
            status.textContent = message;
            status.classList.toggle('text-danger', isError);
        }

        function replaceOptions(select, options, selected, placeholder) {
            select.innerHTML = '';
            select.add(new Option(placeholder, ''));

            options.forEach(function (option) {
                select.add(new Option(option.name, option.id, false, String(option.id) === String(selected)));
            });

            if (selected && !options.some(option => String(option.id) === String(selected))) {
                select.add(new Option('Destino guardado (' + selected + ')', selected, true, true));
            }

            select.value = selected || '';
            window.jQuery && window.jQuery(select).trigger('change.select2');
        }

        async function fetchOptions(url) {
            const response = await fetch(url, {
                headers: {'Accept': 'application/json'},
                credentials: 'same-origin'
            });
            const payload = await response.json();

            if (!response.ok) {
                throw new Error(payload.message || 'No se pudo consultar Monday.');
            }

            return payload.data || [];
        }

        async function loadGroups(boardId, selected = '') {
            if (!boardId) {
                replaceOptions(groupSelect, [], '', 'Selecciona primero un tablero');
                groupSelect.disabled = true;
                return;
            }

            groupSelect.disabled = true;
            setStatus('Cargando grupos de Monday…');

            try {
                const url = new URL(groupSelect.dataset.optionsUrl, window.location.origin);
                url.searchParams.set('board_id', boardId);
                const groups = await fetchOptions(url);
                replaceOptions(groupSelect, groups, selected, 'Selecciona un grupo/subtablero');
                groupSelect.disabled = false;
                setStatus(groups.length ? 'Destino cargado desde Monday.' : 'Este tablero no tiene grupos disponibles.');
            } catch (error) {
                replaceOptions(groupSelect, [], selected, 'No se pudieron cargar los grupos');
                groupSelect.disabled = false;
                setStatus(error.message, true);
            }
        }

        async function loadBoards() {
            boardSelect.disabled = true;
            setStatus('Cargando tableros de Monday…');

            try {
                const boards = await fetchOptions(boardSelect.dataset.optionsUrl);
                replaceOptions(boardSelect, boards, selectedBoard, 'Selecciona un tablero');
                boardSelect.disabled = false;
                await loadGroups(boardSelect.value, selectedGroup);
            } catch (error) {
                replaceOptions(boardSelect, [], selectedBoard, 'No se pudieron cargar los tableros');
                boardSelect.disabled = false;
                replaceOptions(groupSelect, [], selectedGroup, 'No se pudieron cargar los grupos');
                groupSelect.disabled = false;
                setStatus(error.message, true);
            }
        }

        boardSelect.addEventListener('change', function () {
            loadGroups(boardSelect.value);
        });

        if (window.jQuery && window.jQuery.fn.select2) {
            window.jQuery(boardSelect).select2({
                theme: 'default',
                width: '100%',
                placeholder: 'Selecciona un tablero'
            });
            window.jQuery(groupSelect).select2({
                theme: 'default',
                width: '100%',
                placeholder: 'Selecciona un grupo/subtablero'
            });
        }

        enabledInput.addEventListener('change', function () {
            boardSelect.closest('.row').classList.toggle('text-muted', !enabledInput.checked);
        });

        loadBoards();
    });
</script>

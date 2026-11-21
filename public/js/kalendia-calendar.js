(() => {
    const init = () => {
        const root = document.getElementById('kalendia-calendar');
        if (!root) return;
        const error = document.getElementById('kalendia-calendar-error');
        const summary = document.getElementById('kalendia-calendar-summary');
        const filters = document.getElementById('kalendia-calendar-filters');
        const dateInput = document.getElementById('kalendia-calendar-date');
        if (!window.FullCalendar || !window.luxon) {
            error.textContent = 'No se pudo cargar el calendario. Puedes consultar el historial en tabla.';
            error.hidden = false;
            summary.textContent = '';
            return;
        }
        const views = ['dayGridMonth', 'timeGridWeek', 'timeGridDay', 'listMonth'];
        const statuses = ['confirmed', 'pending', 'cancelled', 'completed', 'no_show'];
        let generation = 0;
        let loading = false;
        let loaded = false;
        let zone = window.SefarMeetingTimezone || 'America/Bogota';
        const updateSummary = instance => {
            if (!loaded || loading) return;
            const {activeStart, activeEnd} = instance.view;
            const count = instance.getEvents().filter(event => event.start < activeEnd && (event.end || event.start) > activeStart).length;
            summary.textContent = `${count} ${count === 1 ? 'cita' : 'citas'} en el periodo visible. Horas en ${zone}.`;
        };
        const updateUrl = () => {
            const url = new URL(window.location.href);
            new FormData(filters).forEach((value, key) => value ? url.searchParams.set(key, value) : url.searchParams.delete(key));
            window.history.replaceState(null, '', url);
        };
        const calendar = new FullCalendar.Calendar(root, {
            locale: 'es', timeZone: zone, firstDay: 1,
            initialView: views.includes(root.dataset.initialView) ? root.dataset.initialView : 'dayGridMonth',
            initialDate: root.dataset.initialDate,
            headerToolbar: false, height: 680, fixedWeekCount: false,
            nowIndicator: true, navLinks: true, editable: false, selectable: false,
            allDaySlot: false, scrollTime: '07:00:00', slotDuration: '00:30:00',
            dayMaxEvents: 3, eventDisplay: 'block', displayEventEnd: true,
            eventTimeFormat: {hour: '2-digit', minute: '2-digit', hour12: false},
            slotLabelFormat: {hour: '2-digit', minute: '2-digit', hour12: false},
            noEventsContent: 'No hay reuniones en este periodo con los filtros seleccionados.',
            eventClick(info) {
                info.jsEvent.preventDefault();
                document.dispatchEvent(new CustomEvent('sefar:manage-appointment', {detail: {url: info.event.url}}));
            },
            datesSet(info) {
                document.getElementById('kalendia-calendar-title').textContent = info.view.title;
                const date = luxon.DateTime.fromJSDate(info.view.calendar.getDate(), {zone}).toISODate();
                dateInput.value = date;
                filters.querySelector('[data-calendar-date]').value = date;
                filters.querySelector('[data-calendar-view]').value = info.view.type;
                document.querySelectorAll('[data-calendar-view-button]').forEach(button => {
                    const active = button.dataset.calendarViewButton === info.view.type;
                    button.classList.toggle('btn-primary', active);
                    button.classList.toggle('btn-outline-primary', !active);
                    button.setAttribute('aria-pressed', String(active));
                });
                updateUrl();
                updateSummary(info.view.calendar);
            },
            eventsSet() { updateSummary(calendar); },
            async events(info, success, failure) {
                const current = ++generation;
                loading = true;
                error.hidden = true;
                root.setAttribute('aria-busy', 'true');
                summary.textContent = 'Actualizando calendario…';
                const url = new URL(root.dataset.eventsUrl, window.location.origin);
                url.searchParams.set('start', info.startStr);
                url.searchParams.set('end', info.endStr);
                url.searchParams.set('timeZone', zone);
                for (const name of ['client', 'status']) {
                    const value = filters.elements.namedItem(name)?.value;
                    if (value) url.searchParams.set(name, value);
                }
                try {
                    const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
                    const data = await response.json();
                    if (!response.ok || !Array.isArray(data)) throw new Error(response.status === 422 ? data.message : 'No se pudieron consultar las citas. Recarga la página para volver a intentarlo.');
                    success(data);
                    if (current === generation) {
                        loaded = true;
                    }
                } catch (problem) {
                    failure(problem);
                    if (current === generation) {
                        error.textContent = problem.message || 'No se pudieron consultar las citas.';
                        error.hidden = false;
                        summary.textContent = 'No se pudo actualizar el calendario.';
                    }
                } finally {
                    if (current === generation) { loading = false; root.setAttribute('aria-busy', 'false'); if (error.hidden) updateSummary(calendar); }
                }
            },
            eventClassNames: info => [`kalendia-status-${statuses.includes(info.event.extendedProps.status) ? info.event.extendedProps.status : 'unknown'}`],
            eventContent(info) {
                const box = document.createElement('div');
                box.className = 'kalendia-calendar-event';
                const rows = [
                    [info.timeText ? `${info.timeText} · ${info.event.title}` : info.event.title, 'kalendia-calendar-event-name'],
                    [info.event.extendedProps.service, 'kalendia-calendar-event-meta'],
                    [info.event.extendedProps.statusLabel, 'kalendia-calendar-event-meta'],
                ];
                rows.forEach(([text, className]) => {
                    const node = document.createElement('span'); node.className = className; node.textContent = text; box.appendChild(node);
                });
                return {domNodes: [box]};
            },
            eventDidMount(info) {
                const detail = info.event.extendedProps;
                const text = `${info.event.title} · ${detail.service} · ${detail.staff} · ${detail.statusLabel}`;
                info.el.title = text;
                info.el.setAttribute('aria-label', `${info.timeText || ''} ${text}. Gestionar cita.`);
            },
        });
        calendar.render();
        document.addEventListener('sefar:appointment-updated', () => calendar.refetchEvents());
        document.querySelectorAll('[data-calendar-action]').forEach(button => button.addEventListener('click', () => calendar[button.dataset.calendarAction]()));
        document.querySelectorAll('[data-calendar-view-button]').forEach(button => button.addEventListener('click', () => calendar.changeView(button.dataset.calendarViewButton)));
        dateInput.addEventListener('change', () => { if (dateInput.value && dateInput.validity.valid) calendar.gotoDate(dateInput.value); });
        document.addEventListener('sefar:meeting-timezone', event => {
            const next = event.detail.timezone;
            if (next === zone) return;
            const date = dateInput.value;
            zone = next;
            calendar.batchRendering(() => { calendar.setOption('timeZone', zone); calendar.gotoDate(date); });
        });
        // Refresh the local mirror while visible; remote synchronization stays in Laravel's scheduler.
        window.setInterval(() => { if (!document.hidden && !loading) calendar.refetchEvents(); }, 60000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden && loaded && !loading) calendar.refetchEvents(); });
        if (window.ResizeObserver) new ResizeObserver(() => calendar.updateSize()).observe(root.parentElement);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('[data-registration-agenda]');
        if (!form) return;
        const date = form.querySelector('[name="date"]');
        const slot = form.querySelector('[name="slot"]');
        const status = form.querySelector('[data-agenda-status]');
        const submit = form.querySelector('[data-agenda-submit]');
        let pending;
        let sequence = 0;
        let picker;
        function timezone() { return window.SefarMeetingTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Bogota'; }
        function today() {
            const parts = new Intl.DateTimeFormat('en-CA', {timeZone: timezone(), year: 'numeric', month: '2-digit', day: '2-digit'}).formatToParts(new Date());
            const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
            return `${values.year}-${values.month}-${values.day}`;
        }
        function reset(message) {
            slot.replaceChildren(new Option(message, ''));
            slot.disabled = true;
            submit.disabled = true;
        }
        async function load() {
            const current = ++sequence;
            pending?.abort();
            reset('Consultando horarios…');
            status.textContent = '';
            if (!date.value || date.value < today()) { reset('Selecciona una fecha futura'); return; }
            const day = new Date(date.value + 'T12:00:00Z').getUTCDay();
            if (day === 0 || day === 6) { reset('Selecciona un día de lunes a viernes'); return; }
            pending = new AbortController();
            const url = new URL(form.dataset.availabilityUrl, location.origin);
            url.searchParams.set('purchase', form.dataset.purchase);
            url.searchParams.set('date', date.value);
            const zone = timezone();
            url.searchParams.set('timezone', zone);
            form.querySelector('[name="timezone"]').value = zone;
            try {
                const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store', signal: pending.signal});
                const body = await response.json();
                if (current !== sequence) return;
                if (!response.ok || body.timezone !== zone) throw new Error('No podemos consultar la agenda en este momento. Inténtalo de nuevo.');
                if (!Array.isArray(body.slots) || !body.slots.length) {
                    reset('No hay horarios disponibles');
                    status.textContent = 'No hay horarios disponibles para esta fecha. Prueba con otro día.';
                    return;
                }
                slot.replaceChildren(new Option('Selecciona un horario', ''));
                for (const item of body.slots) {
                    const time = item.display_time || item.time;
                    if (/^[0-9a-f]{64}$/.test(item.key) && /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(time)) slot.add(new Option(time, item.key));
                }
                slot.disabled = slot.options.length <= 1;
                status.textContent = `Horarios en ${zone}. Volveremos a comprobar la disponibilidad al reservar.`;
            } catch (error) {
                if (error.name === 'AbortError' || current !== sequence) return;
                reset('No se pudo consultar la agenda');
                status.textContent = 'No podemos consultar la agenda en este momento. Inténtalo de nuevo.';
            }
        }
        if (window.flatpickr) {
            picker = flatpickr(date, {
                locale: 'es', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
                allowInput: true, disableMobile: true, minDate: today(),
                disable: [day => day.getDay() === 0 || day.getDay() === 6],
                onDayCreate: function (_, __, ___, day) {
                    day.setAttribute('role', 'button');
                    day.setAttribute('aria-disabled', day.classList.contains('flatpickr-disabled') ? 'true' : 'false');
                },
                onOpen: function () { picker.set('minDate', today()); },
                onChange: load
            });
            picker.altInput.id = 'meeting-date-display';
            picker.altInput.setAttribute('aria-describedby', 'meeting-date-help');
            form.querySelector('label[for="meeting-date"]').htmlFor = picker.altInput.id;
        } else date.addEventListener('change', load);
        slot.addEventListener('change', function () { submit.disabled = !slot.value || form.dataset.bookingReady !== '1'; });
        document.addEventListener('sefar:meeting-timezone', function () {
            pending?.abort(); sequence++;
            form.querySelector('[name="timezone"]').value = timezone();
            date.min = today(); if (picker) picker.set('minDate', today());
            if (date.value) load(); else reset('Selecciona primero una fecha');
        });
        form.addEventListener('submit', function (event) {
            if (!slot.value || slot.disabled || form.dataset.bookingReady !== '1') { event.preventDefault(); return; }
            submit.disabled = true;
            submit.textContent = 'Reservando…';
        });
        if (date.value) load();
    });
})();

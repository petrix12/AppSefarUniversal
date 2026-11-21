(() => {
    const init = () => {
        const modal = document.getElementById('kalendia-appointment-modal');
        if (!modal) return;
        const content = document.getElementById('kalendia-appointment-content');
        const feedback = document.getElementById('kalendia-appointment-feedback');
        const reload = modal.querySelector('[data-reload-appointment]');
        let currentUrl = null;
        let busy = false;
        let sequence = 0;
        const notify = (message, kind = 'danger') => {
            feedback.className = `alert alert-${kind}`;
            feedback.textContent = message;
            feedback.hidden = !message;
        };
        const render = html => {
            content.innerHTML = html; // Authenticated, escaped Blade fragment from our own endpoint.
            window.SefarRenderMeetingTimezone?.(content);
        };
        const read = async () => {
            const response = await fetch(currentUrl, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}});
            const data = await response.json();
            if (!response.ok || typeof data.html !== 'string') throw new Error('No se pudo consultar la cita. Vuelve a intentarlo.');
            return data;
        };
        const load = async () => {
            if (!currentUrl || busy) return;
            const current = ++sequence;
            content.textContent = 'Cargando reunión…';
            notify('');
            reload.disabled = true;
            try { const data = await read(); if (current === sequence) render(data.html); }
            catch (_) { if (current === sequence) notify('No se pudo consultar la cita. Pulsa «Actualizar ficha» para volver a intentarlo.'); }
            finally { if (current === sequence) reload.disabled = false; }
        };
        const open = url => {
            if (busy) return;
            const target = new URL(url, window.location.origin);
            if (target.origin !== window.location.origin) return;
            currentUrl = target.href;
            window.jQuery(modal).modal('show');
            load();
        };
        document.addEventListener('sefar:manage-appointment', event => open(event.detail.url));
        document.addEventListener('click', event => {
            const link = event.target.closest('[data-manage-kalendia-appointment]');
            if (!link) return;
            event.preventDefault(); open(link.href);
        });
        reload.addEventListener('click', load);
        document.addEventListener('sefar:appointment-updated', async () => {
            try {
                const response = await fetch(window.location.href, {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'text/html'}});
                if (!response.ok) return;
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                const history = page.getElementById('kalendia-appointment-history');
                const current = document.getElementById('kalendia-appointment-history');
                if (history && current) current.innerHTML = history.innerHTML;
            } catch (_) { /* The calendar and modal remain available if the optional history refresh fails. */ }
        });
        window.jQuery(modal).on('hide.bs.modal', event => { if (busy) event.preventDefault(); });
        window.jQuery(modal).on('hidden.bs.modal', () => {
            const url = new URL(window.location.href); url.searchParams.delete('appointment');
            window.history.replaceState(null, '', url);
        });
        modal.addEventListener('submit', async event => {
            const form = event.target.closest('form');
            if (!form) return;
            event.preventDefault();
            if (busy || !form.reportValidity()) return;
            const body = new FormData(form);
            busy = true;
            modal.querySelectorAll('button').forEach(button => { button.disabled = true; });
            content.setAttribute('aria-busy', 'true');
            notify('Guardando el cambio en Kalendia…', 'info');
            try {
                // The hidden field named "action" shadows HTMLFormElement.action.
                const response = await fetch(form.getAttribute('action'), {method: 'POST', credentials: 'same-origin', headers: {'Accept': 'application/json'}, body});
                const data = await response.json();
                if (data.html) render(data.html);
                if (!response.ok) {
                    const messages = Object.values(data.errors || {}).flat();
                    notify(messages.join(' ') || data.message || 'No se pudo confirmar el cambio.');
                    return;
                }
                notify(data.message || 'Cita actualizada.', 'success');
                document.dispatchEvent(new CustomEvent('sefar:appointment-updated'));
            } catch (_) {
                // A lost response must be checked before offering another action or UUID.
                try { render((await read()).html); } catch (_) {}
                notify('No se pudo confirmar la respuesta. Comprueba el estado de la ficha antes de iniciar otro cambio.');
                document.dispatchEvent(new CustomEvent('sefar:appointment-updated'));
            } finally {
                busy = false;
                content.setAttribute('aria-busy', 'false');
                modal.querySelectorAll('button').forEach(button => { button.disabled = false; });
            }
        });
        modal.addEventListener('click', async event => {
            if (!event.target.closest('[data-copy-meeting-link]')) return;
            const input = content.querySelector('#kalendia-meeting-link');
            if (!input) return;
            try { await navigator.clipboard.writeText(input.value); notify('Enlace copiado.', 'success'); }
            catch (_) { input.focus(); input.select(); notify('Selecciona y copia el enlace de reunión.', 'info'); }
        });
        if (modal.dataset.appointmentUrl) open(modal.dataset.appointmentUrl);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

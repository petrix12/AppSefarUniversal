(() => {
    'use strict';
    const init = () => {
        const modal = document.getElementById('consultation-payment-modal');
        const jqueryModal = window.jQuery?.fn?.modal;
        const nativeModal = !jqueryModal && window.bootstrap?.Modal;
        if (!modal || (!jqueryModal && !nativeModal) || !window.SefarMountConsultationPayment) return;
        const instance = nativeModal ? nativeModal.getOrCreateInstance(modal) : null;
        const content = modal.querySelector('[data-consultation-content]');
        const feedback = modal.querySelector('[data-consultation-feedback]');
        const timezone = modal.querySelector('[data-consultation-timezone]');
        let disposePayment = () => {}, disposeAgenda = () => {};
        let generation = 0, busy = false, completeUrl = null, scheduleUrl = null, trigger = null;
        const show = () => instance ? instance.show() : window.jQuery(modal).modal('show');
        const hide = () => instance ? instance.hide() : window.jQuery(modal).modal('hide');
        const setBusy = value => {
            busy = value;
            content.setAttribute('aria-busy', String(value));
            modal.querySelectorAll('[data-consultation-close]').forEach(button => { button.disabled = value; });
        };
        const notify = (text, kind = 'info') => {
            feedback.textContent = text; feedback.className = `alert alert-${kind}`; feedback.hidden = !text;
        };
        const stage = number => {
            modal.querySelectorAll('[data-consultation-step]').forEach(step => {
                const current = Number(step.dataset.consultationStep) === number;
                step.classList.toggle('font-weight-bold', current);
                step.classList.toggle('text-muted', Number(step.dataset.consultationStep) > number);
                if (current) step.setAttribute('aria-current', 'step'); else step.removeAttribute('aria-current');
            });
            timezone.hidden = number !== 2;
        };
        const clear = () => { disposePayment(); disposeAgenda(); disposePayment = disposeAgenda = () => {}; content.replaceChildren(); };
        const localUrl = value => {
            if (typeof value !== 'string' || !value.trim()) throw new Error('No se pudo abrir esta consulta. Vuelve a cargar la página.');
            const url = new URL(value, window.location.origin);
            if (url.origin !== window.location.origin) throw new Error('La dirección de la consulta no es válida.');
            return url.href;
        };
        const read = async (url, options = {}) => {
            const response = await fetch(localUrl(url), {credentials: 'same-origin', cache: 'no-store', ...options,
                headers: {'Accept': 'application/json', ...(options.headers || {})}});
            let data;
            try { data = await response.json(); }
            catch (_) { throw new Error('No se pudo consultar esta compra. Vuelve a cargar la página para continuar.'); }
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'No se pudo completar la solicitud.');
            return data;
        };
        const finished = data => {
            clear(); stage(3); notify(data.message || 'Tu solicitud quedó registrada.', 'success');
            const link = document.createElement('a');
            link.href = localUrl(data.next_url); link.className = 'btn btn-primary'; link.textContent = 'Ver Mi agenda'; content.append(link);
            content.focus();
        };
        const schedule = async url => {
            scheduleUrl = localUrl(url);
            const current = generation;
            clear(); stage(2); notify('Pago confirmado. Cargando fechas y horarios…', 'success');
            try {
                const data = await read(scheduleUrl);
                if (current !== generation) return;
                if (data.stage === 'complete') { finished(data); return; }
                if (typeof data.html !== 'string') throw new Error('No se pudo abrir la agenda.');
                content.innerHTML = data.html;
                window.SefarRenderMeetingTimezone?.(content);
                disposeAgenda = window.SefarInitRegistrationAgenda?.(content) || (() => {});
                notify(''); content.focus();
            } catch (error) {
                if (current !== generation) return;
                notify(error.message || 'No se pudo consultar la agenda. Tu pago se conserva.', 'warning');
                const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-primary';
                retry.textContent = 'Volver a consultar fechas y horarios'; retry.addEventListener('click', () => schedule(scheduleUrl)); content.append(retry);
            }
        };
        const check = async url => {
            const current = generation;
            setBusy(true); notify('Comprobando el pago…');
            try {
                const data = await read(url || completeUrl);
                if (current !== generation) return;
                if (data.paid) await schedule(data.next_url);
                else notify(data.message || 'El pago está pendiente de confirmación.', 'info');
            } catch (error) { if (current === generation) notify(error.message, 'warning'); }
            finally { if (current === generation) setBusy(false); }
        };
        document.addEventListener('submit', async event => {
            const form = event.target.closest('[data-consultation-offer]');
            if (!form) return;
            event.preventDefault(); if (busy) return;
            trigger = event.submitter || form.querySelector('button');
            const current = ++generation;
            clear(); stage(1); notify('Preparando tu consulta…'); completeUrl = scheduleUrl = null;
            show();
            const button = form.querySelector('button'); button.disabled = true;
            try {
                const data = await read(form.action, {method: 'POST', body: new FormData(form)});
                if (current !== generation) return;
                completeUrl = localUrl(data.complete_url);
                if (data.paid) { await schedule(data.next_url); return; }
                if (typeof data.html !== 'string') throw new Error('No se pudo abrir el pago.');
                content.innerHTML = data.html;
                notify('');
                disposePayment = window.SefarMountConsultationPayment(content, {onPaid: check, onBusy: setBusy, canSubmit: () => !busy});
                content.focus();
            } catch (error) { if (current === generation) notify(error.message, 'danger'); }
            finally { button.disabled = false; }
        });
        modal.addEventListener('click', event => {
            if (event.target.closest('[data-consultation-close]')) { if (!busy) hide(); return; }
            if (event.target.closest('[data-consultation-agenda-retry]')) { if (!busy) schedule(scheduleUrl); return; }
            const link = event.target.closest('[data-consultation-check]');
            if (link) { event.preventDefault(); if (!busy) check(link.href); }
        });
        modal.addEventListener('submit', async event => {
            const form = event.target.closest('[data-registration-agenda], [data-consultation-booking]');
            if (!form || event.defaultPrevented) return;
            event.preventDefault();
            if (busy || !form.reportValidity()) return;
            const current = generation;
            const button = form.querySelector('[type="submit"]');
            setBusy(true); button.disabled = true; notify('Enviando la fecha y la hora elegidas…');
            try {
                const data = await read(form.action, {method: 'POST', body: new FormData(form)});
                if (current === generation) finished(data);
            } catch (error) {
                if (current === generation) {
                    notify(error.message || 'Comprueba el estado de esta misma solicitud en Mi agenda.', 'warning');
                    button.disabled = false;
                    button.textContent = button.dataset.idleLabel || button.textContent;
                }
            } finally { if (current === generation) setBusy(false); }
        });
        const onHide = event => { if (busy) event.preventDefault(); };
        const onHidden = () => {
            generation++; clear(); completeUrl = scheduleUrl = null; notify(''); timezone.hidden = true;
            trigger?.focus(); trigger = null;
        };
        if (jqueryModal) window.jQuery(modal).on('hide.bs.modal', onHide).on('hidden.bs.modal', onHidden);
        else { modal.addEventListener('hide.bs.modal', onHide); modal.addEventListener('hidden.bs.modal', onHidden); }
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

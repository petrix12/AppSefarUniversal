(() => {
    'use strict';
    const instances = new WeakMap();
    const mount = (root = document, options = {}) => {
        const form = root.querySelector('[data-consultation-payment]');
        if (!form) return () => {};
        if (instances.has(form)) return instances.get(form);
        const button = form.querySelector('[data-payment-submit]');
        const message = form.querySelector('[data-payment-message]');
        const controller = new AbortController();
        let stripe, elements, payment, disposed = false, busy = false;
        const showError = text => { if (!disposed) message.textContent = text; };
        const paid = async () => {
            if (disposed) return;
            if (options.onPaid) await options.onPaid(form.dataset.returnUrl);
            else window.location.replace(form.dataset.returnUrl);
        };
        const init = async () => {
            try {
                const response = await fetch(form.dataset.intentUrl, {method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                    headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value}});
                const data = await response.json();
                if (disposed) return;
                if (!response.ok) { showError(Object.values(data.errors || {}).flat()[0] || data.message || 'No se pudo preparar el pago.'); return; }
                if (data.paid) { await paid(); return; }
                if (!data.client_secret || typeof Stripe !== 'function') throw new Error();
                stripe = Stripe(form.dataset.stripeKey);
                elements = stripe.elements({clientSecret: data.client_secret, locale: 'es'});
                payment = elements.create('payment');
                payment.on('ready', () => { if (!disposed) button.disabled = false; });
                payment.on('loaderror', () => showError('No se pudo cargar la pasarela. Puedes volver a esta misma compra.'));
                payment.mount(form.querySelector('[data-payment-element]'));
            } catch (error) { if (error.name !== 'AbortError') showError('No podemos conectar con la pasarela. Vuelve a comprobar esta misma compra.'); }
        };
        const submit = async event => {
            event.preventDefault();
            if (!stripe || !elements || button.disabled || disposed || busy || (options.canSubmit && !options.canSubmit())) return;
            busy = true; options.onBusy?.(true);
            button.disabled = true; showError('Procesando pago…');
            try {
                const result = await stripe.confirmPayment({elements, confirmParams: {return_url: form.dataset.returnUrl}, redirect: 'if_required'});
                if (result.error) { showError(result.error.message || 'No se pudo confirmar el pago.'); button.disabled = false; }
                else await paid();
            } catch (_) { showError('No tenemos confirmación. Usa «Comprobar el estado de este pago» antes de iniciar otra compra.'); }
            finally { busy = false; options.onBusy?.(false); }
        };
        const dispose = () => {
            if (disposed) return;
            disposed = true; controller.abort();
            form.removeEventListener('submit', submit);
            payment?.destroy();
            instances.delete(form);
        };
        instances.set(form, dispose);
        form.addEventListener('submit', submit);
        init();
        return dispose;
    };
    window.SefarMountConsultationPayment = mount;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => mount()); else mount();
})();

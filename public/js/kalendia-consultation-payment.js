(() => {
    const form = document.querySelector('[data-consultation-payment]');
    if (!form) return;
    const button = form.querySelector('[data-payment-submit]');
    const message = form.querySelector('[data-payment-message]');
    let stripe, elements;
    const showError = text => { message.textContent = text; };
    const init = async () => {
        try {
            const response = await fetch(form.dataset.intentUrl, {method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value}});
            const data = await response.json();
            if (!response.ok) { showError(Object.values(data.errors || {}).flat()[0] || 'No se pudo preparar el pago.'); return; }
            if (data.paid) { location.replace(form.dataset.returnUrl); return; }
            if (!data.client_secret || typeof Stripe !== 'function') throw new Error();
            stripe = Stripe(form.dataset.stripeKey);
            elements = stripe.elements({clientSecret: data.client_secret, locale: 'es'});
            const payment = elements.create('payment');
            payment.mount(form.querySelector('[data-payment-element]'));
            payment.on('ready', () => { button.disabled = false; });
            payment.on('loaderror', () => showError('No se pudo cargar la pasarela. Puedes volver a esta misma compra.'));
        } catch (_) { showError('No podemos conectar con la pasarela. Vuelve a comprobar esta misma compra.'); }
    };
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!stripe || !elements || button.disabled) return;
        button.disabled = true; showError('Procesando pago…');
        try {
            const result = await stripe.confirmPayment({elements, confirmParams: {return_url: form.dataset.returnUrl}, redirect: 'if_required'});
            if (result.error) { showError(result.error.message || 'No se pudo confirmar el pago.'); button.disabled = false; }
            else location.replace(form.dataset.returnUrl);
        } catch (_) { showError('No tenemos confirmación. Usa «Comprobar el estado de este pago» antes de iniciar otra compra.'); }
    });
    init();
})();

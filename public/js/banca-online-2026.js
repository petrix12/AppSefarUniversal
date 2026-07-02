(function () {
  const state = {
    navigationBound: false,
    popBound: false,
    stripeLoading: null,
  };

  function csrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  function money(value) {
    return new Intl.NumberFormat('es-ES', {
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(Number(value || 0));
  }

  function escapeHtml(value) {
    return String(value || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function setBusy(button, busy, text) {
    if (!button) return;

    if (busy) {
      button.dataset.originalText = button.textContent;
      button.disabled = true;
      button.textContent = text;
      return;
    }

    button.disabled = false;
    button.textContent = button.dataset.originalText || button.textContent;
  }

  function showAlert(container, message) {
    const current = container.querySelector('.bo-alert[data-js-alert="1"]');
    if (current) current.remove();

    const alert = document.createElement('div');
    alert.className = 'bo-alert';
    alert.dataset.jsAlert = '1';
    alert.textContent = message;
    container.prepend(alert);
  }

  function isBancaUrl(url) {
    return url.origin === window.location.origin && url.pathname.startsWith('/banca-online-2026');
  }

  async function navigate(url, replace) {
    const target = new URL(url, window.location.href);
    if (!isBancaUrl(target)) {
      window.location.href = target.href;
      return;
    }

    const response = await fetch(target.href, {
      headers: {
        Accept: 'text/html',
        'X-Requested-With': 'XMLHttpRequest',
      },
    });

    if (!response.ok) {
      window.location.href = target.href;
      return;
    }

    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const nextTopbar = doc.querySelector('.bo-topbar');
    const nextMain = doc.querySelector('main');
    const currentTopbar = document.querySelector('.bo-topbar');
    const currentMain = document.querySelector('main');

    if (!nextMain || !currentMain) {
      window.location.href = target.href;
      return;
    }

    document.title = doc.title;

    if (nextTopbar && currentTopbar) {
      currentTopbar.replaceWith(nextTopbar);
    }

    currentMain.replaceWith(nextMain);

    if (replace) {
      window.history.replaceState({}, '', target.href);
    } else {
      window.history.pushState({}, '', target.href);
    }

    window.scrollTo({ top: 0, behavior: 'instant' });
    init();
  }

  function bindNavigation() {
    if (state.navigationBound) return;
    state.navigationBound = true;

    document.addEventListener('click', function (event) {
      const link = event.target.closest('a');
      if (!link || link.target || link.hasAttribute('download')) return;

      const target = new URL(link.href, window.location.href);
      if (!isBancaUrl(target)) return;

      event.preventDefault();
      navigate(target.href, false).catch(() => {
        window.location.href = target.href;
      });
    });

    if (!state.popBound) {
      state.popBound = true;
      window.addEventListener('popstate', function () {
        navigate(window.location.href, true).catch(() => window.location.reload());
      });
    }
  }

  function initConfigurator(root) {
    const form = root.querySelector('#bancaCheckoutForm');
    if (!form || form.dataset.boBound === '1') return;
    form.dataset.boBound = '1';

    const totalNode = root.querySelector('#totalAmount');
    const selectedPackageName = root.querySelector('#selectedPackageName');
    const selectedList = root.querySelector('#selectedList');
    const options = Array.from(root.querySelectorAll('.package-option'));
    const emailInput = root.querySelector('#emailLookup');
    const lookupStatus = root.querySelector('#lookupStatus');
    const newClientFields = root.querySelector('#newClientFields');
    const tieneHermanos = root.querySelector('#tieneHermanos');
    const familiarField = root.querySelector('#familiarField');
    let lookupTimer = null;
    let lookupController = null;

    function lookupRouteContext() {
      const actionUrl = new URL(form.action, window.location.href);
      const segments = actionUrl.pathname.split('/').filter(Boolean);
      const baseIndex = segments.indexOf('banca-online-2026');
      const countryInput = form.querySelector('input[name="country"]');

      return {
        country: segments[baseIndex + 1] || (countryInput ? countryInput.value : ''),
        plan: segments[baseIndex + 2] || segments[baseIndex + 1] || '',
      };
    }

    function setLookupStatus(message, tone) {
      if (!lookupStatus) return;

      lookupStatus.textContent = message || '';
      lookupStatus.classList.toggle('is-error', tone === 'error');
      lookupStatus.classList.toggle('is-success', tone === 'success');
    }

    function focusEmailLookup() {
      if (!emailInput) return;

      const panel = emailInput.closest('.bo-client-panel') || emailInput;
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
      window.setTimeout(() => emailInput.focus({ preventScroll: true }), 320);
    }

    function setNewClientMode(enabled) {
      if (!newClientFields) return;

      newClientFields.classList.toggle('is-hidden', !enabled);
      newClientFields.querySelectorAll('[data-required-when-new]').forEach((field) => {
        if (enabled) {
          field.setAttribute('required', 'required');
        } else {
          field.removeAttribute('required');
        }
      });
    }

    function renderSelectedList(selected) {
      if (!selectedList) return;

      selectedList.replaceChildren();
      selected.forEach((entry) => {
        const service = typeof entry === 'string' ? { name: entry } : entry;
        const item = document.createElement('li');
        const icon = document.createElement('i');
        const copy = document.createElement('span');
        const label = document.createElement('strong');
        icon.className = 'fas fa-check';
        copy.className = 'bo-service-line';
        label.textContent = service.name || 'Servicio incluido';
        copy.append(label);

        if (service.description) {
          const description = document.createElement('small');
          description.textContent = service.description;
          copy.append(description);
        }

        if (service.price !== undefined) {
          const price = document.createElement('span');
          price.textContent = `${money(service.price)} EUR`;
          copy.append(price);
        }

        item.append(icon, copy);
        selectedList.appendChild(item);
      });
    }

    function refreshCards() {
      const selectedInput = options.find((input) => input.checked);

      options.forEach((input) => {
        const card = input.closest('.bo-package-card');
        if (card) card.classList.toggle('selected', input.checked);
      });

      if (!selectedInput) {
        if (totalNode) totalNode.textContent = money(0);
        if (selectedPackageName) selectedPackageName.textContent = 'Selecciona una opcion';
        renderSelectedList([]);
        return;
      }

      let components = [];
      try {
        components = JSON.parse(selectedInput.dataset.components || '[]');
      } catch (error) {
        components = [];
      }

      if (totalNode) totalNode.textContent = money(selectedInput.dataset.price);
      if (selectedPackageName) selectedPackageName.textContent = selectedInput.dataset.name || 'Modalidad seleccionada';
      renderSelectedList(components);
    }

    async function verifyEmail() {
      if (!emailInput || !lookupStatus) return true;

      if (lookupController) lookupController.abort();
      lookupController = new AbortController();
      emailInput.setCustomValidity('');

      try {
        const email = emailInput.value.trim();
        if (!email) {
          setLookupStatus('');
          setNewClientMode(false);
          return true;
        }

        if (!emailInput.checkValidity()) {
          setLookupStatus('');
          setNewClientMode(false);
          return false;
        }

        setLookupStatus('Verificando...');

        const context = lookupRouteContext();
        const lookupUrl = new URL('/banca-online-2026/cliente', window.location.origin);
        lookupUrl.searchParams.set('email', email);
        lookupUrl.searchParams.set('country', context.country);
        lookupUrl.searchParams.set('plan', context.plan);

        const response = await fetch(lookupUrl.href, {
          headers: { Accept: 'application/json' },
          signal: lookupController.signal,
        });
        const data = await response.json();

        if (data.has_paid_similar_plan) {
          const message = data.message || 'Este correo ya tiene un pago registrado para un plan similar. Si quieres registrar a otro familiar, usa un correo diferente.';
          emailInput.setCustomValidity(message);
          setLookupStatus(message, 'error');
          setNewClientMode(false);
          return false;
        }

        if (data.exists) {
          setLookupStatus('');
          setNewClientMode(false);
        } else {
          setLookupStatus('Correo no registrado. Completa los datos basicos.');
          setNewClientMode(true);
        }

        return true;
      } catch (error) {
        if (error.name !== 'AbortError') {
          emailInput.setCustomValidity('');
          setLookupStatus('No se pudo verificar el correo. Completa los datos basicos.', 'error');
          setNewClientMode(true);
        }

        return error.name === 'AbortError';
      }
    }

    options.forEach((input) => {
      input.addEventListener('change', () => {
        refreshCards();
        focusEmailLookup();
      });
    });

    if (emailInput) {
      emailInput.addEventListener('input', () => {
        window.clearTimeout(lookupTimer);
        lookupTimer = window.setTimeout(verifyEmail, 550);
      });

      emailInput.addEventListener('blur', () => {
        window.clearTimeout(lookupTimer);
        verifyEmail();
      });
    }

    if (tieneHermanos && familiarField) {
      tieneHermanos.addEventListener('change', () => {
        const input = familiarField.querySelector('input');
        const show = tieneHermanos.value === '1';
        familiarField.classList.toggle('is-hidden', !show);
        if (show) {
          input.setAttribute('required', 'required');
        } else {
          input.removeAttribute('required');
          input.value = '';
        }
      });
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const submitButton = form.querySelector('button[type="submit"]');
      const ok = await verifyEmail();

      if (!ok || !form.reportValidity()) return;

      setBusy(submitButton, true, 'Preparando pago...');

      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: new FormData(form),
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
          const emailError = payload.errors && payload.errors.email && payload.errors.email[0];
          if (emailError && emailInput) {
            emailInput.setCustomValidity(emailError);
            setLookupStatus(emailError, 'error');
            emailInput.reportValidity();
            setBusy(submitButton, false);
            return;
          }

          showAlert(form, payload.message || 'No se pudo preparar el pago.');
          setBusy(submitButton, false);
          return;
        }

        renderPaymentStep(payload.checkout);
      } catch (error) {
        showAlert(form, 'No se pudo conectar con el servidor.');
        setBusy(submitButton, false);
      }
    });

    setNewClientMode(!newClientFields || !newClientFields.classList.contains('is-hidden'));
    refreshCards();
  }

  function paymentItems(items) {
    return (items || [])
      .map((item) => {
        const service = typeof item === 'string' ? { name: item } : item;
        const description = service.description
          ? `<small>${escapeHtml(service.description)}</small>`
          : '';
        const price = service.price !== undefined
          ? `<span>${money(service.price)} EUR</span>`
          : '';

        return `
        <li>
          <i class="fas fa-check"></i>
          <span class="bo-service-line">
            <strong>${escapeHtml(service.name)}</strong>
            ${description}
            ${price}
          </span>
        </li>
      `;
      })
      .join('');
  }

  function maxInstallmentsForInitial(options, initialPercent) {
    const rules = (options.rules || [])
      .map((rule) => ({
        min: Number(rule.min_initial_percent || 0),
        max: Number(rule.max_installments || 1),
      }))
      .filter((rule) => rule.min > 0 && rule.max > 0)
      .sort((a, b) => b.min - a.min);
    const match = rules.find((rule) => initialPercent >= rule.min);

    return Math.max(1, Number(match ? match.max : 1));
  }

  function checkoutQuote(options, period, initialPercent, count) {
    const baseTotal = Number(options.base_total || 0);
    const maxCount = maxInstallmentsForInitial(options, initialPercent);
    const installments = Math.max(1, Math.min(maxCount, Number(count || maxCount)));
    const initialAmount = Math.round((baseTotal * initialPercent / 100) * 100) / 100;
    const remainingAmount = Math.max(0, Math.round((baseTotal - initialAmount) * 100) / 100);
    const surchargePercent = Number(period && period.surcharge_percent ? period.surcharge_percent : 0);
    const surchargeAmount = Math.round((remainingAmount * surchargePercent / 100) * 100) / 100;
    const financedAmount = Math.round((remainingAmount + surchargeAmount) * 100) / 100;
    const installmentAmount = installments > 0 ? Math.round((financedAmount / installments) * 100) / 100 : 0;

    return {
      baseTotal,
      maxCount,
      installments,
      initialPercent,
      initialAmount,
      remainingAmount,
      surchargePercent,
      surchargeAmount,
      financedAmount,
      installmentAmount,
      contractTotal: Math.round((initialAmount + financedAmount) * 100) / 100,
    };
  }

  function checkoutPaymentOptionsHtml(checkout) {
    const options = checkout.payment_options || {};
    const periods = options.periods || [];
    const installmentsEnabled = options.installments_enabled && periods.length > 0;
    const minInitial = Number(options.min_initial_percent || 20);
    const maxInitial = Number(options.max_initial_percent || 99);
    const periodChoices = periods.map((period) => `
      <label class="bo-period-choice">
        <input type="radio" name="checkout_period_choice" value="${escapeHtml(period.slug)}">
        <span>
          <strong>${escapeHtml(period.label)}</strong>
          <small>${Number(period.surcharge_percent || 0).toLocaleString('es-ES', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}% recargo</small>
        </span>
      </label>
    `).join('');

    return `
      <div class="bo-payment-plan bo-checkout-payment-options" id="checkoutPaymentOptions" data-payment-options="${escapeHtml(JSON.stringify(options))}">
        <input type="hidden" name="payment_mode" id="checkoutPaymentMode" value="full">
        <input type="hidden" name="payment_period" id="checkoutPaymentPeriod" value="">
        <input type="hidden" name="initial_percent" id="checkoutInitialPercent" value="${escapeHtml(minInitial)}">

        <label class="bo-pay-choice is-active" data-checkout-payment-choice="full">
          <input type="radio" name="checkout_payment_choice" value="full" checked>
          <span>
            <strong>Pago unico</strong>
            <small id="checkoutFullPaymentLabel">${escapeHtml(checkout.contract_total_label || checkout.total_label)} EUR ahora</small>
          </span>
        </label>

        ${installmentsEnabled ? `
          <label class="bo-pay-choice" data-checkout-payment-choice="installments">
            <input type="radio" name="checkout_payment_choice" value="installments">
            <span>
              <strong>Pago por cuotas</strong>
              <small id="checkoutInstallmentPaymentLabel">Define inicial, periodo y cuotas</small>
            </span>
          </label>

          <div class="bo-installment-checkout is-hidden" id="checkoutInstallmentControls">
            <div class="bo-period-choice-grid" id="checkoutPeriodChoices">${periodChoices}</div>
            <label class="bo-field bo-range-field">
              <span>Inicial: <strong id="checkoutInitialPercentLabel">${money(minInitial)}%</strong></span>
              <input type="range" id="checkoutInitialSlider" min="${escapeHtml(minInitial)}" max="${escapeHtml(maxInitial)}" step="1" value="${escapeHtml(minInitial)}">
            </label>
            <label class="bo-field bo-installment-count-field" id="checkoutInstallmentCountField">
              <span>Numero de cuotas</span>
              <select name="installments_count" id="checkoutInstallmentsCount"></select>
            </label>
          </div>
        ` : ''}
      </div>
    `;
  }

  function setupCheckoutPaymentOptions(root) {
    const box = root.querySelector('#checkoutPaymentOptions');
    const form = root.querySelector('#payment-form');
    if (!box || !form) return;

    let options = {};
    try {
      options = JSON.parse(box.dataset.paymentOptions || '{}');
    } catch (error) {
      options = {};
    }

    const choices = Array.from(box.querySelectorAll('[data-checkout-payment-choice]'));
    const modeInput = box.querySelector('#checkoutPaymentMode');
    const periodInput = box.querySelector('#checkoutPaymentPeriod');
    const initialInput = box.querySelector('#checkoutInitialPercent');
    const controls = box.querySelector('#checkoutInstallmentControls');
    const periodRadios = Array.from(box.querySelectorAll('input[name="checkout_period_choice"]'));
    const initialSlider = box.querySelector('#checkoutInitialSlider');
    const initialLabel = box.querySelector('#checkoutInitialPercentLabel');
    const countSelect = box.querySelector('#checkoutInstallmentsCount');
    const fullLabel = box.querySelector('#checkoutFullPaymentLabel');
    const installmentLabel = box.querySelector('#checkoutInstallmentPaymentLabel');
    const totalLabel = root.querySelector('#paymentTotalLabel');
    const totalAmount = root.querySelector('#paymentTotalAmount');
    const breakdown = root.querySelector('[data-payment-breakdown]');
    const submitButton = form.querySelector('#submit-button');
    const periods = options.periods || [];

    function currentMode() {
      return modeInput ? modeInput.value : 'full';
    }

    function selectedPeriod() {
      const selected = periodRadios.find((radio) => radio.checked) || periodRadios[0];
      if (selected && !selected.checked) selected.checked = true;

      return periods.find((period) => period.slug === (selected ? selected.value : '')) || periods[0] || null;
    }

    function setMode(mode) {
      if (mode === 'installments' && (!options.installments_enabled || periods.length === 0)) {
        mode = 'full';
      }

      if (modeInput) modeInput.value = mode;
      choices.forEach((choice) => {
        const active = choice.dataset.checkoutPaymentChoice === mode;
        const radio = choice.querySelector('input[type="radio"]');
        choice.classList.toggle('is-active', active);
        if (radio) radio.checked = active;
      });
      if (controls) controls.classList.toggle('is-hidden', mode !== 'installments');
    }

    function fillCounts(quote) {
      if (!countSelect) return quote.installments;

      const previous = Number(countSelect.value || quote.maxCount);
      const selectedCount = Math.max(1, Math.min(quote.maxCount, previous));
      countSelect.replaceChildren();

      for (let count = 1; count <= quote.maxCount; count++) {
        const countQuote = checkoutQuote(options, selectedPeriod(), quote.initialPercent, count);
        const option = document.createElement('option');
        option.value = String(count);
        option.textContent = `${count} cuotas de ${money(countQuote.installmentAmount)} EUR`;
        option.selected = count === selectedCount;
        countSelect.appendChild(option);
      }

      return selectedCount;
    }

    function renderBreakdown(mode, quote, period) {
      if (!breakdown) return;

      if (mode !== 'installments') {
        const discount = Number(breakdown.dataset.discount || 0);

        if (discount > 0) {
          breakdown.classList.remove('is-hidden');
          breakdown.innerHTML = `
            <span>Subtotal <strong>${escapeHtml(breakdown.dataset.subtotalLabel || money(quote.baseTotal))} EUR</strong></span>
            <span>Descuento <strong>-${escapeHtml(breakdown.dataset.discountLabel || money(discount))} EUR</strong></span>
          `;
        } else {
          breakdown.innerHTML = '';
          breakdown.classList.add('is-hidden');
        }
        return;
      }

      breakdown.classList.remove('is-hidden');
      breakdown.innerHTML = `
        <span>Total base <strong>${money(quote.baseTotal)} EUR</strong></span>
        <span>Inicial hoy (${money(quote.initialPercent)}%) <strong>${money(quote.initialAmount)} EUR</strong></span>
        <span>Saldo financiado <strong>${money(quote.remainingAmount)} EUR</strong></span>
        ${quote.surchargeAmount > 0 ? `<span>Recargo ${money(quote.surchargePercent)}% <strong>${money(quote.surchargeAmount)} EUR</strong></span>` : ''}
        <span>${quote.installments} cuotas ${escapeHtml(period ? period.plural_label : '')} <strong>${money(quote.installmentAmount)} EUR</strong></span>
      `;
    }

    function refresh() {
      const mode = currentMode();
      const baseTotal = Number(options.base_total || 0);
      const period = selectedPeriod();
      const minInitial = Number(options.min_initial_percent || 20);
      const initialPercent = Number(initialSlider ? initialSlider.value : minInitial);
      let quote = checkoutQuote(options, period, initialPercent, countSelect ? countSelect.value : null);
      const count = fillCounts(quote);
      quote = checkoutQuote(options, period, initialPercent, count);

      if (periodInput) periodInput.value = period ? period.slug : '';
      if (initialInput) initialInput.value = String(initialPercent);
      if (initialLabel) initialLabel.textContent = `${money(initialPercent)}%`;
      if (fullLabel) fullLabel.textContent = `${money(baseTotal)} EUR ahora`;
      if (installmentLabel && period) {
        installmentLabel.textContent = `${money(quote.initialAmount)} EUR inicial + ${quote.installments} cuotas ${period.plural_label} de ${money(quote.installmentAmount)} EUR`;
      }

      const amountDueNow = mode === 'installments' ? quote.initialAmount : baseTotal;
      if (totalLabel) totalLabel.textContent = mode === 'installments' ? 'Total a pagar hoy' : 'Total a pagar';
      if (totalAmount) totalAmount.textContent = money(amountDueNow);
      if (submitButton) {
        submitButton.innerHTML = `${mode === 'installments' ? 'Pagar inicial' : 'Pagar ahora'} <i class="fas fa-credit-card"></i>`;
      }
      renderBreakdown(mode, quote, period);
    }

    if (box.dataset.boCheckoutOptionsBound !== '1') {
      box.dataset.boCheckoutOptionsBound = '1';
      choices.forEach((choice) => {
        choice.addEventListener('click', () => {
          setMode(choice.dataset.checkoutPaymentChoice || 'full');
          refresh();
        });
      });
      periodRadios.forEach((radio) => radio.addEventListener('change', refresh));
      if (initialSlider) initialSlider.addEventListener('input', refresh);
      if (countSelect) countSelect.addEventListener('change', refresh);
    }

    if (periodRadios[0] && !periodRadios.some((radio) => radio.checked)) {
      periodRadios[0].checked = true;
    }
    setMode(currentMode());
    refresh();
  }

  function renderPaymentStep(checkout) {
    const main = document.querySelector('main');
    if (!main) return;

    main.className = 'bo-container';
    main.innerHTML = `
      <section class="bo-payment-title">
        <span class="bo-eyebrow"><i class="fas fa-lock"></i> Pago seguro</span>
        <h1>Completar contratacion</h1>
        <p>${escapeHtml(checkout.plan_title)}.</p>
      </section>

      <div class="bo-payment-layout">
        <section class="bo-panel">
          <h2>Servicios seleccionados</h2>
          <ul class="bo-payment-items">${paymentItems(checkout.items)}</ul>
        </section>

        <aside class="bo-panel">
          ${checkout.stripe_key ? '' : '<div class="bo-alert">No esta configurada la clave publica de Stripe para este servicio.</div>'}
          <div class="bo-payment-breakdown is-hidden" data-payment-breakdown data-subtotal-label="${escapeHtml(checkout.subtotal_label)}" data-discount="${escapeHtml(checkout.discount || 0)}" data-discount-label="${escapeHtml(checkout.discount_label)}"></div>
          <div class="bo-total-label" id="paymentTotalLabel">Total a pagar</div>
          <div class="bo-total"><span id="paymentTotalAmount">${escapeHtml(checkout.contract_total_label || checkout.total_label)}</span> <small>${escapeHtml(checkout.currency || 'EUR')}</small></div>

          <form id="payment-form" class="bo-form" data-stripe-key="${escapeHtml(checkout.stripe_key)}" data-process-url="${escapeHtml(checkout.process_url)}" data-success-url="${escapeHtml(checkout.thank_you_url)}">
            ${checkoutPaymentOptionsHtml(checkout)}
            <div class="bo-field-grid">
              <label class="bo-field">
                <span>Nombres</span>
                <input type="text" name="first_name" required>
              </label>
              <label class="bo-field">
                <span>Apellidos</span>
                <input type="text" name="last_name" required>
              </label>
            </div>
            <label class="bo-field">
              <span>Correo electronico</span>
              <input type="email" name="email" value="${escapeHtml(checkout.billing && checkout.billing.email)}" required>
            </label>
            <label class="bo-field">
              <span>Telefono</span>
              <input type="tel" name="phone">
            </label>
            <label class="bo-field">
              <span>Direccion</span>
              <input type="text" name="address_line1" required>
            </label>
            <label class="bo-field">
              <span>Direccion adicional</span>
              <input type="text" name="address_line2">
            </label>
            <div class="bo-field-grid">
              <label class="bo-field">
                <span>Ciudad</span>
                <input type="text" name="city" required>
              </label>
              <label class="bo-field">
                <span>Estado o provincia</span>
                <input type="text" name="state">
              </label>
            </div>
            <div class="bo-field-grid">
              <label class="bo-field">
                <span>Codigo postal</span>
                <input type="text" name="postal_code" required>
              </label>
              <label class="bo-field">
                <span>Pais ISO 2</span>
                <input type="text" name="country" value="VE" maxlength="2" minlength="2" required>
              </label>
            </div>
            <label class="bo-field">
              <span>Tarjeta</span>
              <div id="card-element"></div>
            </label>
            <div class="bo-card-errors" id="card-errors"></div>
            <button class="bo-button bo-button-primary" id="submit-button" type="submit" ${checkout.stripe_key ? '' : 'disabled'}>
              Pagar ahora <i class="fas fa-credit-card"></i>
            </button>
          </form>
        </aside>
      </div>
    `;

    window.history.pushState({}, '', checkout.payment_url);
    window.scrollTo({ top: 0, behavior: 'instant' });
    initPaymentForm(document);
  }

  function loadStripeJs() {
    if (window.Stripe) return Promise.resolve();
    if (state.stripeLoading) return state.stripeLoading;

    state.stripeLoading = new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.async = true;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });

    return state.stripeLoading;
  }

  async function initPaymentForm(root) {
    const form = root.querySelector('#payment-form');
    if (!form || form.dataset.boPaymentBound === '1') return;
    form.dataset.boPaymentBound = '1';

    const stripeKey = form.dataset.stripeKey;
    const processUrl = form.dataset.processUrl;
    const submitButton = form.querySelector('#submit-button');
    const errorNode = form.querySelector('#card-errors');

    setupCheckoutPaymentOptions(root);

    if (!stripeKey) return;

    try {
      await loadStripeJs();
    } catch (error) {
      errorNode.textContent = 'No se pudo cargar la pasarela de pago.';
      submitButton.disabled = true;
      return;
    }

    const stripe = window.Stripe(stripeKey);
    const elements = stripe.elements();
    const card = elements.create('card', {
      hidePostalCode: true,
      style: {
        base: {
          fontSize: '16px',
          color: '#093143',
          '::placeholder': { color: '#607783' },
        },
        invalid: { color: '#ac2630' },
      },
    });

    card.mount('#card-element');
    card.on('change', function (event) {
      errorNode.textContent = event.error ? event.error.message : '';
    });

    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;

      setBusy(submitButton, true, 'Procesando...');
      errorNode.textContent = '';

      const formData = new FormData(form);
      const billingDetails = {
        name: `${formData.get('first_name')} ${formData.get('last_name')}`.trim(),
        email: formData.get('email'),
        phone: formData.get('phone') || undefined,
        address: {
          line1: formData.get('address_line1'),
          line2: formData.get('address_line2') || undefined,
          city: formData.get('city'),
          state: formData.get('state') || undefined,
          postal_code: formData.get('postal_code'),
          country: String(formData.get('country')).toUpperCase(),
        },
      };

      const result = await stripe.createPaymentMethod({
        type: 'card',
        card: card,
        billing_details: billingDetails,
      });

      if (result.error) {
        errorNode.textContent = result.error.message;
        setBusy(submitButton, false);
        return;
      }

      formData.append('payment_method_id', result.paymentMethod.id);

      try {
        const response = await fetch(processUrl, {
          method: 'POST',
          headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
          },
          body: formData,
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
          errorNode.textContent = payload.message || 'No se pudo procesar el pago.';
          setBusy(submitButton, false);
          return;
        }

        renderThankYou(payload.thank_you, payload.redirect_url);
      } catch (error) {
        errorNode.textContent = 'No se pudo conectar con la pasarela de pago.';
        setBusy(submitButton, false);
      }
    });
  }

  function renderThankYou(thankYou, url) {
    const main = document.querySelector('main');
    if (!main) return;

    const name = thankYou && thankYou.name ? `, ${escapeHtml(thankYou.name)}` : '';
    const paymentPlan = thankYou && thankYou.payment_plan ? thankYou.payment_plan : {};
    const isInstallmentPayment = paymentPlan.mode === 'installments';
    const installmentSummary = isInstallmentPayment ? `
      <div class="bo-payment-breakdown">
        <span>Total del plan <strong>${money(paymentPlan.contract_total)} ${escapeHtml(thankYou.currency || 'EUR')}</strong></span>
        <span>Inicial recibido <strong>${escapeHtml(thankYou.total_label)} ${escapeHtml(thankYou.currency || 'EUR')}</strong></span>
        <span>${escapeHtml(paymentPlan.installments_count || 0)} cuotas ${escapeHtml(paymentPlan.period_plural_label || 'mensuales')} <strong>${money(paymentPlan.installment_amount)} ${escapeHtml(thankYou.currency || 'EUR')}</strong></span>
      </div>
    ` : '';

    main.className = 'bo-confirm-wrap';
    main.innerHTML = `
      <section class="bo-confirm-card">
        <img class="bo-confirm-logo" src="/img/logo2.png" alt="Sefar Universal">
        <div class="bo-confirm-badge"><i class="fas fa-check-circle"></i> ${isInstallmentPayment ? 'Inicial recibido' : 'Pago recibido'}</div>
        <h1>Gracias${name}.</h1>
        <p>Tu contratacion de Banca Online 2026 fue registrada correctamente. El equipo de Sefar Universal continuara el seguimiento operativo del servicio seleccionado.</p>
        ${installmentSummary}
        <div class="bo-confirm-total">${escapeHtml(thankYou && thankYou.total_label)} ${escapeHtml(thankYou && thankYou.currency ? thankYou.currency : 'EUR')}</div>
        <ul class="bo-confirm-list">${paymentItems(thankYou ? thankYou.items : [])}</ul>
      </section>
    `;

    if (url) window.history.pushState({}, '', url);
    window.scrollTo({ top: 0, behavior: 'instant' });
  }

  function init() {
    bindNavigation();
    initConfigurator(document);
    initPaymentForm(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

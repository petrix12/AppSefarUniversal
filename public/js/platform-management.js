(() => {
  'use strict';
  const modal = document.querySelector('#platform-record-modal');
  if (!modal || !window.jQuery) return;
  const content = modal.querySelector('#platform-record-content');
  const feedback = modal.querySelector('#platform-feedback');
  let currentUrl = null;
  const message = (text, error = false) => {
    feedback.textContent = text; feedback.className = `alert alert-${error ? 'warning' : 'success'}`; feedback.hidden = !text;
  };
  const request = async (url, options = {}) => {
    const response = await fetch(url, {credentials: 'same-origin', ...options,
      headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(options.headers || {})}});
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const detail = Object.values(data.errors || {}).flat().join(' ');
      throw new Error(detail || data.message || 'No se pudo completar la solicitud. Actualiza la ficha para comprobar el resultado.');
    }
    return data;
  };
  const openRecord = async (url) => {
    currentUrl = url; content.textContent = 'Cargando registro…'; message(''); window.jQuery(modal).modal('show');
    try { const data = await request(url); if (currentUrl === url) { content.innerHTML = data.html; window.SefarRenderMeetingTimezone?.(content); } }
    catch (error) { message(error.message, true); content.textContent = ''; }
  };
  document.addEventListener('click', async (event) => {
    const templateButton = event.target.closest('[data-platform-template]');
    if (templateButton) {
      const template = document.querySelector(templateButton.dataset.platformTemplate);
      if (template) {
        content.replaceChildren(template.content.cloneNode(true));
        const operation = content.querySelector('[name="operation_id"]');
        if (operation && window.crypto?.randomUUID) operation.value = window.crypto.randomUUID();
        window.SefarRenderMeetingTimezone?.(content);
        message(''); window.jQuery(modal).modal('show');
      }
      return;
    }
    const record = event.target.closest('[data-platform-record]');
    if (record) { event.preventDefault(); await openRecord(record.dataset.platformRecord); return; }
    const notes = event.target.closest('[data-platform-notes]');
    if (notes) {
      notes.disabled = true;
      try { const data = await request(notes.dataset.platformNotes); notes.parentElement.querySelector('[data-platform-notes-content]').innerHTML = data.html; }
      catch (error) { message(error.message, true); } finally { notes.disabled = false; }
      return;
    }
    const load = event.target.closest('[data-booking-load], [data-booking-slots]');
    if (!load) return;
    const section = load.closest('[data-platform-booking]'); const form = section.querySelector('[data-booking-form]');
    const url = new URL(section.dataset.optionsUrl, window.location.origin);
    if (load.hasAttribute('data-booking-slots')) {
      if (!form.elements.service_id.value || !form.elements.date.value) { message('Selecciona el servicio y la fecha.', true); return; }
      ['service_id','date','timezone'].forEach((name) => url.searchParams.set(name, form.elements[name].value));
    }
    load.disabled = true;
    try {
      const data = await request(url);
      if (load.hasAttribute('data-booking-load')) {
        form.elements.service_id.replaceChildren(new Option('Selecciona un servicio', ''), ...data.services.map((service) => new Option(service.name, service.id)));
        const zone = window.SefarMeetingTimezone || Intl.DateTimeFormat().resolvedOptions().timeZone;
        if (zone && ![...form.elements.timezone.options].some((option) => option.value === zone)) form.elements.timezone.add(new Option(zone, zone));
        if (zone) form.elements.timezone.value = zone;
        form.hidden = false;
      } else {
        form.elements.slot.replaceChildren(new Option(data.slots.length ? 'Selecciona una hora' : 'Sin horarios disponibles', ''), ...data.slots.map((slot) => new Option(slot.display_time, slot.key)));
      }
      message('');
    } catch (error) { message(error.message, true); } finally { load.disabled = false; }
  });
  document.addEventListener('change', (event) => {
    const form = event.target.closest('[data-booking-form]');
    if (form && ['service_id','date','timezone'].includes(event.target.name)) form.elements.slot.replaceChildren(new Option('Consulta nuevamente los horarios', ''));
    if (form && event.target.name === 'date' && event.target.value) {
      const day = new Date(`${event.target.value}T12:00:00Z`).getUTCDay();
      if (day === 0 || day === 6) { event.target.value = ''; message('Selecciona una fecha de lunes a viernes.', true); }
    }
  });
  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-platform-form], [data-platform-lookup], [data-platform-appointment-forms] form'); if (!form) return;
    event.preventDefault(); const buttons = [...form.querySelectorAll('button[type="submit"]')]; const body = new FormData(form);
    buttons.forEach((button) => { button.disabled = true; });
    if (form.hasAttribute('data-platform-lookup')) { content.textContent = ''; message(''); window.jQuery(modal).modal('show'); }
    try {
      const data = await request(form.action, {method: 'POST', body});
      if (data.html) content.innerHTML = form.closest('[data-platform-appointment-forms]') ? `<div data-platform-appointment-forms>${data.html}</div>` : data.html;
      window.SefarRenderMeetingTimezone?.(content);
      message(data.message || 'Información actualizada.');
    } catch (error) { message(error.message, true); }
    finally { buttons.forEach((button) => { button.disabled = false; }); }
  });
})();

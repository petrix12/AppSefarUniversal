(() => {
  'use strict';
  const watchSnapshot = () => {
    const notice = document.querySelector('[data-cos-refresh-status-url]');
    if (!notice) return;

    notice.querySelector('[data-cos-refresh-reload]').addEventListener('click', () => window.location.reload());

    let attempts = 0;
    const check = async () => {
      attempts++;
      try {
        const response = await fetch(notice.dataset.cosRefreshStatusUrl, {
          headers: { Accept: 'application/json' },
          cache: 'no-store',
        });
        if (response.ok && (await response.json()).pending === false) {
          notice.hidden = false;
          document.dispatchEvent(new CustomEvent('cos:refresh-ready', { detail: { observed_ms: performance.now() } }));
          return;
        }
      } catch {
        // A failed check can be retried without interrupting the open form.
      }
      if (attempts < 36) window.setTimeout(check, 5000);
    };
    window.setTimeout(check, 5000);
  };

  const init = () => {
    watchSnapshot();
    const workspace = document.querySelector('[data-cos-workspace]');
    const tabs = workspace?.querySelector('#formTabs');
    const Tab = window.bootstrap?.Tab;
    if (!workspace || !tabs || !Tab) return; // The original tabs remain usable if enhancement is unavailable.
    const areas = workspace.querySelector('[data-cos-areas]');
    const label = workspace.querySelector('[data-cos-area-label]');
    const groups = {
      overview: ['mystatus-tab'],
      profile: ['personal-data-tab','basic-edit-tab','formulario-001-tab','adminchangepassword-tab','mypassword-tab','change-history-tab'],
      case: ['familiars-tab','documents-tab','client-req-tab','admin-req-tab','etiquetado-tab'],
      finance: ['payments-tab','paymentspen-tab','negocios-tab'],
      meetings: ['meetings-tab'],
      internal: ['teamleader-migration-tab','client-tasks-tab','client-chat-tab','client-notes-link']
    };
    const labels = {profile: 'Información personal y cuenta', case: 'Documentos y familia', finance: 'Pagos y negocios', internal: 'Trabajo del equipo'};
    const buttons = [...tabs.querySelectorAll('.nav-link')];
    Object.values(groups).flat().forEach((id) => {
      const button = buttons.find((candidate) => candidate.id === id);
      if (button) tabs.append(button.closest('li'));
    });
    const groupFor = (button) => Object.keys(groups).find((key) => groups[key].includes(button?.id));
    const available = (group) => buttons.filter((button) => groups[group].includes(button.id));
    if (buttons.some((button) => !groupFor(button))) return; // A future tab stays reachable until its area is configured.
    const selected = {};
    let group = 'overview';
    const display = (name) => {
      group = name;
      buttons.forEach((button) => { button.closest('li').hidden = !groups[name].includes(button.id); });
      const singlePane = name === 'overview' || name === 'meetings';
      tabs.hidden = singlePane;
      label.hidden = singlePane; label.textContent = labels[name] || '';
      areas.querySelectorAll('[data-cos-area]').forEach((button) => { button.setAttribute('aria-pressed', String(button.dataset.cosArea === name)); });
    };
    areas.querySelectorAll('[data-cos-area]').forEach((button) => {
      const name = button.dataset.cosArea;
      if (!available(name).length) { button.hidden = true; return; }
      button.addEventListener('click', () => {
        const target = selected[name] || available(name)[0];
        if (!target || target.tagName !== 'BUTTON') return;
        display(name); Tab.getOrCreateInstance(target).show();
      });
    });
    buttons.forEach((button) => {
      if (button.getAttribute('role') !== 'tab') return;
      button.setAttribute('aria-selected', String(button.classList.contains('active')));
      button.addEventListener('shown.bs.tab', () => {
        const name = groupFor(button); if (!name) return;
        selected[name] = button; display(name);
        // Existing DataTables can measure their columns once their original pane is visible.
        if (name !== 'overview' && window.jQuery?.fn?.dataTable) window.jQuery.fn.dataTable.tables({visible: true, api: true}).columns.adjust();
      });
    });
    const initial = buttons.find((button) => button.classList.contains('active'));
    display(groupFor(initial) || 'overview'); areas.hidden = false;
    const fromHash = () => {
      const target = buttons.find((button) => button.dataset.bsTarget === window.location.hash);
      if (target && groupFor(target)) { display(groupFor(target)); Tab.getOrCreateInstance(target).show(); }
    };
    window.addEventListener('hashchange', fromHash); fromHash();
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();

(() => {
  'use strict';

  function initializeProfileSearch() {
    const panel = document.getElementById('personal_data');
    if (!panel || panel.dataset.profileSearchReady === 'true') return;

    const toolbar = panel.querySelector('[data-profile-search]');
    const input = panel.querySelector('[data-profile-search-input]');
    const clearButton = panel.querySelector('[data-profile-search-clear]');
    const status = panel.querySelector('[data-profile-search-status]');
    const emptyMessage = panel.querySelector('[data-profile-search-empty]');
    if (!toolbar || !input || !clearButton || !status || !emptyMessage) return;

    function normalizeLabel(value) {
      return value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('es').trim();
    }

    // Hidden legacy fields stay outside the search, including in the disabled client view.
    const fields = [...panel.querySelectorAll(
      '.cos-profile-field, .cos-custom-groups .cos-group-body > .row > [class*="col-"]'
    )].filter((field) => {
      const hiddenAncestor = field.closest('.d-none, [hidden]');
      return !hiddenAncestor || hiddenAncestor === panel || !panel.contains(hiddenAncestor);
    }).map((field) => ({
      element: field,
      label: normalizeLabel([...field.querySelectorAll('label')].map((label) => label.textContent).join(' ')),
    })).filter((field) => field.label !== '');

    const containers = [...panel.querySelectorAll(
      '.cos-profile-grid, .cos-profile-section, .cos-custom-groups, .cos-custom-groups details, '
      + '.cos-custom-groups .cos-group-body > .row'
    )].filter((container) => fields.some((field) => container.contains(field.element)));
    const indexes = [...panel.querySelectorAll('.cos-custom-groups .cos-section-index')];
    const affectedElements = [...new Set([...fields.map((field) => field.element), ...containers, ...indexes])];
    let originalState = null;

    function restoreProfile() {
      if (originalState) {
        originalState.forEach((state, element) => {
          if (state.hidden === null) element.removeAttribute('hidden');
          else element.setAttribute('hidden', state.hidden);
          if (element.tagName === 'DETAILS') element.open = state.open;
        });
        originalState = null;
      }
      clearButton.hidden = true;
      emptyMessage.hidden = true;
      status.textContent = 'Busca por el nombre del campo.';
    }

    function filterProfile() {
      const query = normalizeLabel(input.value);
      if (!query) {
        restoreProfile();
        return;
      }

      // Snapshot when filtering starts so clearing also restores accordions opened by the user.
      if (!originalState) {
        originalState = new Map(affectedElements.map((element) => [element, {
          hidden: element.getAttribute('hidden'),
          open: element.tagName === 'DETAILS' ? element.open : null,
        }]));
      }

      const terms = query.split(/\s+/);
      const matchingFields = fields.filter((field) => terms.every((term) => field.label.includes(term)));
      const matchingElements = new Set(matchingFields.map((field) => field.element));
      fields.forEach((field) => { field.element.hidden = !matchingElements.has(field.element); });
      containers.forEach((container) => {
        const matches = matchingFields.some((field) => container.contains(field.element));
        container.hidden = !matches;
        if (container.tagName === 'DETAILS') container.open = matches;
      });
      indexes.forEach((index) => { index.hidden = true; });

      clearButton.hidden = false;
      emptyMessage.hidden = matchingFields.length !== 0;
      status.textContent = matchingFields.length === 0
        ? 'No hay campos que coincidan con la búsqueda.'
        : `${matchingFields.length} ${matchingFields.length === 1 ? 'campo encontrado' : 'campos encontrados'}.`;
    }

    input.addEventListener('input', filterProfile);
    input.addEventListener('search', filterProfile);
    clearButton.addEventListener('click', () => {
      input.value = '';
      restoreProfile();
      input.focus();
    });
    panel.dataset.profileSearchReady = 'true';
    toolbar.hidden = false;
    filterProfile();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeProfileSearch, {once: true});
  } else {
    initializeProfileSearch();
  }
})();

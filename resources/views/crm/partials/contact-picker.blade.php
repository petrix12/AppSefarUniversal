<div class="form-group position-relative">
    <label for="crm-contact-search">Contacto <span class="text-danger">*</span></label>
    <input id="crm-contact-search" name="contact_search" type="search" class="form-control" value="{{ old('contact_search', $contactLabel) }}"
           placeholder="Busca por nombre o correo" autocomplete="off" required>
    <input type="hidden" name="lead_id" id="crm-lead-id" value="{{ old('lead_id', $leadId ?? '') }}">
    <input type="hidden" name="client_id" id="crm-client-id" value="{{ old('client_id', $clientId ?? '') }}">
    <div id="crm-contact-results" class="list-group position-absolute w-100 shadow" style="z-index: 20; display:none"></div>
    <small class="form-text text-muted">Selecciona un lead activo o un cliente registrado.</small>
    @error('contact_search')<small class="text-danger d-block">{{ $message }}</small>@enderror
    @error('lead_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
    @error('client_id')<small class="text-danger d-block">{{ $message }}</small>@enderror
</div>
<script>
(() => {
    const input = document.getElementById('crm-contact-search');
    const results = document.getElementById('crm-contact-results');
    const lead = document.getElementById('crm-lead-id');
    const client = document.getElementById('crm-client-id');
    let timer;
    input.addEventListener('input', () => {
        lead.value = '';
        client.value = '';
        results.replaceChildren();
        clearTimeout(timer);
        if (input.value.trim().length < 2) { results.style.display = 'none'; return; }
        timer = setTimeout(async () => {
            try {
                const url = new URL(@json(route('crm.contacts.search')), window.location.origin);
                url.searchParams.set('q', input.value.trim());
                const response = await fetch(url, {headers: {'Accept': 'application/json'}});
                if (!response.ok) throw new Error('No se pudo buscar el contacto.');
                const contacts = await response.json();
                results.replaceChildren();
                contacts.forEach(contact => {
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.className = 'list-group-item list-group-item-action';
                    option.textContent = contact.label;
                    option.addEventListener('click', () => {
                        lead.value = contact.type === 'lead' ? contact.id : '';
                        client.value = contact.type === 'client' ? contact.id : '';
                        input.value = contact.label;
                        results.style.display = 'none';
                    });
                    results.append(option);
                });
                if (!contacts.length) {
                    const empty = document.createElement('div');
                    empty.className = 'list-group-item text-muted';
                    empty.textContent = 'No hay coincidencias.';
                    results.append(empty);
                }
                results.style.display = 'block';
            } catch (error) {
                results.replaceChildren();
                const message = document.createElement('div');
                message.className = 'list-group-item text-danger';
                message.textContent = error.message;
                results.append(message);
                results.style.display = 'block';
            }
        }, 250);
    });
    document.addEventListener('click', event => {
        if (!input.contains(event.target) && !results.contains(event.target)) results.style.display = 'none';
    });
})();
</script>

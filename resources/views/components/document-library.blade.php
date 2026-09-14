@props(['documents', 'people' => collect(), 'clientView' => false, 'id' => 'document-library'])

@php
    $kindLabels = \App\Services\GenealogyDocumentService::kinds();
    $people = collect($people);
    $peopleById = $people->keyBy('id');
    $peopleByLegacyId = $people->filter(fn ($person) => filled($person->IDPersona))->keyBy('IDPersona');
    $groups = collect($documents)->groupBy(function ($document) use ($peopleById, $peopleByLegacyId) {
        $person = filled($document->IDPersonaNew)
            ? $peopleById->get($document->IDPersonaNew)
            : $peopleByLegacyId->get($document->IDPersona);

        return $person?->id ?? 'unassigned';
    });
@endphp

<section id="{{ $id }}" class="document-library" aria-label="Documentos">
    <div class="document-library-header">
        <div>
            <span class="document-library-eyebrow">{{ $clientView ? 'Documentos compartidos contigo' : 'Repositorio documental interno' }}</span>
            <h3>{{ $clientView ? 'Mis documentos' : 'Documentos del cliente' }}</h3>
        </div>
        <span class="document-library-count">{{ collect($documents)->count() }} archivos</span>
    </div>

    @forelse($groups as $personId => $group)
        @php
            $person = $personId === 'unassigned' ? null : $peopleById->get($personId);
            $personName = $person ? trim($person->Nombres . ' ' . $person->Apellidos) : 'Sin persona asociada';
        @endphp
        <section class="document-library-group">
            <h4>{{ $personName }}@if($person?->parentesco)<small>{{ $person->parentesco }}</small>@endif</h4>
            <div class="document-library-grid">
                @foreach($group as $document)
                    @php
                        $extension = strtolower(pathinfo($document->file, PATHINFO_EXTENSION));
                        $isPdf = $extension === 'pdf' || str_contains(strtolower($document->mime_type ?? ''), 'pdf');
                        $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp']) || str_starts_with(strtolower($document->mime_type ?? ''), 'image/');
                        $kind = $document->document_kind;
                        $hubspotClientEligible = \App\Services\HubspotService::isClientEligibleFileSource($document->source, $document->source_reference);
                        $visibilityLabel = $document->source === 'hubspot' && ! $hubspotClientEligible
                            ? 'interno'
                            : ($document->client_visible ? 'compartido' : 'privado');
                        $sourceLabel = match ($document->source) {
                            'hubspot' => 'HubSpot',
                            'teamleader' => 'Teamleader',
                            'app_cliente' => 'App cliente',
                            'solicitud_cliente' => 'Solicitud cliente',
                            'staff_upload' => 'Equipo',
                            default => $document->source ?: 'Legado',
                        };
                    @endphp
                    <div class="document-library-card">
                        <button type="button" class="document-library-preview-trigger" data-document-preview-trigger
                            data-url="{{ route('viewfile', $document) }}"
                            data-name="{{ $document->file }}"
                            data-kind="{{ $kindLabels[$kind] ?? ($document->tipo ?: 'Documento sin clasificar') }}"
                            data-pdf="{{ $isPdf ? '1' : '0' }}" data-image="{{ $isImage ? '1' : '0' }}">
                            <span class="document-library-icon {{ $isPdf ? 'is-pdf' : ($isImage ? 'is-image' : '') }}">
                                <i class="far {{ $isPdf ? 'fa-file-pdf' : ($isImage ? 'fa-file-image' : 'fa-file-alt') }}"></i>
                            </span>
                            <span class="document-library-copy">
                                <strong>{{ $kindLabels[$kind] ?? ($document->tipo ?: 'Documento sin clasificar') }}</strong>
                                <small>{{ $document->file }}</small>
                                <em>{{ optional($document->created_at)->format('d/m/Y') ?: 'Sin fecha' }}</em>
                            </span>
                            @if(! $clientView)
                                <span class="document-library-source">{{ $sourceLabel }} · {{ $visibilityLabel }}</span>
                            @endif
                        </button>
                        @if(! $clientView && $document->source === 'hubspot')
                            <button type="button" class="document-library-associate" data-document-associate
                                data-file="{{ $document->id }}" data-name="{{ $document->file }}"
                                data-kind="{{ $kind }}" data-visible="{{ $document->client_visible ? '1' : '0' }}"
                                data-client-eligible="{{ $hubspotClientEligible ? '1' : '0' }}">
                                <i class="fas fa-link"></i> Asociar al árbol
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="document-library-empty">
            <i class="far fa-folder-open"></i>
            <p>{{ $clientView ? 'Todavía no tienes documentos publicados.' : 'No hay archivos registrados para este cliente.' }}</p>
        </div>
    @endforelse
</section>

<dialog id="{{ $id }}-preview" class="document-library-preview">
    <header>
        <div><span id="{{ $id }}-preview-kind"></span><h3 id="{{ $id }}-preview-name">Documento</h3></div>
        <button type="button" data-document-preview-close aria-label="Cerrar"><i class="fas fa-times"></i></button>
    </header>
    <div id="{{ $id }}-preview-body" class="document-library-preview-body"></div>
    <a id="{{ $id }}-preview-open" class="btn btn-primary" target="_blank" rel="noopener">Abrir en otra pestaña</a>
</dialog>

@if(! $clientView)
    <dialog id="{{ $id }}-associate" class="document-library-associate-dialog">
        <form id="{{ $id }}-associate-form" method="dialog">
            <header>
                <div><span>Archivo de HubSpot</span><h3 id="{{ $id }}-associate-name">Asociar al árbol</h3></div>
                <button type="button" data-document-associate-close aria-label="Cerrar"><i class="fas fa-times"></i></button>
            </header>
            <div class="document-library-associate-body">
                <p data-association-policy>Este archivo seguirá privado salvo que se marque explícitamente para compartirlo.</p>
                <label>Persona del árbol
                    <select name="person_id" required data-association-person>
                        <option value="">Selecciona una persona</option>
                        @foreach($people as $person)
                            <option value="{{ $person->id }}" data-client-root="{{ (string) $person->IDPersona === '1' ? '1' : '0' }}">
                                {{ trim($person->Nombres . ' ' . $person->Apellidos) ?: 'Sin nombre' }}{{ $person->parentesco ? ' — ' . $person->parentesco : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <label>Tipo de documento
                    <select name="document_kind" required data-association-kind>
                        @foreach($kindLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label data-association-spouse-row hidden>Otro cónyuge
                    <select name="spouse_id" disabled data-association-spouse>
                        <option value="">Selecciona el otro cónyuge</option>
                        @foreach($people as $person)
                            <option value="{{ $person->id }}">{{ trim($person->Nombres . ' ' . $person->Apellidos) ?: 'Sin nombre' }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="document-library-share-toggle">
                    <input type="checkbox" name="share_with_client" value="1" data-association-visible>
                    <span>Compartir este documento con el cliente</span>
                </label>
                <p class="document-library-associate-error" data-association-error role="alert"></p>
            </div>
            <footer>
                <button type="button" class="btn btn-light" data-document-associate-close>Cancelar</button>
                <button type="submit" class="btn btn-primary" data-association-submit>Guardar asociación</button>
            </footer>
        </form>
    </dialog>
@endif

<style>
    .document-library { background:#fff; border:1px solid #e0e8eb; border-radius:16px; padding:1.25rem; box-shadow:0 4px 18px rgba(26,65,77,.06); }
    .document-library-header { display:flex; justify-content:space-between; gap:1rem; align-items:start; border-bottom:1px solid #e4ecee; padding-bottom:1rem; }.document-library-header h3 { margin:.15rem 0 0; color:#173e4b; font-size:1.25rem; }.document-library-eyebrow { text-transform:uppercase; letter-spacing:.08em; font-size:.68rem; color:#6b8087; font-weight:700; }.document-library-count { font-size:.8rem; background:#edf5f6; color:#285d69; border-radius:999px; padding:.35rem .65rem; white-space:nowrap; }
    .document-library-group { padding-top:1rem; }.document-library-group h4 { font-size:.95rem; color:#244d59; margin:0 0 .65rem; }.document-library-group h4 small { color:#768a91; font-weight:400; margin-left:.45rem; }.document-library-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(255px, 1fr)); gap:.65rem; }
    .document-library-card { overflow:hidden; border:1px solid #dbe6e9; background:#fff; border-radius:12px; transition:.15s ease; }.document-library-card:hover { border-color:#8eb5be; box-shadow:0 8px 20px rgba(17,72,87,.11); transform:translateY(-1px); }.document-library-preview-trigger { position:relative; display:flex; gap:.7rem; align-items:center; width:100%; border:0; background:#fff; padding:.75rem; text-align:left; cursor:pointer; }.document-library-icon { width:2.3rem; height:2.3rem; border-radius:9px; display:grid; place-items:center; background:#edf2f3; color:#506b74; font-size:1.15rem; flex:none; }.document-library-icon.is-pdf { background:#fff0ef; color:#b9443b; }.document-library-icon.is-image { background:#edf7f1; color:#2d8a5d; }.document-library-copy { min-width:0; display:grid; gap:.12rem; }.document-library-copy strong { color:#234854; font-size:.86rem; }.document-library-copy small { color:#637a82; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px; }.document-library-copy em { color:#91a0a5; font-size:.7rem; font-style:normal; }.document-library-source { position:absolute; right:.55rem; top:.5rem; color:#71858c; font-size:.64rem; text-transform:uppercase; letter-spacing:.04em; }.document-library-associate { width:100%; border:0; border-top:1px solid #e6eef0; padding:.5rem .75rem; text-align:left; background:#f4f9f9; color:#286270; font-size:.78rem; font-weight:700; cursor:pointer; }.document-library-associate:hover { background:#e9f4f4; }
    .document-library-empty { text-align:center; color:#73868d; padding:2.5rem 1rem 1rem; }.document-library-empty i { font-size:2rem; color:#b3c1c5; }.document-library-preview, .document-library-associate-dialog { width:min(920px, calc(100vw - 30px)); border:0; padding:0; border-radius:16px; overflow:hidden; box-shadow:0 24px 70px rgba(0,0,0,.35); }.document-library-preview::backdrop, .document-library-associate-dialog::backdrop { background:rgba(6,27,34,.65); }.document-library-preview header, .document-library-associate-dialog header { display:flex; justify-content:space-between; gap:1rem; padding:1rem 1.25rem; border-bottom:1px solid #e1eaed; }.document-library-preview header span, .document-library-associate-dialog header span { color:#71868d; font-size:.75rem; }.document-library-preview header h3, .document-library-associate-dialog header h3 { margin:.1rem 0 0; color:#203f4b; font-size:1rem; }.document-library-preview header button, .document-library-associate-dialog header button { border:0; background:transparent; color:#5e747b; font-size:1.1rem; }.document-library-preview-body { min-height:55vh; display:flex; align-items:center; justify-content:center; background:#f2f6f7; padding:1rem; }.document-library-preview-body iframe { width:100%; height:55vh; border:0; background:#fff; }.document-library-preview-body img { max-width:100%; max-height:55vh; object-fit:contain; }.document-library-preview > a { margin:0 1.25rem 1.25rem; }
    .document-library-associate-dialog { width:min(530px, calc(100vw - 30px)); }.document-library-associate-body { padding:1.1rem 1.25rem; display:grid; gap:.85rem; }.document-library-associate-body > p { margin:0; color:#5d7178; font-size:.86rem; }.document-library-associate-body label { display:grid; gap:.32rem; color:#274b57; font-size:.84rem; font-weight:700; }.document-library-associate-body select { width:100%; border:1px solid #cbdade; border-radius:8px; padding:.55rem .65rem; background:#fff; color:#234854; font-weight:400; }.document-library-share-toggle { grid-template-columns:auto 1fr; align-items:center; gap:.55rem !important; padding:.7rem; border-radius:8px; background:#f0f7f7; }.document-library-share-toggle input { width:1rem; height:1rem; }.document-library-associate-error { min-height:1.1rem; color:#b33d35 !important; }.document-library-associate-dialog footer { display:flex; justify-content:flex-end; gap:.6rem; padding:1rem 1.25rem; border-top:1px solid #e1eaed; }
    @media(max-width:640px) { .document-library { padding:1rem; }.document-library-header { display:block; }.document-library-count { display:inline-block; margin-top:.6rem; }.document-library-grid { grid-template-columns:1fr; } }
</style>

<script>
    (() => {
        const root = document.getElementById(@json($id));
        if (!root) return;
        const preview = document.getElementById(@json($id . '-preview'));
        const body = document.getElementById(@json($id . '-preview-body'));
        const name = document.getElementById(@json($id . '-preview-name'));
        const kind = document.getElementById(@json($id . '-preview-kind'));
        const open = document.getElementById(@json($id . '-preview-open'));
        const escapeHtml = (value) => String(value || '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

        root.querySelectorAll('[data-document-preview-trigger]').forEach((trigger) => trigger.addEventListener('click', () => {
            const url = trigger.dataset.url;
            name.textContent = trigger.dataset.name;
            kind.textContent = trigger.dataset.kind;
            open.href = url;
            const safeName = escapeHtml(trigger.dataset.name);
            body.innerHTML = trigger.dataset.image === '1'
                ? `<img src="${url}" alt="${safeName}">`
                : trigger.dataset.pdf === '1'
                    ? `<iframe src="${url}" title="${safeName}"></iframe>`
                    : '<p class="text-muted">Este formato no admite vista previa integrada.</p>';
            preview.showModal();
        }));
        preview.querySelector('[data-document-preview-close]').addEventListener('click', () => preview.close());
        preview.addEventListener('close', () => { body.innerHTML = ''; });

        const association = document.getElementById(@json($id . '-associate'));
        if (!association) return;
        const associationForm = document.getElementById(@json($id . '-associate-form'));
        const associationName = document.getElementById(@json($id . '-associate-name'));
        const personSelect = associationForm.querySelector('[data-association-person]');
        const kindSelect = associationForm.querySelector('[data-association-kind]');
        const spouseRow = associationForm.querySelector('[data-association-spouse-row]');
        const spouseSelect = associationForm.querySelector('[data-association-spouse]');
        const visibleInput = associationForm.querySelector('[data-association-visible]');
        const policy = associationForm.querySelector('[data-association-policy]');
        const error = associationForm.querySelector('[data-association-error]');
        const submit = associationForm.querySelector('[data-association-submit]');
        let fileId = null;

        const syncAssociationFields = () => {
            const isClient = personSelect.selectedOptions[0]?.dataset.clientRoot === '1';
            const deathOption = kindSelect.querySelector('option[value="death_certificate"]');
            if (deathOption) deathOption.disabled = isClient;
            if (deathOption?.selected && isClient) kindSelect.value = 'passport';
            const isMarriage = kindSelect.value === 'marriage_certificate';
            spouseRow.hidden = !isMarriage;
            spouseSelect.disabled = !isMarriage;
            spouseSelect.required = isMarriage;
            [...spouseSelect.options].forEach((option) => option.disabled = Boolean(option.value) && option.value === personSelect.value);
        };

        root.querySelectorAll('[data-document-associate]').forEach((trigger) => trigger.addEventListener('click', () => {
            fileId = trigger.dataset.file;
            associationName.textContent = trigger.dataset.name;
            personSelect.value = '';
            spouseSelect.value = '';
            kindSelect.value = kindSelect.querySelector(`option[value="${trigger.dataset.kind}"]`) ? trigger.dataset.kind : 'passport';
            const clientEligible = trigger.dataset.clientEligible === '1';
            visibleInput.disabled = !clientEligible;
            visibleInput.checked = clientEligible && trigger.dataset.visible === '1';
            policy.textContent = clientEligible
                ? 'Este archivo seguirá privado salvo que se marque explícitamente para compartirlo.'
                : 'Este campo de HubSpot es interno: puedes asociarlo al árbol, pero nunca se mostrará al cliente.';
            error.textContent = '';
            syncAssociationFields();
            association.showModal();
        }));
        personSelect.addEventListener('change', syncAssociationFields);
        kindSelect.addEventListener('change', syncAssociationFields);
        association.querySelectorAll('[data-document-associate-close]').forEach((button) => button.addEventListener('click', () => association.close()));

        associationForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!fileId) return;
            error.textContent = '';
            submit.disabled = true;
            try {
                const response = await fetch(@json(url('files/__FILE__/genealogy-association')).replace('__FILE__', fileId), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': @json(csrf_token()),
                        'Accept': 'application/json',
                    },
                    body: new FormData(associationForm),
                });
                const payload = await response.json();
                if (!response.ok) {
                    error.textContent = payload.message || Object.values(payload.errors || {}).flat().join(' ');
                    return;
                }
                association.close();
                window.location.reload();
            } catch (_) {
                error.textContent = 'No se pudo guardar la asociación. Inténtalo de nuevo.';
            } finally {
                submit.disabled = false;
            }
        });
    })();
</script>

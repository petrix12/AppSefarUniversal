@extends('adminlte::page')

@section('title', 'Archivos subidos')

@section('content')
    @php
        $requestStatuses = [
            'en_espera_cliente' => ['label' => 'Pendiente de cargar', 'class' => 'is-pending'],
            'rechazada' => ['label' => 'Requiere nueva carga', 'class' => 'is-rejected'],
            'resuelto' => ['label' => 'En revisión', 'class' => 'is-review'],
            'aprobada' => ['label' => 'Aprobado', 'class' => 'is-approved'],
            'no_documento' => ['label' => 'Sin documento disponible', 'class' => 'is-muted'],
        ];
    @endphp

    <main class="client-files-page">
        <section class="client-files-hero">
            <div>
                <span class="client-files-eyebrow"><i class="far fa-folder-open"></i> Gestión documental</span>
                <h1>Archivos subidos</h1>
                <p>Consulta tus documentos, revisa a quién pertenecen y completa cada solicitud desde un solo lugar.</p>
            </div>
            <a href="{{ route('clientes.tree') }}" class="btn client-files-tree-button">
                <i class="fas fa-sitemap" aria-hidden="true"></i> Cargar o asociar en el árbol
            </a>
        </section>

        <section class="client-files-metrics" aria-label="Resumen documental">
            <article>
                <span class="client-files-metric-icon is-files"><i class="far fa-file-alt"></i></span>
                <div><strong>{{ $uploadedFiles->count() }}</strong><span>archivos disponibles</span></div>
            </article>
            <article>
                <span class="client-files-metric-icon is-pending"><i class="fas fa-cloud-arrow-up"></i></span>
                <div><strong>{{ $pendingDocumentRequests->count() }}</strong><span>solicitudes por completar</span></div>
            </article>
            <article>
                <span class="client-files-metric-icon is-linked"><i class="fas fa-link"></i></span>
                <div><strong>{{ $uploadedFiles->filter(fn ($file) => filled($file->IDPersonaNew) || $file->people->isNotEmpty())->count() }}</strong><span>archivos asociados</span></div>
            </article>
        </section>

        <section class="client-requests-panel" aria-labelledby="client-document-requests-title">
            <div class="client-panel-heading">
                <div>
                    <span>GESTIONA TUS SOLICITUDES</span>
                    <h2 id="client-document-requests-title">Documentos requeridos</h2>
                </div>
                <a href="{{ route('clientes.tree') }}" class="client-panel-link">Ver árbol <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>

            @forelse($documentRequests as $documentRequest)
                @php
                    $status = $requestStatuses[$documentRequest->status] ?? ['label' => ucfirst(str_replace('_', ' ', $documentRequest->status)), 'class' => 'is-muted'];
                    $requestPeople = collect();
                    if ($documentRequest->genealogyUnion) {
                        $requestPeople = collect([$documentRequest->genealogyUnion->spouseOne, $documentRequest->genealogyUnion->spouseTwo])->filter();
                    } elseif ($documentRequest->person) {
                        $requestPeople = collect([$documentRequest->person]);
                    }
                    $personLabel = $requestPeople->map(function ($person) {
                        $name = trim($person->Nombres . ' ' . $person->Apellidos) ?: 'Sin nombre';
                        return $name . ' · ' . \App\Services\GenealogyDocumentService::relationshipLabel($person);
                    })->join(' y ');
                    $matchingFiles = $uploadedFiles->filter(fn ($file) => ! $file->document_kind || $file->document_kind === $documentRequest->document_kind);
                    $canComplete = in_array($documentRequest->status, ['en_espera_cliente', 'rechazada'], true);
                @endphp
                <article class="client-request-card {{ $canComplete ? 'is-actionable' : '' }}" data-client-request-card>
                    <div class="client-request-summary">
                        <span class="client-request-icon"><i class="{{ $documentRequest->document_kind === 'passport' ? 'far fa-id-card' : 'far fa-file-lines' }}" aria-hidden="true"></i></span>
                        <div class="client-request-copy">
                            <div class="client-request-title-row">
                                <h3>{{ $documentKinds[$documentRequest->document_kind] ?? $documentRequest->document_name }}</h3>
                                <span class="client-request-status {{ $status['class'] }}">{{ $status['label'] }}</span>
                            </div>
                            <p>
                                <i class="fas fa-user-tag" aria-hidden="true"></i>
                                {{ $personLabel ?: 'Persona del árbol pendiente de asociar' }}
                            </p>
                        </div>
                    </div>

                    @if($canComplete)
                        <div class="client-request-actions">
                            <label class="client-request-dropzone" data-request-dropzone for="request-file-{{ $documentRequest->id }}">
                                <input id="request-file-{{ $documentRequest->id }}" type="file" accept="application/pdf,image/jpeg,image/png,image/webp,image/gif" hidden data-request-file-input>
                                <i class="fas fa-cloud-arrow-up" aria-hidden="true"></i>
                                <span><strong>Arrastra o selecciona un archivo</strong><small>PDF, JPG, PNG, WEBP o GIF · máximo 10 MB</small></span>
                            </label>
                            @if($matchingFiles->isNotEmpty())
                                <div class="client-request-existing">
                                    <select aria-label="Archivo disponible para {{ $documentRequest->document_name }}" data-request-existing-file>
                                        <option value="">Usar archivo ya cargado…</option>
                                        @foreach($matchingFiles as $file)
                                            <option value="{{ $file->id }}">{{ $file->file }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="btn client-request-associate" data-request-associate data-request-id="{{ $documentRequest->id }}">
                                        Asociar
                                    </button>
                                </div>
                            @endif
                        </div>
                    @else
                        <a href="{{ route('clientes.tree') }}" class="client-request-tree-link">Ver detalle en el árbol <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                    @endif
                </article>
            @empty
                <div class="client-requests-empty">
                    <i class="far fa-circle-check" aria-hidden="true"></i>
                    <div><strong>No tienes solicitudes documentales pendientes.</strong><p>Cuando quieras registrar un documento para una persona, puedes hacerlo desde el árbol.</p></div>
                    <a href="{{ route('clientes.tree') }}" class="btn btn-outline-primary">Abrir árbol</a>
                </div>
            @endforelse
        </section>

        <x-document-library
            :documents="$uploadedFiles"
            :people="$people"
            client-view
            id="client-uploaded-files-library"
            eyebrow="TU BIBLIOTECA DOCUMENTAL"
            title="Mis archivos disponibles"
            empty-message="Aún no tienes archivos disponibles. Puedes cargar un PDF o imagen desde el árbol genealógico."
        />
    </main>

    <div class="client-file-upload-overlay" hidden data-client-upload-overlay role="status" aria-live="polite">
        <div><span class="client-file-upload-spinner"></span><strong>Cargando tu archivo…</strong><small>No cierres esta ventana mientras finaliza la carga.</small></div>
    </div>
@stop

@section('css')
    <style>
        .client-files-page{max-width:1180px;margin:1.5rem auto 2.5rem;display:grid;gap:1rem;color:#183946}.client-files-hero{background:linear-gradient(122deg,#0d465b,#1b6a7e);border-radius:18px;padding:1.65rem 1.8rem;display:flex;justify-content:space-between;gap:1.25rem;align-items:center;color:#fff;box-shadow:0 12px 28px rgba(10,60,76,.16)}.client-files-eyebrow,.client-panel-heading>div>span{display:block;font-size:.7rem;font-weight:800;letter-spacing:.09em;text-transform:uppercase;opacity:.78}.client-files-hero h1{font-size:1.7rem;margin:.22rem 0 .35rem;font-weight:800}.client-files-hero p{margin:0;max-width:620px;color:#e0f1f4}.client-files-tree-button{display:inline-flex;align-items:center;gap:.5rem;white-space:nowrap;background:#fff;color:#145c70;border:0;font-weight:800;border-radius:9px;padding:.68rem .9rem}.client-files-tree-button:hover{background:#edf8fa;color:#0c4657}.client-files-metrics{display:grid;grid-template-columns:repeat(3,1fr);gap:.85rem}.client-files-metrics article{background:#fff;border:1px solid #dfebee;border-radius:14px;padding:1rem;display:flex;align-items:center;gap:.75rem;box-shadow:0 3px 12px rgba(24,70,81,.05)}.client-files-metric-icon{width:2.5rem;height:2.5rem;border-radius:10px;display:grid;place-items:center;font-size:1.05rem}.client-files-metric-icon.is-files{background:#e9f5f7;color:#147187}.client-files-metric-icon.is-pending{background:#fff4df;color:#a36206}.client-files-metric-icon.is-linked{background:#eaf7ee;color:#288957}.client-files-metrics strong,.client-files-metrics span{display:block}.client-files-metrics strong{font-size:1.2rem;line-height:1.1}.client-files-metrics article div span{font-size:.78rem;color:#6f8188;margin-top:.16rem}.client-requests-panel{background:#fff;border:1px solid #dfe9ec;border-radius:16px;padding:1.2rem;box-shadow:0 4px 18px rgba(26,65,77,.06)}.client-panel-heading{display:flex;justify-content:space-between;align-items:center;gap:1rem;padding:.1rem .1rem 1rem;border-bottom:1px solid #e3ecee}.client-panel-heading h2{margin:.2rem 0 0;font-size:1.2rem;color:#173e4b}.client-panel-heading>div>span{color:#618089}.client-panel-link,.client-request-tree-link{font-weight:800;color:#12657a;font-size:.83rem}.client-request-card{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem .35rem;border-bottom:1px solid #e9eff1}.client-request-card:last-child{border-bottom:0;padding-bottom:.15rem}.client-request-summary{display:flex;align-items:center;gap:.75rem;min-width:0;flex:1}.client-request-icon{width:2.5rem;height:2.5rem;border-radius:9px;display:grid;place-items:center;background:#edf6f7;color:#176b7e;font-size:1.05rem;flex:none}.client-request-copy{min-width:0}.client-request-title-row{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap}.client-request-copy h3{font-size:.98rem;margin:0;color:#244854}.client-request-copy p{font-size:.8rem;color:#617a83;margin:.28rem 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.client-request-copy p i{color:#769da6;margin-right:.2rem}.client-request-status{font-size:.68rem;font-weight:800;border-radius:999px;padding:.24rem .48rem}.client-request-status.is-pending{background:#fff4df;color:#9c610b}.client-request-status.is-rejected{background:#feecec;color:#af3131}.client-request-status.is-review{background:#eaf2ff;color:#275eaf}.client-request-status.is-approved{background:#e8f8ec;color:#287846}.client-request-status.is-muted{background:#eef1f2;color:#66777c}.client-request-actions{display:flex;align-items:center;gap:.55rem;min-width:500px}.client-request-dropzone{border:1px dashed #8bb5bf;background:#f6fbfc;border-radius:10px;padding:.55rem .7rem;display:flex;gap:.55rem;align-items:center;cursor:pointer;margin:0;flex:1;transition:.15s ease}.client-request-dropzone:hover,.client-request-dropzone.is-dragging{background:#eaf7f8;border-color:#17778b}.client-request-dropzone>i{color:#17778b}.client-request-dropzone strong,.client-request-dropzone small{display:block}.client-request-dropzone strong{font-size:.76rem;color:#27515d}.client-request-dropzone small{font-size:.64rem;color:#748990;margin-top:.08rem}.client-request-existing{display:flex;align-items:center;gap:.35rem}.client-request-existing select{max-width:190px;border:1px solid #cedee1;border-radius:8px;padding:.5rem;font-size:.75rem;color:#395963;background:#fff}.client-request-associate{background:#0e6476;color:#fff;font-size:.75rem;font-weight:800;border-radius:8px;padding:.48rem .65rem}.client-request-associate:hover{background:#084c5b;color:#fff}.client-requests-empty{padding:1.45rem .5rem .35rem;display:flex;align-items:center;gap:.85rem;color:#55717b}.client-requests-empty>i{font-size:1.65rem;color:#45a46d}.client-requests-empty strong{color:#244854}.client-requests-empty p{font-size:.82rem;margin:.15rem 0 0}.client-requests-empty .btn{margin-left:auto;white-space:nowrap}.client-file-upload-overlay{position:fixed;inset:0;z-index:2000;background:rgba(9,38,47,.56);display:grid;place-items:center;padding:1rem}.client-file-upload-overlay[hidden]{display:none!important}.client-file-upload-overlay>div{background:#fff;border-radius:16px;padding:1.4rem 1.6rem;min-width:min(350px,100%);text-align:center;box-shadow:0 18px 45px rgba(0,0,0,.25);color:#244854}.client-file-upload-overlay strong,.client-file-upload-overlay small{display:block}.client-file-upload-overlay small{margin-top:.3rem;color:#73878d;font-size:.78rem}.client-file-upload-spinner{width:2.1rem;height:2.1rem;border:3px solid #dcebed;border-top-color:#17788d;border-radius:50%;display:block;margin:0 auto .75rem;animation:client-file-spin .75s linear infinite}@keyframes client-file-spin{to{transform:rotate(360deg)}}@media(max-width:991px){.client-request-card{align-items:stretch;flex-direction:column}.client-request-actions{min-width:0;width:100%}}@media(max-width:640px){.client-files-page{margin:1rem 0 2rem}.client-files-hero{display:block;padding:1.25rem}.client-files-tree-button{margin-top:1rem}.client-files-metrics{grid-template-columns:1fr}.client-panel-heading{align-items:flex-end}.client-panel-link{white-space:nowrap}.client-request-actions,.client-request-existing{display:grid;grid-template-columns:1fr}.client-request-existing select{max-width:none}.client-request-associate{width:100%}.client-requests-empty{align-items:flex-start;flex-wrap:wrap}.client-requests-empty .btn{margin-left:2.5rem}}
    </style>
@stop

@section('js')
    <script>
        (() => {
            const overlay = document.querySelector('[data-client-upload-overlay]');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || @json(csrf_token());
            const requestBase = @json(url('client/requests'));

            const toggleOverlay = (visible) => {
                overlay.hidden = !visible;
            };

            const notifyFailure = (message) => {
                window.alert(message || 'No se pudo cargar o asociar el archivo. Inténtalo de nuevo.');
            };

            const submitFile = async (requestId, file) => {
                if (!file) return;
                const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (file.size > 10 * 1024 * 1024 || (!allowed.includes(file.type) && !/\.(pdf|jpe?g|png|webp|gif)$/i.test(file.name))) {
                    notifyFailure('Selecciona un PDF o imagen de máximo 10 MB.');
                    return;
                }
                const payload = new FormData();
                payload.append('file', file);
                toggleOverlay(true);
                try {
                    const response = await fetch(`${requestBase}/${encodeURIComponent(requestId)}/upload`, {
                        method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}, body: payload,
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' '));
                    window.location.reload();
                } catch (error) {
                    toggleOverlay(false);
                    notifyFailure(error.message);
                }
            };

            const associateExisting = async (requestId, fileId) => {
                if (!fileId) {
                    notifyFailure('Selecciona primero el archivo que deseas asociar.');
                    return;
                }
                const payload = new FormData();
                payload.append('file_id', fileId);
                toggleOverlay(true);
                try {
                    const response = await fetch(`${requestBase}/${encodeURIComponent(requestId)}/associate-existing`, {
                        method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}, body: payload,
                    });
                    const data = await response.json();
                    if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' '));
                    window.location.reload();
                } catch (error) {
                    toggleOverlay(false);
                    notifyFailure(error.message);
                }
            };

            document.querySelectorAll('[data-client-request-card]').forEach((card) => {
                const fileInput = card.querySelector('[data-request-file-input]');
                const dropzone = card.querySelector('[data-request-dropzone]');
                const associateButton = card.querySelector('[data-request-associate]');
                const requestId = associateButton?.dataset.requestId || fileInput?.id?.replace('request-file-', '');

                fileInput?.addEventListener('change', () => submitFile(requestId, fileInput.files?.[0]));
                ['dragenter', 'dragover'].forEach((eventName) => dropzone?.addEventListener(eventName, (event) => {
                    event.preventDefault(); dropzone.classList.add('is-dragging');
                }));
                ['dragleave', 'drop'].forEach((eventName) => dropzone?.addEventListener(eventName, (event) => {
                    event.preventDefault(); dropzone.classList.remove('is-dragging');
                }));
                dropzone?.addEventListener('drop', (event) => submitFile(requestId, event.dataTransfer.files?.[0]));
                associateButton?.addEventListener('click', () => associateExisting(requestId, card.querySelector('[data-request-existing-file]')?.value));
            });
        })();
    </script>
@stop

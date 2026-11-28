@extends('adminlte::page')

@section('title', 'Constructor de plantillas')

@section('content_header')
    <div class="d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h1 class="mb-0"><i class="fas fa-palette text-primary mr-2"></i>Constructor de plantillas</h1>
            <small class="text-muted">Diseña correos sin escribir HTML.</small>
        </div>
        <a href="{{ route('marketing.campaigns.create') }}" class="btn btn-primary mt-2 mt-sm-0"><i class="fas fa-paper-plane mr-1"></i>Usar en una campaña</a>
    </div>
@stop

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="card card-outline card-primary">
        <div class="card-header"><h2 class="card-title"><i class="fas fa-wand-magic-sparkles mr-1"></i>Crear un correo con IA</h2></div>
        <div class="card-body">
            <details class="mb-4" open>
                <summary class="font-weight-bold mb-2">Revisar y editar el prompt de marca</summary>
                <label for="ai-template-system-prompt">Instrucciones para redactar los correos</label>
                <textarea id="ai-template-system-prompt" class="form-control" rows="8" required minlength="30" maxlength="12000">{{ $templatePrompt }}</textarea>
                <small class="form-text text-muted">Los cambios se aplican al generar el próximo borrador. Guarda el prompt para que también se conserve en futuras visitas y lo use el equipo.</small>
                <div class="d-flex flex-wrap align-items-center mt-2">
                    <button type="button" id="ai-template-save-prompt" class="btn btn-outline-primary btn-sm mr-2">Guardar prompt</button>
                    <button type="button" id="ai-template-reset-prompt" class="btn btn-outline-secondary btn-sm mr-3">Restaurar prompt inicial</button>
                    <span id="ai-template-prompt-status" role="status" class="small text-muted mt-2 mt-sm-0"></span>
                </div>
            </details>
            <div class="row">
                <div class="col-lg-8 form-group"><label for="ai-template-prompt">¿Qué quieres comunicar a tus representados?</label><textarea id="ai-template-prompt" class="form-control" rows="3" maxlength="4000" placeholder="Ej.: Un recordatorio breve del pago de registro para iniciar el análisis genealógico, con un tono cercano y una llamada a la acción clara."></textarea></div>
                <div class="col-lg-4 form-group"><label for="ai-template-url">Enlace del botón principal</label><input id="ai-template-url" type="url" class="form-control" value="https://app.sefaruniversal.com" maxlength="2000"><small class="form-text text-muted">El diseño seguirá los colores y la identidad de Sefar Universal.</small></div>
            </div>
            <div class="d-flex flex-wrap align-items-center"><button type="button" id="ai-template-generate" class="btn btn-primary mr-3"><i class="fas fa-magic mr-1"></i>Generar diseño editable</button><span id="ai-template-status" role="status" class="small text-muted mt-2 mt-sm-0"></span></div>
            <p class="small text-muted mb-0 mt-3">Voz de marca: Sefar Universal como autoridad en Derecho Genealogista, con una comunicación segura y cercana al representado. Podrás modificar el texto, los enlaces y el diseño antes de guardar o enviar.</p>
        </div>
    </div>

    <form method="POST" action="{{ $editingTemplate ? route('marketing.templates.update', $editingTemplate) : route('marketing.templates.store') }}" id="template-builder-form">
        @csrf
        @if($editingTemplate) @method('PUT') @endif
        <input type="hidden" name="body_html" id="template-body-html" value="{{ old('body_html') }}">
        <div class="row">
            <div class="col-xl-3">
                <div class="card card-outline card-primary">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-cubes mr-1"></i>Bloques</h3></div>
                    <div class="card-body">
                        <p class="text-muted small">Arrastra un bloque al lienzo o haz clic para añadirlo.</p>
                        <div class="template-builder-blocks">
                            <button type="button" class="template-builder-palette" draggable="true" data-block="hero"><i class="fas fa-star"></i><span>Portada</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="heading"><i class="fas fa-heading"></i><span>Título</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="text"><i class="fas fa-align-left"></i><span>Texto</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="button"><i class="fas fa-mouse-pointer"></i><span>Botón</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="image"><i class="far fa-image"></i><span>Imagen</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="columns"><i class="fas fa-columns"></i><span>Dos columnas</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="highlight"><i class="fas fa-quote-left"></i><span>Destacado</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="divider"><i class="fas fa-minus"></i><span>Separador</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="spacer"><i class="fas fa-arrows-alt-v"></i><span>Espacio</span></button>
                            <button type="button" class="template-builder-palette" draggable="true" data-block="footer"><i class="far fa-address-card"></i><span>Firma</span></button>
                        </div>
                    </div>
                </div>
                <div class="card card-outline card-secondary">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-magic mr-1"></i>Personalización</h3></div>
                    <div class="card-body">
                        <p class="text-muted small">Selecciona un texto y añade una variable.</p>
                        <div class="template-builder-variables">
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-variable="@{{first_name}}">Nombre</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-variable="@{{last_name}}">Apellido</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-variable="@{{full_name}}">Nombre completo</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" data-variable="@{{email}}">Email</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card card-outline card-primary">
                    <div class="card-header template-builder-toolbar">
                        <h3 class="card-title"><i class="fas fa-pen-ruler mr-1"></i>Lienzo</h3>
                        <div class="card-tools">
                            <span class="text-muted small mr-2" id="template-builder-count">0 bloques</span>
                            <div class="btn-group btn-group-sm mr-1">
                                <button type="button" class="btn btn-outline-secondary active" data-device="desktop" title="Vista de escritorio"><i class="fas fa-desktop"></i></button>
                                <button type="button" class="btn btn-outline-secondary" data-device="mobile" title="Vista móvil"><i class="fas fa-mobile-alt"></i></button>
                            </div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mr-1" id="template-builder-preview"><i class="far fa-eye mr-1"></i>Vista previa</button>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="template-builder-reset"><i class="fas fa-redo mr-1"></i>Reiniciar</button>
                        </div>
                    </div>
                    <div class="card-body template-builder-workspace"><div class="template-builder-dropzone" id="template-builder-canvas" aria-label="Lienzo de diseño de email"></div></div>
                    <div class="card-footer text-muted small"><i class="fas fa-grip-vertical mr-1"></i>Arrastra los bloques para reorganizarlos. Haz clic en uno para editarlo. El logo de SEFAR queda siempre al inicio.</div>
                </div>
                <div class="card d-none" id="template-builder-preview-card">
                    <div class="card-header"><h3 class="card-title"><i class="far fa-eye mr-1"></i>Vista previa del correo</h3><div class="card-tools"><button type="button" class="btn btn-tool" data-dismiss-preview title="Cerrar"><i class="fas fa-times"></i></button></div></div>
                    <div class="card-body bg-light p-3"><iframe title="Vista previa de plantilla" id="template-builder-preview-frame" class="template-builder-preview-frame" sandbox></iframe></div>
                </div>
            </div>

            <div class="col-xl-3">
                <div class="card card-outline card-warning">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-font mr-1"></i>Estilo de plantilla</h3></div>
                    <div class="card-body">
                        <label for="template-builder-global-text-color" class="mb-1">Color de todos los textos</label>
                        <div class="input-group">
                            <input type="color" class="form-control" id="template-builder-global-text-color" value="#093143" aria-label="Color global de textos">
                            <div class="input-group-append"><button type="button" class="btn btn-warning" id="template-builder-apply-text-color">Aplicar</button></div>
                        </div>
                        <small class="text-muted d-block mt-2">Actualiza títulos, párrafos, enlaces, botones y textos de columnas.</small>
                    </div>
                </div>
                <div class="card card-outline card-info">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-sliders-h mr-1"></i>Bloque seleccionado</h3></div>
                    <div class="card-body" id="template-builder-inspector"><p class="text-muted mb-0">Selecciona un bloque para cambiar su estilo, enlace o imagen.</p></div>
                    <div class="card-footer d-none" id="template-builder-block-actions">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="template-builder-duplicate"><i class="far fa-copy mr-1"></i>Duplicar</button>
                        <button type="button" class="btn btn-outline-danger btn-sm float-right" id="template-builder-delete"><i class="far fa-trash-alt mr-1"></i>Eliminar</button>
                    </div>
                </div>
                <div class="card card-outline card-secondary">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-file-import mr-1"></i>Importar HTML</h3></div>
                    <div class="card-body">
                        <p class="text-muted small">Si ya tienes un diseño, pégalo aquí. Después puedes agregar y reordenar bloques.</p>
                        <textarea id="template-builder-source" class="form-control font-monospace" rows="7" placeholder="Pega aquí el HTML del correo"></textarea>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="template-builder-load-source"><i class="fas fa-code mr-1"></i>Cargar HTML</button>
                    </div>
                </div>
                <div class="card card-outline card-success">
                    <div class="card-header"><h3 class="card-title"><i class="far fa-save mr-1"></i>Guardar plantilla</h3></div>
                    <div class="card-body">
                        <div class="form-group"><label for="template-name">Nombre</label><input required class="form-control" id="template-name" name="name" value="{{ old('name', $editingTemplate?->name) }}" placeholder="Ej.: Boletín mensual"></div>
                        <div class="form-group"><label for="template-subject">Asunto sugerido</label><input class="form-control" id="template-subject" name="subject" value="{{ old('subject', $editingTemplate?->subject) }}" placeholder="Ej.: Tenemos una novedad para ti"></div>
                        <div class="form-group mb-0"><label for="template-preheader">Texto de vista previa del correo</label><input class="form-control" id="template-preheader" maxlength="180" placeholder="Una frase breve que acompaña el asunto."></div>
                    </div>
                    <div class="card-footer text-right"><button class="btn btn-success"><i class="fas fa-save mr-1"></i>Guardar plantilla</button></div>
                </div>
            </div>
        </div>
    </form>

    <div class="card card-outline card-secondary">
        <div class="card-header"><h3 class="card-title"><i class="far fa-folder-open mr-1"></i>Plantillas guardadas</h3></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Plantilla</th><th>Asunto sugerido</th><th>Creada</th><th></th></tr></thead>
                <tbody>
                    @forelse ($templates as $template)
                        <tr><td><strong>{{ $template->name }}</strong></td><td>{{ $template->subject ?: 'Sin asunto sugerido' }}</td><td>{{ $template->created_at->format('d/m/Y H:i') }}</td><td><a href="{{ route('marketing.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Editar</a></td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">Aún no hay plantillas guardadas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($templates->hasPages())<div class="card-footer">{{ $templates->links() }}</div>@endif
    </div>
@stop

@section('css')
<link rel="stylesheet" href="{{ asset('css/marketing-template-builder.css') }}?v={{ filemtime(public_path('css/marketing-template-builder.css')) }}">
@stop

@section('js')
<script>window.sefarMarketingTemplateBuilder={initialHtml:@json(old('body_html', $editingTemplate?->body_html)),logoUrl:@json(asset('img/logo2.png')),appUrl:@json(config('app.url')),imageUploadUrl:@json(route('marketing.templates.images.store')),generateUrl:@json(route('marketing.templates.generate')),savePromptUrl:@json(route('marketing.templates.prompt.update')),defaultPrompt:@json($defaultTemplatePrompt),shell:@json($emailShell),theme:@json(app(\App\Services\AppEmailTheme::class)->colors()),csrfToken:@json(csrf_token())};</script>
<script src="{{ asset('js/marketing-template-builder.js') }}?v={{ filemtime(public_path('js/marketing-template-builder.js')) }}"></script>
@stop

@extends('adminlte::page')

@section('title', $campaign->exists ? 'Editar campaña' : 'Nueva campaña')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="mb-0"><i class="fas fa-magic text-primary mr-2"></i>{{ $campaign->exists ? 'Editar campaña' : 'Constructor de campaña' }}</h1>
        <a href="{{ route('marketing.dashboard') }}" class="btn btn-outline-secondary">Volver al panel</a>
    </div>
@stop

@section('content')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ $campaign->exists ? route('marketing.campaigns.update', $campaign) : route('marketing.campaigns.store') }}" id="campaign-form">
        @csrf @if($campaign->exists) @method('PUT') @endif
        <input type="hidden" name="body_html" id="body_html" value="{{ old('body_html', $campaign->body_html) }}">
        <div class="row">
            <div class="col-lg-4">
                <div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title">Datos y audiencia</h3></div><div class="card-body">
                    <div class="form-group"><label>Nombre interno</label><input required class="form-control" name="name" value="{{ old('name', $campaign->name) }}" placeholder="Ej.: Lanzamiento octubre"></div>
                    <div class="form-group"><label>Asunto</label><input required class="form-control" name="subject" id="subject" value="{{ old('subject', $campaign->subject) }}" placeholder="Ej.: Tenemos una novedad para ti"></div>
                    <div class="form-group"><label>Lista de destinatarios</label><select class="form-control" required name="marketing_list_id"><option value="">Selecciona una lista</option>@foreach($lists as $list)<option value="{{ $list->id }}" @selected(old('marketing_list_id', $campaign->marketing_list_id) == $list->id)>{{ $list->name }} ({{ $list->members_count }})</option>@endforeach</select><small><a href="{{ route('marketing.lists.index') }}">Crear o importar lista</a></small></div>
                    <div class="form-group"><label>Remitente verificado en SES</label><input required type="email" class="form-control" name="from_email" value="{{ old('from_email', $campaign->from_email) }}"></div>
                    <div class="form-group"><label>Nombre del remitente</label><input class="form-control" name="from_name" value="{{ old('from_name', $campaign->from_name) }}"></div>
                    <div class="form-group"><label>Responder a</label><input type="email" class="form-control" name="reply_to" value="{{ old('reply_to', $campaign->reply_to) }}"></div>
                    <div class="form-group mb-0"><label>Entrega</label><select class="form-control" name="delivery_mode" id="delivery-mode"><option value="draft" @selected(old('delivery_mode', $campaign->status) !== 'scheduled')>Guardar como borrador</option><option value="scheduled" @selected(old('delivery_mode', $campaign->status) === 'scheduled')>Programar envío</option></select></div>
                    <div class="form-group mt-2" id="scheduled-wrap"><label>Fecha y hora</label><input class="form-control" type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', optional($campaign->scheduled_at)->format('Y-m-d\\TH:i')) }}"></div>
                </div></div>
                <div class="card card-outline card-secondary"><div class="card-header"><h3 class="card-title">Plantillas</h3></div><div class="card-body"><select class="form-control" id="template-select" name="marketing_template_id"><option value="">Sin plantilla guardada</option>@foreach($templates as $template)<option value="{{ $template->id }}" @selected(old('marketing_template_id', $campaign->marketing_template_id) == $template->id)>{{ $template->name }}</option>@endforeach</select><button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="load-template">Cargar plantilla en el lienzo</button><p class="text-muted small mt-2 mb-0">Variables disponibles: <code>&#123;&#123;first_name&#125;&#125;</code>, <code>&#123;&#123;last_name&#125;&#125;</code>, <code>&#123;&#123;full_name&#125;&#125;</code>, <code>&#123;&#123;email&#125;&#125;</code>.</p></div></div>
            </div>
            <div class="col-lg-8">
                <div class="card card-outline card-primary"><div class="card-header"><h3 class="card-title">Arrastra bloques al correo</h3><div class="card-tools"><button type="button" class="btn btn-sm btn-outline-info" id="preview">Vista previa</button><button type="button" class="btn btn-sm btn-primary ml-1" id="preview-random-recipient"><i class="fas fa-user mr-1"></i>Probar variables</button></div></div>
                    <div class="card-body">
                        <div class="mb-3" id="palette">
                            <button type="button" draggable="true" data-block="heading" class="btn btn-outline-primary btn-sm mr-1">Título</button>
                            <button type="button" draggable="true" data-block="text" class="btn btn-outline-primary btn-sm mr-1">Texto</button>
                            <button type="button" draggable="true" data-block="button" class="btn btn-outline-primary btn-sm mr-1">Botón</button>
                            <button type="button" draggable="true" data-block="image" class="btn btn-outline-primary btn-sm mr-1">Imagen</button>
                            <button type="button" draggable="true" data-block="divider" class="btn btn-outline-primary btn-sm mr-1">Separador</button>
                            <button type="button" draggable="true" data-block="spacer" class="btn btn-outline-primary btn-sm">Espacio</button>
                        </div>
                        <div id="email-canvas" class="border rounded bg-white p-4" style="min-height:480px;max-width:700px;margin:auto" aria-label="Lienzo de email"></div>
                        <textarea class="form-control mt-3" name="body_text" rows="3" placeholder="Versión de texto opcional">{{ old('body_text', $campaign->body_text) }}</textarea>
                    </div>
                    <div class="card-footer text-right"><button class="btn btn-primary"><i class="fas fa-save mr-1"></i>Guardar campaña</button></div>
                </div>
                <div class="card d-none" id="preview-card"><div class="card-header"><h3 class="card-title">Vista previa</h3><div class="card-tools"><span class="text-muted small" id="preview-recipient"></span></div></div><div class="card-body bg-light"><p class="small text-muted mb-2 d-none" id="preview-subject"></p><iframe id="preview-frame" title="Vista previa" sandbox class="w-100 border bg-white" style="min-height:500px"></iframe></div></div>
            </div>
        </div>
    </form>
    <script id="templates-data" type="application/json">{!! json_encode($templates->map(fn($template) => ['id' => $template->id, 'subject' => $template->subject, 'body' => $template->body_html]), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@stop

@section('js')
<script>
(() => {
    const canvas = document.getElementById('email-canvas'), input = document.getElementById('body_html');
    const blocks = {
        heading: '<h2 style="font-family:Arial,sans-serif;color:#172b4d" contenteditable="true">Escribe un título</h2>',
        text: '<p style="font-family:Arial,sans-serif;font-size:16px;line-height:1.6;color:#333" contenteditable="true">Escribe aquí el contenido de tu correo.</p>',
        button: '<p style="text-align:center"><a href="https://example.com" style="display:inline-block;background:#0d6efd;color:#ffffff;padding:12px 22px;border-radius:4px;text-decoration:none;font-family:Arial,sans-serif" contenteditable="true">Llamado a la acción</a></p>',
        image: '<p style="text-align:center"><img src="https://via.placeholder.com/600x250?text=Imagen" alt="Imagen" style="max-width:100%;height:auto"></p>',
        divider: '<hr style="border:0;border-top:1px solid #e5e7eb;margin:24px 0">',
        spacer: '<div style="height:28px">&nbsp;</div>'
    };
    canvas.innerHTML = input.value || blocks.heading + blocks.text;
    document.querySelectorAll('#palette [draggable]').forEach(item => item.addEventListener('dragstart', event => event.dataTransfer.setData('block', item.dataset.block)));
    canvas.addEventListener('dragover', event => event.preventDefault());
    canvas.addEventListener('drop', event => { event.preventDefault(); const block = event.dataTransfer.getData('block'); if (blocks[block]) { canvas.insertAdjacentHTML('beforeend', '<div class="email-block border-top pt-2 mt-2">' + blocks[block] + '</div>'); } });
    document.querySelectorAll('#palette button').forEach(item => item.addEventListener('click', () => canvas.insertAdjacentHTML('beforeend', '<div class="email-block border-top pt-2 mt-2">' + blocks[item.dataset.block] + '</div>')));
    document.getElementById('campaign-form').addEventListener('submit', () => input.value = canvas.innerHTML);
    const templates = JSON.parse(document.getElementById('templates-data').textContent);
    document.getElementById('load-template').addEventListener('click', () => { const template = templates.find(item => String(item.id) === document.getElementById('template-select').value); if (template) { canvas.innerHTML = template.body; if (template.subject) document.getElementById('subject').value = template.subject; } });
    const previewCard = document.getElementById('preview-card'), previewFrame = document.getElementById('preview-frame'), previewRecipient = document.getElementById('preview-recipient'), previewSubject = document.getElementById('preview-subject');
    const showPreview = (html, recipient = '', subject = '') => {
        previewCard.classList.remove('d-none');
        previewRecipient.textContent = recipient;
        previewSubject.textContent = subject ? 'Asunto: ' + subject : '';
        previewSubject.classList.toggle('d-none', !subject);
        previewFrame.srcdoc = '<!doctype html><html><head><meta charset="utf-8"></head><body>' + html + '</body></html>';
        previewCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
    document.getElementById('preview').addEventListener('click', () => showPreview(canvas.innerHTML));
    document.getElementById('preview-random-recipient').addEventListener('click', async event => {
        const button = event.currentTarget;
        const token = document.querySelector('#campaign-form input[name="_token"]').value;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Preparando...';

        try {
            const response = await fetch(@json(route('marketing.campaigns.preview-random-recipient')), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({
                    subject: document.getElementById('subject').value,
                    body_html: canvas.innerHTML,
                    body_text: document.querySelector('textarea[name="body_text"]').value,
                }),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'No se pudo generar la vista previa.');
            const recipient = result.recipient;
            showPreview(result.html, 'Variables aplicadas: ' + recipient.full_name + ' · ' + recipient.email, result.subject);
        } catch (error) {
            window.alert(error.message || 'No se pudo generar la vista previa.');
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="fas fa-user mr-1"></i>Probar variables';
        }
    });
    const delivery = document.getElementById('delivery-mode'), scheduled = document.getElementById('scheduled-wrap'); const toggleSchedule = () => scheduled.classList.toggle('d-none', delivery.value !== 'scheduled'); delivery.addEventListener('change', toggleSchedule); toggleSchedule();
})();
</script>
@stop

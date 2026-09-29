(() => {
    const config = window.sefarMarketingTemplateBuilder;
    if (!config) return;

    const canvas = document.getElementById('template-builder-canvas');
    const form = document.getElementById('template-builder-form');
    const output = document.getElementById('template-body-html');
    const source = document.getElementById('template-builder-source');
    const inspector = document.getElementById('template-builder-inspector');
    const actions = document.getElementById('template-builder-block-actions');
    const counter = document.getElementById('template-builder-count');
    const logoUrl = config.logoUrl || '/img/logo2.png';
    const globalTextColor = document.getElementById('template-builder-global-text-color');
    let selectedBlock = null, activeEditor = null, draggingBlock = null;

    const templates = {
        hero: '<div style="margin:0 0 18px;padding:36px 28px;background:#073b4c;color:#fff;text-align:center"><p style="margin:0 0 8px;font-family:Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase" contenteditable="true">Sefar Universal</p><h1 style="margin:0;font-family:Arial,sans-serif;font-size:30px;line-height:1.2" contenteditable="true">Un mensaje importante para ti</h1></div>',
        heading: '<h2 style="margin:18px 0 10px;color:#073b4c;font-family:Arial,sans-serif;font-size:25px;line-height:1.25" contenteditable="true">Escribe un título</h2>',
        text: '<p style="margin:0 0 16px;color:#35424b;font-family:Arial,sans-serif;font-size:16px;line-height:1.65" contenteditable="true">Escribe aquí el contenido de tu correo. Puedes seleccionarlo, modificarlo y añadir variables de personalización.</p>',
        button: '<p style="margin:22px 0;text-align:center"><a href="https://example.com" style="display:inline-block;padding:13px 24px;border-radius:4px;background:#e5a326;color:#fff;font-family:Arial,sans-serif;font-size:16px;font-weight:700;text-decoration:none" contenteditable="true">Conocer más</a></p>',
        image: '<p style="margin:20px 0;text-align:center"><img src="https://placehold.co/640x280/f4f7f8/52636c?text=Selecciona+una+imagen" alt="Imagen de la campaña" style="display:block;max-width:100%;height:auto;margin:0 auto;border:0"></p>',
        columns: '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:20px 0;border-collapse:collapse"><tbody><tr><td width="50%" style="padding:14px;vertical-align:top;background:#f4f7f8"><h3 style="margin:0 0 8px;color:#073b4c;font-family:Arial,sans-serif;font-size:18px" contenteditable="true">Primera idea</h3><p style="margin:0;color:#35424b;font-family:Arial,sans-serif;font-size:14px;line-height:1.55" contenteditable="true">Explica aquí un beneficio o una noticia.</p></td><td width="50%" style="padding:14px;vertical-align:top"><h3 style="margin:0 0 8px;color:#073b4c;font-family:Arial,sans-serif;font-size:18px" contenteditable="true">Segunda idea</h3><p style="margin:0;color:#35424b;font-family:Arial,sans-serif;font-size:14px;line-height:1.55" contenteditable="true">Agrega otra información relevante.</p></td></tr></tbody></table>',
        highlight: '<div style="margin:20px 0;padding:20px;border-left:4px solid #e5a326;background:#fff8e8"><p style="margin:0;color:#5c4819;font-family:Arial,sans-serif;font-size:17px;line-height:1.55" contenteditable="true">Destaca aquí una fecha, condición o mensaje importante.</p></div>',
        divider: '<div style="margin:24px 0;border-top:1px solid #d9e1e4"></div>',
        spacer: '<div style="height:30px">&nbsp;</div>',
        footer: '<div style="margin:26px 0 0;padding:20px 10px 0;border-top:1px solid #d9e1e4;text-align:center"><p style="margin:0;color:#6b7d86;font-family:Arial,sans-serif;font-size:13px;line-height:1.55" contenteditable="true">Sefar Universal<br>Estamos para acompañarte en cada paso.</p></div>',
    };
    const labels = {hero:'Portada',heading:'Título',text:'Texto',button:'Botón',image:'Imagen',columns:'Dos columnas',highlight:'Destacado',divider:'Separador',spacer:'Espacio',footer:'Firma',imported:'HTML importado'};
    const defaults = ['hero', 'heading', 'text', 'button'];

    const escapeHtml = (value) => String(value).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#039;');
    const brandMarkup = () => `<div data-sefar-brand-header="true" style="margin:0 0 14px;padding:12px 20px;border-bottom:2px solid #e5a326;background:#ffffff;text-align:center"><img src="${escapeHtml(logoUrl)}" alt="Sefar Universal" width="86" style="display:block;width:86px;max-width:100%;height:auto;margin:0 auto;border:0"></div>`;
    const makeBrandHeader = () => {
        const header = document.createElement('div');
        header.className = 'template-builder-brand-header';
        header.innerHTML = brandMarkup();
        return header;
    };
    const ensureBrandHeader = () => {
        const headers = [...canvas.querySelectorAll(':scope > .template-builder-brand-header')];
        headers.slice(1).forEach((header) => header.remove());
        if (headers[0]) {
            canvas.prepend(headers[0]);
        } else {
            canvas.prepend(makeBrandHeader());
        }
    };
    const sanitizeHtml = (html) => {
        const doc = document.implementation.createHTMLDocument('email-template');
        doc.body.innerHTML = html;
        doc.querySelectorAll('script,iframe,object,embed,form,base').forEach((node) => node.remove());
        doc.querySelectorAll('*').forEach((node) => [...node.attributes].forEach((attribute) => {
            const name = attribute.name.toLowerCase(), value = attribute.value.trim();
            if (name.startsWith('on') || ['srcdoc','formaction'].includes(name) || ((name === 'href' || name === 'src') && /^\s*(javascript|data:text\/html):/i.test(value))) node.removeAttribute(attribute.name);
        }));
        return doc.body.innerHTML;
    };
    const makeBlock = (type, html = null) => {
        const block = document.createElement('div');
        block.className = 'template-builder-block';
        block.draggable = true;
        block.dataset.blockType = type;
        block.innerHTML = html || templates[type];
        return block;
    };
    const applyTextColor = (root, color) => {
        root.querySelectorAll('*').forEach((element) => {
            const hasText = [...element.childNodes].some((node) => node.nodeType === Node.TEXT_NODE && node.textContent.trim());
            if (hasText) element.style.color = color;
        });
    };
    const applyGlobalTextColor = (color) => {
        canvas.dataset.textColor = color;
        applyTextColor(canvas, color);
    };
    const count = () => {
        const total = canvas.querySelectorAll(':scope > .template-builder-block').length;
        counter.textContent = `${total} ${total === 1 ? 'bloque' : 'bloques'}`;
    };
    const select = (block) => {
        canvas.querySelectorAll('.template-builder-block.is-selected').forEach((item) => item.classList.remove('is-selected'));
        selectedBlock = block;
        if (block) block.classList.add('is-selected');
        renderInspector();
    };
    const add = (type, before = null) => {
        if (!templates[type]) return;
        const block = makeBlock(type);
        if (canvas.dataset.textColor) applyTextColor(block, canvas.dataset.textColor);
        canvas.insertBefore(block, before);
        select(block);
        count();
    };
    const exportHtml = () => {
        const copy = canvas.cloneNode(true);
        if (!copy.querySelector('[data-sefar-brand-header]')) copy.insertAdjacentHTML('afterbegin', brandMarkup());
        copy.querySelectorAll('.template-builder-brand-header').forEach((header) => header.classList.remove('template-builder-brand-header'));
        copy.querySelectorAll('.template-builder-block').forEach((block) => {
            block.classList.remove('template-builder-block','is-selected','is-dragging');
            block.removeAttribute('data-block-type');
            block.removeAttribute('draggable');
        });
        return sanitizeHtml(copy.innerHTML.trim());
    };
    const importHtml = (html) => {
        const doc = document.implementation.createHTMLDocument('email-template');
        doc.body.innerHTML = sanitizeHtml(html);
        doc.querySelectorAll('[data-sefar-brand-header]').forEach((header) => header.remove());
        const nodes = [...doc.body.childNodes].filter((node) => node.nodeType === Node.ELEMENT_NODE || node.textContent.trim());
        canvas.innerHTML = '';
        ensureBrandHeader();
        if (!nodes.length) {
            defaults.forEach((type) => add(type));
            return;
        }
        nodes.forEach((node) => canvas.appendChild(makeBlock('imported', node.outerHTML || escapeHtml(node.textContent))));
        if (canvas.dataset.textColor) applyTextColor(canvas, canvas.dataset.textColor);
        select(null);
        count();
    };
    const renderInspector = () => {
        if (!selectedBlock) {
            inspector.innerHTML = '<p class="text-muted mb-0">Selecciona un bloque para cambiar su estilo, enlace o imagen.</p>';
            actions.classList.add('d-none');
            return;
        }
        const image = selectedBlock.querySelector('img'), link = selectedBlock.querySelector('a[href]');
        const style = selectedBlock.style, padding = parseInt(style.padding || '0', 10);
        inspector.innerHTML = `
            <p class="text-muted small">Editando: <strong>${escapeHtml(labels[selectedBlock.dataset.blockType] || 'Bloque')}</strong></p>
            <div class="form-group mb-2"><label class="template-builder-inspector-label">Fondo del bloque</label><input type="color" class="form-control" id="builder-background" value="${style.backgroundColor || '#ffffff'}"></div>
            <div class="form-group mb-2"><label class="template-builder-inspector-label">Espaciado interior</label><input type="range" class="custom-range" id="builder-padding" min="0" max="48" value="${padding}"><small class="text-muted" id="builder-padding-value">${padding} px</small></div>
            <div class="form-group mb-2"><label class="template-builder-inspector-label">Alineación</label><select class="form-control form-control-sm" id="builder-align"><option value="left">Izquierda</option><option value="center">Centro</option><option value="right">Derecha</option></select></div>
            ${image ? `<div class="form-group mb-2"><label class="template-builder-inspector-label">Imagen</label><div class="d-flex align-items-center mb-2"><button type="button" class="btn btn-outline-primary btn-sm" id="builder-upload-image"><i class="fas fa-cloud-upload-alt mr-1"></i>Subir a S3</button><input type="file" class="d-none" id="builder-image-file" accept="image/jpeg,image/png,image/gif"><small class="text-muted ml-2" id="builder-image-upload-status">JPG, PNG o GIF · Máx. 5 MB</small></div><label class="template-builder-inspector-label">URL de imagen</label><input type="url" class="form-control form-control-sm" id="builder-image-url" value="${escapeHtml(image.getAttribute('src') || '')}"><label class="template-builder-inspector-label mt-2">Texto alternativo</label><input type="text" class="form-control form-control-sm" id="builder-image-alt" value="${escapeHtml(image.getAttribute('alt') || '')}"></div>` : ''}
            ${link ? `<div class="form-group mb-0"><label class="template-builder-inspector-label">URL del botón o enlace</label><input type="url" class="form-control form-control-sm" id="builder-link-url" value="${escapeHtml(link.getAttribute('href') || '')}"></div>` : ''}
        `;
        actions.classList.remove('d-none');
        const align = inspector.querySelector('#builder-align');
        align.value = style.textAlign || 'left';
        inspector.querySelector('#builder-background').addEventListener('input', (event) => selectedBlock.style.backgroundColor = event.target.value);
        inspector.querySelector('#builder-padding').addEventListener('input', (event) => {
            selectedBlock.style.padding = `${event.target.value}px`;
            inspector.querySelector('#builder-padding-value').textContent = `${event.target.value} px`;
        });
        align.addEventListener('change', (event) => selectedBlock.style.textAlign = event.target.value);
        inspector.querySelector('#builder-image-url')?.addEventListener('input', (event) => image.setAttribute('src', event.target.value));
        inspector.querySelector('#builder-image-alt')?.addEventListener('input', (event) => image.setAttribute('alt', event.target.value));
        inspector.querySelector('#builder-link-url')?.addEventListener('input', (event) => link.setAttribute('href', event.target.value));
        inspector.querySelector('#builder-upload-image')?.addEventListener('click', () => inspector.querySelector('#builder-image-file').click());
        inspector.querySelector('#builder-image-file')?.addEventListener('change', async (event) => {
            const file = event.target.files?.[0];
            if (!file) return;

            const status = inspector.querySelector('#builder-image-upload-status');
            status.textContent = 'Subiendo imagen a S3…';
            try {
                const payload = new FormData();
                payload.append('image', file);
                const response = await fetch(config.imageUploadUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'X-CSRF-TOKEN': config.csrfToken, Accept: 'application/json'},
                    body: payload,
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(result.message || 'No se pudo cargar la imagen.');
                image.setAttribute('src', result.url);
                renderInspector();
                inspector.querySelector('#builder-image-upload-status').textContent = 'Imagen cargada en S3.';
            } catch (error) {
                status.textContent = error.message || 'No se pudo cargar la imagen.';
                status.classList.add('text-danger');
            }
        });
    };

    document.querySelectorAll('[data-block]').forEach((button) => {
        button.addEventListener('dragstart', (event) => {
            event.dataTransfer.effectAllowed = 'copy';
            event.dataTransfer.setData('application/sefar-template-block', button.dataset.block);
        });
        button.addEventListener('click', () => add(button.dataset.block));
    });
    canvas.addEventListener('click', (event) => {
        activeEditor = event.target.closest('[contenteditable="true"]') || activeEditor;
        select(event.target.closest('.template-builder-block'));
    });
    canvas.addEventListener('dragstart', (event) => {
        const block = event.target.closest('.template-builder-block');
        if (!block) return;
        draggingBlock = block;
        block.classList.add('is-dragging');
        event.dataTransfer.effectAllowed = 'move';
    });
    canvas.addEventListener('dragend', () => {
        if (draggingBlock) draggingBlock.classList.remove('is-dragging');
        draggingBlock = null;
        count();
    });
    canvas.addEventListener('dragover', (event) => {
        event.preventDefault();
        const target = event.target.closest('.template-builder-block');
        if (draggingBlock && target && target !== draggingBlock) {
            const box = target.getBoundingClientRect();
            canvas.insertBefore(draggingBlock, event.clientY < box.top + box.height / 2 ? target : target.nextSibling);
        }
    });
    canvas.addEventListener('drop', (event) => {
        event.preventDefault();
        const type = event.dataTransfer.getData('application/sefar-template-block');
        if (type) add(type, event.target.closest('.template-builder-block'));
    });
    document.querySelectorAll('[data-variable]').forEach((button) => button.addEventListener('click', () => {
        if (!activeEditor) return;
        activeEditor.focus();
        document.execCommand('insertText', false, button.dataset.variable);
    }));
    document.getElementById('template-builder-apply-text-color').addEventListener('click', () => applyGlobalTextColor(globalTextColor.value));
    document.getElementById('template-builder-duplicate').addEventListener('click', () => {
        if (!selectedBlock) return;
        const copy = selectedBlock.cloneNode(true);
        canvas.insertBefore(copy, selectedBlock.nextSibling);
        select(copy);
        count();
    });
    document.getElementById('template-builder-delete').addEventListener('click', () => {
        if (!selectedBlock) return;
        selectedBlock.remove();
        select(null);
        count();
    });
    document.querySelectorAll('[data-device]').forEach((button) => button.addEventListener('click', () => {
        document.querySelectorAll('[data-device]').forEach((item) => item.classList.remove('active'));
        button.classList.add('active');
        canvas.classList.toggle('is-mobile', button.dataset.device === 'mobile');
    }));
    document.getElementById('template-builder-preview').addEventListener('click', () => {
        document.getElementById('template-builder-preview-card').classList.remove('d-none');
        document.getElementById('template-builder-preview-frame').srcdoc = `<!doctype html><html><body style="margin:0;padding:24px;background:#f1f4f6">${exportHtml()}</body></html>`;
    });
    document.querySelector('[data-dismiss-preview]').addEventListener('click', () => document.getElementById('template-builder-preview-card').classList.add('d-none'));
    document.getElementById('template-builder-reset').addEventListener('click', () => {
        canvas.innerHTML = '';
        ensureBrandHeader();
        defaults.forEach((type) => add(type));
        select(null);
        source.value = '';
    });
    document.getElementById('template-builder-load-source').addEventListener('click', () => importHtml(source.value));
    form.addEventListener('submit', (event) => {
        output.value = exportHtml();
        if (!output.value) {
            event.preventDefault();
            alert('Añade al menos un bloque o importa el HTML de una plantilla.');
        }
    });
    if (config.initialHtml) {
        source.value = config.initialHtml;
        importHtml(config.initialHtml);
    } else {
        ensureBrandHeader();
        defaults.forEach((type) => add(type));
        select(null);
    }
})();

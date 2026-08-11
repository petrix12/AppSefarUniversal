<div id="role-ai-chat" class="role-ai-chat">
    <button type="button" id="role-ai-toggle" class="role-ai-toggle" aria-label="Abrir asistente IA">
        <i class="fas fa-robot"></i>
    </button>

    <section id="role-ai-panel" class="role-ai-panel" aria-live="polite">
        <header class="role-ai-header">
            <div>
                <strong id="role-ai-title">Asistente IA</strong>
                <small id="role-ai-subtitle">Cargando rol...</small>
            </div>
            <button type="button" id="role-ai-close" class="role-ai-icon-button" aria-label="Cerrar asistente">
                <i class="fas fa-times"></i>
            </button>
        </header>

        <div class="role-ai-toolbar">
            <select id="role-ai-assistant-select" class="role-ai-select" aria-label="Seleccionar asistente"></select>
            <div class="role-ai-tabs" role="tablist">
                <button type="button" class="role-ai-tab active" data-tab="chat">
                    <i class="fas fa-comments"></i> Chat
                </button>
                <button type="button" class="role-ai-tab" data-tab="training">
                    <i class="fas fa-book-open"></i> Entrenar
                </button>
            </div>
        </div>

        <div id="role-ai-alert" class="role-ai-alert"></div>

        <div id="role-ai-chat-tab" class="role-ai-tab-content active">
            <div id="role-ai-messages" class="role-ai-messages"></div>
            <form id="role-ai-message-form" class="role-ai-message-form">
                <textarea id="role-ai-input" rows="2" placeholder="Escribe tu pregunta..." required></textarea>
                <button type="submit" class="role-ai-send" aria-label="Enviar mensaje">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </form>
        </div>

        <div id="role-ai-training-tab" class="role-ai-tab-content">
            <form id="role-ai-training-form" class="role-ai-training-form">
                <input id="role-ai-training-title" type="text" maxlength="160" placeholder="Titulo del aprendizaje">
                <textarea id="role-ai-training-content" rows="7" maxlength="12000" placeholder="Agrega instrucciones, criterios, pasos o informacion que este bot debe recordar..." required></textarea>
                <button type="submit" class="role-ai-train-button">
                    <i class="fas fa-save"></i> Guardar contexto
                </button>
            </form>
            <div class="role-ai-knowledge-head">
                <strong>Contexto reciente</strong>
                <button type="button" id="role-ai-refresh-knowledge" class="role-ai-link-button" aria-label="Recargar contexto">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
            <div id="role-ai-knowledge-list" class="role-ai-knowledge-list"></div>
        </div>
    </section>
</div>

<style>
    .role-ai-chat {
        bottom: 20px;
        position: fixed;
        right: 20px;
        z-index: 1997;
    }

    .role-ai-toggle {
        align-items: center;
        background: #093143;
        border: 0;
        border-radius: 50%;
        box-shadow: 0 10px 24px rgba(0, 0, 0, 0.24);
        color: #fff;
        display: flex;
        height: 60px;
        justify-content: center;
        width: 60px;
    }

    .role-ai-toggle i {
        font-size: 26px;
    }

    .role-ai-panel {
        background: #fff;
        border: 1px solid #d9e2e7;
        border-radius: 8px;
        box-shadow: 0 18px 48px rgba(2, 20, 30, 0.28);
        display: none;
        height: 620px;
        max-height: calc(100vh - 40px);
        overflow: hidden;
        width: 430px;
    }

    .role-ai-header {
        align-items: center;
        background: #093143;
        color: #fff;
        display: flex;
        justify-content: space-between;
        padding: 16px 18px;
    }

    .role-ai-header strong,
    .role-ai-header small {
        display: block;
        line-height: 1.2;
    }

    .role-ai-header small {
        color: rgba(255, 255, 255, 0.78);
        margin-top: 4px;
    }

    .role-ai-icon-button,
    .role-ai-link-button {
        background: transparent;
        border: 0;
        color: inherit;
        cursor: pointer;
    }

    .role-ai-toolbar {
        border-bottom: 1px solid #e7edf1;
        padding: 12px;
    }

    .role-ai-select {
        border: 1px solid #d7e0e6;
        border-radius: 6px;
        font-size: 14px;
        margin-bottom: 10px;
        padding: 8px 10px;
        width: 100%;
    }

    .role-ai-tabs {
        display: grid;
        gap: 8px;
        grid-template-columns: 1fr 1fr;
    }

    .role-ai-tab {
        background: #f3f6f8;
        border: 1px solid #d7e0e6;
        border-radius: 6px;
        color: #263840;
        font-size: 14px;
        padding: 8px 10px;
    }

    .role-ai-tab.active {
        background: #093143;
        border-color: #093143;
        color: #fff;
    }

    .role-ai-alert {
        color: #8a4b00;
        display: none;
        font-size: 13px;
        padding: 10px 14px 0;
    }

    .role-ai-tab-content {
        display: none;
    }

    .role-ai-tab-content.active {
        display: block;
    }

    .role-ai-messages {
        background: #f7f9fa;
        height: 340px;
        overflow-y: auto;
        padding: 14px;
    }

    .role-ai-message {
        border-radius: 8px;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
        margin-bottom: 10px;
        max-width: 92%;
        padding: 10px 12px;
        white-space: pre-wrap;
        word-break: break-word;
    }

    .role-ai-message.user {
        background: #fff;
        margin-left: auto;
    }

    .role-ai-message.assistant {
        background: #e5f1f6;
        color: #14313d;
    }

    .role-ai-message.system {
        background: #fff8e6;
        color: #6f4b00;
        margin-left: auto;
        margin-right: auto;
        text-align: center;
    }

    .role-ai-message-form {
        align-items: center;
        background: #fff;
        border-top: 1px solid #e7edf1;
        display: flex;
        gap: 10px;
        padding: 12px;
    }

    .role-ai-message-form textarea,
    .role-ai-training-form textarea,
    .role-ai-training-form input {
        border: 1px solid #d7e0e6;
        border-radius: 6px;
        font-size: 14px;
        outline: none;
        padding: 10px 12px;
        resize: none;
        width: 100%;
    }

    .role-ai-send {
        align-items: center;
        background: #093143;
        border: 0;
        border-radius: 50%;
        color: #fff;
        display: flex;
        flex: 0 0 44px;
        height: 44px;
        justify-content: center;
        width: 44px;
    }

    .role-ai-training-form {
        display: grid;
        gap: 10px;
        padding: 14px;
    }

    .role-ai-train-button {
        background: #093143;
        border: 0;
        border-radius: 6px;
        color: #fff;
        padding: 10px 12px;
    }

    .role-ai-knowledge-head {
        align-items: center;
        border-top: 1px solid #e7edf1;
        display: flex;
        justify-content: space-between;
        padding: 10px 14px;
    }

    .role-ai-link-button {
        color: #093143;
    }

    .role-ai-knowledge-list {
        max-height: 145px;
        overflow-y: auto;
        padding: 0 14px 14px;
    }

    .role-ai-knowledge-item {
        border-top: 1px solid #eef2f4;
        font-size: 13px;
        padding: 8px 0;
    }

    .role-ai-knowledge-item strong {
        display: block;
    }

    .role-ai-knowledge-item small {
        color: #6b7780;
    }

    @media (max-width: 520px) {
        .role-ai-chat {
            bottom: 12px;
            right: 12px;
        }

        .role-ai-panel {
            height: calc(100vh - 24px);
            width: calc(100vw - 24px);
        }

        .role-ai-messages {
            height: calc(100vh - 280px);
        }
    }
</style>

<script>
    (function () {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const toggle = document.getElementById('role-ai-toggle');
        const panel = document.getElementById('role-ai-panel');
        const close = document.getElementById('role-ai-close');
        const title = document.getElementById('role-ai-title');
        const subtitle = document.getElementById('role-ai-subtitle');
        const assistantSelect = document.getElementById('role-ai-assistant-select');
        const alertBox = document.getElementById('role-ai-alert');
        const messages = document.getElementById('role-ai-messages');
        const messageForm = document.getElementById('role-ai-message-form');
        const messageInput = document.getElementById('role-ai-input');
        const trainingForm = document.getElementById('role-ai-training-form');
        const trainingTitle = document.getElementById('role-ai-training-title');
        const trainingContent = document.getElementById('role-ai-training-content');
        const knowledgeList = document.getElementById('role-ai-knowledge-list');
        const refreshKnowledge = document.getElementById('role-ai-refresh-knowledge');

        let assistants = [];
        let currentAssistant = null;
        let sessionId = null;
        let loaded = false;

        function showAlert(message) {
            alertBox.textContent = message || '';
            alertBox.style.display = message ? 'block' : 'none';
        }

        function setBusy(button, busy) {
            button.disabled = busy;
            button.style.opacity = busy ? '0.7' : '1';
        }

        function request(url, options) {
            return fetch(url, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    ...(options && options.headers ? options.headers : {}),
                },
                ...options,
            }).then(async response => {
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.message || 'Error inesperado.');
                }

                return data;
            });
        }

        function appendMessage(content, type) {
            const item = document.createElement('div');
            item.className = 'role-ai-message ' + type;
            item.textContent = content;
            messages.appendChild(item);
            messages.scrollTop = messages.scrollHeight;
        }

        function updateHeader() {
            if (!currentAssistant) return;

            title.textContent = currentAssistant.name;
            subtitle.textContent = 'Rol: ' + currentAssistant.role;
        }

        function renderAssistants() {
            assistantSelect.innerHTML = '';

            assistants.forEach(assistant => {
                const option = document.createElement('option');
                option.value = assistant.id;
                option.textContent = assistant.name + ' (' + assistant.role + ')';
                assistantSelect.appendChild(option);
            });

            assistantSelect.style.display = assistants.length > 1 ? 'block' : 'none';
        }

        function startSession(assistantId) {
            sessionId = null;
            messages.innerHTML = '';
            appendMessage('Hola. Estoy listo para ayudarte con tu rol interno.', 'system');

            return request('{{ route('role-ai.sessions.store') }}', {
                method: 'POST',
                body: JSON.stringify({ assistant_id: assistantId }),
            }).then(data => {
                sessionId = data.session_id;
                currentAssistant = data.assistant;
                updateHeader();
                loadKnowledge();
            });
        }

        function loadAccess() {
            return request('{{ route('role-ai.access') }}')
                .then(data => {
                    assistants = data.assistants || [];

                    if (!assistants.length) {
                        showAlert('No tienes asistentes IA asignados.');
                        return;
                    }

                    currentAssistant = assistants[0];
                    renderAssistants();
                    updateHeader();
                    return startSession(currentAssistant.id);
                });
        }

        function loadKnowledge() {
            if (!currentAssistant) return;

            knowledgeList.textContent = 'Cargando...';

            request('{{ route('role-ai.knowledge.index') }}?assistant_id=' + encodeURIComponent(currentAssistant.id))
                .then(data => {
                    knowledgeList.innerHTML = '';
                    const entries = data.knowledge || [];

                    if (!entries.length) {
                        knowledgeList.textContent = 'Todavia no hay contexto guardado para este rol.';
                        return;
                    }

                    entries.forEach(entry => {
                        const item = document.createElement('div');
                        item.className = 'role-ai-knowledge-item';

                        const itemTitle = document.createElement('strong');
                        itemTitle.textContent = entry.title || 'Sin titulo';

                        const excerpt = document.createElement('div');
                        excerpt.textContent = entry.excerpt || '';

                        const meta = document.createElement('small');
                        meta.textContent = [entry.created_by, entry.created_at].filter(Boolean).join(' - ');

                        item.appendChild(itemTitle);
                        item.appendChild(excerpt);
                        item.appendChild(meta);
                        knowledgeList.appendChild(item);
                    });
                })
                .catch(error => {
                    knowledgeList.textContent = error.message;
                });
        }

        toggle.addEventListener('click', function () {
            panel.style.display = 'block';
            toggle.style.display = 'none';

            if (!loaded) {
                loaded = true;
                loadAccess().catch(error => showAlert(error.message));
            }
        });

        close.addEventListener('click', function () {
            panel.style.display = 'none';
            toggle.style.display = 'flex';
        });

        assistantSelect.addEventListener('change', function () {
            currentAssistant = assistants.find(assistant => String(assistant.id) === assistantSelect.value);
            showAlert('');
            updateHeader();
            startSession(currentAssistant.id).catch(error => showAlert(error.message));
        });

        document.querySelectorAll('.role-ai-tab').forEach(tab => {
            tab.addEventListener('click', function () {
                document.querySelectorAll('.role-ai-tab').forEach(item => item.classList.remove('active'));
                document.querySelectorAll('.role-ai-tab-content').forEach(item => item.classList.remove('active'));

                tab.classList.add('active');
                document.getElementById('role-ai-' + tab.dataset.tab + '-tab').classList.add('active');
            });
        });

        messageForm.addEventListener('submit', function (event) {
            event.preventDefault();

            const content = messageInput.value.trim();
            if (!content || !sessionId) return;

            const button = messageForm.querySelector('button[type="submit"]');
            appendMessage(content, 'user');
            messageInput.value = '';
            setBusy(button, true);
            showAlert('');

            request('{{ route('role-ai.messages.store') }}', {
                method: 'POST',
                body: JSON.stringify({ session_id: sessionId, mensaje: content }),
            })
                .then(data => appendMessage(data.mensaje_bot || 'Sin respuesta.', 'assistant'))
                .catch(error => showAlert(error.message))
                .finally(() => setBusy(button, false));
        });

        messageInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                messageForm.requestSubmit();
            }
        });

        trainingForm.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!currentAssistant) return;

            const button = trainingForm.querySelector('button[type="submit"]');
            setBusy(button, true);
            showAlert('');

            request('{{ route('role-ai.knowledge.store') }}', {
                method: 'POST',
                body: JSON.stringify({
                    assistant_id: currentAssistant.id,
                    title: trainingTitle.value.trim(),
                    content: trainingContent.value.trim(),
                }),
            })
                .then(data => {
                    trainingTitle.value = '';
                    trainingContent.value = '';
                    showAlert(data.message || 'Contexto guardado.');
                    loadKnowledge();
                })
                .catch(error => showAlert(error.message))
                .finally(() => setBusy(button, false));
        });

        refreshKnowledge.addEventListener('click', loadKnowledge);
    })();
</script>

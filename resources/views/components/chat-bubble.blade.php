<div id="chat-container" style="position: fixed; bottom: 20px; right: 20px; z-index:1997;">
    <!-- Icono de la burbuja de chat -->
    <div id="chat-icon" style="cursor: pointer;">
        <button style="background-color: #093143 !important; border: none; border-radius: 50%; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);">
            <i class="fas fa-comment" style="color: white; font-size: 28px;"></i>
        </button>
    </div>

    <!-- Ventana del chat (inicialmente oculta) -->
    <div id="chat-window" style="display: none; width: 420px; height: 540px; background-color: white; border-radius: 10px; box-shadow: 0px 4px 15px rgba(0, 0, 0, 0.2); overflow: hidden;">
        <!-- Encabezado del chat -->
        <div style="padding: 20px; background-color: #093143; color: white; display: flex; justify-content: space-between; align-items: center;">
            <strong style="font-size: 20px;">Treena</strong>
            <button id="close-chat" style="background: none; border: none; cursor: pointer; color: white; font-size: 20px;">X</button>
        </div>
        <!-- Mensajes del chat -->
        <div id="chat-messages" style="height: 385px; overflow-y: auto; padding: 20px; background-color: #f9f9f9;">
            <!-- Mensajes del chat -->
        </div>
        <!-- Input y botón de enviar -->
        <div style="padding: 15px; background-color: white; border-top: 1px solid #eee; display: flex; gap: 10px; align-items: center;">
            <input type="text" id="chat-input" style="width: 75%; padding: 12px; border: 1px solid #ddd; border-radius: 25px; outline: none; font-size: 16px;">
            <button id="send-message" class="cfrSefar" style="width: 25%; padding: 12px; background-color: #093143; color: white; border: none; border-radius: 25px; cursor: pointer; font-size: 16px;">Enviar</button>
        </div>
    </div>
</div>

<script>
    let sessionId = null;

    document.getElementById('chat-icon').addEventListener('click', function() {
        if (!sessionId) {
            fetch('/api/chat/iniciar', { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                sessionId = data.session_id;
                console.log('Nuevo session_id:', sessionId);
            });
        }

        document.getElementById('chat-window').style.display = 'block';
        document.getElementById('chat-icon').style.display = 'none';
    });

    document.getElementById('close-chat').addEventListener('click', function() {
        document.getElementById('chat-window').style.display = 'none';
        document.getElementById('chat-icon').style.display = 'block';
    });

    document.getElementById('send-message').addEventListener('click', function() {
        const input = document.getElementById('chat-input');
        const sendButton = document.getElementById('send-message');
        const chatMessages = document.getElementById('chat-messages');
        const message = input.value.trim();

        if (message) {
            // Desactivar el botón y cambiar el texto
            sendButton.disabled = true;
            sendButton.innerHTML = '...';

            // Mostrar el mensaje del usuario en el chat
            chatMessages.innerHTML += `<div style="margin-bottom: 10px; padding: 10px; background-color: white; border-radius: 10px; box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);">${message}</div>`;
            input.value = '';

            fetch('/api/chat/enviar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ session_id: sessionId, mensaje: message })
            })
            .then(response => response.json())
            .then(data => {
                if (data.mensaje_bot) {
                    chatMessages.innerHTML += `<div style="margin-bottom: 10px; padding: 10px; background-color: #e3f2fd; border-radius: 10px; box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);">${data.mensaje_bot}</div>`;
                }
            })
            .catch(error => console.error('Error al enviar mensaje:', error))
            .finally(() => {
                // Reactivar el botón y restaurar el texto
                sendButton.disabled = false;
                sendButton.innerHTML = 'Enviar';

                // Hacer scroll hasta el último mensaje
                chatMessages.scrollTop = chatMessages.scrollHeight;
            });
        }
    });
</script>


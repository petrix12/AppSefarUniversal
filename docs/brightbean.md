# BrightBean y App Sefar

BrightBean recibe y envía los mensajes de los canales. App Sefar conserva una
copia de mensajes, contactos y estados, y genera respuestas con su integración
OpenRouter. La clave de OpenRouter permanece en la App.

## Despliegue preparado

- URL: `https://chat.sefaruniversal.com`.
- Directorio del VPS: `/opt/brightbean-chat`.
- Proyecto Compose: `sefar-brightbean`.
- Fuente: [BrightBean Chat](https://github.com/brightbeanxyz/brightbean-chat),
  commit `7bf959b30cc7f345f84d2be59e68a0a05c327e4f`.
- Imagen construida: `brightbean-chat:sefar-7bf959b`.
- PostgreSQL, migración inicial, web y worker del Compose oficial.
- Nginx del VPS termina HTTPS y redirige a `127.0.0.1:8010`.
- PostgreSQL no publica ningún puerto. El Caddy del Compose oficial queda
  excluido mediante `deploy/docker-compose.external-tls.yml`.
- `.env` tiene permisos `600` y secretos generados en el VPS.

Referencia del proveedor: [instalación en VPS y proxy externo](https://github.com/brightbeanxyz/brightbean-chat/blob/main/docs/self-hosting.md).

## Publicar el lado de Sefar

Publicar estos cambios mediante el despliegue habitual de Git. El despliegue
de la App ejecuta las migraciones; para una publicación manual:

```bash
php artisan migrate --path=database/migrations/2026_11_04_120000_create_brightbean_integration_tables.php --force
php artisan optimize:clear
```

El almacenamiento de mensajes necesita `utf8mb4` para conservar los emojis.
La conexión MySQL utiliza ese juego de caracteres; no se modifica la conexión
separada de Onidex ni se convierten tablas existentes.

En **Integraciones → Tokens API**, crear un token de un usuario interno con
los permisos:

- `brightbean:sync`: sincronizar mensajes.
- `brightbean:ai`: generar respuestas con IA.

El token se utiliza únicamente desde el conector del VPS. No se coloca en
JavaScript público ni en este repositorio.

## Crear la cuenta de BrightBean

El propietario crea su cuenta en `/accounts/signup/`. Una vez creada,
identificar el UUID de su workspace desde el VPS. La consulta no muestra
contraseñas ni tokens:

```bash
cd /opt/brightbean-chat
docker compose -p sefar-brightbean -f docker-compose.prod.yml \
  -f deploy/docker-compose.external-tls.yml exec -T app \
  python manage.py shell -c 'from django.apps import apps; W=apps.get_model("workspaces", "Workspace"); print(list(W.objects.values("id", "name")))'
```

Seleccionar exclusivamente el workspace de Sefar, no todos los workspaces.
Configurar posteriormente las credenciales y webhooks de los canales desde
BrightBean y su `.env`, según la [documentación de canales](https://github.com/brightbeanxyz/brightbean-chat/tree/main/docs/channels).
El correo transaccional de la instalación (recuperación de contraseña e
invitaciones) requiere configurar SMTP en `.env`.

## Activar el conector

Los archivos propios están en `deploy/brightbean/` de este repositorio. Copiarlos
a `/opt/brightbean-chat/deploy/` en el VPS y completar allí, sin imprimir los
valores en los logs:

```dotenv
SEFAR_APP_URL=https://app.sefaruniversal.com
SEFAR_API_TOKEN=<token interno con los dos permisos BrightBean>
SEFAR_BRIGHTBEAN_WORKSPACE_ID=<UUID del workspace Sefar>
```

Iniciar después el servicio adicional:

```bash
cd /opt/brightbean-chat
docker compose -p sefar-brightbean -f docker-compose.prod.yml \
  -f deploy/docker-compose.external-tls.yml -f deploy/docker-compose.sefar.yml \
  --profile sefar up -d sefar-bridge
```

El conector se ejecuta como usuario sin privilegios dentro de la imagen de
BrightBean. Sincroniza lotes de hasta 20 mensajes y conserva un cursor en un
volumen propio. Los reintentos vuelven a actualizar el mismo mensaje.
Se sincronizan mensajes recibidos y enviados, notas internas, metadatos de
adjuntos, eliminaciones y cambios de estado; no se descargan los archivos
adjuntos al almacenamiento de Sefar.

El conector usa los modelos y el servicio de envío de la versión fijada de
BrightBean. La API pública de esa versión no ofrece una lectura completa de
mensajes. No se parchea el código del proveedor ni se escriben mensajes con
SQL directo. Antes de actualizar BrightBean, ejecutar las pruebas del conector
contra la nueva imagen.

## Respuestas de IA

En la App, abrir **Integraciones → Mensajes BrightBean**. La pantalla muestra
el estado de sincronización, los mensajes y sus conversaciones. Allí se
habilita la IA y se añaden indicaciones de atención al cliente.

El modelo se configura en **Modelos de IA → BrightBean: atención al cliente**,
independiente de los asistentes internos existentes. Utiliza la misma clave
OpenRouter de la App y admite los modelos de respaldo del configurador.

- La IA está desactivada inicialmente.
- Solo contesta texto recibido durante los últimos cinco minutos; el historial
  importado y los adjuntos se sincronizan sin generar respuestas antiguas.
- No contesta notas internas ni mensajes enviados por la propia integración.
- El historial para la IA se limita a la conversación y excluye notas internas,
  mensajes eliminados y envíos fallidos.
- Una conversación cerrada, pausada o respondida por un agente no recibe IA.
  La comprobación se repite después de consultar al modelo.
- La respuesta se guarda en Sefar antes del envío. BrightBean recibe una clave
  de idempotencia estable y aplica sus reglas de consentimiento y del canal.
- No se asocian automáticamente expedientes privados ni se da acceso del bot
  a los asistentes internos por rol.

## Responder manualmente desde la App

En **Integraciones → Mensajes BrightBean**, abrir **Ver conversación**, escribir
en **Tu respuesta** y pulsar **Enviar por BrightBean**. Se utiliza el contacto
y el canal del mensaje sincronizado; el formulario no permite cambiar el
destinatario. Requiere el mismo permiso interno `integrations.manage` de la
bandeja. El límite es de 4000 caracteres por respuesta de texto.

La App conserva una cola con el usuario que escribió cada respuesta. El
conector reclama los envíos de su workspace y utiliza `send_as_agent`, el
servicio nativo de BrightBean para operadores. Así se mantienen las reglas de
consentimiento y del canal y se pausa la automatización al menos 30 minutos.
Una respuesta manual pendiente también impide generar una nueva respuesta IA
desde la App para esa conversación.

Cada envío tiene un UUID estable. El formulario evita duplicados por doble
clic, y los reintentos del conector reutilizan la misma clave de idempotencia
en BrightBean. Las reservas vencen a los cinco minutos; después de cinco
intentos fallidos, la respuesta se marca como fallida para revisión.

La pantalla diferencia **En cola**, **Procesando**, **Recibido por BrightBean**
y **No se pudo enviar**. Que BrightBean acepte el mensaje no significa que el
cliente lo haya recibido: la sincronización posterior muestra los estados
reales del canal, incluidos entrega, lectura y fallo.

El conector reutiliza el token `brightbean:sync` para reclamar y confirmar
esta cola; no necesita un nuevo token ni la clave OpenRouter. Las rutas son
`POST /api/brightbean/outbox/claim` y
`POST /api/brightbean/outbox/{id}/ack`. Ambas exigen autenticación y la reserva
se confirma con un token único, además del workspace.

Después de publicar este cambio por Git, ejecutar la migración
`2026_11_05_170000_create_brightbean_outbound_messages_table.php` y limpiar
cachés. Actualizar `deploy/sefar_bridge.py` en el VPS y recrear el servicio
`sefar-bridge`. El conector actualizado tolera que la App todavía no publique
las rutas nuevas, manteniendo activa la sincronización anterior.

## Operación y verificación

```bash
cd /opt/brightbean-chat
docker compose -p sefar-brightbean -f docker-compose.prod.yml \
  -f deploy/docker-compose.external-tls.yml ps
curl --fail https://chat.sefaruniversal.com/healthz
```

Comprobar que el worker está ejecutándose además de la web. Los logs del
conector omiten cuerpos de mensajes, cabeceras de autorización y claves.

Pruebas de Sefar, con base SQLite en memoria y llamadas a OpenRouter simuladas:

```bash
php vendor/bin/phpunit tests/Feature/BrightbeanIntegrationTest.php
```

Pruebas del conector dentro de la imagen de BrightBean, con envíos y consultas
de mensajería simulados (no contactan a clientes ni modifican sus mensajes):

```bash
docker compose -p sefar-brightbean -f docker-compose.prod.yml \
  -f deploy/docker-compose.external-tls.yml run --rm --no-deps \
  --volume /opt/brightbean-chat/deploy:/app/sefar_tests:ro \
  app python /app/sefar_tests/test_sefar_bridge.py
```

Respaldar PostgreSQL, los medios y los secretos `SECRET_KEY` y
`ENCRYPTION_KEY_SALT` siguiendo la guía del proveedor. El cursor del conector
puede reconstruirse mediante una resincronización; perder los secretos de
cifrado requiere volver a conectar los canales.

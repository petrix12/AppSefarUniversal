# Cartera, revisión de pagos y sincronización CRM

## Certificados y saldos

Usuarios y permisos → **Cartera y certificados** (`/cos/cartera`). El acceso directo al filtro pedido es `/cos/cartera?certificate=yes&balance=debt`; el botón CSV conserva los filtros y descarga todos los resultados, no solamente la página visible.

«Con certificado» significa que existe una fecha válida registrada en HubSpot o en el proyecto Teamleader importado. No comprueba que exista un archivo de certificado descargable. El saldo conocido suma importes legibles en EUR y concilia los registros locales; una moneda sin convertir o información insuficiente requiere revisión. Sin saldo registrado no equivale a una certificación de solvencia. Se respetan permisos, cartera del responsable y bloqueos de expedientes.

## Sobrepagos fuera del COS

La ficha COS no muestra importes, etiquetas, enlaces ni avisos de sobrepagos. La cartera tampoco publica el motivo específico de revisión relacionado con ellos. Los cálculos contables y avisos internos por correo conservan su comportamiento.

La consulta está en **Pagos y servicios → Revisión de pagos**, abierta en otra pestaña: `/admin/finance/payment-reviews`. Permite buscar un cliente y abrir su revisión independiente. Utiliza exactamente las filas de la conciliación final del COS, incluida la conciliación de recibos históricos, y muestra su fecha. El informe dura 24 horas; si no existe, indica que se debe abrir la ficha para generarlo. Abrir el informe no sincroniza plataformas ni genera cobros.

Requiere usuario interno con `crud.users.index`; Ventas y Coordinación solo consultan sus clientes. Un usuario con rol Cliente no obtiene acceso aunque acumule otros roles. Los bloqueos se vuelven a comprobar en cada consulta.

## Evaluación de webhooks

El requerimiento posterior amplía el objetivo a App ↔ HubSpot ↔ Teamleader, con prioridad por modificación real de cada campo y control de ecos. El diseño y la secuencia de activación están en [sincronizacion-crm-bidireccional.md](sincronizacion-crm-bidireccional.md). El código descrito a continuación sigue siendo la base de recepción CRM → App; la ampliación no está activada por desplegarla.

Es viable reemplazar los refrescos de contactos disparados al abrir el COS por eventos, como se hizo con Monday. Ambos proveedores tienen webhooks de contactos y negocios. Teamleader permite registrar destinos mediante API. En una app privada legacy de HubSpot las suscripciones se administran en la interfaz, no mediante su token de acceso CRM. Desde el 26 de octubre de 2026 HubSpot no permite crear nuevas apps privadas legacy; para una integración nueva corresponde la plataforma de desarrollo actual.

El código agrega receptores y espejos locales. **No registra suscripciones en producción durante la instalación de archivos, ni activa automáticamente el modo de lectura local.** Se necesita desplegarlo, configurar secretos, cargar el estado inicial y comprobar entregas reales. El conector de HubSpot usado en el chat no administra suscripciones de aplicaciones.

### Criterio de cliente

- Por proveedor: un contacto con al menos un negocio asociado es `client`, incluidos negocios abiertos, ganados o perdidos.
- Sin negocios es `lead`: se comprueba su elegibilidad, pero no se importa ni actualiza su perfil. Solo se conserva la referencia necesaria para clasificarlo.
- Un proyecto o una factura sin negocio no bastan. Un negocio cuyo cliente es una empresa en Teamleader no convierte automáticamente a todos los contactos de esa empresa en clientes.
- Al perder su último negocio se retira el perfil del espejo activo; se conserva el historial local anterior y la cuenta App.
- Un contacto eliminado en origen queda `deleted`. Un fallo de API conserva el estado anterior y se reintenta; nunca se interpreta como ausencia de negocios.
- Se vincula con la App por `hs_id` o `tl_id` exacto y único. Una identidad ambigua se conserva sin vincular. No se crean cuentas, se fusionan personas ni se reasignan identidades automáticamente.

### Flujo implementado

`Proveedor → POST firmado/autenticado → crm_webhook_events → cola crm-sync → lectura actual del contacto y sus negocios → espejo local`.

HubSpot: `/api/webhooks/hubspot`. Valida firma v3, ventana de cinco minutos y portal autorizado. Si la app legacy emite v1, se configura **explícitamente** v1; no hay degradación automática. La firma usa el secreto de la aplicación que emite el webhook, no el token CRM.

Teamleader: `/api/webhooks/teamleader?token=…`. Requiere secreto aleatorio de al menos 32 caracteres y cuenta autorizada. El token completo no se imprime en la pantalla de administración. Debe protegerse también en los registros del proxy. El formato recibido debe comprobarse con una entrega real: `type`, `subject.id` y `account.id`.

Se almacenan metadatos de encaminamiento, fecha y procedencia del evento, sin valores de propiedades ni credenciales. Las entregas repetidas se identifican mediante una huella del evento sin `attemptNumber`; no se depende únicamente de `eventId`, que HubSpot no garantiza único. Cada trabajo vuelve a leer el estado actual para soportar eventos fuera de orden. Las asociaciones eliminadas y los antiguos contactos de un negocio se vuelven a evaluar. Los trabajos fallidos conservan clase de error y número de intentos; la recuperación automática se detiene después de cinco intentos de procesamiento. Con `CRM_EVENT_PROCESSING_ENABLED=false` se conserva la recepción sin encolar ni reintentar trabajos; al reactivarlo, la recuperación recoge los eventos pendientes.

Para clientes vinculados, HubSpot actualiza sus negocios locales y los campos App `nombres`, `apellidos` y `phone`; también se evalúa el bloqueo por retiro. Teamleader actualiza sus contactos y negocios importados. El perfil completo calificado queda disponible en el espejo de su proveedor. Email de acceso, pasaporte, permisos, pagos y contratos no se modifican por propiedades arbitrarias.

**Dirección de esta implementación: CRM → App.** No se reenvía automáticamente una modificación de HubSpot a Teamleader o Monday. Para esa sincronización entre plataformas hace falta cerrar el mapa de campos y la fuente responsable de cada campo: identidades, responsables, datos financieros y documentos tienen reglas diferentes. Las operaciones explícitas existentes de la App hacia cada proveedor se conservan. La capa canónica de unificación mantiene su interruptor de escritura actual; esta función no lo activa.

Los eventos de propiedades HubSpot ajenas al catálogo COS se aceptan sin lecturas ni escrituras de perfiles. Los proyectos clásicos Teamleader se releen con `projects.info` y solo se importan si su contacto tiene negocios. Los cambios de los trece campos COS invalidan su caché local; una descripción ajena al cálculo no provoca otra actualización. Un 404 confirmado excluye el proyecto del COS manteniendo historial, documentos, facturas y asociaciones. Una excepción de API conserva el estado anterior; una eliminación atrasada relee el estado actual. Si el proyecto cambia de cliente y contradice su asociación App, se oculta su espejo del COS y se registra un error para revisión, sin transferir expedientes. Importar un proyecto no crea facturas, recibos ni cobros. Facturas, documentos y carga inicial conservan sus importadores independientes.

## Activación

1. Desplegar los archivos y ejecutar `php artisan migrate --force`. Se crean dos tablas nuevas; no se cambia S3.
2. Configurar `CRM_HUBSPOT_CLIENT_SECRET`, `CRM_HUBSPOT_PORTAL_ID`, `CRM_HUBSPOT_SIGNATURE_VERSION` (`v3`, o `v1` para el emisor legacy comprobado), `CRM_TEAMLEADER_WEBHOOK_SECRET`, `CRM_TEAMLEADER_ACCOUNT_ID`. Configurar `APP_URL` HTTPS y el proxy confiable para que la URI firmada coincida con la pública. No guardar secretos en Git.
3. Habilitar `CRM_HUBSPOT_WEBHOOKS_ENABLED=true` y `CRM_TEAMLEADER_WEBHOOKS_ENABLED=true`, manteniendo `CRM_EVENT_PROCESSING_ENABLED=false` y `CRM_LOCAL_READS_ENABLED=false`; ejecutar `php artisan config:clear`. Así se pueden verificar entregas sin actualizar perfiles mientras se completa la ampliación bidireccional.
4. En **Integraciones → Sincronización CRM**, un administrador interno puede pulsar **Registrar webhooks de Teamleader**. Alternativa CLI: `php artisan crm:register-teamleader-webhooks` para verificar y `php artisan crm:register-teamleader-webhooks --apply` para registrar doce eventos de contactos/negocios/proyectos clásicos. Se vuelve a consultar el registro antes de anunciar éxito. Solo administra el destino exacto de esta App y conserva tipos existentes; no elimina otros destinos. Todavía debe comprobarse una entrega HTTP.
5. HubSpot existente: Development → Legacy apps → aplicación correspondiente → Webhooks → Edit webhooks. Destino `/api/webhooks/hubspot`. Suscribir `contact.creation`, `contact.deletion`, `contact.restore`, `contact.merge`, `contact.associationChange`, `contact.propertyChange`, `deal.creation`, `deal.deletion`, `deal.restore`, `deal.associationChange` y `deal.propertyChange`. Los cambios de propiedades requieren suscripciones individuales, limitadas al catálogo `CosCrmSyncFields`, verificado contra la cuenta real. El 16-11-2026 se activaron las 49 suscripciones de Api-Call-Sefar manteniendo el procesamiento pausado. La prueba nativa obtuvo HTTP 202 y la App conserva su evento pendiente con cero intentos. Las doce suscripciones Teamleader también quedaron registradas y verificadas en su API; falta una entrega originada en un cambio real. En la plataforma nueva, usar el formato clásico `legacyCrmObjects`, no `object.*`.
6. Mantener el scheduler de Laravel cada minuto. Agrega el trabajador de conexión **database** `crm`, cola `crm-sync`, aunque la cola global sea `sync`, y recuperación cada cinco minutos. Con Supervisor se puede usar `php artisan queue:work crm --queue=crm-sync --timeout=300 --tries=5`.
7. Una vez implementada y validada la política de conflictos de la ampliación bidireccional, habilitar expresamente `CRM_EVENT_PROCESSING_ENABLED=true` y ejecutar `php artisan config:clear`. Carga inicial: `php artisan crm:reconcile-contacts hubspot --limit=50 --after=0` y lo mismo con `teamleader`; repetir usando el cursor mostrado. Solo recorre cuentas Cliente App ya vinculadas. Los contactos nuevos calificados se incorporan por eventos sin crearles acceso. Los proyectos existentes se cargan con el importador independiente antes de usar el modo local.
8. Verificar en **Integraciones → Sincronización CRM** (`/admin/integrations/crm-sync`) un evento real de cada proveedor, incorporación de un negocio y eliminación del último, además de un evento repetido. La pantalla muestra recepción configurada, clasificaciones e intentos; «habilitada» no significa «suscripción comprobada».
9. Solo después de verificar carga inicial, suscripciones, importación financiera independiente y espejo Monday, activar `CRM_LOCAL_READS_ENABLED=true` y ejecutar `php artisan config:clear`. Este modo exige procesamiento activado, ambos receptores habilitados y el espejo Monday: abrir el COS calcula estados locales sin encolar refrescos externos; Formulario 001 usa el espejo HubSpot. Si falta su perfil calificado se informa indisponibilidad, sin volver silenciosamente a la API.
10. Mantener una conciliación periódica por lotes de contactos vinculados como respaldo de eventos perdidos; usar el cursor y revisar los errores. Desactivar `CRM_LOCAL_READS_ENABLED` devuelve el comportamiento anterior de lectura. Deshabilitar un receptor impide nuevas entregas y su procesamiento; sus eventos pendientes se conservan.

## Fuentes verificadas el 16-11-2026

- [Teamleader Focus: registro de webhooks](https://developer.focus.teamleader.eu/docs/api/webhooks-register).
- [HubSpot: configuración en la plataforma actual](https://developers.hubspot.com/docs/apps/developer-platform/build-apps/features/configure-webhooks).
- [HubSpot: suscripciones en apps privadas legacy y limitaciones](https://developers.hubspot.com/docs/apps/legacy-apps/private-apps/create-and-edit-webhook-subscriptions-in-private-apps).
- [HubSpot: firma de solicitudes](https://developers.hubspot.com/docs/apps/legacy-apps/authentication/validating-requests).
- [HubSpot: eventos, asociaciones, duplicados y orden de entrega](https://developers.hubspot.com/docs/api-reference/legacy/webhooks/guide).

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

Se almacenan metadatos de encaminamiento, sin valores de propiedades ni credenciales. Las entregas repetidas se identifican mediante una huella del evento sin `attemptNumber`; no se depende únicamente de `eventId`, que HubSpot no garantiza único. Cada trabajo vuelve a leer el estado actual para soportar eventos fuera de orden. Las asociaciones eliminadas y los antiguos contactos de un negocio se vuelven a evaluar. Los trabajos fallidos conservan clase de error y número de intentos; la recuperación automática se detiene después de cinco intentos de procesamiento.

Para clientes vinculados, HubSpot actualiza sus negocios locales y los campos App `nombres`, `apellidos` y `phone`; también se evalúa el bloqueo por retiro. Teamleader actualiza sus contactos y negocios importados. El perfil completo calificado queda disponible en el espejo de su proveedor. Email de acceso, pasaporte, permisos, pagos y contratos no se modifican por propiedades arbitrarias.

**Dirección de esta implementación: CRM → App.** No se reenvía automáticamente una modificación de HubSpot a Teamleader o Monday. Para esa sincronización entre plataformas hace falta cerrar el mapa de campos y la fuente responsable de cada campo: identidades, responsables, datos financieros y documentos tienen reglas diferentes. Las operaciones explícitas existentes de la App hacia cada proveedor se conservan. La capa canónica de unificación mantiene su interruptor de escritura actual; esta función no lo activa.

Los proyectos, facturas y documentos de Teamleader siguen usando su importador independiente. Estos webhooks de contactos/negocios no sustituyen ese importador ni sus controles de documentos y pagos. La ampliación futura de eventos financieros debe respetar la asociación contacto/empresa y el criterio de negocios.

## Activación

1. Desplegar los archivos y ejecutar `php artisan migrate --force`. Se crean dos tablas nuevas; no se cambia S3.
2. Configurar `CRM_HUBSPOT_CLIENT_SECRET`, `CRM_HUBSPOT_PORTAL_ID`, `CRM_HUBSPOT_SIGNATURE_VERSION` (`v3`, o `v1` para el emisor legacy comprobado), `CRM_TEAMLEADER_WEBHOOK_SECRET`, `CRM_TEAMLEADER_ACCOUNT_ID`. Configurar `APP_URL` HTTPS y el proxy confiable para que la URI firmada coincida con la pública. No guardar secretos en Git.
3. Habilitar `CRM_HUBSPOT_WEBHOOKS_ENABLED=true` y `CRM_TEAMLEADER_WEBHOOKS_ENABLED=true`; ejecutar `php artisan config:cache`.
4. Ejecutar `php artisan crm:register-teamleader-webhooks` para verificar y `php artisan crm:register-teamleader-webhooks --apply` para registrar los nueve eventos de contactos/negocios. Solo administra el destino exacto de esta App y conserva los tipos existentes en él; no elimina otros destinos.
5. HubSpot existente: Development → Legacy apps → aplicación correspondiente → Webhooks → Edit webhooks. Destino `/api/webhooks/hubspot`. Suscribir `contact.creation`, `contact.deletion`, `contact.restore`, `contact.merge`, `contact.associationChange`, `contact.propertyChange`, `deal.creation`, `deal.deletion`, `deal.restore`, `deal.associationChange` y `deal.propertyChange`. Las propiedades incluyen nombres/teléfonos y las utilizadas por Formulario 001, retiro, certificado, fases y estatus. Los cambios de propiedades requieren suscripciones individuales; inventariarlas con las definiciones reales de la cuenta. En la plataforma nueva, usar el formato clásico `legacyCrmObjects` que admite este receptor, no `object.*`.
6. Mantener el scheduler de Laravel cada minuto. Agrega el trabajador de conexión **database** `crm`, cola `crm-sync`, aunque la cola global sea `sync`, y recuperación cada cinco minutos. Con Supervisor se puede usar `php artisan queue:work crm --queue=crm-sync --timeout=300 --tries=5`.
7. Carga inicial de cuentas App ya vinculadas: `php artisan crm:reconcile-contacts hubspot --limit=50 --after=0` y lo mismo con `teamleader`. Repetir usando el cursor mostrado. Los contactos calificados nuevos sin cuenta App se incorporan al espejo mediante eventos; no se les crea acceso. Estos comandos no recorren indiscriminadamente todos los leads del CRM.
8. Verificar en **Integraciones → Sincronización CRM** (`/admin/integrations/crm-sync`) un evento real de cada proveedor, incorporación de un negocio y eliminación del último, además de un evento repetido. La pantalla muestra recepción configurada, clasificaciones e intentos; «habilitada» no significa «suscripción comprobada».
9. Solo después de verificar carga inicial, suscripciones, importación financiera independiente y espejo Monday, activar `CRM_LOCAL_READS_ENABLED=true` y regenerar la caché de configuración. Este modo exige ambos receptores habilitados y el espejo Monday: abrir el COS calcula estados locales sin encolar refrescos externos; Formulario 001 usa el espejo HubSpot. Si falta su perfil calificado se informa indisponibilidad, sin volver silenciosamente a la API.
10. Mantener una conciliación periódica por lotes de contactos vinculados como respaldo de eventos perdidos; usar el cursor y revisar los errores. Desactivar `CRM_LOCAL_READS_ENABLED` devuelve el comportamiento anterior de lectura. Deshabilitar un receptor impide nuevas entregas y su procesamiento; sus eventos pendientes se conservan.

## Fuentes verificadas el 16-11-2026

- [Teamleader Focus: registro de webhooks](https://developer.focus.teamleader.eu/docs/api/webhooks-register).
- [HubSpot: configuración en la plataforma actual](https://developers.hubspot.com/docs/apps/developer-platform/build-apps/features/configure-webhooks).
- [HubSpot: suscripciones en apps privadas legacy y limitaciones](https://developers.hubspot.com/docs/apps/legacy-apps/private-apps/create-and-edit-webhook-subscriptions-in-private-apps).
- [HubSpot: firma de solicitudes](https://developers.hubspot.com/docs/apps/legacy-apps/authentication/validating-requests).
- [HubSpot: eventos, asociaciones, duplicados y orden de entrega](https://developers.hubspot.com/docs/api-reference/legacy/webhooks/guide).

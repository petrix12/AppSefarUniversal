# Consultas, citas y COS con Kalendia

## Comportamiento

La reunión inicial de las cuatro modalidades de nacionalidad se ofrece después de **pago → 001 → contrato**. Solo se ofrece una primera reserva por cliente. Una cita con identificador remoto consume esa reunión incluida, incluso si después se cancela. Un intento incierto debe comprobarse antes de iniciar otra reserva inicial. Al terminar, el cliente continúa al COS y consulta el historial en **Mis reuniones**; la agenda inicial deja de abrirse al iniciar sesión o volver al inicio.

Las consultas adicionales se contratan desde **Contratar consulta** (`/consultas`). Los servicios activos con precio positivo en EUR se importan automáticamente desde Kalendia al abrir el catálogo, desde administración y cada cinco minutos con `kalendia:sync-consultations`. El administrador dispone de **Actualizar servicios y precios de Kalendia**. La sincronización conserva la visibilidad elegida y las modalidades manuales, actualiza por identificador remoto y deshabilita los servicios retirados sin borrar compras. Un catálogo incompleto o una caída de la API conserva la información anterior. Los servicios gratuitos incluidos en el registro no se publican como consultas de pago.

Antes de crear una compra importada se vuelve a consultar el precio. La orden conserva ese importe y moneda al reintentar, aunque cambie después el catálogo. No se usan importes enviados por el navegador. Precios ausentes, negativos, con más de dos decimales o en otra moneda no habilitan cobros en EUR. Si la API omite la moneda, se usa `KALENDIA_CURRENCY` (EUR por defecto, la moneda configurada de la integración).

Al enviar el horario, la App guarda una solicitud por compra y abre **Mis reuniones**. El programador ejecuta `kalendia:process-bookings` cada minuto y toma una solicitud por ejecución. Comprueba la disponibilidad y crea la cita en Kalendia. Los fallos temporales se reintentan con la misma referencia de reserva y, si se agotan los intentos, la solicitud queda en revisión. Si Kalendia rechaza el horario por falta de disponibilidad, el cliente puede elegir otro desde **Mis reuniones**.

El administrador configura las modalidades en **Agenda y consultas → Consultas y precios** (`/admin/kalendia/consultas`). El cliente paga con Stripe en App Sefar antes de reservar. La reserva omite `payment_method`, que es opcional según [AppointmentInput de Kalendia](https://developers.kalendia.co/openapi.yaml), y conserva importe, moneda y referencia del comprobante en las notas. No se configura `KALENDIA_PREPAID_PAYMENT_METHOD` ni se envía `stripe` para iniciar otro checkout en Kalendia. El estado de pago de App Sefar se confirma con Stripe; no se fuerza el estado de un pago remoto.

El flujo de consulta es **elegir modalidad → pagar en la App con Stripe → comprobar pago → agendar con Kalendia**. La orden conserva un importe fijo y un identificador de pago al reintentar. La App verifica importe, moneda, titular y referencia con Stripe; la respuesta del navegador no confirma pagos. Se reutilizan el comprobante y la compra confirmada, sin duplicarlos. El cobro de una consulta conserva los estados del registro y contrato. Los pagos de consulta usan su propia referencia CRM y no aumentan las fases o saldos del negocio de nacionalidad.

Una confirmación de Stripe que llegue antes de que termine la creación del intento también se conserva. Si se pierde la respuesta de creación, se repite el mismo cuerpo y clave. Después de 23 horas, una creación incierta sin identificador pasa a revisión en lugar de arriesgar un nuevo cargo tras vencer la retención de claves del proveedor. [Idempotencia de Stripe](https://docs.stripe.com/api/idempotent_requests). Los datos de tarjeta se introducen en Stripe Elements; el servidor de la App no recibe esos campos.

## Agenda administrativa y del cliente

**Agenda y consultas → Calendario de Kalendia** (`/admin/kalendia/citas`) permite consultar citas, filtrar por cliente y estado, abrir el enlace virtual, reagendar y cancelar. Solo administradores internos acceden a estas rutas; una cuenta Cliente con permisos adicionales tampoco puede entrar. La ampliación de contactos, notas, formularios y seguimiento de eventos está en **Agenda y consultas → Administrar Kalendia**, descrita en [la guía de administración de plataformas](stripe-kalendia-administracion.md).

La agenda administrativa abre en un calendario mensual, con vistas **Mes, Semana, Día y Lista**, navegación por fechas y estados identificados por color y texto. Al pulsar una cita o «Gestionar» en la tabla se abre un **modal en la misma página**, con detalles, enlace copiable, reprogramación y confirmación de cancelación dentro del propio modal. Guardar conserva la posición del calendario y actualiza la cita y el historial sin abrir pestañas ni navegar a otra pantalla. Los errores se muestran dentro del modal; una operación incierta conserva su identificador y ofrece comprobar el cambio pendiente. El filtro de cliente del COS y el filtro de estado se aplican a todas las vistas. El historial paginado sigue disponible bajo «Historial completo en tabla». Los eventos del calendario se consultan completos para el intervalo visible en la copia local, sin limitarse a los 30 registros de la tabla ni llamar a Kalendia al navegar. Las horas respetan la zona IANA seleccionada y su horario de verano. En móvil, los controles se distribuyen en varias filas y la cuadrícula semanal permite desplazamiento dentro del calendario.

Mientras la agenda está visible, vuelve a leer la copia local cada minuto. La consulta remota sigue programada cada cinco minutos: el cron debe ejecutar `schedule:run` **cada minuto**. La ruta autenticada `/cron/scheduler-run` invoca ese mismo comando; si se usa cron-job.org, revisar también que sus ejecuciones tengan HTTP 200 y la credencial con permiso `cron:scheduler:run`. Un icono de tarea activa no confirma por sí solo la frecuencia ni el resultado. No hace falta otro cron exclusivo para Kalendia. Los recursos del calendario se sirven desde la App y no requieren compilar JavaScript ni cargar un CDN.

Las modificaciones se envían mediante PATCH a la API. Una operación pendiente conserva el mismo identificador y cuerpo; antes de iniciar otra se debe comprobar la pendiente. La sincronización también puede confirmar una operación incierta cuando Kalendia devuelve el estado o el horario solicitado, sin enviar otro PATCH. Reagendar valida fecha futura, lunes a viernes y disponibilidad del profesional. Cancelar una cita no ejecuta ningún reembolso ni devuelve otra reunión inicial gratuita.

El cliente usa **Mis reuniones** (`/mis-reuniones`) para ver fechas, estados y enlaces propios. La lectura se limita a su usuario y no muestra citas de otras cuentas. La zona del dispositivo se detecta con `Intl` y se puede cambiar en el selector; la preferencia se conserva en ese navegador y sesión. Se usan identificadores IANA (Madrid/Canarias, Santiago/Magallanes/Rapa Nui, Caracas y Bogotá), con las reglas de horario de verano correspondientes a la fecha de la cita. No se fija un desfase constante por país. En su COS aparece el bloque **Reuniones y consultas** con el próximo evento y su estado. El personal autorizado dispone allí de **Ver reuniones del cliente**, que abre el filtro administrativo correspondiente. Este bloque de agenda no sustituye el estado jurídico del proceso.

La copia local guarda los instantes en UTC. La disponibilidad se presenta para el día y la zona elegidos por quien consulta; se incluyen los días colindantes del calendario del negocio cuando cambia la fecha al convertir. La reserva y el PATCH se envían en la hora base de Kalendia (Bogotá), manteniendo el mismo instante, y la ficha del contacto y las observaciones conservan la zona del cliente. Se vuelven a validar fines de semana, pasado y disponibilidad al reservar.

La copia local de citas se actualiza al reservar/cambiar una cita y en segundo plano cada cinco minutos. El COS consulta únicamente la próxima cita en esa copia; no llama a Kalendia al abrir la ficha. Las listas tienen paginación. Las citas existentes creadas fuera de la App se vinculan por identificadores ya confirmados o por correo exacto y único de una cuenta Cliente. Los identificadores compartidos o conflictivos no se asignan automáticamente.

La sincronización obtiene una página de hasta 100 citas por ejecución y conserva cursor, página y un margen de dos minutos. Si falla, no avanza el punto de lectura. Para una carga inicial grande se necesitan varias ejecuciones; no se vuelven a descargar todos los contactos en cada COS.

## Despliegue

Subir todos los archivos del cambio. En la carpeta Laravel de producción, conservar las claves ya configuradas:

```dotenv
KALENDIA_TOKEN=TOKEN_SIN_PREFIJO_BEARER
REGISTRATION_MEETING_WEBHOOK_ENABLED=true
REGISTRATION_CONTACT_NOTIFY_N8N=false
```

Las consultas usan `STRIPE_KEY` y `STRIPE_SECRET`, ya utilizados por la App. Revisar los nombres efectivos en `config/services.php`; no pegar secretos en logs ni en el chat. Para el webhook nuevo de Stripe configurar:

```dotenv
STRIPE_KALENDIA_WEBHOOK_SECRET=SECRETO_DEL_ENDPOINT
```

El secreto es exclusivo del endpoint de consultas; ya no se utiliza `STRIPE_WEBHOOK_SECRET` como alternativa. En Stripe se registra **solo `payment_intent.succeeded`**, formato **Snapshot**, eventos de **tu cuenta**, para:

`https://app.sefaruniversal.com/api/webhooks/kalendia/stripe`

El receptor valida la firma y rechaza eventos sin firma o con secreto ausente. Se recomienda el webhook para confirmar aunque el cliente cierre la página. Existe además una conciliación de respaldo cada cinco minutos: verifica hasta diez pagos pendientes mediante GET y no genera intentos ni cargos nuevos. [Confirmación del pago con Stripe](https://docs.stripe.com/payments/payment-intents/verifying-status).

Stripe puede enviar pagos de otros servicios de la misma cuenta. Recibir un evento no significa aplicarlo a una consulta: la App descarta tipos ajenos, eventos de cuentas conectadas, modo de pruebas/producción que no coincide con la clave configurada, objetos distintos de PaymentIntent, referencias sin UUID de consulta y órdenes desconocidas. También descarta referencias de pago o de usuario diferentes de las guardadas. Para un pago propio se comprueban importe, moneda y estado antes de reutilizar la compra y factura; los reenvíos no crean otro cobro o comprobante. Una discrepancia financiera de un pago propio requiere revisión, no se acredita automáticamente. Los eventos ajenos se reconocen con HTTP 200 para evitar reintentos innecesarios. La suscripción del endpoint se restringe por tipo de evento; el filtro de pertenencia a la consulta se hace dentro de la App. [Configuración de destinos de Stripe](https://docs.stripe.com/webhooks).

## Webhook de Kalendia hacia la App

Este receptor es distinto del webhook de Stripe:

`https://app.sefaruniversal.com/api/webhooks/kalendia`

En **Kalendia → Automatizaciones → Webhooks**, usar la conexión propia y seleccionar solamente `appointment.created`, `appointment.confirmed`, `appointment.rescheduled` y `appointment.cancelled`. Los eventos de clientes, tienda, formularios y pagos no forman parte de este receptor. El secreto de verificación generado por Kalendia va en:

```dotenv
KALENDIA_WEBHOOK_SECRET=SECRETO_DEL_WEBHOOK_DE_KALENDIA
```

Este valor es distinto de `KALENDIA_TOKEN` y de `STRIPE_KALENDIA_WEBHOOK_SECRET`. Opcionalmente se puede fijar `KALENDIA_WEBHOOK_BUSINESS_ID` con el `business_id` real de los eventos del negocio; no copiar el valor del ejemplo de la documentación. La firma propia del webhook y la API restringida al subdominio de Sefar ya proporcionan aislamiento; fijar el negocio añade otra comprobación.

Se verifican las cabeceras `X-Kalendia-Timestamp`, `X-Kalendia-Delivery`, `X-Kalendia-Event` y `X-Kalendia-Signature` contra los bytes originales del cuerpo: `v1=` más HMAC SHA-256 de `timestamp.delivery_id.event_type.raw_body`. La ventana de vigencia es de cinco minutos. Se comprueba también la coincidencia de tipo, versión y recurso. [Contrato oficial de webhooks de Kalendia](https://developers.kalendia.co/#firmas).

La petición solo persiste identificadores mínimos y devuelve HTTP 202; los duplicados válidos devuelven 200 y no se vuelven a encolar. No guarda el cuerpo ni los datos personales del evento. El procesamiento se ejecuta **cada minuto** con el scheduler existente y consulta por GET únicamente `/appointments?id=apt_…`, verificando que la API devuelve exactamente esa cita. Se utiliza el estado actual del proveedor, no el contenido parcial o antiguo de la notificación. Los fallos conservan la referencia y se reintentan con espera creciente; después de ocho fallos se conserva para revisión. No se crean pagos, facturas, clientes ni citas a partir de estos eventos. La App tampoco envía un PATCH al recibirlos, evitando ciclos entre ambos sistemas. La sincronización incremental de cinco minutos permanece como respaldo; si hay más eventos que el límite de cuatro por ejecución, se procesan en las siguientes ejecuciones.

Después de subir esta actualización y configurar el secreto de Kalendia:

```bash
php artisan config:clear
php artisan migrate --force --path=database/migrations/2026_11_22_120000_create_kalendia_webhook_events_table.php
php artisan kalendia:check
php artisan kalendia:process-webhooks
```

No ejecutar `config:cache`. El scheduler debe seguir invocándose cada minuto. Tras recibir una notificación real firmada se debe comprobar un 202/200 en Kalendia y que la cita se refleja en la App. Las pruebas locales de firmas, duplicados y aislamiento no sustituyen esa validación de entrega real después del despliegue.

En SSH:

```bash
cd /home/xzysctv/gitproyects/app/AppSefarUniversal
php artisan config:clear
php artisan migrate --force --path=database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php --path=database/migrations/2026_11_20_190000_create_kalendia_consultation_management_tables.php --path=database/migrations/2026_11_23_190000_create_kalendia_booking_requests_table.php
php artisan kalendia:check
php artisan kalendia:sync-appointments
```

Si están pendientes las migraciones del 19/11, aplicar primero las indicadas en `docs/superadmins-notas-registro-reunion.md`. No ejecutar `config:cache`. El cron Laravel `schedule:run` debe seguir activo. La primera sincronización consulta una página; si hay más de 100 citas, las siguientes ejecuciones continúan la carga. Las nuevas órdenes y citas se guardan en tablas independientes de los calendarios locales antiguos.

## Validación y pendientes externos

La prueba real previamente autorizada creó una sola cita ficticia el 19/03/2027, de 09:00 a 10:00 de Colombia, con el Dr. Alex. Se comprobó por API el mismo cliente, el estado confirmado, el enlace Zoom y que ese horario dejó de estar disponible. Esta actualización no realizó nuevos cobros reales ni modificó citas de clientes de producción.

Los nuevos cobros, firmas de ambos proveedores, reintentos, comprobantes y permisos se validan con proveedores simulados y base de datos aislada. El 22/11/2026 se desplegó el receptor de Kalendia, se aplicó su migración y el administrador configuró el secreto propio. Se creó y activó el webhook en la conexión «Alejandro APP Sefar universal», apuntando a `/api/webhooks/kalendia`, con los cuatro eventos de citas. La prueba enviada desde Kalendia confirmó «Prueba entregada correctamente. La plataforma respondió con éxito»: se verificó una entrega firmada al receptor de producción. Esta prueba de transporte no sustituye comprobar el cambio completo de una cita y su procesamiento programado.

En Stripe se comprobó que el endpoint de consultas está activo, solo escucha `payment_intent.succeeded` de la propia cuenta y está separado del de Banca Online; no había entregas registradas, por lo que su recepción firmada sigue pendiente. Antes de activar precios reales se debe comprobar un pago completo en modo prueba del servidor. La App no crea ni altera precios remotos de Kalendia: su API proporcionada permite leer servicios, no editarlos. Los reembolsos siguen los canales de pago existentes; cancelar la agenda no los procesa.

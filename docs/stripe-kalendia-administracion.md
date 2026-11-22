# Administración de Stripe y Kalendia

Guía del código incorporado el 22/11/2026, pendiente de despliegue y comprobación en el entorno destino. Tener una credencial configurada no demuestra conexión válida, entrega de webhooks ni procesamiento correcto.

## Acceso y alcance

- Stripe: **Finanzas → Stripe → Administrar Stripe**. La principal, `main`, permite la administración autorizada; **España → Solo consulta**, `spain_legacy`, conserva un histórico aislado y no admite escrituras externas.
- Kalendia: **Agenda y consultas → Administrar Kalendia**, cuenta `main`.
- PayPal antiguo de España: **Finanzas → PayPal España · Histórico**, exclusivamente consultas de transacciones.
- Se exige sesión, rol `Administrador`, ausencia del rol `Cliente` y la autorización `stripe.manage` o `kalendia.manage`, según corresponda. Estas autorizaciones se resuelven a partir del rol; no es necesario crear permisos nuevos para el acceso administrativo.
- `refund` y `cancel_payment` exigen además `isSuperadmin()` en el servidor, tanto en el controlador como en el servicio. Este método comprueba el indicador `is_superadmin`; el permiso de administración por sí solo no autoriza estas acciones.

| Plataforma y recursos | Consulta | Escritura administrativa implementada |
| --- | --- | --- |
| Stripe: pagos, intentos de pago, clientes, facturas, reembolsos, suscripciones, disputas, productos y precios | Listados, búsqueda por ID, actualización e importación | Crear y editar clientes; reembolsar pagos cobrados/capturados y cancelar intentos en estados permitidos, con las restricciones anteriores |
| Kalendia: clientes | Sí | Crear y actualizar; se conservan email/teléfono originales al actualizar para mantener la identidad |
| Kalendia: notas de clientes | Sí, consulta directa | Crear y editar notas |
| Kalendia: citas | Sí | Crear citas de servicios gratuitos con disponibilidad validada; los servicios con precio utilizan la contratación y el pago de consultas de la App |
| Kalendia: formularios y respuestas | Sí | Crear y editar respuestas; los esquemas se consultan |
| Kalendia: servicios, profesionales, recursos, pagos, pedidos, productos, categorías, inventario y cupones | Sí | Solo lectura mediante la API pública integrada |

La API pública de Kalendia condiciona el alcance: la pantalla no ofrece edición general de catálogos, configuración de pagos ni esquemas. Las citas existentes se pueden reagendar y cancelar en los modales del calendario y en su ficha administrativa. Las escrituras requieren también los permisos correspondientes en la credencial externa.

## Credenciales y destinos

Configurar mediante el mecanismo seguro del entorno, sin incluir valores en este documento:

| Uso | Variables | Endpoint público |
| --- | --- | --- |
| Stripe administrativo principal | `STRIPE_SECRET`, `STRIPE_ADMIN_WEBHOOK_SECRET` | `/api/webhooks/stripe/admin/main` |
| Stripe histórico España, solo lectura | Opcional `STRIPE_HISTORY_ES_SECRET` | Sin nuevo webhook; consulta e importación por GET |
| PayPal histórico España, solo lectura | Opcionales `PAYPAL_HISTORY_ES_CLIENT_ID`, `PAYPAL_HISTORY_ES_SECRET` | Sin webhook; OAuth para autenticar y GET de Transaction Search |
| Stripe de consultas existente | Conservar `STRIPE_KALENDIA_WEBHOOK_SECRET` | `/api/webhooks/kalendia/stripe` |
| Stripe de Banca Online existente | Conservar `STRIPE_BANCA_ONLINE_WEBHOOK_SECRET` y su configuración actual | Conservar el endpoint existente |
| Kalendia API y webhook nativo | Conservar `KALENDIA_TOKEN`, `KALENDIA_WEBHOOK_SECRET` y `KALENDIA_WEBHOOK_BUSINESS_ID` si está configurado | `/api/webhooks/kalendia` |

Todos los cobros, países y servicios utilizan exclusivamente `STRIPE_KEY` y `STRIPE_SECRET`, de la cuenta principal. Los secretos de firma pertenecen a sus respectivos endpoints; el administrativo no sustituye los secretos de consultas o Banca Online, que también deben recibir eventos de la cuenta principal. `KALENDIA_TOKEN` es la credencial de API, no el secreto de firma. Mantener también la configuración previa de consultas, incluido `KALENDIA_PREPAID_PAYMENT_METHOD`.

## Históricos de España

El código no cambia el `.env` de producción ni activa las cuentas históricas por sí solo. Sin sus credenciales dedicadas, las pantallas muestran que falta configurar la conexión; nunca recurren a las claves principales ni a variables regionales antiguas.

Los bloques comentados con `#` son inactivos. La antigua clave española etiquetada como `STRIPE_SECRET_PORT` no se reactiva por ese nombre: podría colisionar con una variable activa de otra cuenta. Cuando se decida conectar el histórico, copiar esa clave al nombre exclusivo `STRIPE_HISTORY_ES_SECRET`. No hace falta una clave pública para consultar el histórico desde el servidor. Las credenciales de PayPal comentadas se asignan, cuando corresponda, a sus dos nombres históricos exclusivos de la tabla anterior. Esta configuración queda pendiente hasta que el administrador la aplique; no debe descomentarse un bloque antiguo sobre la configuración activa.

Stripe histórico conserva la separación por proveedor, cuenta, modo e ID; admite búsqueda, detalles, actualización e importación, que solo recuperan información mediante GET. Crear o editar contactos, reembolsar, cancelar y reintentar operaciones se bloquea en controlador, servicio de operaciones y transporte, incluso para superadmins. Su histórico no genera compras ni comprobantes de la App y no se concilia como una operación de la cuenta principal.

PayPal histórico usa exclusivamente el host de producción, POST al endpoint OAuth para obtener el token de consulta y GET a `/v1/reporting/transactions`. No guarda respuestas crudas ni tokens, no permite operaciones de dinero y muestra una proyección limitada de datos. La búsqueda admite hasta tres años atrás y rangos inclusivos de hasta 31 días; el extremo del día actual se limita a la hora actual. Las fechas del filtro se interpretan en UTC y las horas se muestran en la zona elegida por el usuario. Las credenciales deben conservar el permiso Transaction Search de PayPal; su presencia no prueba que dicho permiso o la cuenta sigan vigentes. No se promete acceso a movimientos anteriores al límite de la API. [Documentación oficial de Transaction Search](https://developer.paypal.com/api/transaction-search/v1/search-get).

## Configurar webhooks

En la cuenta principal de Stripe y el modo que se vaya a usar, crear un destino HTTPS para `/api/webhooks/stripe/admin/main`. Seleccionar eventos de **la propia cuenta**, con objetos **snapshot**, no eventos de cuentas conectadas. El controlador ignora cuentas conectadas, eventos de otro modo y tipos no admitidos. Guardar el secreto de firma de ese destino en la variable administrativa correspondiente.

Copiar estos tipos exactos de `PlatformCatalog::STRIPE_EVENTS`:

```text
charge.succeeded
charge.failed
charge.refunded
charge.captured
charge.updated
payment_intent.succeeded
payment_intent.payment_failed
payment_intent.canceled
payment_intent.processing
payment_intent.requires_action
payment_intent.amount_capturable_updated
customer.created
customer.updated
customer.deleted
customer.subscription.created
customer.subscription.updated
customer.subscription.deleted
invoice.created
invoice.updated
invoice.paid
invoice.payment_failed
invoice.voided
invoice.deleted
refund.created
refund.updated
refund.failed
charge.dispute.created
charge.dispute.updated
charge.dispute.closed
product.created
product.updated
product.deleted
price.created
price.updated
price.deleted
```

En Kalendia, ampliar los eventos del **mismo** destino `/api/webhooks/kalendia` conservando **el mismo secreto**; no crear otro secreto ni reemplazar el endpoint de citas existente. Incluir `PlatformCatalog::KALENDIA_EVENTS`:

```text
appointment.created
appointment.confirmed
appointment.rescheduled
appointment.cancelled
appointment.payment_updated
customer.created
customer.updated
customer.note_created
customer.note_updated
form.submitted
form.submission_updated
form.schema_updated
payment.confirmed
payment.failed
payment.refunded
payment.updated
order.created
order.payment_updated
order.fulfillment_updated
product.updated
```

Las cuatro notificaciones nativas de creación, confirmación, reprogramación y cancelación de citas conservan su bandeja y procesador existentes. El resto alimenta la bandeja administrativa. Ambas aparecen en el monitor de Kalendia.

## Despliegue y primera importación

Con el código y las variables ya instalados, ejecutar desde la raíz de la aplicación, con el usuario y PHP habituales del despliegue:

```sh
php artisan migrate --force --path=database/migrations/2026_11_22_180000_create_platform_management_tables.php
php artisan route:clear
php artisan config:clear
```

No ejecutar `config:cache`. La migración crea `platform_records`, `platform_events`, `platform_operations` y `platform_sync_states`; debe aplicarse antes de utilizar las nuevas pantallas o entregar eventos administrativos.

El scheduler existente debe seguir ejecutando Laravel cada minuto. `app/Console/Kernel.php` ya agenda `platforms:process-events --limit=4` y `platforms:sync --limit=2` cada minuto, con bloqueo de solapamiento y ejecución en segundo plano. También conserva `kalendia:process-webhooks --limit=4` para las citas nativas, condicionado a sus credenciales.

1. Abrir cada plataforma y usar la comprobación de conexión. En Stripe consulta cuenta, saldo y endpoints; en Kalendia consulta conexión y permisos. Revisar cualquier rechazo antes de importar.
2. Abrir **Integraciones → Copias de plataformas** y solicitar **Importar conexiones configuradas**. También puede usarse **Importar todos los recursos** desde cada plataforma. Se incluyen los recursos disponibles de Stripe y Kalendia; PayPal histórico permanece como consulta API. La importación no se inicia por configurar una credencial; el scheduler consume las solicitudes por páginas. Véase [Copias de plataformas](copias-plataformas.md) para planificación, filtros y reanudación.
3. Comprobar avance, fecha de actualización y errores. Las interrupciones conservan cursores; tras resolver un error, volver a solicitar la importación o reiniciarla desde la pantalla cuando proceda.
4. Para comprobar el procesamiento manualmente, usar los comandos acotados siguientes; no crear bucles de importación:

```sh
php artisan platforms:process-events --limit=4
php artisan platforms:sync --limit=2
```

## Validación operativa

Verificar firma y procesamiento como pasos separados. Una prueba Stripe sin firma válida debe responder `400`; Kalendia rechaza firmas inválidas con `401`. Una entrega válida nueva responde normalmente `202` y una repetida `200`: la aceptación solo confirma recepción, no que el espejo haya sido actualizado. Revisar también el contenido de la respuesta, porque un evento puede ser ignorado.

Enviar o reenviar desde el proveedor un evento admitido correspondiente a un objeto real de la cuenta/modo y comprobar después en el monitor que pasa de pendiente a sincronizado. El procesador recupera el objeto por GET; una prueba con un ID ficticio puede validar la firma y fallar al consultar la API. Confirmar asimismo que una entrega duplicada no duplica el registro. Para citas nativas, revisar su bandeja y procesador específicos.

Los eventos reintentan con espera progresiva; tras ocho intentos fallidos, ante un cambio de modo o una cuenta retirada, pasan a revisión. Resolver la causa y solicitar el reintento desde el monitor. Ver una credencial o secreto como configurado no acredita ninguna entrega real; comprobar proveedor, respuesta HTTP, bandeja y resultado final.

## Integridad y operaciones pendientes

- Los registros históricos conservan su cuenta de origen; no se borran ni se reasignan IDs externos a la principal. Los trabajos de cuentas retiradas quedan detenidos sin llamar a Stripe con la credencial principal. Esta consolidación dirige los cobros nuevos a la principal; no transfiere objetos entre cuentas ni migra o cancela calendarios recurrentes ya creados.
- El espejo separa proveedor, cuenta, modo, recurso e ID remoto. Stripe distingue `test` y `live` según su clave; cambiar de modo no mezcla los registros anteriores.
- `platform_records.data` y `platform_operations.payload` se cifran con Laravel. Las columnas de búsqueda, como nombre y email, no llevan ese cast. Se proyectan campos permitidos; no se guardan tarjetas, `client_secret` ni secretos de firma. Los eventos almacenan referencias, no el cuerpo completo.
- Importar o procesar eventos administrativos no crea nuevas compras ni facturas de la App, no cobra tarjetas y no replica escrituras hacia el proveedor. Esto evita ciclos de sincronización.
- El checkout verifica el cliente guardado usando la cuenta principal. Solo un `404 resource_missing` permite crear su ficha allí; autenticación, límites y fallos de conexión conservan la referencia local. Los intentos nuevos guardan su identidad antes de confirmar el cobro y se recuperan al reintentar.
- Las operaciones usan UUID, claves de idempotencia y bloqueos. Si el resultado queda **sin confirmar**, reintentar la misma operación desde su historial; no abrir otra para el mismo efecto. Un reembolso recibido puede seguir pendiente hasta su confirmación externa.
- Una operación Stripe sin confirmar de más de **23 horas** bloquea el reenvío automático por el riesgo de caducidad de la clave idempotente. Revisar Stripe y actualizar el pago: la App consulta reembolsos por GET y reconcilia mediante `metadata.app_operation`, verificando pago, importe y moneda. Si no aparece una coincidencia, mantener la revisión manual antes de iniciar otra operación.

Referencias oficiales: [webhooks de Stripe](https://docs.stripe.com/webhooks), [creación de reembolsos](https://docs.stripe.com/api/refunds/create) y [API pública de Kalendia](https://developers.kalendia.co/). La validación en el entorno destino queda pendiente; esta guía no acredita disponibilidad en producción.

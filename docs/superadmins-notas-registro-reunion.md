# Superadmins, notas y reuniones de registro

## Instalación

Desplegar todos los archivos, incluidas las dos migraciones del 19/11/2026. En la carpeta Laravel de producción:

```bash
php artisan config:clear
php artisan migrate --force --path=database/migrations/2026_11_19_120000_add_superadmin_and_note_deletion_audit.php --path=database/migrations/2026_11_19_120100_create_registration_meeting_webhooks_table.php
```

La migración marca únicamente la cuenta interna existente `sistemasccs@sefarvzla.com`. No crea otra cuenta, no crea un rol y no concede esta marca a futuros registros. Si esa cuenta no existe o tiene el rol Cliente, no se concede la marca. Comprobación de solo lectura:

```bash
php artisan tinker --execute='dump(App\Models\User::where("is_superadmin",true)->get(["id","email","is_superadmin"])->toArray());'
```

Con esa cuenta: **Usuarios y permisos → Superadmins**, URL `/admin/superadmins`. Solo un superadmin puede conceder o retirar marcas de cuentas internas. Se registra el cambio en la auditoría de usuarios. No se puede retirar la marca del último superadmin ni borrar una cuenta que aún la conserve. Los administradores habituales mantienen el borrado de usuarios ordinarios.

En las notas del cliente aparece **Eliminar** únicamente para superadmins. El servidor vuelve a comprobar ese permiso y que la nota pertenezca al cliente de la URL. Se oculta la nota y se conservan `deleted_at`, `deleted_by` y el contenido para auditoría; no se expone una ruta de borrado por token n8n.

## Flujo de registro, pago y agenda (20/11/2026)

Flujo confirmado: **000 → pago en la App → formulario 001 → contrato → Mi reunión**. El formulario 000 ya no solicita fecha ni hora. Se aplica a:

- Nacionalidad portuguesa por cónyuge.
- Nacionalidad portuguesa para familiares.
- Nacionalidad española por cónyuge.
- Nacionalidad española para familiares.

Se admiten mayúsculas y tildes equivalentes del catálogo. Los demás servicios conservan su recorrido. Una cuenta existente debe iniciar sesión; el formulario público no reemplaza sus datos ni confirma pagos mediante el campo `pay`.

Al confirmar Stripe, PayPal o un cupón completo, se reutilizan la compra y la factura de registro. Se guarda un evento único por compra; no se crea otro cobro. El scheduler crea o actualiza primero el cliente en Kalendia, incluyendo el pasaporte en la propiedad real `cf_nopasaporte`. Después agrega una nota con el importe recibido, moneda, servicio, compra, factura y enlace al comprobante de la App. Esta parte funciona antes de que el cliente complete el 001; no reserva una cita.

Tras completar y verificar el 001, el cliente continúa con el contrato. **La agenda se habilita únicamente cuando la App registra el contrato completado (`contrato=1`), además del 001 completo (`pay=2`) y una compra propia pagada con factura de un medio confirmado.** La confirmación existente del contrato (`/checkContrato`) abre directamente `/mi-reunion` para las cuatro modalidades. El menú **Agendar reunión inicial** aparece solo después del contrato y antes de completar la primera reserva. Después, el cliente usa **Mis reuniones** y el inicio continúa al COS. La reunión inicial no se vuelve a ofrecer por haber sido cancelada; las consultas adicionales se gestionan por el catálogo de consultas. La pantalla redirige al contrato si falta; las peticiones de disponibilidad y reserva se rechazan antes de consultar Kalendia. Los campos enviados por el navegador no pueden sustituir el estado guardado. La confirmación del contrato tampoco permite saltarse el pago ni el 001. Una declaración pública de pago o una factura manual no habilitan la agenda. Las restricciones de cuentas bloqueadas continúan aplicándose.

La agenda usa los estilos de AdminLTE y consulta la API desde el servidor. El token nunca se entrega al navegador. Los horarios visibles se adaptan automáticamente a la zona IANA del navegador y permiten una selección manual. El servidor conserva los instantes en UTC y consulta Kalendia en su zona base verificada, `America/Bogota`. La conversión respeta el horario de verano de la fecha en España y Chile, además de Venezuela y Colombia. Se deshabilitan sábados, domingos y fechas pasadas, y se filtran las horas que ya pasaron durante el día actual. El servidor repite esas validaciones. Flatpickr 4.6.13 y su localización española se sirven como archivos locales con licencia MIT.

La App busca el servicio remoto por nombre normalizado exacto. Si falta o es ambiguo, no sustituye una modalidad por otra. Consulta `/availability` y vuelve a comprobar el horario antes de reservar. Solo acepta fecha, hora y profesional procedentes de esa respuesta; el cliente no puede cambiar el importe o la identidad enviados.

La respuesta real de disponibilidad incluye `staffId` numérico y el nombre del profesional. La App busca ese nombre exacto y único en `/staff` para obtener el identificador `stf_…` que admite la creación. No transforma ni adivina identificadores numéricos.

La creación realiza **cliente → cita**. Una reserva local única por compra conserva la solicitud cifrada, sus referencias y una clave estable para cada operación remota. Los reintentos por timeout usan exactamente el mismo contenido y clave, sin crear otra compra, factura ni reserva local. Una respuesta inicial `409 slot_unavailable` permite elegir otro horario. Cuando el resultado de un intento anterior es incierto, se conserva la operación original para evitar duplicados.

La confirmación muestra el estado y el enlace de Meet/Zoom. La respuesta real de creación omite ese enlace: después de guardar la reserva confirmada, la App consulta la lista incremental de citas y recupera únicamente la coincidencia exacta de cita, cliente y servicio. Si esa lectura falla, conserva la reserva confirmada sin repetir su creación; los detalles pueden llegar en la confirmación del proveedor. El contrato ya debe estar completado para llegar a esta pantalla. El cliente puede continuar con su expediente aunque la agenda todavía no esté disponible.

## Configuración de Kalendia

En el `.env` de Laravel de producción:

```dotenv
KALENDIA_TOKEN=TOKEN_DE_LA_CONEXION
```

Guardar solo el token, sin el prefijo `Bearer`. El valor facilitado está configurado únicamente en el `.env` local ignorado por Git; debe instalarse en producción sin pegarlo en logs o en este documento. No se necesita activar el procesamiento general de webhooks CRM.

La conexión requiere `business.read`, `services.read`, `staff.read`, `availability.read`, `customers.write`, `customers.notes.write`, `appointments.create` y `appointments.read`. El diagnóstico consulta también la conexión y su lista de permisos. El negocio y el host están fijados al tenant de Sefar.

**Reservas incluidas, sin un segundo cobro:** las cuatro modalidades de nacionalidad están configuradas con precio cero en Kalendia. Para un servicio cuyo precio remoto sea explícitamente cero, la App omite `payment_method`; este flujo se verificó con una cita real de prueba. El pago previo de la App sigue siendo obligatorio. La nota conserva el importe y comprobante de ese pago, aunque la cita incluida tenga total cero en Kalendia.

Para servicios con precio remoto distinto de cero o desconocido, la documentación no define un método para «pago externo ya recibido». La conexión ofrece `payments.read`, sin una operación documentada para registrar pagos externos. Una nota con comprobante deja constancia; no equivale a una factura o pago saldado en Kalendia.

Las reservas de compras confirmadas en App Sefar omiten `payment_method`, también cuando el servicio tiene precio en Kalendia. No hace falta configurar `KALENDIA_PREPAID_PAYMENT_METHOD`: el cobro lo confirma Stripe en la App y la referencia del comprobante queda en las notas de la cita. Los precios desconocidos o inválidos mantienen la creación protegida. No se inserta el iframe de venta. Los reintentos de una creación incierta conservan su cuerpo original, aunque cambie después el precio del catálogo.

Los cuatro servicios deben existir, estar activos y tener agenda y profesionales disponibles en Kalendia. El diagnóstico no los crea ni cambia su configuración:

```bash
php artisan config:clear
php artisan migrate --force --path=database/migrations/2026_11_20_180000_create_kalendia_bookings_table.php
php artisan kalendia:check
```

Aplicar también las migraciones del 19/11 indicadas al inicio si todavía están pendientes. No ejecutar `config:cache`. Si la migración nueva aún no está instalada, la pantalla muestra que la agenda se está preparando y conserva el acceso al expediente.

`kalendia:check` es de solo lectura: comprueba conexión, zona, permisos, correspondencia de los servicios y configuración del método de reserva. No consulta ni crea pagos de clientes. Un código de salida 1 indica que faltan requisitos, aunque la autenticación funcione.

## Entrega en segundo plano y n8n opcional

El scheduler existente ejecuta `registration:deliver-meeting-webhooks` cada minuto en lotes de cuatro. Debe funcionar el cron habitual `php artisan schedule:run`. No se instala otro cron ni se necesita un worker nuevo. El nombre del comando se conserva para mantener compatibilidad con el despliegue anterior; ahora entrega primero el cliente y la nota directamente a Kalendia:

```bash
php artisan registration:deliver-meeting-webhooks --limit=4
```

Este comando **sí escribe** clientes y notas de pagos confirmados pendientes. No crea citas. No ejecutarlo como una consulta de diagnóstico.

Cada paso confirmado conserva su identificador remoto. Si falla la nota después de crear el cliente, el siguiente intento reutiliza el cliente y la misma operación de nota. Hay espera creciente y un máximo de ocho intentos; después queda `failed` para revisión. La entrega está fuera de la petición de pago y no sigue redirecciones. Los logs no incluyen tokens ni cuerpos de respuesta del proveedor.

La notificación adicional a n8n está desactivada por defecto para evitar que dos integraciones creen la misma cita. Puede habilitarse con `REGISTRATION_CONTACT_NOTIFY_N8N=true` si el flujo receptor solo consume información y no vuelve a reservar. Su destino de producción se conserva:

`https://ia.sefaruniversal.com/webhook/33428247-fdd4-4794-9ec1-5020c63c49aa`

El evento contiene `event_id`, `evento=registro.pagado`, `formulario=000`, identidad y contacto del cliente, servicio, compra, factura, `monto_pagado`, `moneda`, `referencia_pago` y `pago_confirmado_en`. Ya no incluye campos de fecha de reunión. Los datos internos de sincronización no se envían a n8n. El receptor debe deduplicar por `event_id`, recibido también como `Idempotency-Key`, antes de hacer escrituras propias.

Las claves opcionales previas se conservan: `REGISTRATION_MEETING_WEBHOOK_ENABLED`, `REGISTRATION_MEETING_WEBHOOK_URL` y `REGISTRATION_MEETING_WEBHOOK_TEST_URL`. El primer interruptor controla la entrega completa. No desactivarlo si se desea la sincronización directa con Kalendia.

Estados agregados, sin datos personales:

```bash
php artisan tinker --execute='dump(App\Models\RegistrationMeetingWebhook::select("status")->selectRaw("COUNT(*) AS total")->groupBy("status")->get()->toArray());'
```

`registration:deliver-meeting-webhooks --test` sigue siendo una prueba ficticia dirigida exclusivamente al webhook de test de n8n. No crea un cliente ni una cita en Kalendia y requiere que n8n esté escuchando.

## Comprobaciones reales y límites

El 20/11/2026, por petición expresa, se creó el cliente ficticio **PRUEBA API APP SEFAR - NO ATENDER**: `POST /customers` respondió 201 y la consulta posterior del cliente respondió 200. El intento para **Consulta Jurídica**, Dr. Alex Goiticoa, **23/11/2026 a las 09:00 de Colombia**, respondió **409 `slot_unavailable`**. No se creó ninguna cita; la consulta posterior encontró cero citas para ese cliente. El cliente de prueba permanece disponible para inspección. No se creó ningún pago, compra o factura de la App durante esa prueba.

`GET /availability` devolvió `slots: []` y `reason: no_availability`. La lista de citas devolvió 60 registros y ninguno activo para el 23/11; no se encontró un solapamiento en el horario probado. Esto apunta a configuración de disponibilidad, pero la API no da un motivo específico. En esa primera comprobación no se pudo verificar una reserva positiva; el resultado posterior se detalla más abajo.

En la última comprobación ya aparecen las cuatro modalidades de nacionalidad con una correspondencia única. Se volvió a consultar «Nacionalidad española para familiares» para el 23/11/2026: HTTP 200, `slots: []`, `reason: no_availability`. La creación de los servicios está confirmada; falta que su disponibilidad ofrezca un horario.

Posteriormente, por petición del usuario, se consultó **19/03/2027** para Consulta Jurídica con el Dr. Alex. La API respondió 200, `reason: available`, con horarios de 08:00 a 16:00. Se intentó crear la cita ficticia a las 09:00 reutilizando el cliente de prueba. Kalendia respondió **422 `payment_method_unavailable`** con `payment_method: cash` y también al omitir ese campo opcional. Los dos rechazos tienen claves distintas porque corresponden a cuerpos distintos y resultados definitivos; no se repitió una creación incierta. La consulta posterior confirmó cero citas del cliente de prueba para esa fecha. La lectura de disponibilidad positiva quedó verificada. El bloqueo correspondía a ese servicio con precio en Kalendia. No se habilitó ni cambió ningún método de pago del negocio.

Después se comprobó que «Nacionalidad española para familiares» ya tenía precio cero y su reserva pública no incluía pago. Se creó una cita en ese servicio, reutilizando el mismo cliente de prueba y omitiendo `payment_method`: **HTTP 201**, identificador `apt_NjQ_80b3ec294782af90`, estado **confirmed**, **19/03/2027 de 09:00 a 10:00, America/Bogota**, Dr. Alex Goiticoa. La consulta posterior devolvió exactamente esa cita, el mismo cliente y un enlace virtual de Zoom; `/availability` dejó de ofrecer las 09:00. El total remoto es 0 EUR. No se creó otro cliente, compra o factura en la App ni se realizó un cobro. La cita permanece marcada como **PRUEBA / NO ATENDER**. Su enlace virtual queda en el registro de prueba local, sin publicarlo en este documento.

Validación local: pruebas aisladas de registros y pagos, alta de cliente y nota, disponibilidad, permisos, bloqueo de días y horas, reservas y reintentos sin duplicados, con respuestas simuladas y sin llamadas reales. La revisión visual usa vistas reales con datos ficticios. La batería completa del repositorio tiene bloqueos previos ajenos a este cambio: migraciones Onidex sin conexión en pruebas y la excepción de autenticación preexistente `POST api/webhooks/{provider}`.


La gestión administrativa, las consultas adicionales y su despliegue se documentan en [Consultas, citas y COS con Kalendia](kalendia-consultas-citas-cos.md).

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

## Registro y pago

`/registerv2?servicio=...` solicita fecha, hora y zona horaria para:

- Nacionalidad portuguesa por cónyuge.
- Nacionalidad portuguesa para familiares.
- Nacionalidad española por cónyuge.
- Nacionalidad española para familiares.

Admite las mayúsculas y tildes equivalentes del catálogo por nombre o código HubSpot. Los demás servicios conservan su formulario. La fecha debe ser futura y representa una **solicitud de reunión**, pendiente de confirmar disponibilidad. No se instala un iframe ni se consulta un calendario.

Los datos de reunión se guardan en la compra pendiente ya utilizada por `/pay`. El campo público `pay` no confirma el pago de estos cuatro servicios. Si la cuenta ya existe, el formulario público pide iniciar sesión y no reemplaza su información.

Al confirmar Stripe, PayPal o un cupón completo, se guarda un único evento por compra en `registration_meeting_webhooks`. Se reutilizan la compra y la factura existentes. No se generan otro cobro ni otra compra. Este cambio se aplica a registros nuevos con reunión guardada, sin reenviar automáticamente pagos históricos.

## n8n

Destino real: `https://ia.sefaruniversal.com/webhook/33428247-fdd4-4794-9ec1-5020c63c49aa`.

El flujo debe estar publicado/activo. El scheduler Laravel existente ejecuta el envío cada minuto, en lotes de cuatro. Tiene que estar funcionando el cron habitual `php artisan schedule:run`; este cambio no instala un cron nuevo ni necesita un worker adicional. Si es necesario ejecutar manualmente los envíos pendientes:

```bash
php artisan registration:deliver-meeting-webhooks --limit=4
```

JSON de ejemplo, exclusivamente ficticio:

```json
{
  "event_id": "UUID-ESTABLE-DEL-EVENTO",
  "evento": "registro.pagado",
  "formulario": "000",
  "cliente_id": 123,
  "compra_id": 456,
  "nombre": "Persona",
  "apellido": "Ficticia",
  "nombre_completo": "Persona Ficticia",
  "pasaporte": "PRUEBA-SIN-VALIDEZ",
  "telefono": "000000000",
  "correo_electronico": "prueba@example.invalid",
  "servicio": "Nacionalidad portuguesa por cónyuge",
  "servicio_id": 10,
  "reunion_fecha_hora": "2027-03-05T14:30:00-04:00",
  "reunion_zona_horaria": "America/Caracas",
  "reunion_utc": "2027-03-05T18:30:00+00:00",
  "pago_confirmado_en": "2027-03-01T15:00:00+00:00"
}
```

Los nombres de servicio conservan la grafía del catálogo. El mismo `event_id` se envía también en la cabecera `Idempotency-Key`.

**n8n debe guardar y comprobar `event_id` antes de crear el contacto en Kalendia.** Un timeout puede producir un reenvío aunque el receptor haya procesado el primer intento. Al encontrar una referencia ya procesada, debe responder 2xx sin repetir la creación. Conviene confirmar 2xx después de guardar el evento de forma duradera. El emisor no puede garantizar por sí solo una única creación en un sistema externo.

Los fallos de red o respuestas distintas de 2xx se reintentan con espera creciente; ocho intentos fallidos dejan el evento en `failed` para revisión, conservando el contenido y la referencia. La entrega HTTP se realiza fuera de la petición de pago. No se siguen redirecciones. No se registran pasaportes, teléfonos ni cuerpos de respuesta en la salida del comando.

Estados, sin mostrar datos personales:

```bash
php artisan tinker --execute='dump(App\Models\RegistrationMeetingWebhook::select("status")->selectRaw("COUNT(*) AS total")->groupBy("status")->get()->toArray());'
```

No hacen falta variables nuevas: los destinos facilitados y el envío habilitado ya tienen valores predeterminados. Si se desea sobrescribirlos, las claves opcionales son `REGISTRATION_MEETING_WEBHOOK_ENABLED`, `REGISTRATION_MEETING_WEBHOOK_URL` y `REGISTRATION_MEETING_WEBHOOK_TEST_URL`. Mantener separados los destinos de prueba y producción. No hace falta activar el procesamiento general de webhooks CRM.

## Prueba sin pagos reales

En n8n, abrir la escucha del webhook de test. Después:

```bash
php artisan registration:deliver-meeting-webhooks --test
```

Solo envía datos ficticios, añade `prueba: true` y no consulta ni modifica pagos. La prueba realizada el 19/11/2026 respondió **HTTP 404**, por lo que falta confirmar la recepción en n8n cuando esté disponible la escucha. No se enviaron datos de clientes a la URL de producción durante la comprobación.

Validación local: pruebas de permisos, bootstrap de la cuenta, notas, registros de los cuatro servicios, confirmación de pagos, reintentos, facturas CRM y accesos existentes. La revisión visual usa las vistas reales con datos ficticios y conserva los estilos actuales. La batería completa anterior tiene bloqueos ajenos a este cambio: migraciones Onidex sin conexión en pruebas y la excepción de autenticación preexistente `POST api/webhooks/{provider}`.

# COS: identidades cruzadas en HubSpot

## Causa comprobada

El `502 cos_refresh_failed` del cliente 7295 se producía al guardar los datos del contacto de HubSpot: el pasaporte recibido pertenecía a otra cuenta local. El log también mostraba una colisión de correo al actualizar el cliente 7317. Las restricciones únicas de la base de datos impidieron esas escrituras.

La consulta de producción confirmó dos personas distintas con el mismo `users.hs_id`. La consulta de HubSpot confirmó que ese ID antiguo había sido fusionado en otro contacto canónico, con datos de una de esas personas. También se encontró un contacto independiente que coincidía por nombre y pasaporte con la otra persona. El usuario confirmó posteriormente que los correos de la App son los correctos para ambas cuentas.

No se fusionaron ni borraron cuentas. Los valores personales y los enlaces firmados presentes en el log no se reproducen aquí.

## Corrección del código

- `HubspotClientIdentityGuard` comprueba propietarios de correo, pasaporte e IDs HubSpot, incluidos los alias fusionados. Excluye la cuenta que se está actualizando y no interpreta valores vacíos como coincidencias.
- La comprobación se ejecuta antes de leer archivos/negocios o aplicar restricciones desde HubSpot, también sobre caché y antes de vincular automáticamente un contacto existente.
- Un conflicto detectado al leer el contacto detiene el refresco antes de continuar con Teamleader y no modifica el perfil, los negocios ni el COS a partir de la identidad ambigua. Se repite la comprobación antes de guardar el perfil y se controla una colisión concurrente de correo/pasaporte.
- La API devuelve `409`, mantiene `error: cos_refresh_failed`, e incluye `reason: identity_conflict`, `retryable: false`, `conflicting_fields` y `action.type: review_client_identity`.
- Los errores incluyen una referencia UUID compartida por JSON, cabecera `X-Error-Reference` y log privado. Los IDs de otras cuentas no se publican en la respuesta.
- No se alteran índices únicos, asociaciones, credenciales, cálculos de pagos ni configuración de S3.

## Validación local

44 pruebas y 395 aserciones correctas:

```sh
php vendor/phpunit/phpunit/phpunit \
  tests/Feature/ClientCosIdentityConflictTest.php \
  tests/Unit/ClientCosRefreshDiagnosticsTest.php \
  tests/Feature/HubspotDealLocalSyncServiceTest.php \
  tests/Feature/CosMondayLocalDataTest.php \
  tests/Feature/ClientCosPageTest.php \
  tests/Feature/CachedIntegrationConfigurationTest.php
```

La revisión adicional de `tests/Unit/CosServiceTest.php` mostró dos discrepancias de textos esperados (`Informe cargado` / `Revisión del expediente` y `Certificado Descargado` / `Informe Cargado - En Revisión`). Se reproducen al ejecutar ese archivo solo; ni ese test ni `CosService` ni `CosPresentation` se modificaron para este arreglo. No se considera aprobada toda la batería.

Los ocho archivos PHP nuevos o modificados de este arreglo pasan el análisis de sintaxis y `git diff --check` no detecta errores de formato.

## Correcciones aplicadas en producción

Desde la edición básica administrativa de la App, sin abrir la ficha que inicia las sincronizaciones del COS:

- Cuenta 7295: `hs_id` corregido a `19862351`. Se restauraron sus campos de nombres y apellidos, que contenían los de la otra persona, usando la coincidencia entre nombre visible, pasaporte y contacto independiente de HubSpot.
- Cuenta 7317: `hs_id` corregido al contacto canónico `244299079456`.
- Se mantuvieron los correos de la App confirmados por el usuario. Los roles y permisos seleccionados se conservaron.
- Ambas operaciones mostraron `Usuario actualizado (modo básico)` y los IDs guardados. Capturas locales: `outputs/crm-webhook-setup/cos-roberto-link-corrected.png` y `outputs/crm-webhook-setup/cos-alejandro-link-corrected.png`.

La conexión de HubSpot confirmó lectura disponible y edición `REQUIRES_PERMISSION_MODIFICATION`. No se intentó eludir esa limitación mediante otra cuenta o credenciales. Se indicó al administrador la corrección exacta de ambos correos. Antes de asignar el correo de una persona a su contacto, debe liberarse del otro contacto también si figura como dirección adicional: [gestión de correos múltiples en HubSpot](https://knowledge.hubspot.com/records/add-multiple-email-addresses-to-a-contact).

La última lectura posterior confirmó que el correo principal de la cuenta 7317 ya coincide entre HubSpot y la App. El contacto correspondiente a 7295 todavía presentaba el correo incorrecto; su corrección y la disponibilidad de la dirección correcta como identificador primario/secundario quedan pendientes de completar.

El acceso SSH desde esta sesión fue rechazado. No se desplegaron los cambios locales de protección.

## Pendiente en producción

1. Desplegar la protección completa, incluidos los nuevos archivos. No requiere migraciones ni cambios de dependencias. Mantener la configuración sin cachear conforme a la incidencia de configuración del mismo día; no ejecutar `config:cache`.
2. Completar y verificar los correos en HubSpot según la confirmación del usuario. Revisar si existen vínculos canónicos adicionales al campo `users.hs_id` antes de modificar esas tablas; no se cambiaron desde la edición básica.
3. Revisar los negocios/documentos que pudieran haberse importado previamente con el vínculo compartido; no reasignarlos ni eliminarlos automáticamente.
4. Repetir las solicitudes de COS con el token existente y comprobar un `200` con `meta.refreshed: true` para cada identidad corregida. En n8n, los conflictos deben ir a revisión interna y no a reintentos automáticos ni entregas de información.

La respuesta controlada evita nuevas importaciones ambiguas, pero no repara por sí sola los registros históricos ni sustituye la verificación de identidad.

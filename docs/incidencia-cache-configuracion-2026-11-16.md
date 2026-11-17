# Incidencia de configuración — 16-11-2026

## Recuperación comprobada en producción

Después de recomendar `php artisan config:cache` durante la configuración de los receptores CRM, la aplicación devolvió HTTP 500. Se indicó retirar esa caché mediante `php artisan config:clear`. El usuario confirmó la ejecución y se comprobó que volvían a responder el panel CRM y la página de actualizaciones.

La versión de producción se mantiene sin caché de configuración. Los receptores HubSpot y Teamleader están habilitados; el procesamiento de eventos y las lecturas locales del COS continúan desactivados. No se modificó Amazon S3.

## Fallo reproducido y corrección preparada

`HubspotService` construía el SDK leyendo `env('HUBSPOT_KEY')` durante la petición, aunque `services.hubspot.key` ya existía en los archivos de configuración. Un proceso nuevo con configuración cacheada no carga `.env`; el SDK exige un token de tipo `string` y recibía `null`. Las dos primeras pruebas de regresión reprodujeron ese `TypeError` al construir el servicio y resolver `UserController`.

La corrección cambia las lecturas de entorno durante la ejecución por `config(...)` en servicios, controladores, comandos y plantillas. Los valores se definen en `config/services.php`, `config/mcp.php` y `config/app.php`, conservando nombres de variables, valores predeterminados y selección de cuentas de pago. No cambia importes ni reglas contables. La comprobación estática no encuentra llamadas `env()` restantes en `app/`, `resources/views/` ni `routes/`.

La corrección todavía necesita desplegarse. No se debe regenerar la caché de configuración de la versión anterior. La guía operativa utiliza ahora únicamente `php artisan config:clear` para aplicar cambios del entorno.

Referencia del framework: [Laravel 12 — Configuration caching](https://laravel.com/docs/12.x/configuration#configuration-caching).

## Validación

`CachedIntegrationConfigurationTest`: seis pruebas correctas y 17 aserciones. Comprueban SDK HubSpot, resolución del controlador, opciones de transporte, cuenta de pagos y su alternativa, pipeline de soporte y un proceso HTTP nuevo con archivo real de configuración cacheada, repositorio de entorno vacío y respuesta HTTP 200 en `/login`. El proceso usa credenciales sintéticas y archivos temporales privados que se eliminan al terminar.

Revisión ampliada: 139 pruebas correctas. Otros seis casos no pasan: cinco se detienen al intentar migrar la conexión externa Onidex, sin credenciales locales; uno de `BancaOnlineCatalogCmsTest` presenta una aserción de componentes del catálogo. Este último también falla al ejecutar ese archivo por separado, sin el nuevo test de configuración, y su servicio/modelo no fueron modificados por esta corrección. No se anuncian esos seis casos como aprobados.

Lint PHP correcto en los 25 archivos PHP cambiados, plantillas Blade compiladas y caché de vistas de prueba eliminada. Las pruebas no ejecutan operaciones financieras reales. Los registros están en `outputs/crm-webhook-setup/cache-configuration-tests.log`, `cache-regression-tests.log` y `cache-unrelated-catalog-check.log`.

## Suscripciones y entrega comprobada

- Teamleader: se registraron doce tipos desde el botón administrativo y se verificaron con una consulta posterior a su API. Falta comprobar una entrega originada en un cambio real de Teamleader.
- HubSpot: se activaron y guardaron las 49 suscripciones ya preparadas de Api-Call-Sefar: doce de contacto y 37 de negocio. La prueba nativa de `contact.propertyChange / firstname`, con su carga de ejemplo, obtuvo HTTP 202 de la App. El panel muestra el evento recibido con referencia `1`, estado `pending` e intentos `0`.
- El procesamiento permaneció pausado durante las comprobaciones; no se editaron perfiles de clientes para generar la prueba. La ampliación bidireccional, su arbitraje por campo y el control de ecos siguen pendientes y no se anuncian como terminados.

Capturas: `production-recovered-event-received.png`, `teamleader-registered-after-recovery.png`, `hubspot-active-after-recovery.png` y `hubspot-delivery-test.png`, dentro de `outputs/crm-webhook-setup/`.

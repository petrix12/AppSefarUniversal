# Protección de rutas web

## Organización

`routes/web.php` contiene un grupo explícito con `auth` y `RestrictClientRoutes` que engloba todas sus rutas. `RouteServiceProvider` conserva además la protección de carga. Una ruta nueva en ese grupo requiere sesión y queda bloqueada para Cliente salvo que se añada deliberadamente a la lista de accesos del portal. Los permisos `can:*` siguen definiendo qué puede hacer cada usuario autenticado.

`routes/api.php` también contiene un grupo explícito `auth:sanctum`. Las consultas de servicios y alertas, y los endpoints de chat antiguo, requieren ahora autenticación; el chat interno también rechaza clientes y tiene un límite específico.

Las excepciones están separadas:

- `routes/public.php`: registro, OAuth de registro, catálogo/checkout público de banca online, seguimiento y baja de correos y páginas de agradecimiento.
- `routes/cron.php`: tareas automáticas autenticadas con tokens de la plataforma central y permisos específicos. No dependen de una sesión de navegador.
- `routes/public-api.php`: comprobación de email del registro, con un límite de cinco peticiones por minuto por IP.
- `routes/integrations.php`: WhatsApp y n8n con tokens de la plataforma central; Stripe, Monday y SES con verificación de firma o token del proveedor.
- `routes/mcp.php` y las rutas de Fortify/Jetstream conservan sus controles específicos.

No usar `withoutMiddleware('auth')` en rutas internas para resolver un error de permisos. Revisar el rol y el permiso requerido; los accesos públicos deliberados se registran en `public.php`.

Las actualizaciones, cargas y previsualizaciones de Livewire requieren sesión. Los recursos JavaScript de Livewire siguen siendo públicos. La ruta de modo oscuro de AdminLTE se registra dentro del grupo autenticado de la aplicación.

## Tokens de la plataforma central

WhatsApp, n8n y todos los cron HTTP utilizan la plataforma existente **Integraciones → Tokens API** (`/admin/integrations/api-tokens`). Se reutilizan Sanctum, el almacenamiento del hash, la validación de caducidad, el último uso, la revocación y la auditoría de altas/bajas.

Un administrador debe crear un token por consumidor, asignarlo a un usuario interno y marcar únicamente sus permisos. El token completo se muestra una sola vez. Una sesión web no lo sustituye y los usuarios con rol Cliente no pueden usarlo, aunque dispongan del permiso.

| Método y ruta | Permiso del token |
| --- | --- |
| `GET /api/whatsapp-requests/pending` | `whatsapp:read` |
| `POST /api/whatsapp-requests/{id}/success` y `/fail` | `whatsapp:update` |
| `POST /api/n8n/tasks` | `tasks:create` |
| `GET /cron/scheduler-run` | `cron:scheduler:run` |
| `GET /cron/queue-worker` | `cron:queue:work` |
| `GET /cron/followups-registration-payment` | `cron:followups:run` |
| `GET /makereport` | `cron:reports:generate` |
| `GET /cron/teamleader/sync` | `cron:teamleader:sync` |
| `GET /cron/teamleader/work` | `cron:teamleader:work` |
| `GET /cron/tasks/work` | `cron:tasks:work` |
| `GET /cron/jotform/coupons` | `cron:jotform:sync` |
| `GET /internal/tasks/daily-workflow` | `tasks:workflow` |

Todas estas llamadas deben enviar, por HTTPS:

```http
Authorization: Bearer <TOKEN_CREADO_EN_LA_PLATAFORMA>
Accept: application/json
```

Una credencial ausente, inválida, revocada o vencida devuelve 401. Un token sin el permiso requerido, o de un usuario con rol Cliente, devuelve 403. Los permisos genéricos `read`, `create`, `update` y `mcp:read` no conceden por sí solos ninguno de estos permisos específicos.

La auditoría existente añade la fuente **Integraciones HTTP**: registra usuario, ID y nombre del token, operación, fecha, ruta y resultado. No almacena el token completo, los headers, parámetros de URL, cuerpos de mensajes ni respuestas. Los administradores pueden filtrar estos eventos en Integraciones → Auditoría.

### Migración de consumidores

Coordinar el cambio de consumidores con el despliegue: pausar temporalmente las llamadas HTTP del bot/n8n/cron, desplegar el catálogo de permisos y las rutas, crear los tokens desde la plataforma, actualizar sus headers y reanudar las llamadas. El código anterior de la pantalla no permite seleccionar los permisos nuevos. Si hay que preparar credenciales antes del despliegue, Sanctum permite emitirlas por CLI con las abilities de la tabla; después también aparecen en la plataforma. No hay fallback a secretos estáticos. Los valores anteriores de `MAINTENANCE_CRON_TOKEN`, `WHATSAPP_WORKER_TOKEN`, `N8N_WEBHOOK_TOKEN`, `TEAMLEADER_CRON_TOKEN`, `JOTFORM_CRON_TOKEN` y `TASKS_DAILY_WORKFLOW_TOKEN` ya no autentican estas rutas. Tampoco se aceptan `?token=...` ni el antiguo header `X-Webhook-Token` de n8n.

Para un flujo n8n que además refresque COS o cree notas, pueden seleccionarse `cos:refresh` y `client-notes:create` en ese mismo token. Revocar el token desde la plataforma impide las siguientes llamadas; no detiene trabajos que ya estuvieran ejecutándose.

### Webhooks de proveedores

Stripe conserva su secreto de firma `STRIPE_BANCA_ONLINE_WEBHOOK_SECRET` o el fallback `STRIPE_WEBHOOK_SECRET`: lo genera Stripe y se configura únicamente en el servidor. Sin secreto el webhook responde 503; con firma incorrecta responde 400.

Monday y SES mantienen sus verificaciones existentes. Estas credenciales de proveedor no se sustituyen por tokens de usuarios, porque los proveedores no usan nuestra plataforma para emitir sus llamadas.

## Mantenimiento manual y despliegue

Las siguientes URLs ahora aceptan **POST**, requieren sesión y permiso `administrador`, conservan la verificación CSRF y tienen un límite compartido de seis peticiones por minuto por usuario:

- `/storage-link`
- `/config-cache`
- `/cache-clear`
- `/route-clear`
- `/config-clear`
- `/view-clear`
- `/admin/process-queue`
- `/deploy`
- `/hubspot/sync-client-owners`

Los GET de mantenimiento devuelven 405 y no ejecutan comandos. `/key-generate` fue eliminado del router; la rotación de `APP_KEY` debe hacerse mediante CLI con un procedimiento específico.

Una llamada anterior a `GET /deploy` debe sustituirse por el flujo administrativo autenticado con POST y CSRF, o por un despliegue mediante CLI. Un token de cron no concede permiso para desplegar ni para limpiar cachés. No se añadió ninguna excepción CSRF.

Este cambio no ejecuta `config:cache` automáticamente. Antes de activarlo en producción deben corregirse las lecturas de `env()` que aún estén fuera de los archivos de configuración de las integraciones.

## Operación de cron

Se mantienen las URLs y los métodos GET de los cron. Usar el Bearer token y el permiso de la tabla anterior; una sesión de administrador no sustituye la credencial de integración.

Los cuatro cron generales comparten un límite de seis peticiones por minuto por usuario dueño de los tokens. Los demás conservan los límites de sus propias rutas. Un token de cron no concede acceso a `/deploy`, a limpieza de cachés o a funciones de administración web.

Preferir el scheduler y los workers ejecutados directamente en el servidor cuando estén disponibles. Si hay caché de rutas, regenerarla durante el despliegue para cargar estas restricciones. No hace falta añadir los tokens emitidos por la plataforma al `.env` de la app; deben guardarse en el almacén privado de credenciales del consumidor.

## Permisos de aplicación

### Restricción de rutas para Cliente

Se revisaron las 527 entradas registradas desde `web.php` contra los 19 roles configurados en este entorno. Cliente conserva 48 entradas del portal; las otras 479 devuelven 403 incluso si tiene permisos internos o un rol adicional. Los otros 18 roles conservan sus permisos actuales. La matriz detallada por ruta, método y rol está en [acceso-rutas-por-rol.md](acceso-rutas-por-rol.md).

La lista permitida incluye las pantallas del cliente y sus acciones auxiliares: pagos, datos propios, contrato, servicios, soporte, notificaciones, carga del árbol y documentos solicitados. Fortify/Jetstream conservan perfil y seguridad de la propia cuenta; `registerv2` sigue público. No se modificó el menú AdminLTE para implementar este bloqueo.

`RestrictClientRoutes` se ejecuta antes del binding de modelos y persiste en las actualizaciones de Livewire. Las cuatro operaciones de archivos compartidas con el portal utilizan `tree-files.view/create/edit`: el personal conserva los requisitos anteriores (`genealogista` y permiso de archivo), y Cliente se limita al árbol asignado. Los controladores comprueban pertenencia antes de modificar personas, cambiar tipos, subir archivos o firmar enlaces S3. Los CRUD internos de personas y archivos siguen bloqueados para Cliente.

### Controles específicos

Se añadieron controles explícitos para:

- contraseñas administrativas, auditoría, editor de procesos, configuración de IA, pruebas de correo y configuración de WhatsApp: `administrador`;
- funciones auxiliares de Stripe y Monday: el permiso del módulo correspondiente;
- cambios de fases y exoneraciones de negocios: `notCliente` y `crud.users.index`, al igual que la pantalla de edición del negocio;
- archivos auxiliares del árbol: `genealogista` y el permiso de lectura, creación o edición de archivos para el personal; las operaciones que usa el portal también admiten Cliente con comprobación de pertenencia al árbol;
- administración de solicitudes documentales: `notCliente` y `administrar.documentos`;
- comprobantes internos, exportación del árbol y aprobación de cupones: el permiso de su módulo.

Los clientes pueden cargar únicamente documentos solicitados a su propia cuenta. La propiedad se verifica antes de borrar el archivo anterior o escribir en S3. La acción «no tengo documento» ahora apunta al método existente y utiliza el binding del modelo para aplicar su control de propietario.

El control de rutas es una primera capa. Todavía debe revisarse la autorización por expediente de los controladores heredados, incluyendo las rutas que firman URLs de archivos, y la configuración de privacidad de S3. Tener sesión o permiso de un módulo no demuestra por sí solo acceso a todos sus registros.

Las acciones `/cuponaceptar/{id}`, `/cuponrechazar/{id}`, `/fixCouponHubspot` y `/fixPayDataHubspot` también pasan a POST con CSRF y permisos explícitos. No se encontraron consumidores en el código del repositorio; cualquier llamada externa anterior por GET debe actualizarse.

## Validación

```sh
php vendor/bin/phpunit tests/Feature/WebRouteSecurityTest.php
php vendor/bin/phpunit tests/Feature/RouteAuthenticationCoverageTest.php
php vendor/bin/phpunit tests/Feature/PlatformIntegrationTokensTest.php
php vendor/bin/phpunit tests/Feature/ClientRouteAccessTest.php
php artisan route:list --json
```

Las pruebas usan SQLite en memoria, permisos y servicios simulados, y almacenamiento S3 falso. No ejecutan migraciones generales, despliegues, comandos de mantenimiento reales ni llamadas a integraciones externas. Comprueban visitantes, usuarios sin permisos, administradores, CSRF, tokens ausentes/incorrectos/válidos, páginas públicas y propiedad de documentos.

Resultado del 2 de noviembre de 2026, incluyendo la restricción por rol Cliente: pasan 185 pruebas específicas con 1.752 aserciones; también pasan 54 comprobaciones con caché de rutas aislada (1.172 aserciones). La cobertura de autenticación revisa las 614 rutas registradas y exige autenticación o una excepción revisada. La cobertura de acceso de clientes ejecuta las 479 entradas internas bloqueadas incluso con permisos administrativos, verifica que los otros 18 roles pasan la nueva barrera sin restricciones añadidas, comprueba actualizaciones Livewire y acceso a personas y documentos propios. Las pruebas de plataforma usan tokens Sanctum reales y verifican permisos por operación, revocación, vencimiento, rechazo de clientes y sesiones web, creación/revocación desde la plataforma y auditoría sin secretos.

La comprobación adicional de dos pruebas del catálogo de banca online produce un fallo en `test_selected_components_override_seeded_features_and_fixed_prices`: espera omitir beneficios sembrados y el servicio aún los devuelve. El servicio del catálogo, su configuración y esa prueba no se modificaron. PHPUnit también informa una deprecación de la configuración XML existente.

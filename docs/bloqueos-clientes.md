# Bloqueos de clientes y suspensión de entregas

## Comportamiento de la App

El bloqueo es reversible y no borra el expediente ni modifica pagos, contratos o documentos. La fuente central es `user_access_blocks`. Las acciones y resoluciones quedan en `client_restriction_events`; la propagación externa se registra por plataforma en `client_restriction_syncs`.

- El cliente bloqueado conserva únicamente la página con el motivo público, el contacto de atención y el cierre de sesión.
- Solo `Administrador` y personal interno con `clients.blocks.review` pueden consultar expedientes bloqueados. Se mantienen además los permisos ordinarios de cada pantalla.
- Las descargas de documentos y árboles se rechazan también para revisores. Las exportaciones de clientes y cartera excluyen registros bloqueados. La exportación GEDCOM global se rechaza si hay expedientes bloqueados.
- Los registros relacionados de las réplicas de Monday y Teamleader se ocultan al personal sin permiso de revisión cuando existe una asociación local inequívoca.
- Los envíos de Laravel, notificaciones, campañas SES y salidas conocidas de BrightBean comprueban la restricción de nuevo en el momento del envío. Se cotejan correo principal, correo alternativo cuando existe y teléfono conocido para BrightBean.
- Los motivos internos de retiro se guardan por separado; el cliente recibe una explicación pública genérica. No se muestran notas jurídicas ni referencias internas en su pantalla de bloqueo.

## Enlaces de documentos dentro de la App

Los enlaces de expedientes, documentos solicitados, documentos de Teamleader, facturas y notas de crédito apuntan a rutas autenticadas de la App. Cada apertura vuelve a comprobar los permisos, la pertenencia del documento y el bloqueo. La App lee el archivo como un flujo y devuelve su contenido; no redirige al navegador a S3 ni genera URLs firmadas de S3. Las respuestas impiden el almacenamiento en caché y los archivos HTML/SVG se descargan para evitar ejecutar contenido en el dominio de la App.

Este cambio solo afecta a la entrega desde la App. **No se modifican objetos, políticas, ACL ni permisos de Amazon S3.** Los enlaces públicos de S3 distribuidos anteriormente conservan su comportamiento; este cambio no los revoca.

## Retiro desde HubSpot

Se verificaron las definiciones reales del portal:

| Objeto | Propiedad | Regla |
| --- | --- | --- |
| Negocio | `el_cliente_se_encuentra_retirado` | Booleano `true` en cualquiera de los negocios asociados |
| Negocio | `motivo_del_retiro` | Nota interna, no motivo público |
| Negocio | `soporte_del_retiro` | Referencia interna; no se entrega al cliente |
| Contacto | `retirado` | Texto afirmativo explícito, por ejemplo `true`, `1`, `sí` o `retirado` |

Un contacto puede tener `retirado` vacío y seguir retirado por sus negocios. La detección se aplica durante la sincronización de HubSpot y de negocios del COS. Una respuesta vacía, un error de conexión o un cambio a `false` no desbloquean automáticamente el expediente.

Para reconciliar los retiros históricos, habilitar `CLIENT_BLOCK_HUBSPOT_POLL_ENABLED=true`. El scheduler ejecuta `clients:reconcile-blocks --limit=30` cada minuto, con exclusión de ejecuciones simultáneas. Consulta una página de contactos y otra de negocios retirados por ejecución, conserva el cursor y repite el recorrido al terminar. Los usuarios se asocian por el ID de HubSpot existente; no se reasignan por similitud de nombres. Los contactos remotos sin usuario vinculado requieren revisión/vinculación y no se presentan como protegidos por la App.

La detección no es instantánea por sondeo. Para activar el bloqueo al cambiar la propiedad, el workflow/n8n debe invocar inmediatamente el endpoint autenticado descrito abajo. El endpoint vuelve a consultar HubSpot; no confía en un booleano enviado por el llamante.

## Pantalla y utilidad de pruebas

Menú: **Usuarios y permisos → Bloqueos de clientes** (`/admin/client-restrictions`).

Un administrador puede generar una vista previa de los clientes de la App con las palabras `prueba`, `pruebas`, `test` o `tests`, incluidos sufijos numéricos, en campos de contacto. Se comprueban atributos locales, campos adicionales y los datos de Teamleader ya importados/vinculados. Se excluyen credenciales, cachés de COS y cuentas con roles internos. La búsqueda remota de contactos sin cuenta en la App no forma parte de esta utilidad.

Por defecto se evita confundir un apellido como `Testa` con una prueba. La opción de coincidencias parciales amplía la búsqueda y puede incluir palabras reales: sus resultados deben revisarse. La vista previa muestra los campos coincidentes, pertenece al administrador que la generó, vence a los 30 minutos y solo se puede aplicar una vez. Antes de aplicar se vuelven a comprobar los registros; si un registro seleccionado cambió o no estaba en la vista previa, se rechaza todo el lote. Puede bloquearse una selección o todos los resultados revisados mediante confirmación explícita.

Para desbloquear, el administrador debe registrar una resolución de al menos diez caracteres. Si el origen fue HubSpot, la pantalla comprueba que el retiro ya no siga activo allí. El bloqueo manual preexistente mantiene su motivo; una detección posterior no lo reemplaza.

## Integraciones para impedir entregas

Usar tokens de usuarios internos con las capacidades mínimas de **Usuarios y permisos → Tokens de API**:

1. `POST /api/v1/clientes/reconcile-retirement`, capacidad `client-blocks:sync`, JSON `{"hubspot_id":"123"}`. Para workflows de retiro. El contacto debe tener una cuenta vinculada.
2. `POST /api/v1/clientes/delivery-permission`, capacidad `client-blocks:read`, JSON con `hubspot_id`, `email` o `user_id`. Si se envían varios identificadores deben coincidir todos. Responde `{"allowed":false,"reason":"..."}` para clientes bloqueados, identidad desconocida o ambigua. **Cada flujo de entrega externo debe detenerse si no recibe explícitamente `allowed: true`, incluidos timeouts y respuestas HTTP de error.**

No utilizar tokens de clientes ni enviar secretos en URLs. Los endpoints de lectura del COS no sustituyen esta comprobación de autorización de entrega.

## Marcas de las plataformas

Las marcas son un mecanismo de coordinación para los flujos que las respeten. **No imponen por sí mismas permisos nativos ni revocan enlaces públicos existentes.** Los estados del panel son `pending`, `configuration_required`, `failed` y `marker_synced`; este último confirma únicamente la escritura de la marca. Un fallo externo mantiene el bloqueo local y se reintenta; un resultado de una versión anterior no confirma un desbloqueo más reciente.

| Plataforma | Configuración propuesta | Cambio al bloquear |
| --- | --- | --- |
| HubSpot | `CLIENT_BLOCK_HUBSPOT_ENABLED=true`, `CLIENT_BLOCK_HUBSPOT_PROPERTY=sefar_entregas_bloqueadas` | Propiedad booleana del contacto a `true`; al liberar, `false`. Crear/verificar antes la propiedad y permisos de escritura. |
| Monday | `CLIENT_BLOCK_MONDAY_ENABLED=true`, `CLIENT_BLOCK_MONDAY_COLUMN=sefar_entregas_bloqueadas` | Etiqueta `Bloqueado`/`Activo` en el elemento principal y registros de servicio vinculados. La columna y etiquetas deben existir en cada tablero afectado. |
| Teamleader | `CLIENT_BLOCK_TEAMLEADER_ENABLED=true`, `CLIENT_BLOCK_TEAMLEADER_TAG=SEFAR_ENTREGAS_BLOQUEADAS` | Añadir/quitar exclusivamente esa etiqueta mediante `contacts.tag`/`contacts.untag`, conservando las demás. |

Las escrituras externas están desactivadas por defecto para evitar cambios con una configuración o permisos sin verificar. El conector de HubSpot de este chat reportó lectura disponible y escritura pendiente de modificación de permisos para contactos y negocios. No se usaron otras credenciales para eludir esa limitación ni se modificaron registros reales.

### Restricción nativa pendiente de configurar

Antes de considerar cumplido el bloqueo en las cuatro plataformas:

- **HubSpot:** definir el equipo autorizado, limitar acceso por propiedad/equipo y herramientas de exportación/comunicación. Restringir edición de una etapa no impide necesariamente su consulta ni operaciones de workflows/API. Revisar también las asociaciones, actividades y accesos de superadministradores. [Documentación oficial de acceso a registros](https://knowledge.hubspot.com/records/assign-access-to-records).
- **Monday:** usar un tablero privado de retención o permisos equivalentes verificados y limitar invitados, exportaciones, archivos y automatizaciones. La marca de estado no limita la lectura de un tablero principal. Revisar las referencias a elementos movidos antes de habilitar cambios automáticos de tablero. [Documentación oficial de permisos](https://support.monday.com/hc/en-us/articles/360019222479-Permissions-on-monday-com).
- **Teamleader:** configurar permisos y procedimientos de consulta/entrega del equipo autorizado y aplicar el control antes de enviar documentos. La etiqueta sirve para segmentar y señalar el bloqueo; no es una ACL. [Etiquetas y operaciones oficiales](https://github.com/teamleadercrm/api/blob/master/apiary.apib).

No se han cambiado propietarios, borrado contactos, movido elementos entre tableros ni alterado permisos generales de producción.

## Despliegue y validación

1. Desplegar el código y ejecutar `php artisan migrate --force` (no añade más columnas grandes a `users`). La migración incorpora los bloqueos anteriores a la cola de propagación.
2. Asignar `clients.blocks.review` solo al personal autorizado; los administradores ya pueden revisar.
3. Verificar las restricciones nativas y propiedades anteriores; configurar los flujos de entrega y la invocación inmediata desde HubSpot.
4. Activar el sondeo y las marcas externas únicamente después de comprobar permisos, vínculos y configuración. Ejecutar el comando de reconciliación y comprobar los estados por plataforma.
5. Probar con cuentas controladas: retiro de un negocio, acceso web/API del cliente, lectura de personal autorizado/no autorizado, documentos, correo en cola, exportación y desbloqueo con resolución. Repetir la entrega directamente desde cada CRM con una cuenta ordinaria.

La validación automatizada utiliza SQLite en memoria y APIs simuladas, sin modificar datos de producción. El informe de pruebas de este trabajo está en `outputs/client-restriction-check/` (ignorado por Git).

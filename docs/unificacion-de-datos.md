# Unificación de datos: App, HubSpot, Monday y Teamleader

## Decisión de base

La aplicación es la fuente canónica. Durante la transición, el cliente sigue siendo `users.id`: ya es la identidad que usan las compras, documentos, permisos y demás relaciones de la aplicación. No se crea un segundo cliente ni se eliminan columnas existentes.

No deben añadirse más campos de negocio a `users`. Los campos nuevos se administran mediante `custom_field_definitions` y `custom_field_values`.

## Entidades que no se mezclan

El mapa audita dos carriles independientes:

- **Contacto/cliente:** `users` ↔ HubSpot Contacts ↔ Teamleader Contacts.
- **Negocio/operación:** `negocios` ↔ HubSpot Deals ↔ Teamleader Projects.

Una coincidencia de nombre no habilita un cruce entre ambos carriles. Los campos de `negocios` y los atributos estructurales de `tl_projects` se muestran como catálogo comercial local, no como una migración ni una sincronización. Monday se mantiene sin inferencia automática hasta que cada tablero sea clasificado explícitamente como tablero de contactos, negocios u otra entidad.

## Modelo implementado

| Necesidad | Tablas | Regla |
| --- | --- | --- |
| Campo configurable | `custom_field_definitions`, `custom_field_values` | Un valor tipado por cliente y campo; conserva origen y fecha de origen. |
| Identidad externa | `external_entity_links` | Un contacto/item remoto solo puede enlazarse a un cliente de la app. |
| Correspondencia de campos | `integration_field_mappings` | Declara plataforma, entidad, campo externo, dirección, transformación y política de conflicto. |
| Sincronización confiable | `integration_sync_runs`, `integration_outbox` | Las escrituras externas quedan en cola y son reintentables; una pantalla no llama a una API externa directamente. |
| Monday multi-tablero | `workflow_boards`, `workflow_stages`, `workflow_memberships`, `workflow_transitions` | Un cliente puede pertenecer a varios tableros sin duplicarse. Todo cambio queda auditado. |
| Mapa de auditoría | `unification_audit_links`, `unification_audit_relations` | Registra propuestas y decisiones de diseño; permite asociar cualquier par de plataformas sin activar mapeos ni sincronizar datos. |

El servicio `UnifiedClientProfileService` es la vía de escritura para los campos y enlaces. `WorkflowTransferService` mueve un cliente entre tableros/etapas y encola la operación remota correspondiente.

## Estado detectado antes de la migración

- `users` contiene una ampliación grande de propiedades de HubSpot. Ese enfoque es el que está agotando la tabla y se sustituye para los campos nuevos.
- El catálogo histórico `assoc_tl_hs` contiene 103 correspondencias Teamleader–HubSpot, con 100 propiedades HubSpot distintas y tres propiedades repetidas con más de un campo Teamleader. El informe de la base local encontró solo 45 de esas propiedades todavía como columnas en `users`; las 55 restantes se crearán igualmente como campos configurables, sin volver a ampliar esa tabla.
- Teamleader ya se respalda localmente (`tl_contacts`, proyectos, facturas y documentos) y se trata inicialmente como fuente de consulta: sus mapeos se importan en dirección `pull`.
- Monday ya registra clientes por servicio. Al completarse correctamente un registro, ahora también se refleja como una membresía de flujo local.

## Política de sincronización

1. La app escribe el dato canónico.
2. Si existe un mapeo de salida, se crea/actualiza una entrada de `integration_outbox`.
3. Un adaptador por plataforma procesa esa cola, confirma la respuesta y registra el resultado. El adaptador de HubSpot/Monday es la siguiente fase de activación; no se habilita una escritura externa masiva de forma automática.
4. Al llegar información externa, solo se aplica si existe un mapeo explícito. El comportamiento por defecto ante una discrepancia es `manual`; no se pierde un dato local silenciosamente.
5. Durante la transición no se desactiva ninguna sincronización antigua. Se compara y reconcilia antes de retirar cada ruta previa.

## Auditoría obligatoria antes de cualquier migración

La primera herramienta operativa es el panel **Mapa de unificación** (`/admin/unification-map`). Es de solo lectura mientras no se despliegue su tabla de auditoría y, cuando se habilite, las propuestas que guarda continúan siendo solo diseño. No toca `users`, no llena `custom_field_values`, no crea `integration_field_mappings` ni programa una transferencia de Monday.

La secuencia obligatoria es:

1. Inventariar y revisar los campos y tableros en el mapa.
2. Registrar por campo la decisión: aprobar diseño, pedir información o desasociarlo (rechazado, conservando el historial de auditoría).
3. Aprobar un acta de migración con responsable, alcance, respaldo y plan de reversión.
4. Solo entonces autorizar una carga controlada.

No hay ningún comando ni pantalla que convierta una propuesta de auditoría en una sincronización activa automáticamente.

OpenRouter se usa únicamente dentro del constructor **Relacionar dos plataformas**. El paso 1 exige seleccionar dos plataformas y sus módulos: Contactos/Negocios en la App, Contacts/Deals en HubSpot, Contactos/Deals/Proyectos en Teamleader o un tablero de Monday. El paso 2 muestra solo los campos de esos módulos. El valor por defecto es `qwen/qwen3-32b`: aporta mejor razonamiento semántico que el modelo pequeño anterior, mantiene un coste bajo y está servido por varios proveedores. Si recibe un error o un límite `429`, OpenRouter intenta automáticamente `google/gemini-2.5-flash` como respaldo. Ambos se pueden cambiar con `OPENROUTER_UNIFICATION_MODEL` y `OPENROUTER_UNIFICATION_FALLBACK_MODELS`. Al pulsar **IA: revisar este par**, recibe solo los metadatos de los dos campos seleccionados, aunque no sean una coincidencia determinista local. El constructor también permite añadir muchas parejas a un lote: por defecto revisa hasta 200 (`OPENROUTER_UNIFICATION_MAX_BATCH_CANDIDATES`) y lo divide internamente en llamadas de hasta 40 (`OPENROUTER_UNIFICATION_MAX_CANDIDATES`) para no sobrecargar una única petición. Si el endpoint se usa sin campos concretos, revisa como máximo 40 coincidencias deterministas disponibles. Por compatibilidad con rutas económicas usa `response_format=json_object` por defecto y valida la respuesta en la aplicación; `OPENROUTER_UNIFICATION_RESPONSE_FORMAT=json_schema` queda disponible solo si el modelo/proveedor elegido admite el esquema estricto. No analiza el mapa completo ni recibe datos de clientes.

Las relaciones manuales se crean seleccionando ambos extremos: App, HubSpot, Teamleader o Monday, y después un campo de cada plataforma. **Actualizar catálogos** es la acción explícita que incorpora al selector los metadatos remotos: nombre, clave/ID de API, tipo y módulo. Para HubSpot se leen todas las propiedades de Contacts y Deals; para Teamleader, sus definiciones de campos personalizados junto a los campos estándar conocidos; para Monday, las columnas de todos los tableros. No se consulta ningún valor de contacto, negocio, proyecto o item. Monday se procesa en una cola por bloques de tableros y publica el progreso; esto evita que una cuenta con muchos tableros bloquee la pantalla. El selector conserva además las columnas existentes de `users`, los campos flexibles y los catálogos heredados. Los campos HubSpot inferidos desde una columna de `users` se marcan para confirmación hasta que el catálogo remoto los confirme. Tras una revisión IA, el administrador puede guardar varias recomendaciones como `proposed` o pulsar **Aprobar como diseño**: en ese caso quedan `approved` en `unification_audit_relations` y pueden servir de puente para sugerencias derivadas en el mapa. Ninguna de las dos acciones crea un mapeo activo ni una sincronización.

El mapa además genera coincidencias automáticas por clave/etiqueta exacta o muy similar, sin llamar a una API ni escribir datos. Puede mostrar una relación derivada `A ↔ C` si existen `A ↔ B` y `B ↔ C`; ambas clases de sugerencia se pueden convertir en una propuesta de auditoría con un clic, pero incluso entonces no son mapeos operativos. Requieren revisión y una futura promoción explícita por campo.

Incluso después de desplegar las tablas, las proyecciones automáticas de operaciones existentes hacia la capa canónica quedan apagadas por defecto. Solo se pueden habilitar después de la auditoría mediante `UNIFICATION_CANONICAL_WRITES_ENABLED=true`, con un despliegue aprobado. Mientras esté apagado, el registro actual de Monday y la resolución de contactos Teamleader continúan por sus rutas existentes, sin escribir en las tablas nuevas. Desasociar desde el mapa solo marca una propuesta de auditoría como rechazada; no borra ni altera las asociaciones heredadas.

## Operación segura tras la aprobación de auditoría

Primero se revisan los reportes, que no escriben datos:

```powershell
php artisan unified:backfill-external-links
php artisan unified:import-legacy-fields
```

Después de la aprobación documentada, se aplican las migraciones del despliegue. La primera carga crea únicamente definiciones y borradores inactivos; no activa sincronizaciones:

```powershell
php artisan migrate
php artisan unified:backfill-external-links --write
php artisan unified:import-legacy-fields --write
```

La copia de valores es una segunda operación, opcional y separada:

```powershell
php artisan unified:import-legacy-fields --write --copy-values
```

El último comando puede procesar muchos valores; se recomienda ejecutarlo en una ventana controlada y conservar una copia de seguridad de la base antes del despliegue. Los mapeos importados quedan inactivos y requieren una promoción futura, explícita y por campo. Ningún comando borra columnas ni cambia datos en HubSpot, Monday o Teamleader.

## Siguientes entregas

1. Flujo de promoción por campo, desde una decisión aprobada de auditoría hasta un mapeo activo, con doble confirmación y plan de reversión.
2. Adaptadores de salida de la cola para HubSpot y Monday, con reintentos, límites de API y trazabilidad por ejecución.
3. Vista única de cliente que combine perfil, relaciones externas, documentos/facturas Teamleader y membresías Monday.
4. Reconciliación por lotes y retirada gradual de los campos heredados de `users` solo después de validar paridad.

## Automatizaciones programables

La app incorpora un motor propio de automatizaciones, administrado en `/admin/automations`.

- Disparadores por evento: `client.field_changed` y `workflow.transitioned`.
- Disparadores por fecha: un campo de fecha y un desplazamiento en minutos (por ejemplo, `-43200` para 30 días antes).
- Disparadores recurrentes con expresión cron y zona horaria.
- Acciones iniciales: crear tarea, notificar en la app o correo, actualizar un campo configurable y mover al cliente entre flujos.

Cada acción genera una fila en `automation_runs` con una clave de idempotencia, estado, resultado y error. El scheduler ejecuta `automations:run` cada minuto; para que funcione en producción, el cron existente debe seguir invocando `php artisan schedule:run` al menos una vez por minuto.

Una acción que modifica un campo no vuelve a disparar automatizaciones de cambio de campo: esto evita bucles involuntarios. Las ediciones de una regla conservan las acciones históricas y sus ejecuciones para auditoría.

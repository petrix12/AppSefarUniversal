# Sincronización App ↔ HubSpot ↔ Teamleader

Requerimiento actualizado el 16-11-2026: sincronizar datos en ambas direcciones, priorizar la modificación real más reciente y evitar ciclos. Se conserva el alcance de contactos con negocios; los leads no se incorporan a la actualización de perfiles.

**Estado:** diseño de la ampliación. El receptor preparado actualmente importa CRM → App; no implementa todavía el arbitraje por campo ni la propagación automática entre los tres sistemas. No debe anunciarse como sincronización bidireccional terminada.

## Despliegue inicial

Se puede desplegar la base existente y ejecutar la migración manteniendo:

```dotenv
CRM_HUBSPOT_WEBHOOKS_ENABLED=false
CRM_TEAMLEADER_WEBHOOKS_ENABLED=false
CRM_EVENT_PROCESSING_ENABLED=false
CRM_LOCAL_READS_ENABLED=false
```

Estos interruptores corresponden a los nuevos receptores y al modo local. No desactivan las integraciones anteriores de la App. Registrar y probar los destinos se hace después del despliegue, siguiendo `crm-webhooks-y-cartera.md`, antes de activar propagación automática.

Se puede habilitar la recepción de cada proveedor manteniendo `CRM_EVENT_PROCESSING_ENABLED=false`: se valida y conserva el evento, sin modificar perfiles, certificados ni pagos. El trabajo permanece pendiente y no consume intentos. La carga inicial también exige habilitar expresamente el procesamiento.

## Alcance confirmado: entradas del COS

El alcance se limita a los campos que alimentan el cálculo del COS. El catálogo ejecutable está en `app/Services/CosCrmSyncFields.php`; los identificadores de proyectos se reutilizan de los analizadores actuales para evitar un segundo mapa financiero distinto.

- Contacto HubSpot: siete propiedades de identificación, servicio y retiro. Sus eventos no autorizan cambiar automáticamente la identidad o el acceso de una cuenta App.
- Negocio HubSpot: 33 propiedades existentes en producción, con servicio, código, certificado, fechas de informe/jurídico/resolución/recursos, tasas, preestablecidos y pagos de fases 1/2/3, Carta de Naturaleza y CIL/FCJE. Incluye estado del negocio y referencias para distinguir servicios auxiliares.
- Proyecto Teamleader clásico: trece campos personalizados usados por el COS: servicio, código, certificado y diez campos de importes preestablecidos/pagados. Los eventos `project.created`, `project.updated` y `project.deleted` releen el proyecto y comprueban que su contacto tenga negocios antes de importarlo. `nextgenProject` requiere un adaptador distinto y no se registra: la App utiliza `projects.info`.
- Monday conserva su espejo y sus entradas de estado genealógico. Solicitudes, documentos y recibos locales conservan su identidad. Progreso, saldos calculados, permisos y `pay`/`contrato` no se convierten en campos comerciales intercambiables.

Definiciones verificadas en el portal el 16-11-2026: no existen en los negocios HubSpot `titulo`, `fecha_solicitud_recurso_urgencia`, `fecha_solicitud_recursoalzada`, `fecha_solicitud_resolucionexpresa`, `fecha_solicitud_viajudicial`, `carta_nat_montopagado` ni `cilfcje_montopagado`. El cálculo tiene caminos locales para esos valores; no se crearon propiedades remotas ni se inventaron equivalencias. Tampoco existen propiedades de contacto `pay` y `contrato` con esos nombres.

**Configuración nativa guardada el 16-11-2026:** Api-Call-Sefar, portal 20053496, tiene 49 suscripciones HubSpot pausadas: doce de contacto (siete propiedades y cinco eventos de ciclo de vida/asociación) y 37 de negocio (33 propiedades y cuatro eventos de ciclo de vida/asociación). Destino `https://app.sefaruniversal.com/api/webhooks/hubspot`. Comprobado después de guardar, sin activar entregas ni editar expedientes. El registro nativo de Teamleader y las entregas reales siguen pendientes; el código nuevo ofrece un botón de administrador que registra y vuelve a consultar sus doce tipos para verificar el resultado.

## Decisión por campo

La App coordina el intercambio. Cada cambio conserva entidad, campo canónico, valor normalizado, origen, fecha original en UTC, versión y referencia de operación. Recibir o reenviar un cambio no lo convierte en una edición nueva.

Se comparan cambios del mismo campo. Un teléfono editado en Teamleader no sobrescribe un apellido más reciente en HubSpot. La comparación actual de `UserSyncService::calculateFieldUpdates()` utiliza `users.updated_at` y `lastmodifieddate` del contacto completo; debe reemplazarse en el flujo automático y revisarse cada llamada antigua que escriba en paralelo.

HubSpot permite pedir historial de propiedades. Debe utilizarse la fecha de modificación de la propiedad y conservarse la procedencia del evento. En Teamleader hay que verificar en la cuenta real la precisión disponible: `updated_at` del contacto no demuestra por sí solo cuándo cambió cada campo. Se necesitan instantáneas anteriores para identificar diferencias. Cuando no se pueda determinar cuál de dos valores es más reciente, registrar conflicto y conservar ambos para revisión; no inventar una fecha a partir de la llegada del webhook.

Un valor vacío explícito es una edición posible. Una propiedad ausente no es una orden de borrado. Fechas, monedas, catálogos y colecciones necesitan normalización reversible y equivalencias verificadas.

## Evitar ciclos

1. Normalizar y comparar con el valor actual antes de emitir una escritura. No escribir si ya coincide.
2. Mantener una bandeja de salida duradera: entidad, campo, versión original y destino. La combinación debe ser única, de modo que los reintentos no creen nuevas operaciones.
3. Registrar el valor y la versión confirmados por cada destino. El webhook que devuelve ese mismo valor normalizado se reconoce como confirmación, conserva su procedencia y no crea otra propagación.
4. Distinguir por campo: un webhook puede confirmar el teléfono enviado e incluir una edición humana nueva del apellido. No descartar el evento completo.
5. Antes de escribir, comprobar que la versión de salida siga siendo vigente y leer el estado actual del destino. Si contiene una edición más reciente, reconciliarla primero. Usar actualizaciones condicionales donde la API las permita; sin ellas existe una ventana de concurrencia que exige comprobación posterior.
6. Serializar los cambios de un mismo cliente; agrupar ráfagas, reintentar con espera creciente y registrar errores. Una transformación que no converja debe detenerse como conflicto, sin reenvíos ilimitados.

No basta con ignorar eventos durante unos segundos: se perderían ediciones humanas hechas durante ese intervalo. Tampoco basta con ignorar todos los cambios marcados como API, porque podrían proceder de otra integración legítima. La deduplicación de la bandeja de entrada ya preparada es necesaria, pero no sustituye el control de versiones y confirmaciones de salida.

Ejemplo esperado: se cambia el teléfono en la App a las 10:00; se envía ese valor a HubSpot y Teamleader. Sus respuestas confirman el cambio sin reenviarlo. Si una persona lo modifica en Teamleader a las 10:01, esa edición se compara como una versión nueva y se distribuye a los demás destinos.

## Cobertura y reglas existentes

Reutilizar el inventario de `/admin/unification-map` para relacionar campos equivalentes de contactos, negocios y proyectos, incluidos campos personalizados. Clasificar campos de solo lectura o calculados y datos sin equivalente; no copiar nombres de columnas entre plataformas sin verificar tipos y significado.

La sincronización de información comercial no convierte propiedades del CRM en permisos o credenciales de la App. Los bloqueos y desbloqueos conservan los controles de autorización ya implementados. Pagos, facturas, contratos y documentos mantienen su identidad y sus reglas de conciliación: sincronizarlos no significa generar operaciones contables duplicadas. La instrucción de no modificar S3 se conserva.

Auditar también automatizaciones externas y escritores antiguos para que no compitan con el coordinador. Primero se carga una base de comparación, luego se verifican cambios en ambas direcciones por cliente y finalmente se amplía la cobertura. No se activa la escritura canónica solo por desplegar esta documentación.

## Verificación antes de activar

- Ediciones desde cada una de las tres plataformas llegan a las otras dos una sola vez.
- Dos campos distintos editados en paralelo se conservan; el mismo campo se decide por modificación real más reciente.
- Ecos, duplicados y eventos atrasados no reactivan versiones antiguas.
- Una edición humana durante una confirmación o un reintento no se pierde.
- Borrado explícito, propiedad ausente, formatos equivalentes y errores parciales tienen resultados diferentes y auditables.
- Sin negocios: el perfil no se propaga. Un error de consulta no se interpreta como pérdida de todos los negocios.
- Retirados, entregas suspendidas y permisos internos conservan sus restricciones.

Fuentes: [HubSpot Contacts y propertiesWithHistory](https://developers.hubspot.com/docs/api-reference/legacy/crm/objects/contacts/guide), [entrega y duplicados de webhooks HubSpot](https://developers.hubspot.com/docs/api-reference/legacy/webhooks/guide), [Teamleader contacts.info](https://developer.focus.teamleader.eu/docs/api/contacts-info), [Teamleader contacts.update](https://developer.focus.teamleader.eu/docs/api/contacts-update).

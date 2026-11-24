# Misión «BYE HubSpot»: hoja de ruta CRM

**Fecha de auditoría:** 24/11/2026  
**Alcance:** revisión estática de la aplicación Laravel, sin consultar clientes, secretos ni producción.

## Objetivo

Convertir App Sefar en el sistema CRM principal de Sefar Universal y dejar HubSpot como integración opcional o histórico de transición. La App debe ser la fuente canónica de los datos propios, mientras que cada plataforma externa conserva su identidad y sus límites operativos.

La migración es una copia bidireccional de los objetos y relaciones de CRM, no solo una importación de contactos. No se debe retirar HubSpot hasta que la reconciliación confirme paridad y las escrituras en ambas direcciones estén operativas.

## Estado actual comprobado

| Área | Situación actual | Evidencia principal | Evaluación |
| --- | --- | --- | --- |
| Clientes y expedientes | Usuarios, COS, documentos, familias, bloqueos y notas | `app/Models/User.php`, `app/Models/Cos.php`, `routes/web.php`, `docs/bloqueos-clientes.md` | Operativa |
| Facturación y cobros | Facturas, líneas, compras, comprobantes, Stripe principal e históricos de España | `app/Models/Invoice.php`, `app/Models/Factura.php`, `app/Services/StripeAdminApi.php`, `docs/stripe-kalendia-administracion.md` | Operativa, con conectores externos que deben verificarse en producción |
| Negocios y cartera | `negocios`, sincronización de negocios HubSpot y proyectos Teamleader, conciliación de pagos | `app/Models/Negocio.php`, `app/Models/TlProject.php`, `app/Services/CosPaymentReconciliationService.php` | Operativa, todavía dependiente de espejos externos |
| Tareas y pipeline operativo | Tareas, tableros Kanban, etapas, transferencias y automatizaciones | `app/Models/Task.php`, `app/Models/WorkflowStage.php`, `app/Services/AutomationEngine.php`, `routes/web.php` | Operativa |
| Citas y consultas | Kalendia, citas, consultas, pagos y cola de reservas | `app/Models/KalendiaAppointment.php`, `app/Models/KalendiaBookingRequest.php`, `app/Console/Kernel.php` | Operativa en código; falta validar despliegue y entregas reales |
| Copias de plataformas | Réplicas locales de Stripe/Kalendia, Monday y datos de Teamleader | `app/Models/PlatformRecord.php`, `app/Services/MondayMirrorService.php`, `docs/copias-plataformas.md` | Parcial; no sustituye aún todas las escrituras externas |
| Marketing por correo | Listas, plantillas, campañas SES, seguimiento y bajas | `app/Models/MarketingCampaign.php`, `app/Services/MarketingSesSender.php`, `routes/web.php` | Operativa, separada del CRM comercial |
| Automatizaciones | Reglas por cambio de campo, transición y fecha; ejecución cada minuto | `app/Services/AutomationEngine.php`, `app/Console/Kernel.php`, `resources/views/automations` | Operativa, falta conectar formularios y más canales |
| Mensajería | BrightBean para conversaciones y outbox; solicitudes WhatsApp heredadas | `app/Models/BrightbeanMessage.php`, `app/Models/BrightbeanOutboundMessage.php`, `docs/brightbean.md` | Parcial; falta multi-cuenta y una bandeja de canales unificada |
| Integraciones CRM | Webhooks, espejos y sincronización HubSpot/Teamleader/Monday | `config/crm_sync.php`, `app/Jobs/ProcessCrmWebhookEvent.php`, `docs/sincronizacion-crm-bidireccional.md` | Parcial; nuevo espejo genérico HubSpot por webhook. Aún falta proyección funcional y salida bidireccional completa |
| Navegación CRM | Menú por áreas, pero sin un espacio único de CRM con contactos, leads, negocios y actividades | `config/adminlte.php` | Hueco de producto |

### Primer bloque iniciado

- Se añadió la entidad `Lead` y su migración en `app/Models/Lead.php` y `database/migrations/2026_11_23_090000_create_leads_table.php`.
- Se añadió el panel administrativo **Leads CRM** (`/admin/crm/leads`) para registrar, filtrar y asignar leads a usuarios internos.
- La conversión automática se ejecuta al registrar un cliente desde los flujos actuales (`CreateNewUser` y `RegisterV2Controller`). Conserva el historial del lead con `estado=convertido`, `converted_user_id` y `converted_at`; deja de aparecer como lead activo.
- El listado administrativo de usuarios ahora separa visualmente **Contactos/clientes**, **Usuarios internos** y **Todos**. La separación usa los scopes `User::clients()` y `User::internalUsers()` sin alterar todavía las relaciones históricas del COS.
- Se añadieron etapas controladas para leads, actividades CRM con seguimiento y presupuestos con conceptos, moneda, impuestos y estado. Los presupuestos se guardan en el CRM local y no emiten facturas ni cobros.
- Se habilitó la creación administrativa de cuentas internas con selección de roles no Cliente/Administrador, contraseña aleatoria y enlace de recuperación para que cada persona defina su contraseña.
- La copia entrante de webhooks HubSpot ya puede guardar propiedades completas de contactos, leads, negocios, facturas, presupuestos, productos, empresas, tickets, actividades y tipos de objeto personalizados en `crm_object_mirrors`; las relaciones recibidas se guardan en `crm_object_associations`.
- El backfill de contactos usa la misma clasificación acordada: un contacto que puede asociarse sin ambigüedad a un `Cliente` con negocio local (o trae negocios que se copian a `negocios`) actualiza ese usuario; los demás se guardan como `Lead`. Los negocios sin cliente local quedan en `negocios` con `user_id` nulo para revisión/asociación posterior.
- Antes de aplicar cambios de contacto o negocio existentes, se compara `updated_at` local con `updatedAt` de HubSpot; cuando el dato local es más nuevo, se preserva. Es una protección temporal de registro completo, no arbitraje de última modificación por campo.
- Los espejos guardan eliminaciones como archivo lógico y conservan las propiedades anteriores. La recepción sigue dependiendo de la firma, el portal configurado, `CRM_EVENT_PROCESSING_ENABLED` y de que HubSpot envíe las suscripciones de cada objeto.
- Esta copia técnica no convierte por sí sola cada registro en una ficha operativa ni empuja cambios locales a HubSpot. Es el primer bloque de la sincronización general; no habilita la salida de HubSpot.
- Para backfill por tipo, `crm:mirror-hubspot-objects --type=contacts` solo cuenta en modo lectura; `--write` persiste esa página tras página en el espejo local. `--archived` lee los archivados. El backfill no modifica HubSpot y requiere la migración nueva aplicada antes de usar `--write`.
- La importación histórica tiene ahora una modalidad en cola y reanudable. Cada job lee 25 registros, guarda su cursor y programa la página siguiente con una pausa. Un `429` respeta `Retry-After`; otros errores quedan con clase de excepción para reintentar sin guardar la respuesta o datos personales.
- El limitador compartido espacia las solicitudes de importación/sincronización que pasan por el cliente CRM (predeterminado: máximo 4 por segundo) y aplica un techo propio de 10.000 solicitudes por día UTC (`CRM_HUBSPOT_DAILY_REQUEST_BUDGET`). Al alcanzar el techo, pausa la cola hasta el siguiente día UTC. El techo es por esta App, no lee el consumo de otras integraciones del portal; se debe ajustar con el panel de uso de HubSpot. Requiere una caché con locks compartidos entre los workers.
- Cambios locales de clientes, leads y negocios pueden ir al outbox idempotente y al worker `crm-sync`. La salida queda apagada por defecto (`CRM_HUBSPOT_OUTBOUND_ENABLED=false`). Actualiza contactos por el ID existente o correo exacto; la creación de negocios requiere una propiedad única `sefar_app_negocio_id` en HubSpot para que los reintentos no creen duplicados. No empuja facturas, pagos, presupuestos ni actividades todavía.
- Comandos para ejecutar después del despliegue de código: `php artisan migrate --force`; `php artisan crm:mirror-hubspot-objects --all --queue --write --include-archived`; `php artisan crm:mirror-hubspot-objects --status`. La cola usa la conexión `crm` y el scheduler despierta el worker cuando hay trabajos en `crm-sync`. `completed` confirma snapshots de objetos, no paridad completa de todas las asociaciones.
- Webhook receptor: `https://app.sefaruniversal.com/api/webhooks/hubspot`. La ruta verifica firma, portal e idempotencia y encola los eventos que HubSpot tenga suscritos. Falta configurar y comprobar en la App de HubSpot las suscripciones de cada tipo de objeto y asociación; el endpoint por sí solo no crea esas suscripciones.
- El backfill de contactos consulta negocios asociados para proyectarlos a `negocios` y guardar la relación contacto↔negocio. Las demás asociaciones no se backfillean todavía de forma completa. También quedan pendientes la proyección operativa de facturas, presupuestos, empresas y objetos personalizados, la salida de esos tipos desde la App y la reconciliación de paridad; el espejo JSON sirve como copia de transición, no basta para retirar HubSpot.

Esta es una separación lógica y reversible. La extracción física de contactos fuera de `users` se hará después de migrar compras, documentos, restricciones, notas, tareas, autenticación e integraciones.

## Capacidades solicitadas y brechas

### 1. Leads separados de clientes

La primera entidad `Lead` ya existe con datos de contacto, origen, etapa, responsable y trazabilidad de conversión. El panel permite alta, edición, búsqueda y filtrado por etapa/estado. La conversión desde los registros de clientes está conectada a los flujos de registro actuales.

**Pendiente:** captación desde formularios/canales, consentimiento, próxima acción automatizada y conversión manual desde una ficha CRM. La separación física de contactos se mantiene como fase futura.

### 2. Presupuestos

Ya existe una primera tabla de presupuestos CRM con conceptos, cantidad, precio unitario, moneda, impuestos, vigencia y estado. El total se calcula en el servidor y no crea factura ni cargo automáticamente.

**Pendiente:** versiones, descuentos, aprobaciones, PDF/envío al cliente y conversión controlada a factura o negocio. El presupuesto no debe alterar el COS hasta que exista una contratación confirmada.

### 3. Actividades comerciales

Se agregó un registro CRM de actividades para llamadas, correos, WhatsApp, reuniones, notas y otros contactos, asociado a un lead o a un cliente, con fecha y próximo seguimiento. El módulo existente de tareas continúa separado para acciones pendientes.

**Pendiente:** integrar automáticamente citas, correos, llamadas y mensajería a una sola línea de tiempo; asociar negocios y reforzar el historial de cambios.

### 4. Llamadas 3CX

No se encontró conector 3CX en el código auditado.

**Pendiente:** definir si 3CX ofrece API/webhook, qué cuenta y qué identificador se usará para asociar una llamada, y qué campos se deben guardar. La primera versión debe ser consulta y registro de actividad, no control de llamadas.

### 5. Correo de Google Workspace

La configuración de Google existente cubre autenticación de registro, no un conector Gmail/Google Workspace para enviar desde el buzón del usuario.

**Pendiente:** OAuth por usuario o cuenta delegada, scopes mínimos, envío mediante Gmail API, hilos, adjuntos, límites, auditoría y revocación. No se debe reutilizar `MAIL_*` ni SES para este flujo.

### 6. WhatsApp, Telegram e Instagram

BrightBean ya proporciona inbox, entrada, respuestas y outbox. La App también conserva rutas de solicitudes WhatsApp. No se encontró integración equivalente completa para Telegram o Instagram, y BrightBean no equivale automáticamente a la API oficial de Meta.

**Propuesta:** una tabla de cuentas/canales con proveedor, identificador, estado, credencial cifrada, webhook, consentimiento y responsable. La bandeja debe mostrar el canal y la cuenta, evitando mezclar conversaciones de tres números de WhatsApp.

### 7. Formularios y automatizaciones

Hay formularios de registro, componentes heredados de Monday/HubSpot y un motor propio de automatizaciones. Aún falta un constructor general de formularios que pueda disparar automatizaciones comerciales.

**Propuesta:** formulario → envío versionado → validación → evento interno → automatización. Cada envío debe conservar versión, consentimiento, origen y relación con lead/cliente. Las acciones deben usar cola e idempotencia para no crear duplicados ni bucles.

## Arquitectura de datos propuesta

La App debe conservar una separación estricta:

- `leads`: personas o empresas aún no convertidas.
- `users`: clientes con cuenta, contratación o expediente operativo.
- `negocios`: oportunidades/servicios contratados, con etapas y valores.
- `crm_activities`: registro inicial de llamadas, correos, mensajes, reuniones y notas.
- `crm_quotes` y `crm_quote_items`: primera versión de presupuestos con conceptos.
- `crm_object_mirrors` y `crm_object_associations`: copia íntegra e independiente del modelo operativo de cada objeto y relación de los proveedores.
- `channel_accounts` y `conversations`: canales de comunicación y sus hilos.
- `integration_outbox`, `external_entity_links` e historial de sincronización: coordinación con proveedores externos.

No se deben seguir agregando columnas de negocio a `users`; el diseño existente ya prevé campos configurables en `custom_field_definitions` y `custom_field_values`.

La prioridad de sincronización debe ser por campo y por versión, no por la hora de llegada del webhook. Los eventos que no puedan resolverse deben quedar en revisión, nunca sobrescribir silenciosamente un dato local.

## Fases de ejecución

### Fase 0 — Preparación y control

1. Aprobar este mapa de alcance y congelar la eliminación de HubSpot.
2. Crear un catálogo de campos, relaciones y responsables.
3. Revisar permisos, retención, consentimiento y auditoría.
4. Añadir un espacio **CRM** en AdminLTE sin romper los menús actuales.

### Fase 1 — Núcleo CRM propio

1. Leads, etapas, responsables, etiquetas y conversión a cliente.
2. Negocios/oportunidades con pipeline configurable.
3. Actividades y línea de tiempo unificada.
4. Presupuestos versionados y conversión a factura.
5. Búsqueda global y ficha CRM que enlace COS, facturación, tareas y comunicaciones.

### Fase 2 — Comunicaciones

1. Gmail API de Google Workspace, por usuario y con OAuth revocable.
2. Registro de llamadas 3CX.
3. Multi-cuenta BrightBean/WhatsApp.
4. Telegram e Instagram, solo después de confirmar API y consentimiento.

### Fase 3 — Formularios y automatizaciones

1. Constructor y versiones de formularios.
2. Eventos `form.submitted` y `form.updated`.
3. Automatizaciones con acciones en cola, reintentos e idempotencia.
4. Plantillas de flujo para captación, calificación, seguimiento y conversión.

### Fase 4 — Salida gradual de HubSpot

1. Suscribir y reconciliar cada objeto: contactos, leads (clasificados dentro de contactos), negocios, presupuestos, facturas, líneas, empresas, actividades, pagos y tipos personalizados habilitados.
2. Asociar esos espejos con `users`, `leads`, `negocios`, facturas, presupuestos y actividades locales sin duplicar clientes ni cobros.
3. Extender el outbox idempotente a facturas, presupuestos, actividades y asociaciones. Los pagos no se deben crear de nuevo; solo sincronizarse como registros contables ya existentes.
4. Ejecutar una reconciliación paginada y repetible, mostrar diferencias y dejar conflictos ambiguos en revisión.
5. Medir paridad por objeto, campos y asociaciones; observar ambos sentidos antes de operar en modo autónomo.
6. Retirar suscripciones y credenciales únicamente después de confirmar paridad y exportación.

## Criterios de terminado de la misión

- Un lead puede entrar por formulario o canal, asignarse, avanzar por etapas y convertirse en cliente sin duplicar `users`.
- Un negocio puede tener actividades, citas, tareas, presupuesto y factura en una sola ficha.
- Un usuario autorizado puede enviar correo desde Google Workspace y ver el hilo en la actividad.
- Las tres cuentas de WhatsApp, Telegram e Instagram se distinguen por cuenta y canal.
- Un formulario puede activar una automatización reproducible e idempotente.
- HubSpot deja de ser dependencia obligatoria para consultar y operar el CRM.
- Cada escritura externa tiene cola, reintentos, trazabilidad y mecanismo de revisión.
- Las pruebas cubren permisos, bloqueo de clientes, duplicados, consentimiento, webhooks y fallos de proveedores.

## Próximo bloque recomendado

Implementar el outbox local por objeto y el comando de reconciliación HubSpot. Empezar con contactos/leads y negocios usando los IDs ya existentes; continuar con facturas y presupuestos con protección de duplicados e importes; después sumar asociaciones y actividades. Los campos de acceso, pagos y COS requieren reglas propias antes de permitir que un valor externo los sobrescriba.

No se han creado migraciones ni se han activado integraciones nuevas en esta auditoría.

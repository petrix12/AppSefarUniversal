# Misión «BYE HubSpot»: hoja de ruta CRM

**Fecha de auditoría:** 24/11/2026  
**Alcance:** revisión estática de la aplicación Laravel, sin consultar clientes, secretos ni producción.

## Objetivo

Convertir App Sefar en el sistema CRM principal de Sefar Universal y dejar HubSpot como plataforma sincronizada durante la transición. HubSpot es la prioridad de la carga inicial; después, un cambio iniciado en la App debe actualizar el registro existente en HubSpot. Si ambos lados modifican el mismo campo antes de sincronizar, HubSpot prevalece y la diferencia debe quedar registrada.

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
- El receptor genérico puede conservar snapshots de distintos tipos de HubSpot que tengan webhook activo. El alcance acordado de la migración inicial se limita a contactos, negocios, tickets, líneas, presupuestos, facturas, pagos, pedidos, suscripciones y actividades; no se migran empresas, productos ni objetos personalizados.
- El backfill clasifica como `Lead` los contactos de HubSpot sin negocios asociados, salvo que un usuario local no cliente o con negocios propios coincida por ID; esa contradicción queda para Sistemas. Solo enlaza un contacto con negocios a un cliente por `users.hs_id`; el correo y el pasaporte sirven únicamente para detectar posibles colisiones, nunca para enlazar automáticamente. Los casos ambiguos, los correos secundarios duplicados, los IDs cruzados y los contactos con negocios sin cliente local quedan en `crm_identity_reviews` para revisión de Sistemas. Sus negocios pueden conservarse localmente sin asignar a un usuario.
- La proyección de contactos confirma estos mapas: `firstname → users.nombres`, `lastname → users.apellidos`, `phone → users.phone`, `numero_de_pasaporte → users.passport`, `firstname + lastname → leads.nombre`, y `email/phone/numero_de_pasaporte → campos equivalentes de Lead`. `email` también se copia a `users.email` únicamente por `hs_id`; si coincide con otro usuario, lead o correo secundario, se deja para Sistemas. La fecha de nacimiento permanece en el snapshot hasta acordar su columna local. Para negocios, `dealname → negocios.proyecto`; una migración añade `amount`, `deal_currency_code`, `pipeline`, `dealstage` y `hubspot_owner_id`, que se copian por su ID exacto.
- La reconciliación de contactos compara cada campo con el snapshot anterior. Si solo cambió localmente, conserva el valor para el outbox; si cambió en HubSpot, HubSpot prevalece en ese campo. Cuando ambos cambiaron el mismo campo, guarda el par cifrado en `crm_sync_conflicts`. El comando `crm:hubspot-sync-conflicts` lista metadatos sin valores; `--show-values` revela el diff cifrado para revisión autorizada.
- Los espejos guardan eliminaciones como archivo lógico y conservan las propiedades anteriores. La recepción sigue dependiendo de la firma, el portal configurado, `CRM_EVENT_PROCESSING_ENABLED` y de que HubSpot envíe las suscripciones de cada objeto.
- Esta copia técnica conserva cada tipo de HubSpot como snapshot con su ID externo y todas sus propiedades. No genera cargos ni números de factura o presupuesto. Todavía no convierte facturas, pagos, presupuestos y actividades en registros editables de sus módulos operativos locales.
- Alcance inicial acordado: contactos (clasificados como clientes o leads en la App), negocios, tickets, líneas, presupuestos, facturas, pagos, pedidos, suscripciones y actividad histórica (llamadas, correos registrados, reuniones, notas y tareas). No incluye empresas, productos ni objetos personalizados.
- La importación inicial tiene el perfil `crm:mirror-hubspot-objects --initial-crm --queue --write`; crea cargas reanudables para registros activos y archivados, sin escribir en HubSpot. `crm:hubspot-migration-report` muestra el avance guardado localmente y no llama a la API. El objeto Leads de HubSpot no se encola; la App clasifica como lead el contacto que no tiene negocios asociados.
- `crm:hubspot-identity-reviews` muestra los IDs y motivos pendientes sin exponer correos ni pasaportes. Sistemas resuelve cada caso de forma explícita con `--resolve=<contact-id> --as=client|lead|ignore --write`; una asignación a cliente requiere un `users.hs_id` único y al menos un negocio local.
- La importación histórica tiene ahora una modalidad en cola y reanudable. Cada job lee 25 registros, guarda su cursor y programa la página siguiente con una pausa. Un `429` respeta `Retry-After`; otros errores quedan con clase de excepción para reintentar sin guardar la respuesta o datos personales.
- El limitador compartido conserva el techo de configuración de la App (predeterminado: 4 solicitudes por segundo y 10.000 por día), pero reduce la velocidad a la mitad del burst anunciado en las cabeceras de HubSpot. También pausa al llegar a la mitad del límite diario anunciado, lo que conserva la otra mitad para las integraciones compartidas. Los límites diarios se reinician según `CRM_HUBSPOT_DAILY_RESET_TIMEZONE`, que debe coincidir con la zona horaria configurada en HubSpot; si faltan cabeceras (por ejemplo, en Search/OAuth), se mantiene el presupuesto local conservador. Requiere una caché con locks compartidos entre los workers.
- Cambios locales de clientes, leads y negocios pueden ir al outbox idempotente y al worker `crm-sync`. La salida queda apagada por defecto (`CRM_HUBSPOT_OUTBOUND_ENABLED=false`). Los contactos existentes se actualizan solo por `hs_id`/vínculo externo; no se enlazan por correo. Un registro histórico sin ID queda en revisión. La creación de contactos nuevos usa el marcador `sefar_app_contact_id`; los negocios nuevos usan `sefar_app_negocio_id`, para que los reintentos recuperen el mismo objeto. No empuja facturas, pagos, presupuestos ni actividades todavía.
- El perfil anterior se ejecuta por la cola `crm-sync`, en páginas pequeñas con pausa, cursor, límite de solicitudes y presupuesto diario compartidos. Se puede pausar y reanudar a lo largo del tiempo. `completed` confirma snapshots de objetos, no paridad de asociaciones ni proyección operativa.
- Webhook receptor: `https://app.sefaruniversal.com/api/webhooks/hubspot`. La ruta verifica firma, portal e idempotencia y encola los eventos que HubSpot tenga suscritos. Falta configurar y comprobar en la App de HubSpot las suscripciones de cada tipo de objeto y asociación; el endpoint por sí solo no crea esas suscripciones.
- El backfill de contactos consulta negocios asociados para proyectarlos a `negocios` y guardar la relación contacto↔negocio. La cola `crm:hubspot-backfill-associations --queue --write` añade un backfill del conjunto explícito de relaciones comerciales, facturas, presupuestos y actividades; `--status` informa el avance. Ambas modalidades escriben solo el espejo local y usan Associations API `2026-09` con el limitador compartido. Facturas, presupuestos, pagos y actividad histórica se conservan íntegros en el espejo JSON y sus asociaciones genéricas; todavía falta proyectarlos a fichas operativas locales y sincronizar escrituras salientes de esos tipos.
- El informe compara filas guardadas con registros leídos por cada backfill, además de estados, revisiones, conflictos y asociaciones. Esa comparación es local: no equivale a consultar HubSpot en vivo ni certifica paridad actual después de nuevos cambios.

Esta es una separación lógica y reversible. La extracción física de contactos fuera de `users` se hará después de migrar compras, documentos, restricciones, notas, tareas, autenticación e integraciones.

## Capacidades solicitadas y brechas

### 1. Leads separados de clientes

La entidad `Lead` conserva datos de contacto, origen, etapa y responsable. Al registrarse como cliente, una coincidencia única mueve el ID de HubSpot y los datos faltantes a `users`, transfiere sus actividades y presupuestos asociados y elimina la fila del lead. Si hay más de una coincidencia, un ID cruzado o asociaciones a otro cliente, no borra ni crea otro contacto remoto y lo deja pendiente para Sistemas.

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

En esta fase se añadió la cola local `crm_identity_reviews` y el comando `crm:hubspot-identity-reviews`. La migración todavía debe desplegarse antes de ejecutar la clasificación de contactos en producción. La salida a HubSpot continúa apagada por defecto; no se ha iniciado ninguna importación real ni se han creado registros remotos.

## Secuencia inicial de producción

Después de desplegar el código, ejecutar en la carpeta Laravel:

```bash
php artisan config:clear
php artisan migrate --force
php artisan crm:mirror-hubspot-objects --initial-crm --queue --write
php artisan crm:hubspot-migration-report
```

El scheduler de Laravel debe seguir ejecutándose cada minuto para procesar `crm-sync`. Revisar `crm:mirror-hubspot-objects --status` y el informe hasta que cada tipo activo y archivado indique `completed`. Después, iniciar las relaciones:

```bash
php artisan crm:hubspot-backfill-associations --queue --write
php artisan crm:hubspot-backfill-associations --status
php artisan crm:hubspot-migration-report
```

Los comandos de carga escriben únicamente en la base local y respetan el limitador compartido. Los pendientes de `crm_identity_reviews`, fallos de backfill y conflictos de campos deben resolverse antes de aprobar paridad. La recepción continua requiere suscripciones configuradas en HubSpot y `CRM_EVENT_PROCESSING_ENABLED=true`; la salida App → HubSpot sigue apagada hasta aprobar el mapa y el informe.

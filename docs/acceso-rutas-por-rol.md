# Acceso a rutas por rol

Revisión del 2 de noviembre de 2026. Se consultaron únicamente roles y permisos de la base configurada en este entorno; no se modificaron asignaciones ni se consultaron datos de personas. Corresponde al código local, pendiente de despliegue.

La matriz cubre las 527 entradas registradas desde `routes/web.php`. GET y HEAD se cuentan juntos; métodos distintos se cuentan por separado. El registro global contiene 614 entradas.

Esta tabla evalúa sesión, barrera de Cliente y permisos `can:*` de ruta. Propiedad del expediente, contrato del coordinador, bloqueo individual, verificación del email y controles del controlador siguen aplicando. No incluye permisos directos de personas ni combinaciones de roles. Si una cuenta tiene Cliente junto a otro rol, prevalece la restricción de Cliente.

## Roles configurados

| Código | Rol | Rutas que superan esta capa |
| --- | --- | ---: |
| R1 | Admin | 275 |
| R2 | Administrador | 513 |
| R3 | Analista | 116 |
| R4 | ATC | 116 |
| R5 | BadellLaw | 98 |
| R6 | Cliente | 48 |
| R7 | Coord. de Nacionalidad y Genealogía | 116 |
| R8 | Documentalista | 160 |
| R9 | Genealogista | 227 |
| R10 | German-Fleitas | 98 |
| R11 | MG-Tours | 98 |
| R12 | Mujica-Coto | 98 |
| R13 | P&V-Abogados | 98 |
| R14 | Produccion | 197 |
| R15 | Proveedor | 77 |
| R16 | Soma-Consultores | 98 |
| R17 | Traviesoevans | 98 |
| R18 | Vargassequera | 98 |
| R19 | Ventas | 184 |

## Resultado

- Cliente conserva 48 entradas del portal y recibe 403 en las otras 479 entradas de este archivo.
- Los otros 18 roles conservan sus permisos actuales.
- GET/POST `/registerv2` y OAuth siguen públicos en `routes/public.php`. Login y recuperación siguen en Fortify. Perfil y seguridad de la propia cuenta conservan las rutas de Fortify/Jetstream.
- Los endpoints compartidos del árbol validan la pertenencia al árbol asignado antes de leer, modificar o subir datos. Firmar un archivo requiere un registro del mismo árbol.
- Livewire vuelve a ejecutar la barrera de la ruta de origen.

## Observaciones

- Cliente conserva permisos heredados `crud.agclientes.index/create/edit` y `crud.files.index/create/edit`; ya no permiten abrir los CRUD internos.
- Rutas de tareas de asesor, propuestas estratégicas, HubSpot Owners y búsquedas de contactos solo exigían sesión. Ahora rechazan a Cliente. Los demás roles conservan el acceso que tenían, conforme al alcance solicitado.
- `Admin` y `Administrador` no son equivalentes. Solo `Administrador` satisface `integrations.manage` y `docs.upload`.
- El menú Documentos consulta `verdocs.index`, pero `/docs` consulta `docs.view`. Este último se concede por permiso almacenado o por el gate de Coord. de Nacionalidad y Genealogía. La matriz usa la evaluación real de Laravel Gate/Spatie; no se modificaron estas condiciones.

## Matriz de rutas

`Internos` significa los 18 roles salvo Cliente; `Todos` incluye Cliente; `Ninguno` indica que ningún rol consultado satisface los permisos de ruta. Las rutas sin `can:*` conservan las validaciones de sus controladores. Los códigos se definen arriba.

| Método | Ruta | Nombre | Permisos de ruta | Roles admitidos por esta capa |
| --- | --- | --- | --- | --- |
| GET | `/` | `inicio` | — | Todos |
| GET | `/acceso-restringido` | `client-access-blocked` | — | Todos |
| GET | `/actualizaciones` | `deployment-histories.index` | `administrador` | R1, R2 |
| GET | `/actualizaciones/{deploymentHistory}` | `deployment-histories.show` | `administrador` | R1, R2 |
| GET | `/admin/automations` | `automations.index` | `administrador` | R1, R2 |
| POST | `/admin/automations` | `automations.store` | `administrador` | R1, R2 |
| GET | `/admin/automations/create` | `automations.create` | `administrador` | R1, R2 |
| POST | `/admin/automations/run-due` | `automations.run-due` | `administrador` | R1, R2 |
| DELETE | `/admin/automations/{automation}` | `automations.destroy` | `administrador` | R1, R2 |
| PUT | `/admin/automations/{automation}` | `automations.update` | `administrador` | R1, R2 |
| GET | `/admin/automations/{automation}/edit` | `automations.edit` | `administrador` | R1, R2 |
| PATCH | `/admin/automations/{automation}/toggle` | `automations.toggle` | `administrador` | R1, R2 |
| GET | `/admin/banca-online-2026` | `admin.banca-online.index` | `administrador` | R1, R2 |
| POST | `/admin/banca-online-2026/items` | `admin.banca-online.items.store` | `administrador` | R1, R2 |
| PUT | `/admin/banca-online-2026/items/{servicio}` | `admin.banca-online.items.update` | `administrador` | R1, R2 |
| PUT | `/admin/banca-online-2026/packages/{servicio}` | `admin.banca-online.packages.update` | `administrador` | R1, R2 |
| POST | `/admin/banca-online-2026/sync` | `admin.banca-online.sync` | `administrador` | R1, R2 |
| GET | `/admin/consultation-calendars` | `admin.consultation-calendars.index` | `administrador` | R1, R2 |
| POST | `/admin/consultation-calendars` | `admin.consultation-calendars.store` | `administrador` | R1, R2 |
| GET | `/admin/consultation-calendars/create` | `admin.consultation-calendars.create` | `administrador` | R1, R2 |
| DELETE | `/admin/consultation-calendars/{consultation_calendar}` | `admin.consultation-calendars.destroy` | `administrador` | R1, R2 |
| PUT/PATCH | `/admin/consultation-calendars/{consultation_calendar}` | `admin.consultation-calendars.update` | `administrador` | R1, R2 |
| GET | `/admin/consultation-calendars/{consultation_calendar}/edit` | `admin.consultation-calendars.edit` | `administrador` | R1, R2 |
| GET | `/admin/docs` | `docs.admin` | `docs.upload` | R2 |
| POST | `/admin/docs` | `docs.store` | `docs.upload` | R2 |
| DELETE | `/admin/docs/{id}` | `docs.destroy` | `docs.delete` | R2 |
| PUT | `/admin/docs/{id}` | `docs.update` | `docs.upload` | R2 |
| GET | `/admin/integrations/ai-models` | `admin.integrations.ai-models.index` | `integrations.manage` | R2 |
| PUT | `/admin/integrations/ai-models` | `admin.integrations.ai-models.update` | `integrations.manage` | R2 |
| GET | `/admin/integrations/api-tokens` | `admin.integrations.api-tokens.index` | `integrations.manage` | R2 |
| POST | `/admin/integrations/api-tokens` | `admin.integrations.api-tokens.store` | `integrations.manage` | R2 |
| GET | `/admin/integrations/audit` | `admin.integrations.audit.index` | `integrations.manage` | R2 |
| GET | `/admin/integrations/mcp` | `admin.integrations.mcp.index` | `integrations.manage` | R2 |
| POST | `/admin/integrations/mcp/tokens` | `admin.integrations.mcp.tokens.store` | `integrations.manage` | R2 |
| DELETE | `/admin/integrations/tokens/{token}` | `admin.integrations.tokens.destroy` | `integrations.manage` | R2 |
| GET | `/admin/marketing` | `marketing.dashboard` | `administrador` | R1, R2 |
| POST | `/admin/marketing/campaigns` | `marketing.campaigns.store` | `administrador` | R1, R2 |
| GET | `/admin/marketing/campaigns/create` | `marketing.campaigns.create` | `administrador` | R1, R2 |
| POST | `/admin/marketing/campaigns/preview-random-recipient` | `marketing.campaigns.preview-random-recipient` | `administrador` | R1, R2 |
| GET | `/admin/marketing/campaigns/{campaign}` | `marketing.campaigns.show` | `administrador` | R1, R2 |
| PUT | `/admin/marketing/campaigns/{campaign}` | `marketing.campaigns.update` | `administrador` | R1, R2 |
| GET | `/admin/marketing/campaigns/{campaign}/edit` | `marketing.campaigns.edit` | `administrador` | R1, R2 |
| POST | `/admin/marketing/campaigns/{campaign}/pause` | `marketing.campaigns.pause` | `administrador` | R1, R2 |
| POST | `/admin/marketing/campaigns/{campaign}/send` | `marketing.campaigns.send` | `administrador` | R1, R2 |
| POST | `/admin/marketing/campaigns/{campaign}/test` | `marketing.campaigns.test` | `administrador` | R1, R2 |
| GET | `/admin/marketing/lists` | `marketing.lists.index` | `administrador` | R1, R2 |
| POST | `/admin/marketing/lists/import` | `marketing.lists.import` | `administrador` | R1, R2 |
| GET | `/admin/marketing/setup` | `marketing.setup` | `administrador` | R1, R2 |
| GET | `/admin/marketing/templates` | `marketing.templates.index` | `administrador` | R1, R2 |
| POST | `/admin/marketing/templates` | `marketing.templates.store` | `administrador` | R1, R2 |
| POST | `/admin/marketing/templates/images` | `marketing.templates.images.store` | `administrador` | R1, R2 |
| GET | `/admin/monday` | `admin.monday.index` | `crud.mondayreportes.index` | R2 |
| GET | `/admin/monday/actividad` | `admin.monday.activity` | `crud.mondayreportes.index` | R2 |
| GET | `/admin/monday/elementos/{item}` | `admin.monday.items.show` | `crud.mondayreportes.index` | R2 |
| GET | `/admin/monday/tableros/{board}` | `admin.monday.boards.show` | `crud.mondayreportes.index` | R2 |
| GET | `/admin/procesos` | `admin.procesos.index` | `administrador` | R1, R2 |
| PUT | `/admin/procesos/pasos/{paso}` | `admin.procesos.pasos.updateFull` | `administrador` | R1, R2 |
| GET | `/admin/procesos/{cos}` | `admin.procesos.show` | `administrador` | R1, R2 |
| POST | `/admin/process-queue` | — | `administrador` | R1, R2 |
| GET | `/admin/referral-codes` | `admin.referral-codes.index` | `administrador` | R1, R2 |
| POST | `/admin/referral-codes/send-all` | `admin.referral-codes.send-all` | `administrador` | R1, R2 |
| POST | `/admin/referral-codes/sync` | `admin.referral-codes.sync` | `administrador` | R1, R2 |
| PUT | `/admin/requests/{documentRequest}` | `admin.requests.update` | `notCliente` + `administrar.documentos` | R2, R8, R9, R14 |
| POST | `/admin/requests/{documentRequest}/approve` | `admin.requests.approve` | `notCliente` + `administrar.documentos` | R2, R8, R9, R14 |
| POST | `/admin/requests/{documentRequest}/reject` | `admin.requests.reject` | `notCliente` + `administrar.documentos` | R2, R8, R9, R14 |
| DELETE | `/admin/requests/{request}` | `admin.requests.destroy` | `notCliente` + `administrar.documentos` | R2, R8, R9, R14 |
| POST | `/admin/requests/{user}` | `admin.requests.store` | `notCliente` + `administrar.documentos` | R2, R8, R9, R14 |
| GET | `/admin/role-ai-assistants` | `admin.role-ai-assistants.index` | `administrador` | R1, R2 |
| GET | `/admin/role-ai-assistants/{assistant}` | `admin.role-ai-assistants.show` | `administrador` | R1, R2 |
| PUT | `/admin/role-ai-assistants/{assistant}` | `admin.role-ai-assistants.update` | `administrador` | R1, R2 |
| PATCH | `/admin/role-ai-assistants/{assistant}/knowledge/{knowledgeEntry}/archive` | `admin.role-ai-assistants.knowledge.archive` | `administrador` | R1, R2 |
| PATCH | `/admin/role-ai-assistants/{assistant}/knowledge/{knowledgeEntry}/restore` | `admin.role-ai-assistants.knowledge.restore` | `administrador` | R1, R2 |
| GET | `/admin/support-ticket-test` | `admin.support-ticket-test.create` | `administrador` | R1, R2 |
| POST | `/admin/support-ticket-test` | `admin.support-ticket-test.store` | `administrador` | R1, R2 |
| GET | `/admin/unification-map` | `unification-map.index` | `administrador` | R1, R2 |
| POST | `/admin/unification-map` | `unification-map.store` | `administrador` | R1, R2 |
| POST | `/admin/unification-map/catalogues/refresh` | `unification-map.catalogues.refresh` | `administrador` | R1, R2 |
| GET | `/admin/unification-map/catalogues/status` | `unification-map.catalogues.status` | `administrador` | R1, R2 |
| GET | `/admin/unification-map/diagram` | `unification-map.diagram` | `administrador` | R1, R2 |
| GET | `/admin/unification-map/fields` | `unification-map.fields` | `administrador` | R1, R2 |
| GET | `/admin/unification-map/monday/boards` | `unification-map.monday.boards` | `administrador` | R1, R2 |
| GET | `/admin/unification-map/monday/fields` | `unification-map.monday.fields` | `administrador` | R1, R2 |
| POST | `/admin/unification-map/relations` | `unification-map.relations.store` | `administrador` | R1, R2 |
| GET | `/admin/unification-map/relations/automatic` | `unification-map.relations.automatic` | `administrador` | R1, R2 |
| POST | `/admin/unification-map/relations/bulk` | `unification-map.relations.bulk` | `administrador` | R1, R2 |
| PATCH | `/admin/unification-map/relations/{auditRelation}` | `unification-map.relations.review` | `administrador` | R1, R2 |
| POST | `/admin/unification-map/suggest` | `unification-map.suggest` | `administrador` | R1, R2 |
| PATCH | `/admin/unification-map/{auditLink}` | `unification-map.review` | `administrador` | R1, R2 |
| POST | `/adminchangepassword` | `adminchangepassword` | `administrador` | R1, R2 |
| POST | `/adminlte/darkmode/toggle` | `adminlte.darkmode.toggle` | — | Todos |
| GET | `/agclientes` | `crud.agclientes.index` | `crud.agclientes.index` | R2, R5, R9, R10, R11, R12, R13, R14, R16, R17, R18, R19 |
| POST | `/agclientes` | `crud.agclientes.store` | `crud.agclientes.index` | R2, R5, R9, R10, R11, R12, R13, R14, R16, R17, R18, R19 |
| GET | `/agclientes/create` | `crud.agclientes.create` | `crud.agclientes.index` | R2, R5, R9, R10, R11, R12, R13, R14, R16, R17, R18, R19 |
| DELETE | `/agclientes/{agcliente}` | `crud.agclientes.destroy` | `crud.agclientes.index` | R2, R5, R9, R10, R11, R12, R13, R14, R16, R17, R18, R19 |
| GET | `/agclientes/{agcliente}` | `crud.agclientes.show` | `crud.agclientes.index` | R2, R5, R9, R10, R11, R12, R13, R14, R16, R17, R18, R19 |
| PUT/PATCH | `/agclientes/{agcliente}` | `crud.agclientes.update` | `crud.agclientes.index` | R2, R5, R9, R10, R11, R12, R13, R14, R16, R17, R18, R19 |
| GET | `/agclientes/{agcliente}/edit` | `crud.agclientes.edit` | `crud.agclientes.index` | R2, R5, R9, R10, R11, R12, R13, R14, R16, R17, R18, R19 |
| POST | `/agclientesnew` | `agclientesnew.store` | — | Todos |
| GET | `/agclientesp` | `test.agclientesp` | `administrador` | R1, R2 |
| POST | `/agclientesupdate` | `agclientesnew.update` | — | Todos |
| GET | `/ajax/users` | `ajax.users.search` | — | Internos |
| GET | `/albero/{IDCliente}` | `arboles.albero.index` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/alerts` | `alerts.index` | `alertasapp.index` | R2 |
| POST | `/alerts` | `alerts.store` | `alertasapp.create` | R2 |
| GET | `/alerts/create` | `alerts.create` | `alertasapp.create` | R2 |
| DELETE | `/alerts/{alert}` | `alerts.destroy` | `alertasapp.delete` | R2 |
| GET | `/anualindex` | `anualindex` | `reportes.index` | R2, R14, R19 |
| GET | `/api/contacts/search` | `api.contacts.search` | — | Internos |
| GET | `/api/getemail/{id}` | `getemail` | — | Internos |
| GET | `/books` | `crud.books.index` | `crud.books.index` | R2, R8, R9 |
| POST | `/books` | `crud.books.store` | `crud.books.index` | R2, R8, R9 |
| GET | `/books/create` | `crud.books.create` | `crud.books.index` | R2, R8, R9 |
| DELETE | `/books/{book}` | `crud.books.destroy` | `crud.books.index` | R2, R8, R9 |
| GET | `/books/{book}` | `crud.books.show` | `crud.books.index` | R2, R8, R9 |
| PUT/PATCH | `/books/{book}` | `crud.books.update` | `crud.books.index` | R2, R8, R9 |
| GET | `/books/{book}/edit` | `crud.books.edit` | `crud.books.index` | R2, R8, R9 |
| POST | `/cache-clear` | — | `administrador` | R1, R2 |
| GET | `/capturar_parametros_get` | `test.capturar_parametros_get` | `administrador` | R1, R2 |
| POST | `/changepassword` | `changepassword` | — | Todos |
| GET | `/checkContrato` | `checkContrato` | — | Todos |
| GET | `/checkMondayTest` | `checkMondayTest` | `administrador` | R1, R2 |
| GET | `/checkRegAlzada` | `checkRegAlzada` | — | Internos |
| GET | `/client-import` | `client-import.index` | `administrador` | R1, R2 |
| POST | `/client-import` | `client-import.store` | `administrador` | R1, R2 |
| POST | `/client/requests/{documentRequest}/upload` | `upload` | — | Todos |
| POST | `/client/requests/{requestModel}/no-doc` | `no_doc` | — | Todos |
| GET | `/clientes/status/{agcliente}` | `getuserstatus_ventas` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/comprobantes` | `crud.comprobantes.index` | `crud.comprobantes.index` | R2, R19 |
| POST | `/comprobantes` | `crud.comprobantes.store` | `crud.comprobantes.index` | R2, R19 |
| GET | `/comprobantes/create` | `crud.comprobantes.create` | `crud.comprobantes.index` | R2, R19 |
| DELETE | `/comprobantes/{comprobante}` | `crud.comprobantes.destroy` | `crud.comprobantes.index` | R2, R19 |
| GET | `/comprobantes/{comprobante}` | `crud.comprobantes.show` | `crud.comprobantes.index` | R2, R19 |
| PUT/PATCH | `/comprobantes/{comprobante}` | `crud.comprobantes.update` | `crud.comprobantes.index` | R2, R19 |
| GET | `/comprobantes/{comprobante}/edit` | `crud.comprobantes.edit` | `crud.comprobantes.index` | R2, R19 |
| POST | `/config-cache` | — | `administrador` | R1, R2 |
| POST | `/config-clear` | — | `administrador` | R1, R2 |
| GET | `/connections` | `crud.connections.index` | `crud.connections.index` | R2, R9 |
| POST | `/connections` | `crud.connections.store` | `crud.connections.index` | R2, R9 |
| GET | `/connections/create` | `crud.connections.create` | `crud.connections.index` | R2, R9 |
| DELETE | `/connections/{connection}` | `crud.connections.destroy` | `crud.connections.index` | R2, R9 |
| GET | `/connections/{connection}` | `crud.connections.show` | `crud.connections.index` | R2, R9 |
| PUT/PATCH | `/connections/{connection}` | `crud.connections.update` | `crud.connections.index` | R2, R9 |
| GET | `/connections/{connection}/edit` | `crud.connections.edit` | `crud.connections.index` | R2, R9 |
| GET | `/consultaodx` | `consultas.onidex.index` | `consultas.onidex.index` | R2, R9, R19 |
| POST | `/consultaodx` | `consultas.onidex.show` | `consultas.onidex.show` | R2, R9, R19 |
| GET | `/contrato` | `cliente.contrato` | `cliente` | R2, R6 |
| GET | `/contrato-coordinador` | `contrato.coordinador.form` | — | Internos |
| GET | `/contrato-coordinador/confirmar` | `contrato.coordinador.confirm` | — | Internos |
| GET | `/cos/cartera` | `crud.cos.portfolio` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/cos/cartera/asociaciones` | `crud.cos.portfolio.associate` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/cosvisitas` | `cosvisitas` | `cosvisitas.index` | R2 |
| GET | `/countries` | `crud.countries.index` | `crud.countries.index` | R2, R14 |
| POST | `/countries` | `crud.countries.store` | `crud.countries.index` | R2, R14 |
| GET | `/countries/create` | `crud.countries.create` | `crud.countries.index` | R2, R14 |
| DELETE | `/countries/{country}` | `crud.countries.destroy` | `crud.countries.index` | R2, R14 |
| GET | `/countries/{country}` | `crud.countries.show` | `crud.countries.index` | R2, R14 |
| PUT/PATCH | `/countries/{country}` | `crud.countries.update` | `crud.countries.index` | R2, R14 |
| GET | `/countries/{country}/edit` | `crud.countries.edit` | `crud.countries.index` | R2, R14 |
| GET | `/coupons` | `crud.coupons.index` | `crud.coupons.index` | R2, R19 |
| POST | `/coupons` | `crud.coupons.store` | `crud.coupons.index` | R2, R19 |
| GET | `/coupons/create` | `crud.coupons.create` | `crud.coupons.index` | R2, R19 |
| DELETE | `/coupons/{coupon}` | `crud.coupons.destroy` | `crud.coupons.index` | R2, R19 |
| GET | `/coupons/{coupon}` | `crud.coupons.show` | `crud.coupons.index` | R2, R19 |
| PUT/PATCH | `/coupons/{coupon}` | `crud.coupons.update` | `crud.coupons.index` | R2, R19 |
| GET | `/coupons/{coupon}/edit` | `crud.coupons.edit` | `crud.coupons.index` | R2, R19 |
| GET | `/crud/lists` | `crud.lists.index` | `lists.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| POST | `/crud/lists` | `crud.lists.store` | `lists.create` | R2 |
| GET | `/crud/lists/create` | `crud.lists.create` | `lists.create` | R2 |
| DELETE | `/crud/lists/{lista}` | `crud.lists.destroy` | `lists.delete` | R2 |
| GET | `/crud/lists/{lista}` | `crud.lists.show` | `lists.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| PUT | `/crud/lists/{lista}` | `crud.lists.update` | `lists.edit` | R2 |
| GET | `/crud/lists/{lista}/edit` | `crud.lists.edit` | `lists.edit` | R2 |
| POST | `/crud/lists/{lista}/members/add` | `crud.lists.members.add` | `administrador` | R1, R2 |
| POST | `/crud/lists/{lista}/members/import/preview` | `crud.lists.members.import.preview` | `administrador` | R1, R2 |
| POST | `/crud/lists/{lista}/members/import/process` | `crud.lists.members.import.process` | `administrador` | R1, R2 |
| DELETE | `/crud/lists/{lista}/members/{user}` | `crud.lists.members.remove` | `administrador` | R1, R2 |
| PATCH | `/crud/lists/{lista}/members/{user}/contacted` | `crud.lists.members.contacted` | `lists.manage_members` | R2 |
| GET | `/crud/users/{user}/edit-basic` | `crud.users.editBasic` | `administrador` | R1, R2 |
| GET | `/crud/users/{user}/information` | `crud.users.information` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| PUT | `/crud/users/{user}/update-basic` | `crud.users.updateBasic` | `administrador` | R1, R2 |
| POST | `/cuponaceptar/{id}` | `cuponaceptar` | `crud.solicitudcupones.index` | R2 |
| GET | `/cuponaplicado` | `cuponaplicado` | — | Todos |
| POST | `/cuponenable` | `cuponenable` | `crud.coupons.edit` | R2, R19 |
| POST | `/cuponrechazar/{id}` | `rechazarcupon` | `crud.solicitudcupones.index` | R2 |
| GET | `/dashboard` | `dashboard` | — | Internos |
| GET | `/deal/{id}/edit` | `deals.edit` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/deals/{id}/teamleader-suggestions` | `deals.teamleader.suggestions` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/deals/{id}/update` | `deals.update` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/deletefile` | `deletefile` | `crud.files.destroy` | R2 |
| POST | `/deploy` | `deploy.run` | `administrador` | R1, R2 |
| POST | `/destroypayelement` | `destroypayelement` | — | Todos |
| GET | `/diarioindex` | `diarioindex` | `reportes.index` | R2, R14, R19 |
| GET | `/docs` | `docs.index` | `docs.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| GET | `/docs/{id}/download` | `docs.download` | `docs.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| GET | `/downloadExcel/{id}` | `getExcelCliente` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/downloadTree/{id}` | `getGedcomCliente` | `administrador` | R1, R2 |
| POST | `/etiquetasgenealogiamonday` | `etiquetasgenealogiamonday` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/exonerarcartanat` | `exonerarcartanat` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/exonerarfase1` | `exonerarfase1` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/exonerarfase2` | `exonerarfase2` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/exonerarfase3` | `exonerarfase3` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/exonerarfcjecil` | `exonerarcilfcje` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/families` | `crud.families.index` | `crud.families.index` | R2, R9, R14 |
| POST | `/families` | `crud.families.store` | `crud.families.index` | R2, R9, R14 |
| GET | `/families/create` | `crud.families.create` | `crud.families.index` | R2, R9, R14 |
| DELETE | `/families/{family}` | `crud.families.destroy` | `crud.families.index` | R2, R9, R14 |
| GET | `/families/{family}` | `crud.families.show` | `crud.families.index` | R2, R9, R14 |
| PUT/PATCH | `/families/{family}` | `crud.families.update` | `crud.families.index` | R2, R9, R14 |
| GET | `/families/{family}/edit` | `crud.families.edit` | `crud.families.index` | R2, R9, R14 |
| GET | `/family-groups` | `family-groups.index` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| POST | `/family-groups` | `family-groups.store` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/family-groups/create` | `family-groups.create` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| POST | `/family-groups/{familyGroup}/members` | `family-groups.members.store` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| DELETE | `/family-groups/{familyGroup}/members/{member}` | `family-groups.members.destroy` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| POST | `/family-groups/{familyGroup}/recalculate` | `family-groups.recalculate` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| DELETE | `/family-groups/{family_group}` | `family-groups.destroy` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/family-groups/{family_group}` | `family-groups.show` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/files` | `crud.files.index` | `crud.files.index` | R2, R5, R8, R9, R10, R11, R12, R13, R14, R16, R17, R18 |
| POST | `/files` | `crud.files.store` | `crud.files.index` | R2, R5, R8, R9, R10, R11, R12, R13, R14, R16, R17, R18 |
| GET | `/files/create` | `crud.files.create` | `crud.files.index` | R2, R5, R8, R9, R10, R11, R12, R13, R14, R16, R17, R18 |
| DELETE | `/files/{file}` | `crud.files.destroy` | `crud.files.index` | R2, R5, R8, R9, R10, R11, R12, R13, R14, R16, R17, R18 |
| GET | `/files/{file}` | `crud.files.show` | `crud.files.index` | R2, R5, R8, R9, R10, R11, R12, R13, R14, R16, R17, R18 |
| PUT/PATCH | `/files/{file}` | `crud.files.update` | `crud.files.index` | R2, R5, R8, R9, R10, R11, R12, R13, R14, R16, R17, R18 |
| GET | `/files/{file}/edit` | `crud.files.edit` | `crud.files.index` | R2, R5, R8, R9, R10, R11, R12, R13, R14, R16, R17, R18 |
| POST | `/fixCouponHubspot` | `fixCouponHubspot` | `administrador` | R1, R2 |
| POST | `/fixPayDataHubspot` | `fixPayDataHubspot` | `administrador` | R1, R2 |
| GET | `/fixpassport` | `fixpassport` | `administrador` | R1, R2 |
| POST | `/fixpassport` | `fixpassportprocess` | `administrador` | R1, R2 |
| GET | `/flex` | `test.flex` | `administrador` | R1, R2 |
| GET | `/formats` | `crud.formats.index` | `crud.formats.index` | R2, R14 |
| POST | `/formats` | `crud.formats.store` | `crud.formats.index` | R2, R14 |
| GET | `/formats/create` | `crud.formats.create` | `crud.formats.index` | R2, R14 |
| DELETE | `/formats/{format}` | `crud.formats.destroy` | `crud.formats.index` | R2, R14 |
| GET | `/formats/{format}` | `crud.formats.show` | `crud.formats.index` | R2, R14 |
| PUT/PATCH | `/formats/{format}` | `crud.formats.update` | `crud.formats.index` | R2, R14 |
| GET | `/formats/{format}/edit` | `crud.formats.edit` | `crud.formats.index` | R2, R14 |
| POST | `/gedcom/import` | `gedcom.import` | `administrador` | R1, R2 |
| GET | `/gedcomexport` | `gedcomexport` | `administrador` | R1, R2 |
| GET | `/generalcoupons` | `generalcoupons.index` | `generalcoupons.index` | R2 |
| POST | `/generalcoupons` | `generalcoupons.store` | `generalcoupons.create` | R2 |
| GET | `/generalcoupons/create` | `generalcoupons.create` | `generalcoupons.create` | R2 |
| DELETE | `/generalcoupons/{generalCoupon}` | `generalcoupons.destroy` | `generalcoupons.delete` | R2 |
| GET | `/getGedcomGlobal` | `getGedcomGlobal` | `administrador` | R1, R2 |
| POST | `/getclientfiles` | `getclientfiles` | `tree-files.view` | R2, R5, R6, R9, R10, R11, R12, R13, R16, R17, R18 |
| POST | `/getfileedit` | `getfileedit` | `genealogista` + `crud.files.edit` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18 |
| POST | `/getfileupdate` | `getfileupdate` | `genealogista` + `crud.files.edit` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18 |
| GET | `/getinfo` | `clientes.getinfo` | `cliente` | R2, R6 |
| POST | `/getinfo` | `procesargetinfo` | `cliente` | R2, R6 |
| POST | `/getreporteanual` | `getreporteanual` | `reportes.index` | R2, R14, R19 |
| POST | `/getreportediario` | `getreportediario` | `reportes.index` | R2, R14, R19 |
| POST | `/getreportemensual` | `getreportemensual` | `reportes.index` | R2, R14, R19 |
| POST | `/getreportesemanal` | `getreportesemanal` | `reportes.index` | R2, R14, R19 |
| POST | `/google-review` | `clientes.google-review` | `cliente` | R2, R6 |
| POST | `/gotopayfases` | `gotopayfases` | `cliente` | R2, R6 |
| POST | `/guardar-datos-personales` | `saveuserdata` | — | Todos |
| POST | `/guardarcartanat` | `guardarcartanat` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/guardarfase1` | `guardarfase1` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/guardarfase2` | `guardarfase2` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/guardarfase3` | `guardarfase3` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/guardarfcjecil` | `guardarfcjecil` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/hermanoscliente` | `cliente.hermanos` | `cliente` | R2, R6 |
| GET | `/hsreferidos` | `crud.hsreferidos.index` | `crud.hsreferidos.index` | Ninguno |
| POST | `/hsreferidos` | `crud.hsreferidos.store` | `crud.hsreferidos.index` | Ninguno |
| GET | `/hsreferidos/create` | `crud.hsreferidos.create` | `crud.hsreferidos.index` | Ninguno |
| DELETE | `/hsreferidos/{hsreferido}` | `crud.hsreferidos.destroy` | `crud.hsreferidos.index` | Ninguno |
| GET | `/hsreferidos/{hsreferido}` | `crud.hsreferidos.show` | `crud.hsreferidos.index` | Ninguno |
| PUT/PATCH | `/hsreferidos/{hsreferido}` | `crud.hsreferidos.update` | `crud.hsreferidos.index` | Ninguno |
| GET | `/hsreferidos/{hsreferido}/edit` | `crud.hsreferidos.edit` | `crud.hsreferidos.index` | Ninguno |
| GET | `/hubspot-owners` | `hubspot-owners.index` | — | Internos |
| POST | `/hubspot-owners` | `hubspot-owners.store` | — | Internos |
| GET | `/hubspot-owners/create` | `hubspot-owners.create` | — | Internos |
| POST | `/hubspot-owners/users/{user}/task-assignment` | `hubspot_owners.task_assignment` | — | Internos |
| DELETE | `/hubspot-owners/{hubspot_owner}` | `hubspot-owners.destroy` | — | Internos |
| PUT/PATCH | `/hubspot-owners/{hubspot_owner}` | `hubspot-owners.update` | — | Internos |
| GET | `/hubspot-owners/{hubspot_owner}/edit` | `hubspot-owners.edit` | — | Internos |
| POST | `/hubspot-owners/{owner}/assign-user` | `hubspot_owners.assign_user` | — | Internos |
| POST | `/hubspot/sync-client-owners` | `hubspot.sync-client-owners` | `administrador` | R1, R2 |
| POST | `/incluidofase1cilfcje` | `incluidofase1cilfcje` | `notCliente` + `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/informes/actividad/trello` | `systems-activity.manual-trello.index` | `administrador` | R1, R2 |
| POST | `/informes/actividad/trello` | `systems-activity.manual-trello.generate` | `administrador` | R1, R2 |
| GET | `/integraciones/trello/oauth/callback` | `trello.oauth.callback` | `administrador` | R1, R2 |
| GET | `/integraciones/trello/oauth/iniciar` | `trello.oauth.start` | `administrador` | R1, R2 |
| GET | `/invoices` | `invoices.index` | `administrador` | R1, R2 |
| POST | `/invoices` | `invoices.store` | `administrador` | R1, R2 |
| GET | `/invoices-user-data/{user}` | `invoices.user-data` | `administrador` | R1, R2 |
| GET | `/invoices-user-search` | `invoices.user-search` | `administrador` | R1, R2 |
| GET | `/invoices/create` | `invoices.create` | `administrador` | R1, R2 |
| DELETE | `/invoices/{invoice}` | `invoices.destroy` | `administrador` | R1, R2 |
| GET | `/invoices/{invoice}` | `invoices.show` | `administrador` | R1, R2 |
| PUT/PATCH | `/invoices/{invoice}` | `invoices.update` | `administrador` | R1, R2 |
| GET | `/invoices/{invoice}/edit` | `invoices.edit` | `administrador` | R1, R2 |
| GET | `/invoices/{invoice}/pdf` | `invoices.pdf` | `administrador` | R1, R2 |
| GET | `/lados` | `crud.lados.index` | `crud.lados.index` | R2, R9, R14 |
| POST | `/lados` | `crud.lados.store` | `crud.lados.index` | R2, R9, R14 |
| GET | `/lados/create` | `crud.lados.create` | `crud.lados.index` | R2, R9, R14 |
| DELETE | `/lados/{lado}` | `crud.lados.destroy` | `crud.lados.index` | R2, R9, R14 |
| GET | `/lados/{lado}` | `crud.lados.show` | `crud.lados.index` | R2, R9, R14 |
| PUT/PATCH | `/lados/{lado}` | `crud.lados.update` | `crud.lados.index` | R2, R9, R14 |
| GET | `/lados/{lado}/edit` | `crud.lados.edit` | `crud.lados.index` | R2, R9, R14 |
| GET | `/libraries` | `crud.libraries.index` | `crud.libraries.index` | R2, R8, R9 |
| POST | `/libraries` | `crud.libraries.store` | `crud.libraries.index` | R2, R8, R9 |
| GET | `/libraries/create` | `crud.libraries.create` | `crud.libraries.index` | R2, R8, R9 |
| DELETE | `/libraries/{library}` | `crud.libraries.destroy` | `crud.libraries.index` | R2, R8, R9 |
| GET | `/libraries/{library}` | `crud.libraries.show` | `crud.libraries.index` | R2, R8, R9 |
| PUT/PATCH | `/libraries/{library}` | `crud.libraries.update` | `crud.libraries.index` | R2, R8, R9 |
| GET | `/libraries/{library}/edit` | `crud.libraries.edit` | `crud.libraries.index` | R2, R8, R9 |
| GET | `/listLatestStripeData` | `listLatestStripeData` | `crud.stripeverify.index` | R2, R19 |
| POST | `/listLatestStripeData/export` | `exportdatastripeexcel` | `crud.stripeverify.index` | R2, R19 |
| POST | `/listLatestStripeData/getStripeAJAX` | `getStripeAJAX` | `crud.stripeverify.index` | R2, R19 |
| GET | `/listProjectsWithProductoField` | `listProjectsWithProductoField` | `administrador` | R1, R2 |
| GET | `/mensualindex` | `mensualindex` | `reportes.index` | R2, R14, R19 |
| GET | `/miscelaneos` | `crud.miscelaneos.index` | `crud.miscelaneos.index` | R2, R8, R9 |
| POST | `/miscelaneos` | `crud.miscelaneos.store` | `crud.miscelaneos.index` | R2, R8, R9 |
| GET | `/miscelaneos/create` | `crud.miscelaneos.create` | `crud.miscelaneos.index` | R2, R8, R9 |
| DELETE | `/miscelaneos/{miscelaneo}` | `crud.miscelaneos.destroy` | `crud.miscelaneos.index` | R2, R8, R9 |
| GET | `/miscelaneos/{miscelaneo}` | `crud.miscelaneos.show` | `crud.miscelaneos.index` | R2, R8, R9 |
| PUT/PATCH | `/miscelaneos/{miscelaneo}` | `crud.miscelaneos.update` | `crud.miscelaneos.index` | R2, R8, R9 |
| GET | `/miscelaneos/{miscelaneo}/edit` | `crud.miscelaneos.edit` | `crud.miscelaneos.index` | R2, R8, R9 |
| GET | `/mondayregistrar` | `mondayregistrar` | `crud.mondayreportes.index` | R2 |
| GET | `/mondayreportes` | `mondayreportes` | `crud.mondayreportes.index` | R2 |
| GET | `/my_status` | `my_status` | — | Todos |
| POST | `/news` | `news.store` | `news.admin` | R2 |
| GET | `/news/admin` | `news.admin` | `news.admin` | R2 |
| DELETE | `/news/{news}` | `news.destroy` | `news.admin` | R2 |
| PUT | `/news/{news}` | `news.update` | `news.admin` | R2 |
| GET | `/notifications` | `notifications.index` | — | Todos |
| POST | `/notifications/read-all` | `notifications.read-all` | — | Todos |
| PATCH | `/notifications/{notification}/read` | `notifications.read` | — | Todos |
| GET | `/olivo/{IDCliente}` | `arboles.olivo.index` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| POST | `/openfile` | `openfile` | `tree-files.view` | R2, R5, R6, R9, R10, R11, R12, R13, R16, R17, R18 |
| GET | `/pagospendientes` | `clientes.pagospendientes` | `cliente` | R2, R6 |
| GET | `/parentescos` | `crud.parentescos.index` | `crud.parentescos.index` | R2, R9, R14 |
| POST | `/parentescos` | `crud.parentescos.store` | `crud.parentescos.index` | R2, R9, R14 |
| GET | `/parentescos/create` | `crud.parentescos.create` | `crud.parentescos.index` | R2, R9, R14 |
| DELETE | `/parentescos/{parentesco}` | `crud.parentescos.destroy` | `crud.parentescos.index` | R2, R9, R14 |
| GET | `/parentescos/{parentesco}` | `crud.parentescos.show` | `crud.parentescos.index` | R2, R9, R14 |
| PUT/PATCH | `/parentescos/{parentesco}` | `crud.parentescos.update` | `crud.parentescos.index` | R2, R9, R14 |
| GET | `/parentescos/{parentesco}/edit` | `crud.parentescos.edit` | `crud.parentescos.index` | R2, R9, R14 |
| GET | `/pay` | `clientes.pay` | `cliente` | R2, R6 |
| POST | `/pay` | `procesarpay` | `cliente` | R2, R6 |
| POST | `/payfases` | `procesarpayfases` | `cliente` | R2, R6 |
| GET | `/permissions` | `crud.permissions.index` | `crud.permissions.index` | R2 |
| POST | `/permissions` | `crud.permissions.store` | `crud.permissions.index` | R2 |
| GET | `/permissions/create` | `crud.permissions.create` | `crud.permissions.index` | R2 |
| DELETE | `/permissions/{permission}` | `crud.permissions.destroy` | `crud.permissions.index` | R2 |
| GET | `/permissions/{permission}` | `crud.permissions.show` | `crud.permissions.index` | R2 |
| PUT/PATCH | `/permissions/{permission}` | `crud.permissions.update` | `crud.permissions.index` | R2 |
| GET | `/permissions/{permission}/edit` | `crud.permissions.edit` | `crud.permissions.index` | R2 |
| POST | `/procesar` | `clientes.procesar` | — | Todos |
| POST | `/procesar-pago-stripe` | `procesar-pago-stripe` | — | Todos |
| POST | `/procesarpaypal` | `procesarpaypal` | — | Todos |
| POST | `/procesarpaypalfases` | `procesarpaypalfases` | — | Todos |
| GET | `/prompttreena` | `treena.index` | `administrador` | R1, R2 |
| GET | `/referral-codes/validate` | `referral-codes.validate` | — | Todos |
| POST | `/registrarMD` | `registrarMD` | `crud.mondayreportes.index` | R2 |
| POST | `/registrarhermanoscliente` | `registrarhermanoscliente` | — | Todos |
| GET | `/registro` | `test.registro` | `administrador` | R1, R2 |
| GET | `/reportes/dashboard` | `reportes.dashboard` | `reportes.index` | R2, R14, R19 |
| GET | `/reports` | `crud.reports.index` | `crud.reports.index` | Ninguno |
| POST | `/reports` | `crud.reports.store` | `crud.reports.index` | Ninguno |
| GET | `/reports/create` | `crud.reports.create` | `crud.reports.index` | Ninguno |
| DELETE | `/reports/{report}` | `crud.reports.destroy` | `crud.reports.index` | Ninguno |
| GET | `/reports/{report}` | `crud.reports.show` | `crud.reports.index` | Ninguno |
| PUT/PATCH | `/reports/{report}` | `crud.reports.update` | `crud.reports.index` | Ninguno |
| GET | `/reports/{report}/edit` | `crud.reports.edit` | `crud.reports.index` | Ninguno |
| GET | `/request-audits` | `request-audits.index` | `administrador` | R1, R2 |
| GET | `/revisarcupon` | `revisarcupon` | `cliente` | R2, R6 |
| GET | `/role-ai/access` | `role-ai.access` | `notCliente` | Internos |
| GET | `/role-ai/knowledge` | `role-ai.knowledge.index` | `notCliente` | Internos |
| POST | `/role-ai/knowledge` | `role-ai.knowledge.store` | `notCliente` | Internos |
| POST | `/role-ai/messages` | `role-ai.messages.store` | `notCliente` | Internos |
| POST | `/role-ai/sessions` | `role-ai.sessions.store` | `notCliente` | Internos |
| GET | `/roles` | `crud.roles.index` | `crud.roles.index` | R2 |
| POST | `/roles` | `crud.roles.store` | `crud.roles.index` | R2 |
| GET | `/roles/create` | `crud.roles.create` | `crud.roles.index` | R2 |
| DELETE | `/roles/{role}` | `crud.roles.destroy` | `crud.roles.index` | R2 |
| GET | `/roles/{role}` | `crud.roles.show` | `crud.roles.index` | R2 |
| PUT/PATCH | `/roles/{role}` | `crud.roles.update` | `crud.roles.index` | R2 |
| GET | `/roles/{role}/edit` | `crud.roles.edit` | `crud.roles.index` | R2 |
| POST | `/route-clear` | — | `administrador` | R1, R2 |
| GET | `/salir` | `clientes.salir` | `cliente` | R2, R6 |
| GET | `/semanalindex` | `semanalindex` | `reportes.index` | R2, R14, R19 |
| GET | `/servicios` | `crud.servicios.index` | `crud.servicios.index` | R2 |
| POST | `/servicios` | `crud.servicios.store` | `crud.servicios.index` | R2 |
| GET | `/servicios-disponibles` | `service-store.index` | `cliente` | R2, R6 |
| GET | `/servicios-disponibles/{servicio}` | `service-store.show` | `cliente` | R2, R6 |
| POST | `/servicios-disponibles/{servicio}` | `service-store.purchase` | `cliente` | R2, R6 |
| GET | `/servicios/create` | `crud.servicios.create` | `crud.servicios.index` | R2 |
| GET | `/servicios/monday/boards` | `crud.servicios.monday.boards` | `crud.servicios.index` | R2 |
| GET | `/servicios/monday/groups` | `crud.servicios.monday.groups` | `crud.servicios.index` | R2 |
| DELETE | `/servicios/{servicio}` | `crud.servicios.destroy` | `crud.servicios.index` | R2 |
| GET | `/servicios/{servicio}` | `crud.servicios.show` | `crud.servicios.index` | R2 |
| PUT/PATCH | `/servicios/{servicio}` | `crud.servicios.update` | `crud.servicios.index` | R2 |
| GET | `/servicios/{servicio}/edit` | `crud.servicios.edit` | `crud.servicios.index` | R2 |
| POST | `/sincronizarhsytl` | `sincronizarhsytl` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/solicitudcupones` | `crud.solicitudcupones.index` | `crud.solicitudcupones.index` | R2 |
| POST | `/solicitudcupones` | `crud.solicitudcupones.store` | `crud.solicitudcupones.index` | R2 |
| GET | `/solicitudcupones/create` | `crud.solicitudcupones.create` | `crud.solicitudcupones.index` | R2 |
| DELETE | `/solicitudcupones/{solicitudcupone}` | `crud.solicitudcupones.destroy` | `crud.solicitudcupones.index` | R2 |
| GET | `/solicitudcupones/{solicitudcupone}` | `crud.solicitudcupones.show` | `crud.solicitudcupones.index` | R2 |
| PUT/PATCH | `/solicitudcupones/{solicitudcupone}` | `crud.solicitudcupones.update` | `crud.solicitudcupones.index` | R2 |
| GET | `/solicitudcupones/{solicitudcupone}/edit` | `crud.solicitudcupones.edit` | `crud.solicitudcupones.index` | R2 |
| GET | `/status` | `clientes.status` | `cliente` | R2, R6 |
| POST | `/storage-link` | — | `administrador` | R1, R2 |
| POST | `/storefile` | `storefile` | `tree-files.create` | R2, R5, R6, R9, R10, R11, R12, R13, R16, R17, R18 |
| GET | `/strategic-suggestion-attachments/{attachment}/download` | `strategic-suggestions.attachments.download` | — | Internos |
| GET | `/strategic-suggestions` | `strategic-suggestions.index` | — | Internos |
| POST | `/strategic-suggestions` | `strategic-suggestions.store` | — | Internos |
| GET | `/strategic-suggestions/create` | `strategic-suggestions.create` | — | Internos |
| DELETE | `/strategic-suggestions/{strategic_suggestion}` | `strategic-suggestions.destroy` | — | Internos |
| GET | `/strategic-suggestions/{strategic_suggestion}` | `strategic-suggestions.show` | — | Internos |
| PUT | `/strategic-suggestions/{strategic_suggestion}` | `strategic-suggestions.update` | — | Internos |
| POST | `/strategic-suggestions/{suggestion}/reply` | `strategic-suggestions.reply` | — | Internos |
| POST | `/stripefind` | `stripefind` | `crud.stripeverify.index` | R2, R19 |
| POST | `/stripegetidpago` | `stripegetidpago` | `crud.stripeverify.index` | R2, R19 |
| POST | `/stripeupdatedata` | `stripeupdatedata` | `crud.stripeverify.index` | R2, R19 |
| GET | `/stripeverify` | `stripeverify` | `crud.stripeverify.index` | R2, R19 |
| GET | `/support-ticket` | `clientes.support-ticket.create` | `cliente` | R2, R6 |
| POST | `/support-ticket` | `clientes.support-ticket.store` | `cliente` | R2, R6 |
| GET | `/t_files` | `crud.t_files.index` | `crud.t_files.index` | R2, R9, R14 |
| POST | `/t_files` | `crud.t_files.store` | `crud.t_files.index` | R2, R9, R14 |
| GET | `/t_files/create` | `crud.t_files.create` | `crud.t_files.index` | R2, R9, R14 |
| DELETE | `/t_files/{t_file}` | `crud.t_files.destroy` | `crud.t_files.index` | R2, R9, R14 |
| GET | `/t_files/{t_file}` | `crud.t_files.show` | `crud.t_files.index` | R2, R9, R14 |
| PUT/PATCH | `/t_files/{t_file}` | `crud.t_files.update` | `crud.t_files.index` | R2, R9, R14 |
| GET | `/t_files/{t_file}/edit` | `crud.t_files.edit` | `crud.t_files.index` | R2, R9, R14 |
| GET | `/tableros` | `kanban.index` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| POST | `/tableros` | `kanban.store` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| GET | `/tableros/organigrama` | `kanban.org-chart` | `tasks.view` + `administrador` | R1, R2 |
| POST | `/tableros/organigrama` | `kanban.org-chart.store` | `tasks.view` + `administrador` | R1, R2 |
| DELETE | `/tableros/organigrama/{line}` | `kanban.org-chart.destroy` | `tasks.view` + `administrador` | R1, R2 |
| DELETE | `/tableros/{board}` | `kanban.destroy` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| GET | `/tableros/{board}` | `kanban.show` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| PATCH | `/tableros/{board}` | `kanban.update` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| DELETE | `/tableros/{board}/cards/{card}` | `kanban.cards.destroy` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| PATCH | `/tableros/{board}/cards/{card}` | `kanban.cards.update` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| PATCH | `/tableros/{board}/cards/{card}/move` | `kanban.cards.move` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| POST | `/tableros/{board}/columns` | `kanban.columns.store` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| PATCH | `/tableros/{board}/columns/order` | `kanban.columns.reorder` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| DELETE | `/tableros/{board}/columns/{column}` | `kanban.columns.destroy` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| PATCH | `/tableros/{board}/columns/{column}` | `kanban.columns.update` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| POST | `/tableros/{board}/columns/{column}/cards` | `kanban.cards.store` | `tasks.view` | R1, R2, R3, R4, R7, R8, R9, R14, R15, R19 |
| GET | `/tasks` | `tasks.index` | — | Internos |
| GET | `/tasks/admin` | `tasks.admin.index` | `administrador` | R1, R2 |
| POST | `/tasks/admin` | `tasks.admin.store` | `administrador` | R1, R2 |
| DELETE | `/tasks/admin/bulk` | `tasks.admin.bulk-destroy` | `administrador` | R1, R2 |
| DELETE | `/tasks/admin/bulk-filtered` | `tasks.admin.bulk-destroy-filtered` | `administrador` | R1, R2 |
| POST | `/tasks/admin/bulk-reassign-contacts` | `tasks.admin.bulk-reassign-contacts` | `administrador` | R1, R2 |
| GET | `/tasks/admin/create` | `tasks.admin.create` | `administrador` | R1, R2 |
| POST | `/tasks/admin/daily-workflow/force` | `tasks.admin.daily-workflow.force` | `administrador` | R1, R2 |
| POST | `/tasks/admin/generate-daily` | `tasks.admin.generate-daily` | `administrador` | R1, R2 |
| GET | `/tasks/admin/reports` | `tasks.admin.reports` | `administrador` | R1, R2 |
| GET | `/tasks/admin/reports/export` | `tasks.admin.reports.export` | `administrador` | R1, R2 |
| GET | `/tasks/admin/summary` | `tasks.admin.summary` | `administrador` | R1, R2 |
| DELETE | `/tasks/admin/{task}` | `tasks.admin.destroy` | `administrador` | R1, R2 |
| PUT | `/tasks/admin/{task}` | `tasks.admin.update` | `administrador` | R1, R2 |
| GET | `/tasks/admin/{task}/edit` | `tasks.admin.edit` | `administrador` | R1, R2 |
| GET | `/tasks/{task}` | `tasks.show` | — | Internos |
| PUT | `/tasks/{task}/complete-internal` | `tasks.completeInternal` | — | Internos |
| POST | `/tasks/{task}/flow` | `tasks.submitFlow` | — | Internos |
| PUT | `/tasks/{task}/sales-tracking` | `tasks.updateSalesTracking` | — | Internos |
| POST | `/tasks/{task}/sync-contact` | `tasks.syncContact` | — | Internos |
| GET | `/teamleader/callback` | `teamleader.callback` | `administrador` | R1, R2 |
| GET | `/teamleader/contacts` | `teamleader.contacts.index` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/contacts/{id}` | `teamleader.contacts.show` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| POST | `/teamleader/contacts/{id}/documents/import` | `teamleader.contacts.documents.import` | `tl.view` + `administrador` | R1, R2 |
| POST | `/teamleader/contacts/{id}/refresh` | `teamleader.contacts.refresh` | `tl.view` + `administrador` | R1, R2 |
| GET | `/teamleader/deals/{id}` | `teamleader.deals.show` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/documents` | `teamleader.documents.index` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/documents/{id}/download` | `teamleader.documents.download` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/invoices` | `teamleader.invoices.index` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/invoices/{id}` | `teamleader.invoices.show` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/invoices/{id}/pdf` | `teamleader.invoices.pdf` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/jobs` | `teamleader.jobs.index` | `administrador` | R1, R2 |
| DELETE | `/teamleader/jobs/failed` | `teamleader.jobs.failed.clear` | `administrador` | R1, R2 |
| POST | `/teamleader/jobs/failed/retry` | `teamleader.jobs.failed.retry` | `administrador` | R1, R2 |
| DELETE | `/teamleader/jobs/failed/{failedJob}` | `teamleader.jobs.failed.clear-one` | `administrador` | R1, R2 |
| POST | `/teamleader/jobs/failed/{failedJob}/retry` | `teamleader.jobs.failed.retry-one` | `administrador` | R1, R2 |
| POST | `/teamleader/jobs/work` | `teamleader.jobs.work` | `administrador` | R1, R2 |
| GET | `/teamleader/projects` | `teamleader.projects.index` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/projects/{id}` | `teamleader.projects.show` | `tl.view` | R1, R2, R8, R9, R14, R19 |
| GET | `/teamleader/redirect` | `teamleader.redirect` | `administrador` | R1, R2 |
| GET | `/teamleader/success` | `teamleader.success` | — | Internos |
| GET | `/testcorreos` | `testcorreos` | `administrador` | R1, R2 |
| POST | `/testcorreos` | `sendcorreo` | `administrador` | R1, R2 |
| GET | `/tree` | `clientes.tree` | `cliente` | R2, R6 |
| GET | `/tree/user/{user}` | `arboles.tree.user` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/tree/{IDCliente}` | `arboles.tree.index` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/tree/{IDCliente}/branch/{id}/{gen}/{parent}` | `arboles.tree.branch` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| POST | `/tree/{IDCliente}/line-color/{id}` | `arboles.tree.line-color` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/tree/{IDCliente}/person/{id}/detail` | `arboles.tree.person-detail` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| GET | `/tree/{IDCliente}/{id}/{gen}/{parent}` | `arboles.tree.treepart` | `genealogista` | R2, R5, R9, R10, R11, R12, R13, R16, R17, R18, R19 |
| POST | `/updatefiletype` | `updatefiletype` | `tree-files.edit` | R2, R5, R6, R9, R10, R11, R12, R13, R16, R17, R18 |
| POST | `/updatetreena` | `treena.update` | `administrador` | R1, R2 |
| GET | `/users` | `crud.users.index` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/users` | `crud.users.store` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/create` | `crud.users.create` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/status/{id}` | `getuserstatus` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| DELETE | `/users/{user}` | `crud.users.destroy` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/{user}` | `crud.users.show` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| PUT/PATCH | `/users/{user}` | `crud.users.update` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/{user}/cos-refresh-status` | `crud.users.cos-refresh-status` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/{user}/edit` | `crud.users.edit` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/{user}/internal-chat` | `crud.users.internal-chat.index` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/users/{user}/internal-chat` | `crud.users.internal-chat.store` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/{user}/internal-chat/attachments/{attachment}` | `crud.users.internal-chat.attachments.download` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/{user}/internal-chat/mentions` | `crud.users.internal-chat.mentions` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| GET | `/users/{user}/notes` | `crud.users.notes.index` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/users/{user}/notes` | `crud.users.notes.store` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| PATCH | `/users/{user}/owner` | `crud.users.update-owner` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| PATCH | `/users/{user}/phase-payment-visibility` | `crud.users.update-phase-payment-visibility` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/users/{user}/process-associations` | `crud.users.process-associations` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/users/{user}/support-ticket` | `crud.users.support-ticket` | `crud.users.index` | R1, R2, R3, R4, R7, R8, R9, R14, R19 |
| POST | `/users/{user}/sync-deals` | `users.sync-deals` | — | Internos |
| POST | `/view-clear` | — | `administrador` | R1, R2 |
| GET | `/viewcomprobante/{id}` | `viewcomprobante` | `crud.comprobantes.index` | R2, R19 |
| GET | `/viewcomprobantecliente/{id}` | `viewcomprobantecliente` | — | Todos |
| GET | `/viewfile/{id}` | `viewfile` | `crud.files.index` | R2, R5, R8, R9, R10, R11, R12, R13, R14, R16, R17, R18 |
| GET | `/vinculaciones` | `cliente.vinculaciones` | `cliente` | R2, R6 |
| GET | `/vinculaciones/{id}` | `cliente.regvinculaciones` | — | Todos |
| GET | `/vmodal` | `test.vmodal` | `administrador` | R1, R2 |
| GET | `/whatsapp/numbers` | `whatsapp.numbers.index` | `administrador` | R1, R2 |
| POST | `/whatsapp/numbers` | `whatsapp.numbers.store` | `administrador` | R1, R2 |
| DELETE | `/whatsapp/numbers/{number}` | `whatsapp.numbers.destroy` | `administrador` | R1, R2 |
| PUT/PATCH | `/whatsapp/numbers/{number}` | `whatsapp.numbers.update` | `administrador` | R1, R2 |
| GET | `/whatsapp/url` | `whatsapp.url.form` | `administrador` | R1, R2 |
| POST | `/whatsapp/url` | `whatsapp.url.store` | `administrador` | R1, R2 |

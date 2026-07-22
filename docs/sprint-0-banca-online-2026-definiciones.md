# Sprint 0: definiciones operativas para Banca Online 2026

Generado: 2026-07-10

## Objetivo del sprint

Convertir la vision del CEO en reglas claras antes de implementar cambios grandes.

Este sprint no busca redisenar pantallas todavia. Busca cerrar:

1. Que es un candidato.
2. Que es un representado.
3. Cuales son los estados del expediente.
4. Que lenguaje debe usar la plataforma.
5. Que contratos debe pedir Juridico.
6. Que datos actuales del sistema soportan esas decisiones.

Decision de arquitectura incorporada: las reglas editables por negocio, direccion, ventas, operaciones o Juridico no deben vivir en archivos de configuracion. Deben vivir en base de datos y actualizarse desde un panel interno de la app.

Los archivos de configuracion solo pueden usarse como soporte tecnico temporal o como semilla inicial de datos. No deben ser la fuente de verdad para estados, rutas, textos, documentos, contratos, precios, paquetes, recomendaciones ni reglas de elegibilidad.

## Hallazgos tecnicos actuales

### Campos y datos ya disponibles

| Dato | Donde existe | Uso actual |
|---|---|---|
| Rol `Cliente` | `users` + Spatie roles | Identifica usuario cliente de la plataforma |
| `users.pay` | `users` | Controla pago/getinfo/arbol en flujo principal |
| `users.contrato` | `users` | Controla si puede avanzar al arbol |
| `users.servicio` | `users` | Servicio/nacionalidad solicitada |
| `users.cosready` | `users` | Permite mostrar menu "Estatus de mi Proceso" |
| `users.arraycos` | `users` | Cache de estados COS calculados |
| `compras.pagado` | `compras` | Indica pago completado o pendiente |
| `compras.source` | `compras` | Distingue origen, por ejemplo `banca_online_2026` |
| `compras.metadata` | `compras` | Guarda plan, paquete, pais, token, componentes y pago |
| `servicios.metadata` | `servicios` | Define planes/paquetes/componentes de Banca Online |
| `negocios` | tabla `negocios` | Datos de procesos/deals sincronizados |
| `agclientes` | tabla `agclientes` | Base genealogica del cliente |

### Flujo actual relevante

En el flujo principal de registro actual:

```text
/registerv2 -> /pay -> /getinfo -> /contrato -> /tree
```

Reglas actuales:

1. El registro inicial entra por `/registerv2`.
2. Si `pay = 0`, el cliente va a `/pay`.
3. Si `pay = 1` o `pay = 3`, el cliente va a `/getinfo`.
4. Al completar `/getinfo`, el sistema cambia a `pay = 2`.
5. Si no firmo contrato, va a `/contrato`.
6. Si firmo contrato, puede entrar al arbol.

Decision: Banca Online no reemplaza este flujo base. El flujo de registro principal sigue siendo por fases.

En Banca Online 2026:

1. El usuario puede entrar desde fuera de la app o desde dentro de la app.
2. La Banca Online funciona como modulo de cotizacion, seleccion de alcance y activacion de servicios/procesos.
3. Si entra desde fuera, puede actuar como experiencia de captacion/cotizacion antes de completar el flujo base.
4. Si entra desde dentro, debe usar el estado real del cliente, COS, compras previas y datos del expediente.
5. Cuando un cliente arranca otros procesos, se le cotiza o selecciona su cotizacion en Banca Online.
6. Actualmente el usuario entra por pais/nacionalidad.
7. Ve rutas estrategicas.
8. Elige una modalidad.
9. Si no existe usuario, se crea con:
   - `pay = 0`
   - `contrato = 0`
   - `cosready = 1`
   - rol `Cliente`
   - permisos `pay.services` y `finish.register`
10. Se crea una compra con `source = banca_online_2026`.
11. Al pagar, la compra pasa a `pagado = 1`.
12. El usuario queda con:
   - `pay = 1`
   - `contrato = 0`
   - `pago_registro` actualizado

Decision: si Banca Online genera o cobra una activacion inicial, debe respetar el proceso posterior actual: `/getinfo` y luego `/contrato`.

### Entradas de Banca Online

| Entrada | Usuario tipico | Objetivo | Tratamiento recomendado |
|---|---|---|---|
| Publica/externa | Visitor o candidato | Cotizar, orientar y convertir | Captacion, explicacion, seleccion de alcance, registro/pago |
| Interna/app | Candidato autenticado | Completar o continuar una activacion | Respetar `pay`, `/getinfo`, `/contrato` |
| Interna/app | Representado | Cotizar otros procesos o servicios adicionales | Usar expediente, COS, compras previas y cotizaciones |

### Papel correcto de Banca Online

Banca Online debe ser un modulo de:

1. Cotizacion.
2. Seleccion de alcance.
3. Activacion de servicios o procesos.
4. Recomendacion estrategica.
5. Formalizacion del siguiente servicio/proceso.

Banca Online no debe ser:

1. Sustituto completo de `/registerv2`.
2. Sustituto de `/getinfo`.
3. Sustituto del contrato.
4. Sustituto del COS.
5. Una tienda generica desconectada del expediente.

### Hallazgo importante

`cosready = 1` no debe usarse por si solo para determinar que alguien es representado.

Motivo: Banca Online puede crear usuarios nuevos con `cosready = 1`, pero todavia con `pay = 0` y `contrato = 0`.

Por tanto, `cosready` sirve para visibilidad del COS, pero no como criterio unico de representacion profesional.

### Hallazgo sobre configuracion actual de Banca Online

La app ya tiene un panel administrativo para Banca Online 2026 en:

1. `AdminBancaOnlineController`
2. `resources/views/admin/banca-online/index.blade.php`
3. registros `servicios` con `categoria = banca_online_2026`
4. `servicios.metadata` para paquetes, beneficios, cuotas y componentes

Esto es positivo porque ya existe una base para gestionar contenido desde la app.

Auditoria rapida de base de datos local:

1. Existen 44 registros en `servicios` para `categoria = banca_online_2026`.
2. De esos registros, 35 son componentes y 9 son paquetes segun `metadata.record_type`.
3. Existen 6 registros en `compras` con `source = banca_online_2026`.
4. Existe tabla `settings`, pero es generica y no parece tener un modulo especifico para administrar Banca Online.
5. No se detectaron tablas especializadas tipo `banca_strategy_routes`, `banca_case_statuses`, `banca_strategy_documents` o similares.

Pero el servicio `BancaOnlineCatalog` todavia lee desde `config('banca_online...')` para:

1. paises/nacionalidades disponibles
2. rutas estrategicas
3. paquetes/modalidades base
4. reglas iniciales de cuotas
5. categoria/source del flujo

Conclusion: no se debe crear otro archivo de configuracion para estados o reglas. La tarea correcta es ampliar el panel administrativo existente y mover estas fuentes a base de datos.

Conclusion complementaria: tampoco conviene duplicar el catalogo que ya existe en `servicios`. Hay que reutilizarlo como base comercial y crear estructura nueva solo para las reglas estrategicas que hoy no tienen tabla clara.

## Definiciones propuestas

### Candidato

Persona interesada en contratar o activar un servicio, pero que todavia no tiene contrato firmado.

Regla operativa propuesta:

```text
Usuario registrado o identificado como interesado
Y sin contrato firmado
```

En campos actuales:

1. Existe usuario pero `pay` es `null` o `0`; o
2. existe una compra/activacion pendiente con `pagado = 0`; o
3. existe pago completado pero falta `/getinfo` o contrato; o
4. `users.contrato != 1`.

Nota: una persona anonima que solo entra a ver no es candidato. Es `visitor`.

### Representado

Persona que ya pago y firmo contrato. Puede tener expediente, arbol o COS pendientes, pero ya tiene relacion formal de representacion.

Regla operativa propuesta para version 1:

```text
Usuario con rol Cliente
Y pago inicial completado
Y contrato firmado
```

En campos actuales:

1. `user->hasRole('Cliente')`
2. `users.pay = 2` o equivalente operativo aprobado
3. `users.contrato = 1`

Senales complementarias:

1. Tiene informacion complementaria completada.
2. Tiene registro raiz en `agclientes` con `IDCliente = users.passport`.
3. Tiene `negocios` sincronizados.
4. Tiene `arraycos` con procesos validos.
5. Tiene compras pagadas relacionadas con servicios profesionales.

Recomendacion: estas senales complementarias deben fortalecer el estado operativo del representado, pero no reemplazar el criterio principal: pago + contrato firmado.

## Estados del expediente

### Matriz version 1

| Estado interno propuesto | Condicion actual aproximada | Perfil | Experiencia recomendada |
|---|---|---|---|
| `visitor` | Persona anonima que solo entra a ver | Visitante | Bienvenida, quienes somos, como trabajamos |
| `candidate_registered` | Usuario existe, rol Cliente, `pay` null/0 | Candidato | Activar analisis inicial |
| `candidate_activation_pending_payment` | Compra pendiente `pagado = 0` | Candidato | Completar pago de activacion |
| `candidate_paid_pending_info` | `pay = 1` o `pay = 3` | Candidato | Completar informacion `/getinfo` |
| `candidate_pending_contract` | `pay = 2` y `contrato != 1` | Candidato | Firmar contrato |
| `represented_initial` | `pay = 2` y `contrato = 1` | Representado | Mostrar expediente, arbol, COS si aplica |
| `represented_with_cos` | Representado + `arraycos` valido o `cosready = 1` | Representado | Mostrar COS, recomendaciones y siguiente accion |
| `represented_pending_document` | Representado + solicitud documental pendiente | Representado | Mostrar documento faltante y solucion |
| `represented_pending_professional_activation` | Compra de servicio profesional pendiente `pagado = 0` | Representado | Activar servicio profesional |
| `represented_service_active` | Compra profesional `pagado = 1` + contrato de servicio firmado | Representado | Servicio incorporado al expediente |

### Decision operativa

Para Banca Online, representado empieza cuando existe pago completado y contrato firmado. El pago sin contrato es una fase de candidato/interesado pendiente de formalizacion.

## Matriz de situacion inicial para Banca Online

La primera pregunta debe ser situacion del expediente, no nacionalidad.

Opciones aprobables para Sprint 1:

| Opcion visible | Estado interno sugerido | Perfil por defecto |
|---|---|---|
| Todavia no he iniciado mi expediente | `not_started` | Candidato |
| Ya contrate el analisis inicial | `initial_analysis_contracted` | Candidato/Representado en transicion |
| Ya presentamos mi solicitud | `application_submitted` | Representado |
| Mi expediente esta siendo estudiado | `under_review` | Representado |
| He recibido un requerimiento | `requirement_received` | Representado |
| Mi solicitud fue denegada | `denied` | Representado |
| Ya soy representado de Sefar Universal | `represented_active` | Representado |

### Mapeo inicial a rutas estrategicas

| Situacion | Ruta sugerida |
|---|---|
| Todavia no he iniciado mi expediente | Primera ruta: solicitud estrategica |
| Ya contrate el analisis inicial | Primera ruta o continuar flujo actual segun estado |
| Ya presentamos mi solicitud | Segunda ruta: administrativo |
| Mi expediente esta siendo estudiado | Segunda ruta: administrativo |
| He recibido un requerimiento | Segunda ruta: administrativo |
| Mi solicitud fue denegada | Tercera ruta: judicial |
| Ya soy representado de Sefar Universal | Depende de COS/estado real |

### Decision pendiente

La opcion "Ya soy representado de Sefar Universal" no debe llevar automaticamente a una ruta. Debe intentar usar datos internos:

1. `arraycos`
2. `negocios`
3. compras previas
4. documentos pendientes
5. estado de nacionalidad

Si no hay datos suficientes, debe pedir contexto adicional.

## Panel de configuracion estrategica

### Objetivo

Crear una seccion administrativa dentro de la app para que el equipo pueda actualizar la Banca Online sin tocar codigo.

Este panel debe permitir editar:

1. Estados del expediente.
2. Situaciones visibles para el cliente.
3. Paises/nacionalidades disponibles.
4. Rutas estrategicas.
5. Reglas de recomendacion.
6. Documentos habituales por ruta.
7. Soluciones cuando falten documentos.
8. Contratos requeridos por servicio profesional.
9. Textos publicos de las pantallas principales.
10. Paquetes/alcances, beneficios, precios y cuotas.

### Entidades administrables propuestas

| Entidad | Fuente recomendada | Para que sirve | Accion |
|---|---|---|---|
| Catalogo comercial | `servicios` + `servicios.metadata` | Componentes, paquetes, precios, beneficios y cuotas | Reutilizar |
| Activaciones/compras | `compras` + `compras.metadata` | Compra pendiente/pagada, token, plan, paquete, pago | Reutilizar |
| Ajustes simples | `settings` o tabla dedicada | Flags generales, textos globales o valores pequenos | Evaluar antes de crear |
| Estados del expediente | tabla nueva si no existe equivalente | Estados internos y visibles del expediente | Crear si no hay tabla reusable |
| Rutas estrategicas | tabla nueva o `servicios.metadata` si se decide mantener JSON | Primera, segunda, tercera ruta y futuras rutas | Preferible tabla propia |
| Reglas de recomendacion | tabla nueva | Mapeo estado + pais + perfil -> ruta recomendada | Crear |
| Documentos por estrategia | tabla nueva o integrar con `document_requests` si aplica | Documentos habituales por ruta/pais | Crear o integrar |
| Soluciones documentales | tabla nueva vinculada a `servicios` | Servicio o texto de solucion si falta un documento | Crear |
| Contratos por servicio | tabla nueva vinculada a `servicios` | Contrato requerido por servicio profesional | Crear |
| Textos publicos | `settings` estructurado o tabla dedicada | Textos publicos, CTAs y explicaciones aprobadas | Evaluar |

### Pantallas administrativas necesarias

1. Configuracion general de Banca Online.
2. Estados del expediente.
3. Situaciones iniciales del cliente.
4. Rutas estrategicas.
5. Reglas de recomendacion.
6. Documentos y soluciones.
7. Contratos por servicio.
8. Textos visibles/glosario.
9. Paquetes, precios, beneficios y cuotas.

### Reglas de seguridad

1. Solo administradores o usuarios con permiso especifico deben editar estas reglas.
2. Todo cambio debe guardar usuario, fecha y valor anterior cuando sea posible.
3. El cliente nunca debe ver campos internos, notas administrativas ni condiciones ocultas.
4. Los textos internos para ventas/operaciones deben estar separados de los textos publicos.
5. La publicacion de rutas, contratos o precios debe poder activarse/desactivarse sin borrar datos.

### Migracion desde el estado actual

1. Mantener el panel actual de Banca Online como punto de partida.
2. Reutilizar `servicios` para catalogo, paquetes, precios, beneficios y cuotas.
3. Reutilizar `compras` para activaciones, pagos y metadata transaccional.
4. Auditar si `settings` sirve para textos/flags o si conviene una tabla dedicada.
5. Crear tablas nuevas solo para estados, rutas, reglas, documentos, soluciones y contratos que no tengan estructura existente.
6. Poblar esas tablas con los valores actuales.
7. Cambiar `BancaOnlineCatalog` para leer primero de base de datos.
8. Dejar cualquier archivo `config` solo como respaldo temporal durante la migracion.
9. Retirar el boton/flujo de "sincronizar catalogo base" cuando la app sea la fuente de verdad.

## Glosario de lenguaje profesional

### Terminos a evitar en UI cliente

| Evitar | Usar |
|---|---|
| Comprar | Contratar |
| Compra | Activacion |
| Producto | Servicio profesional |
| Carrito | Solicitud de activacion |
| Pedido | Activacion de servicio |
| Checkout | Formalizacion de activacion |
| Pagar producto | Completar pago de activacion |
| Total a pagar | Importe de activacion |
| Pago recibido | Activacion recibida |
| Elegir modalidad | Seleccionar alcance |
| Modalidad | Alcance profesional |
| Plan | Estrategia |
| Paquete | Alcance |
| Venta | Contratacion |

### Terminos que si refuerzan identidad

1. Expediente.
2. Representado.
3. Estrategia.
4. Actuacion profesional.
5. Servicio profesional.
6. Activacion.
7. Alcance.
8. Equipo asignado.
9. Documentacion requerida.
10. Formalizacion.
11. Proteccion de datos.
12. Representacion.

### Lugares detectados para cambio de lenguaje

| Archivo | Texto actual | Cambio recomendado |
|---|---|---|
| `resources/views/banca-online/configurator.blade.php` | `Elige tu modalidad` | `Selecciona el alcance profesional` |
| `resources/views/banca-online/configurator.blade.php` | `Elegir modalidad` | `Seleccionar alcance` |
| `resources/views/banca-online/configurator.blade.php` | `Continuar al pago` | `Continuar con la activacion` |
| `resources/views/banca-online/payment.blade.php` | `Pago seguro` | `Pago de activacion seguro` |
| `resources/views/banca-online/payment.blade.php` | `Total a pagar` | `Importe de activacion` |
| `resources/views/banca-online/payment.blade.php` | `Pagar ahora` | `Completar activacion` |
| `resources/views/banca-online/payment.blade.php` | `Pago unico` | `Activacion completa` |
| `resources/views/banca-online/payment.blade.php` | `Pago por cuotas` | `Activacion con cuotas` |
| `resources/views/banca-online/thank-you.blade.php` | `Pago recibido` | `Activacion recibida` |
| `public/js/banca-online-2026.js` | `Preparando pago...` | `Preparando activacion...` |
| `public/js/banca-online-2026.js` | `Completar contratacion` | `Formalizar activacion` |

## Contratos por servicio

### Matriz legal inicial

| Servicio profesional | Contrato requerido | Prioridad Juridico |
|---|---|---|
| Analisis inicial | Contrato de analisis inicial digital | P0 |
| Investigacion documental | Contrato de investigacion documental | P1 |
| Investigacion genealogica | Contrato de investigacion genealogica | P1 |
| Estrategia administrativa | Contrato de estrategia administrativa | P1 |
| Estrategia judicial | Contrato de estrategia judicial | P1 |
| Consultas especializadas | Contrato/condiciones de consulta especializada | P2 |
| Gestion documental | Contrato de gestion documental | P2 |
| Servicios mixtos o personalizados | Anexo/condiciones particulares | P2 |

### Flujo legal objetivo

```text
Comprender alcance
-> aceptar condiciones particulares
-> firmar contrato del servicio
-> aceptar proteccion de datos
-> completar pago de activacion
-> servicio activado
-> asignacion al equipo
```

### Decision pendiente

Definir si el contrato se firma antes o despues del pago.

Recomendacion del CEO: antes del pago.

Recomendacion operativa: contrato antes del cobro para servicios profesionales nuevos; en caso de pago fallido, el servicio no se activa.

## Fuente de datos para recomendador estrategico

### Version 1: reglas simples

Usar:

1. Situacion seleccionada por usuario.
2. Nacionalidad/pais seleccionado.
3. Si existe usuario, revisar `pay`, `contrato`, `servicio`.
4. Si existe representado, revisar COS/`arraycos`.
5. Si hay compras previas de Banca Online, evitar duplicar familia de plan.

### Version 2: reglas con expediente

Usar:

1. `negocios.servicio_solicitado2`
2. campos de etapa juridica/genealogica
3. `arraycos.currentStepName`
4. documentos pendientes
5. compras activas/pagadas

### No hacer aun

No introducir IA para decidir estrategia en Sprint 1.

Primero debe existir matriz de reglas aprobada por negocio/Juridico.

## Issues propuestos para Sprint 0

### Producto / negocio

1. Aprobar definicion de candidato.
2. Aprobar definicion de representado.
3. Aprobar matriz de estados de expediente.
4. Aprobar opciones visibles de situacion actual.
5. Aprobar mapeo inicial situacion -> ruta estrategica.

### Legal

1. Revisar contrato actual de analisis inicial.
2. Crear/actualizar contrato digital de analisis inicial.
3. Crear matriz de contratos por servicio profesional.
4. Definir si cada servicio requiere contrato independiente o anexo.
5. Confirmar orden legal: contrato -> datos -> pago -> activacion.

### UX / contenido

1. Aprobar glosario de lenguaje.
2. Reescribir textos visibles de Banca Online con lenguaje de expediente.
3. Redactar explicacion corta para las tres rutas.
4. Redactar pantalla "Por que recomendamos esta estrategia".
5. Redactar textos de documentos faltantes y solucion.

### Backend / datos

1. Crear servicio futuro `ClientStageResolver`.
2. Reutilizar `servicios` y `compras` para catalogo y activaciones de Banca Online.
3. Definir que datos se guardaran en `settings` y cuales necesitan tablas propias.
4. Crear tablas administrables solo para estados, rutas, reglas, documentos, soluciones y contratos que no existan.
5. Ampliar el panel admin de Banca Online para editar esas entidades desde la app.
6. Migrar `BancaOnlineCatalog` para leer de base de datos y no de archivos `config`.
7. Agregar eventos de medicion del flujo.
8. Preparar metadata de `compras` para guardar situacion seleccionada.
9. Preparar metadata de `compras` para guardar contrato asociado.
10. Agregar auditoria de cambios administrativos en configuracion estrategica.

## Criterios de cierre de Sprint 0

Sprint 0 queda cerrado cuando existan decisiones aprobadas para:

1. Definicion de candidato.
2. Definicion de representado.
3. Estados de expediente.
4. Opciones de situacion inicial.
5. Mapeo situacion -> ruta.
6. Glosario de lenguaje.
7. Contrato de analisis inicial a revisar por Juridico.
8. Lista de contratos por servicio.
9. Modelo de panel administrativo aprobado para reemplazar reglas en archivos de configuracion.

## Cierre Sprint 0

### Decisiones ya cerradas

1. El flujo base actual se mantiene: `/registerv2 -> /pay -> /getinfo -> /contrato -> /tree`.
2. Banca Online no reemplaza el flujo base; lo complementa como modulo de cotizacion, seleccion de alcance y activacion.
3. Banca Online puede llamarse desde fuera de la app o desde dentro de la app.
4. Si entra desde fuera, debe servir para captar, orientar, cotizar y llevar al flujo correspondiente.
5. Si entra desde dentro, debe usar el estado real del usuario, compras previas, COS y expediente.
6. Cuando el cliente arranca otros procesos, Banca Online debe permitir cotizar o seleccionar una cotizacion.
7. `visitor` es quien entra y solo ve.
8. `candidate` es el interesado sin contrato firmado.
9. `represented` requiere pago completado y contrato firmado.
10. `cosready = 1` no convierte por si solo a un cliente en representado.
11. Las reglas editables deben gestionarse desde la app/base de datos, no desde archivos de configuracion.
12. `servicios` y `compras` son la base existente para catalogo y activaciones; no se debe duplicar ese catalogo.

### Pendientes que no bloquean Sprint 1

1. Juridico debe validar contrato de analisis inicial y contratos por servicio.
2. Negocio debe aprobar textos finales de rutas y explicaciones.
3. Operaciones debe validar matriz completa de documentos por estrategia.
4. Administracion debe confirmar que datos van en `settings` y cuales en tablas especializadas.

### Pendientes que si condicionan desarrollo

1. Definir como identificar una cotizacion existente cuando el cliente entra desde dentro de la app.
2. Definir si una cotizacion se modela solo como `compras.metadata`, como `servicios.metadata`, o con tabla propia.
3. Definir si los contratos de servicios adicionales se firman antes o despues del pago.
4. Definir el permiso administrativo exacto para editar reglas de Banca Online.

### Sprint 1 recomendado

Sprint 1 debe implementar una Banca Online guiada y contextual sin romper el flujo base.

Prioridad P0:

1. Guardar `entry_point` en `compras.metadata`: `external`, `internal`, `admin_quote`, `cos`, u otro valor aprobado.
2. Guardar `selected_case_status` en `compras.metadata`.
3. Guardar `quote_context` en `compras.metadata` cuando venga desde dentro de la app.
4. Mostrar primera pregunta de situacion actual antes de nacionalidad/ruta.
5. Respetar `ClientStageResolver` para diferenciar visitor, candidate y represented.

Prioridad P1:

1. Crear pantalla "Por que recomendamos esta estrategia".
2. Cambiar lenguaje visible principal: comprar/producto/carrito -> contratar/servicio/activacion.
3. Mostrar una recomendacion principal y dejar alternativas como secundarias.
4. Evitar que un representado contrate duplicado un servicio/proceso ya activo.

Prioridad P2:

1. Registrar eventos de medicion del flujo.
2. Mostrar contexto de expediente para representados.
3. Integrar COS como senal de recomendacion para usuarios internos.

## Recomendacion para arrancar Sprint 1

No esperar todos los contratos finales para empezar Sprint 1.

Sprint 1 puede arrancar si ya estan aprobados:

1. Estados de expediente.
2. Opciones de situacion inicial.
3. Glosario.
4. Textos introductorios de rutas.
5. Pantalla "Por que recomendamos esta estrategia".

Los contratos pueden avanzar en paralelo, pero el flujo de activacion final debe quedar bloqueado hasta que Juridico apruebe al menos el contrato del analisis inicial y el criterio de contratos por servicio.

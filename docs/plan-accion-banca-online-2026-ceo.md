# Plan de accion: Banca Online 2026 como asistente estrategico del expediente

Generado: 2026-07-10

## Idea central

La respuesta del CEO no pide una Banca Online "mas bonita". Pide cambiar el modelo mental:

De:

```text
Catalogo de estrategias / servicios
```

A:

```text
Sistema que entiende el expediente, distingue candidato vs representado y recomienda la siguiente actuacion profesional
```

Esto implica que la plataforma debe:

1. Reconocer si la persona es candidato o representado.
2. Conocer o preguntar el estado real del expediente.
3. Recomendar una estrategia, no solo mostrar opciones.
4. Explicar por que recomienda esa estrategia.
5. Pedir solo la documentacion relevante.
6. Ofrecer solucion cuando falte un documento.
7. Formalizar cada servicio profesional con su contrato.
8. Cambiar el lenguaje de tienda por lenguaje juridico/profesional.
9. Integrar Banca Online con el expediente y el COS.
10. Permitir que las reglas de negocio se actualicen desde la app, sin editar archivos de configuracion.
11. Funcionar tanto desde fuera de la app como desde dentro de la app.
12. Servir como modulo de cotizacion/activacion para otros procesos del cliente.

## Decision de arquitectura

La Banca Online 2026 no debe depender de archivos de configuracion para elementos que el equipo necesite ajustar con frecuencia.

Debe existir un panel interno de configuracion estrategica desde donde se pueda administrar:

1. Estados del expediente.
2. Situaciones visibles para el cliente.
3. Rutas estrategicas.
4. Reglas de recomendacion.
5. Documentos requeridos o habituales.
6. Soluciones cuando falten documentos.
7. Contratos por servicio profesional.
8. Textos publicos y CTAs.
9. Paquetes, precios, beneficios y cuotas.

El archivo `config/banca_online.php` debe tratarse como una fuente transitoria o semilla inicial, no como fuente de verdad final.

Auditoria tecnica inicial: ya existen datos de Banca Online en `servicios` y `compras`. En la base local hay 44 registros `servicios` con `categoria = banca_online_2026` y 6 registros `compras` con `source = banca_online_2026`. Por tanto, el plan no debe duplicar el catalogo actual; debe reutilizarlo y completar las entidades que faltan.

## Definiciones base

### Flujo base actual

El flujo principal de registro se mantiene:

```text
/registerv2 -> /pay -> /getinfo -> /contrato -> /tree
```

Banca Online no reemplaza este flujo. Lo complementa.

### Banca Online

Banca Online es un modulo de cotizacion, seleccion de alcance, recomendacion y activacion.

Puede ser invocada:

1. Desde fuera de la app, para visitantes o interesados.
2. Desde dentro de la app, para candidatos que continuan su proceso.
3. Desde dentro de la app, para representados que quieren cotizar o activar otros procesos/servicios.

Cuando un cliente arranca otros procesos, Banca Online debe permitir cotizar o seleccionar una cotizacion.

### Candidato

Persona interesada en contratar o activar un servicio, pero que todavia no tiene contrato firmado.

Experiencia esperada:

```text
Bienvenida
-> quienes somos
-> como trabajamos
-> activacion del analisis inicial
-> contrato del analisis
-> clausulas de datos
-> pago de activacion
-> completa informacion/contrato si aplica
-> pasa a representado
-> inicio del analisis
```

### Representado

Persona que ya pago y firmo contrato. Puede tener expediente, arbol o COS pendientes, pero ya debe tratarse como representado formal.

Preguntas que la plataforma debe responder:

1. Donde esta mi expediente.
2. Que ha hecho Sefar.
3. Que falta.
4. Que depende del cliente.
5. Que depende de Sefar.
6. Cual es el siguiente paso recomendado.
7. Que estrategia conviene activar ahora.

## Frentes de trabajo

### 0. Panel de configuracion estrategica

Objetivo: que direccion, operaciones, ventas y Juridico puedan actualizar Banca Online desde la app.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P0 | Auditar tablas actuales de Banca Online | Backend | Se confirma que se reutiliza `servicios`, `compras` y metadata existente |
| P0 | Definir modelo de datos administrable | Backend/Producto | Queda decidido que se reutiliza y que se crea nuevo |
| P0 | Reutilizar el admin actual de Banca Online como base | Backend/Admin | El panel actual deja de ser solo catalogo/precios y pasa a configuracion estrategica |
| P1 | Crear CRUD de estados del expediente | Backend/Admin | Admin puede crear, ordenar, activar y desactivar estados |
| P1 | Crear CRUD de rutas estrategicas | Backend/Admin | Admin puede editar titulos, resumenes, explicaciones y visibilidad |
| P1 | Crear CRUD de reglas de recomendacion | Backend/Admin | Admin puede mapear estado + pais + perfil a ruta recomendada |
| P1 | Crear CRUD de documentos y soluciones | Backend/Admin/Legal | Admin puede definir documentos y respuesta si faltan |
| P1 | Crear CRUD de contratos por servicio | Backend/Admin/Legal | Admin puede asociar contrato a servicio/ruta |
| P2 | Crear administracion de textos visibles | UX/Admin | Textos publicos y CTAs se cambian sin desplegar codigo |
| P2 | Auditar cambios administrativos | Backend/Seguridad | Se guarda quien cambio que y cuando |

Regla: no crear nuevos archivos de configuracion para estas decisiones. Si hay datos existentes en config, se migran una vez a base de datos. Si ya hay datos existentes en `servicios`, `compras` o `settings`, se reutilizan antes de crear tablas nuevas.

### 1. Perfilado candidato vs representado

Objetivo: que la plataforma cambie la experiencia segun el estado del usuario.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P0 | Definir regla operativa de "candidato" | Producto/Backend | Documento con campos actuales usados: pago, contrato, servicio, expediente, rol |
| P0 | Definir regla operativa de "representado" | Producto/Legal/Backend | Regla aprobada: pago completado + contrato firmado; expediente/COS son subestados posteriores |
| P1 | Crear helper/servicio `ClientStageResolver` o equivalente | Backend | Devuelve `candidate`, `represented` u otro estado derivado |
| P1 | Usar esa regla en Banca Online | Backend/Frontend | La pantalla inicial cambia segun perfil |
| P1 | Registrar punto de entrada de Banca Online | Backend/Data | Cada activacion indica si vino de fuera, app interna, COS o cotizacion admin |
| P1 | Registrar contexto de cotizacion | Backend/Data | La compra/activacion conserva la cotizacion seleccionada o contexto de proceso |
| P2 | Registrar evento de cambio de candidato a representado | Backend/Data | Hay timestamp/auditoria del cambio |

Nota: no conviene duplicar estados si ya se pueden derivar de campos existentes. Primero resolver con lo que existe.

### 2. Nuevo inicio de Banca Online

Objetivo: empezar por contexto del expediente, no por nacionalidad.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P1 | Cambiar primera pregunta de Banca Online | Frontend/Producto | La primera decision es "Cual describe mejor tu situacion actual" |
| P1 | Mantener nacionalidad como segunda pregunta | Frontend | El flujo pregunta nacionalidad despues del estado |
| P1 | Crear opciones de situacion actual | Producto | Opciones aprobadas por CEO/Juridico |
| P1 | Mapear situacion + nacionalidad -> ruta estrategica | Producto/Backend | Matriz de recomendacion inicial funcionando |
| P2 | Guardar seleccion de situacion | Backend/Data | Se puede medir que estados escogen los usuarios |

Opciones iniciales sugeridas:

1. Todavia no he iniciado mi expediente.
2. Ya contrate el analisis inicial.
3. Ya presentamos mi solicitud.
4. Mi expediente esta siendo estudiado.
5. He recibido un requerimiento.
6. Mi solicitud fue denegada.
7. Ya soy representado de Sefar Universal.

### 3. Reforzar rutas estrategicas actuales

Objetivo: conservar las tres rutas, pero explicar mejor cuando aplica cada una.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P1 | Mantener las tres rutas actuales | Producto | No se eliminan las rutas existentes |
| P1 | Agregar frase contextual antes de cada ruta | UX/Contenido | Cada ruta explica en una frase su momento procesal |
| P1 | Revisar textos con tono juridico | Contenido/Legal | Textos aprobados sin lenguaje de tienda |
| P2 | Relacionar cada ruta con estados de expediente | Backend/Producto | Cada estado recomienda una ruta por defecto |

Textos base:

1. Primera ruta: "Esta es la etapa en la que disenamos la estrategia antes de presentar cualquier solicitud."
2. Segunda ruta: "Esta etapa corresponde a expedientes que ya han sido formalmente presentados."
3. Tercera ruta: "Esta estrategia se recomienda cuando la via administrativa ya no ofrece posibilidades razonables de exito."

### 4. Pantalla intermedia: por que recomendamos esta estrategia

Objetivo: no enviar al usuario directo a activar/pagar. Primero explicar la recomendacion.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P1 | Crear pantalla `strategy-rationale` o equivalente | Frontend | Existe paso entre tarjeta y activacion |
| P1 | Mostrar "Por que recomendamos esta estrategia" | UX/Contenido | La pantalla responde las preguntas clave |
| P1 | Mover CTA de activacion al final de esta pantalla | Frontend | El boton aparece despues de la explicacion |
| P2 | Personalizar texto por estado de expediente | Backend/Contenido | Estado + nacionalidad cambian la explicacion |
| P2 | Medir vistas y conversion de esta pantalla | Data | Eventos disponibles |

Preguntas que debe responder:

1. Por que recomendamos esta estrategia.
2. Que objetivo tiene.
3. Que profesionales participaran.
4. Que esperamos conseguir.
5. Que documentacion suele necesitarse.
6. Que ocurre cuando termina esta etapa.

### 5. Documentos y solucion cuando falten

Objetivo: no limitarse a listar documentos; ofrecer solucion profesional cuando el cliente no los tiene.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P1 | Crear catalogo de documentos por estrategia | Producto/Legal | Cada estrategia tiene documentos habituales |
| P1 | Agregar bloque "si no tienes este documento" | UX/Contenido | Cada documento puede tener solucion asociada |
| P1 | Vincular falta de documento con servicio documental | Backend/Servicios | Se puede solicitar activacion de investigacion documental |
| P2 | Integrar con `DocumentRequest` cuando aplique | Backend | Documento requerido se puede convertir en solicitud |
| P2 | Medir documentos faltantes frecuentes | Data | Dashboard o reporte disponible |

Mensaje base:

```text
Si no dispones de este documento, nuestro equipo puede localizarlo mediante un servicio profesional de investigacion documental.
```

### 6. Contratos por servicio profesional

Objetivo: que cada activacion tenga formalidad juridica propia.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P0 | Pedir a Juridico matriz de contratos por servicio | Legal/Producto | Lista aprobada de servicios y contratos requeridos |
| P0 | Revisar contrato del analisis inicial | Legal | Contrato actualizado para recorrido digital |
| P1 | Crear flujo estandar de activacion | Backend/Frontend | Alcance -> condiciones -> contrato -> datos -> pago -> servicio activado |
| P1 | Asociar contrato a servicio activado | Backend | Compra/servicio queda vinculado a contrato firmado |
| P2 | Automatizar asignacion al equipo correspondiente | Backend/Operaciones | Activacion genera tarea/asignacion |

Contratos mencionados por CEO:

1. Analisis inicial.
2. Investigacion documental.
3. Investigacion genealogica.
4. Estrategia administrativa.
5. Estrategia judicial.
6. Consultas especializadas.
7. Gestion documental.
8. Otros servicios profesionales.

### 7. Cambio de lenguaje en toda la plataforma

Objetivo: pasar de lenguaje de tienda a lenguaje de firma juridica especializada.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P1 | Crear glosario oficial de terminos | Producto/Legal/Contenido | Documento aprobado |
| P1 | Auditar textos visibles de Banca Online | UX/Contenido | Lista de textos a cambiar |
| P1 | Reemplazar lenguaje comercial inapropiado | Frontend/Contenido | No se muestran "comprar", "producto", "carrito" al usuario |
| P2 | Revisar emails/notificaciones | Contenido | Correos usan lenguaje profesional |
| P2 | Revisar nombres internos si afectan UI | Backend/Frontend | Labels visibles alineados |

Reemplazos recomendados:

| Evitar | Usar |
|---|---|
| Comprar | Contratar |
| Venta | Activacion |
| Producto | Servicio profesional |
| Carrito | Solicitud de activacion |
| Pedido | Activacion de servicio |
| Pagar producto | Completar pago de activacion |
| Checkout | Formalizacion de activacion |

### 8. Expediente como eje de la experiencia

Objetivo: que el representado no sienta que entra a una tienda, sino a su expediente.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P1 | Crear bloque "Mi expediente" dentro de Banca Online | Frontend/Producto | El representado ve estado, ruta y siguiente accion |
| P1 | Integrar informacion del COS | Backend/Frontend | Estado del expediente alimenta recomendaciones |
| P1 | Mostrar estrategias como incorporaciones al expediente | UX/Contenido | CTA dice "Incorporar esta estrategia a mi expediente" |
| P2 | Mostrar acciones pendientes del cliente | Backend/Frontend | Documentos/pagos/contratos pendientes visibles |
| P2 | Mostrar acciones en curso de Sefar | Backend/Frontend | Cliente entiende que depende del equipo |

Cambio psicologico buscado:

```text
No: "Voy a comprar un servicio."
Si: "Voy a incorporar esta estrategia a mi expediente."
```

### 9. Recomendador estrategico

Objetivo: que la plataforma deje de mostrar todas las estrategias por igual.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P1 | Crear matriz inicial de recomendacion por reglas | Producto/Legal | Reglas aprobadas por situacion, nacionalidad y estado COS |
| P1 | Mostrar una recomendacion principal | Frontend | Usuario ve estrategia recomendada, no todas iguales |
| P2 | Mostrar estrategias secundarias solo si aplican | Frontend/Backend | Se reducen opciones irrelevantes |
| P2 | Explicar por que aplica o no aplica cada estrategia | UX/Contenido | Transparencia de recomendacion |
| P3 | Evaluar IA solo despues de tener reglas claras | Producto/Data | No se introduce IA sin control juridico |

Mensaje base:

```text
Hemos revisado el estado de tu expediente y recomendamos activar la siguiente estrategia.
```

### 10. Metricas y seguimiento

Objetivo: medir si el nuevo flujo realmente guia mejor y convierte mejor.

Tareas:

| Prioridad | Tarea | Area | Criterio de aceptacion |
|---|---|---|---|
| P1 | Registrar estado elegido por el usuario | Data/Backend | Evento disponible |
| P1 | Registrar estrategia recomendada | Data/Backend | Evento disponible |
| P1 | Registrar vista de pantalla de explicacion | Data/Backend | Evento disponible |
| P1 | Registrar solicitud de activacion | Data/Backend | Evento disponible |
| P2 | Registrar firma de contrato por servicio | Data/Backend | Evento disponible |
| P2 | Registrar pago de activacion | Data/Backend | Evento disponible |
| P2 | Crear reporte de conversion por estado/ruta | Data | Reporte operativo |

Eventos sugeridos:

1. `bo_profile_detected`
2. `bo_case_status_selected`
3. `bo_nationality_selected`
4. `bo_strategy_recommended`
5. `bo_strategy_rationale_viewed`
6. `bo_activation_requested`
7. `bo_contract_started`
8. `bo_contract_signed`
9. `bo_activation_payment_started`
10. `bo_service_activated`

## Roadmap recomendado

### Sprint 0: decisiones base

Duracion: 1 semana.

Objetivo: cerrar decisiones antes de tocar mucho codigo.

Tareas:

1. Definir candidato vs representado.
2. Aprobar matriz de estados de expediente.
3. Aprobar glosario de lenguaje.
4. Pedir a Juridico contrato de analisis inicial actualizado.
5. Definir contratos requeridos por servicio.
6. Definir documentos habituales por estrategia.

Resultado: base de producto/legal clara.

### Sprint 1: Banca Online guiada

Duracion: 1-2 semanas.

Objetivo: cambiar el orden de conversacion.

Tareas:

1. Preguntar primero situacion del expediente.
2. Preguntar despues nacionalidad.
3. Mantener rutas estrategicas.
4. Agregar textos explicativos de cada ruta.
5. Crear pantalla "Por que recomendamos esta estrategia".
6. Cambiar lenguaje visible principal.
7. Registrar eventos basicos.

Resultado: Banca Online deja de ser catalogo y empieza a recomendar.

### Sprint 2: expediente y COS

Duracion: 2-3 semanas.

Objetivo: conectar la recomendacion con el expediente real.

Tareas:

1. Crear bloque "Mi expediente".
2. Usar datos del COS para representados.
3. Mostrar siguiente accion recomendada.
4. Mostrar que depende del cliente y que depende de Sefar.
5. Mostrar documentos requeridos/faltantes.
6. Ofrecer servicio documental cuando falte documento.

Resultado: el representado siente que Banca Online es parte de su expediente.

### Sprint 3: contratos por servicio

Duracion: 3-5 semanas, depende de Juridico.

Objetivo: formalizar juridicamente cada activacion.

Tareas:

1. Asociar cada servicio profesional a un contrato.
2. Crear flujo: alcance -> contrato -> datos -> pago -> activacion.
3. Guardar contrato firmado vinculado al servicio.
4. Activar servicio despues del pago.
5. Crear asignacion/tarea al equipo correspondiente.

Resultado: cada activacion queda juridicamente ordenada.

### Sprint 4: recomendador avanzado

Duracion: 2-4 semanas.

Objetivo: mejorar la inteligencia de recomendaciones.

Tareas:

1. Afinar reglas por estado real del expediente.
2. Ocultar estrategias no aplicables.
3. Mostrar alternativas solo cuando tengan sentido.
4. Medir conversion por recomendacion.
5. Evaluar IA solo para explicar/revisar, no para decidir sin reglas.

Resultado: la plataforma actua como asistente estrategico controlado.

## Primeras tareas para convertir en issues

1. Definir candidato vs representado con campos actuales.
2. Crear matriz de estados de expediente.
3. Cambiar primera pregunta de Banca Online a situacion actual.
4. Crear segunda pregunta de nacionalidad.
5. Crear matriz situacion + nacionalidad -> ruta.
6. Agregar textos introductorios a las tres rutas.
7. Crear pantalla "Por que recomendamos esta estrategia".
8. Mover CTA de activacion al final de esa pantalla.
9. Crear glosario de lenguaje profesional.
10. Reemplazar "comprar/producto/carrito/pedido" en UI visible.
11. Crear catalogo de documentos por estrategia.
12. Crear bloque "si no tienes este documento".
13. Vincular documento faltante con servicio de investigacion documental.
14. Pedir a Juridico contrato actualizado del analisis inicial.
15. Crear matriz contratos por servicio.
16. Definir flujo estandar de activacion de servicio profesional.
17. Conectar representado con datos del COS/expediente.
18. Mostrar "Mi expediente" en Banca Online para representados.
19. Crear eventos de medicion del flujo.
20. Crear reporte de conversion por estado/ruta.

## Recomendacion de prioridad

La secuencia mas sana es:

1. Producto/legal primero: definiciones, contratos, lenguaje.
2. UX despues: nuevo flujo guiado y pantalla de explicacion.
3. Backend despues: reglas, estados, eventos, contratos vinculados.
4. Operacion despues: asignacion automatica a equipos.
5. Inteligencia avanzada al final.

No conviene empezar por IA ni por automatizaciones complejas. Primero hay que convertir el criterio estrategico del CEO en reglas claras, aprobadas y medibles.

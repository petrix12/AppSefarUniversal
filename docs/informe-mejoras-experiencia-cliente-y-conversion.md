# Informe: experiencia de cliente, conversion y mejoras de la app

Generado: 2026-07-03

## Resumen ejecutivo

La app de Sefar Universal ya tiene una base muy amplia: registro de clientes, pago de registro, cupones, referidos, formulario de informacion complementaria, contrato, arbol genealogico, pagos posteriores, tienda de servicios, Banca Online 2026, COS como canal de estatus del proceso, seguimiento comercial, tareas, documentos, notificaciones internas, chat interno, integraciones con HubSpot, Monday, Teamleader, Stripe, Jotform, S3 y reportes.

El problema principal no es falta de funcionalidades. El problema principal es que muchas funcionalidades existen como piezas operativas separadas, pero no siempre se presentan al cliente con una experiencia clara, confiable y comercialmente persuasiva.

La recomendacion central es mantener las fases separadas, porque asi funciona el proceso de registro, pero mejorar cada fase para que tenga:

1. Un objetivo claro.
2. Una promesa de valor visible.
3. Una explicacion breve de por que ese paso importa.
4. Una transicion fuerte hacia la siguiente fase.
5. Soporte visible.
6. Medicion de abandono.
7. Mayor coherencia visual y de tono.

El mayor impacto comercial probablemente esta en mejorar `/registerv2`, `/pay`, `/getinfo`, `/contrato` y `/tree`. Banca Online 2026 ya tiene una experiencia mas moderna y puede servir como referencia visual, pero no debe reemplazar el flujo principal de registro por fases.

## Alcance revisado

Se revisaron, entre otros, estos puntos de la app:

| Area | Archivos/rutas principales |
|---|---|
| Flujo cliente | `routes/web.php`, `app/Http/Controllers/Controller.php`, `app/Http/Controllers/ClienteController.php` |
| Registro | `app/Http/Controllers/RegisterV2Controller.php`, `resources/views/auth/registerv2.blade.php` |
| Pago inicial | `resources/views/clientes/pay.blade.php`, `ClienteController::pay`, `ClienteController::procesarPagoStripe` |
| Informacion HubSpot | `resources/views/clientes/getinfo.blade.php`, `ClienteController::getinfo`, `ClienteController::procesargetinfo`, formulario HubSpot `https://share.hsforms.com/1rnPjIxSoQPSiDEozowqr3gbxtdk` |
| Contrato | `resources/views/clientes/contrato.blade.php`, `ClienteController::contrato`, `ClienteController::checkContrato` |
| Arbol genealogico | `resources/views/arboles/tree.blade.php`, `ClienteController::tree`, `App\Services\GenealogyService` |
| Servicios adicionales | `ServiceStoreController`, `resources/views/services/store/*` |
| Banca Online 2026 | `BancaOnlineController`, `BancaOnlineCatalog`, `resources/views/banca-online/*`, `public/css/banca-online-2026.css` |
| COS / estatus del proceso | `App\Services\CosService`, `App\Services\CosHelperService`, `CosPasoEditorController`, `CosVisitController`, `resources/views/crud/users/edit.blade.php`, `resources/views/crud/users/partials/progress-bars.blade.php`, `resources/views/cosvisitas/index.blade.php` |
| Menu y roles | `config/adminlte.php`, `App\Models\User`, permisos Spatie |
| Pagos y servicios | `App\Models\Compras`, `App\Models\Servicio`, Stripe, PayPal |
| Followups | `SendRegistrationPaymentFollowups`, `RegistrationFollowup`, `RegistrationPaymentReminder` |
| Documentos | `DocumentRequestController`, `DocumentRequest`, secciones en `crud/users/edit.blade.php` |
| Notificaciones/chat | `ClientNotificationController`, `ClientAppNotification`, `ClientChatController`, `components/chat-bubble` |
| Seguridad/rutas | `routes/web.php`, `routes/api.php`, endpoints cron/mantenimiento/webhooks |

## Que se tiene actualmente

### 1. Flujo principal de cliente

El flujo principal de registro funciona por fases:

```text
Registro -> /pay -> /getinfo -> /contrato -> /tree
```

La app ya fuerza el orden desde el backend:

1. Si el cliente tiene pago pendiente o `pay = 0`, se redirige a `/pay`.
2. Si ya pago pero no completo informacion, se redirige a `/getinfo`.
3. Si ya completo informacion pero no firmo contrato, se redirige a `/contrato`.
4. Si ya cumplio todo, entra al arbol genealogico.

Esto es una fortaleza importante porque evita que el cliente salte pasos criticos.

### 2. Registro

El registro se maneja con `/registerv2`.

Actualmente captura:

1. Nombre y apellido.
2. Email.
3. Telefono.
4. Pasaporte.
5. Pais de nacimiento.
6. Referido.
7. Servicio solicitado.
8. Si tiene familiares en proceso.
9. Consentimientos.

Tambien:

1. Crea usuario.
2. Crea/actualiza `Agcliente`.
3. Crea compra pendiente.
4. Crea/verifica contacto en HubSpot.
5. Envia correo de clave generada.
6. Asigna rol Cliente y permisos.
7. Hace autologin.

Fortaleza: el registro esta conectado con CRM y con el flujo de pago.

Debilidad: visualmente se siente como formulario administrativo, y el campo de referido es muy largo. Eso puede cansar al usuario antes de llegar al pago.

### 3. Pago inicial `/pay`

La pantalla `/pay` es una de las piezas mas importantes.

Actualmente incluye:

1. Validacion de cupon.
2. Codigo de referido.
3. Datos personales.
4. Direccion de facturacion.
5. Stripe Elements.
6. PayPal oculto.
7. Resumen de compra.
8. Bloque de pago seguro.
9. Politica de privacidad.
10. Alertas flotantes con cupon.

Fortaleza: ya procesa pago real, maneja descuentos, referidos y compras multiples.

Debilidad: la experiencia mezcla demasiadas cosas al mismo nivel. El cliente no recibe una explicacion fuerte de lo que esta activando con el pago. El formulario domina la pantalla antes de reforzar confianza y valor.

### 4. Pagina de gracias

`clientes/gracias.blade.php` usa SweetAlert para comunicar pago exitoso y redirigir a `/getinfo` o `/tree`.

Fortaleza: ya existe transicion posterior al pago.

Debilidad: un popup no transmite el peso de una compra importante. Conviene convertirlo en una pantalla de confirmacion real, con siguiente paso claro.

### 5. Informacion complementaria `/getinfo`

`/getinfo` carga un formulario HubSpot embebido y prellena campos desde el usuario.

Formulario revisado:

1. URL publica: `https://share.hsforms.com/1rnPjIxSoQPSiDEozowqr3gbxtdk`.
2. Portal HubSpot: `20053496`.
3. Form ID: `ae73e323-14a8-40f4-a20c-4a33a30aabde`.
4. Boton de envio: `Enviar`.
5. Tiene 11 grupos de campos, 23 campos visibles y 2 campos dependientes.
6. `captchaEnabled = false`.

Campos principales detectados:

1. Datos personales: `firstname`, `lastname`, `phone`, `email`, `city`, `address`, `genero`, `edo_civil`.
2. Nacimiento: `fecha_nac`, `pais_de_nacimiento`, `ciudad_de_nacimiento`.
3. Familia directa: `nombres_y_apellidos_del_padre`, `nombres_y_apellidos_de_madre`.
4. Pasaporte: `numero_de_pasaporte`, `fecha_de_caducidad_del_pasaporte`, `pais_de_expedicion_del_pasaporte`.
5. Archivos: `pasaporte__documento_`, `partida_de_nacimiento_simple__`, `documentos_adicionales`.
6. Hijos: `tiene_hijos_`, `cuantos_hijos_tiene_`.
7. Servicio: `nacionalidad_solicitada`.
8. Tutor/representante: `requiere_tutor_o_representante_legal_`.
9. Campos dependientes para LMD: `tengo_certeza_de_mi_antepasado_espanol_` y `vinculo_antepasados`.

Fortaleza:

1. Aprovecha HubSpot como fuente comercial.
2. Prellena datos conocidos.
3. Al enviar, sincroniza datos al usuario y al arbol.
4. Cambia `pay` a `2` y revoca permiso `finish.register`.

Debilidad:

1. La experiencia depende de un iframe externo.
2. Si HubSpot demora o falla, el usuario puede sentir que la app fallo.
3. No hay mucho contexto de por que se pide esa informacion.
4. El mensaje de carga es util, pero puede sentirse tecnico.
5. El formulario vuelve a pedir datos que el cliente ya entrego en registro o pago, como nombre, correo, telefono, direccion y pasaporte. Aunque se prellenen, visualmente puede sentirse repetitivo.
6. `nacionalidad_solicitada` muestra muchas opciones de servicio. Si el cliente puede modificarlo, puede generar errores operativos; si no debe modificarlo, conviene ocultarlo o bloquearlo desde la app.
7. `tiene_hijos_` es texto libre y `cuantos_hijos_tiene_` aparece como requerido. Esto puede crear friccion si el cliente no tiene hijos o no entiende como responder.
8. La nota de caducidad de pasaporte recomienda usar PC o laptop si la fecha es posterior a 2032. Eso confirma una friccion movil real en un paso critico.
9. La carga de archivos dentro de HubSpot puede ser util, pero tambien puede duplicarse con el modulo documental propio de la app.
10. El mensaje final de HubSpot dice que el usuario debe cargar los datos del arbol genealogico. Conviene coordinar ese mensaje con la redireccion real de la app hacia `/tree`.

### 6. Contrato

`/contrato` muestra un iframe de Jotform con datos del usuario en la URL.

Fortaleza: la firma esta separada como fase formal.

Debilidad importante:

1. La pantalla es muy simple y no transmite formalidad suficiente.
2. El iframe tiene altura fija de 500px, puede ser incomodo.
3. `checkContrato` marca `contrato = 1` y redirige al arbol, pero no se ve una verificacion robusta de que Jotform confirmo la firma.

### 7. Arbol genealogico

El arbol es una de las piezas de mayor valor del producto.

Actualmente:

1. Construye el arbol con `GenealogyService`.
2. Muestra nodos familiares.
3. Permite navegar, hacer zoom, abrir personas, cargar archivos y editar segun rol.
4. Si no puede construir el arbol, redirige a `/getinfo` con mensaje.

Fortaleza: es un activo diferencial. Pocas empresas muestran al cliente una herramienta de carga genealogica propia.

Debilidad: la experiencia puede sentirse densa. No queda suficientemente claro al cliente que el arbol es parte esencial de la estrategia juridica/genealogica ni cuanto avance lleva.

### 8. Tienda interna de servicios

`/servicios-disponibles` permite mostrar servicios vendibles y agregar servicios al pago.

Fortaleza:

1. Ya existe venta adicional para clientes.
2. Soporta servicios con agenda.
3. Crea compras pendientes y bookings.

Debilidad:

1. La UI es muy basica.
2. Las tarjetas no explican suficientemente valor, beneficios, urgencia o recomendacion.
3. El boton "Ver" y "Agregar al pago" son correctos, pero poco persuasivos.

### 9. Banca Online 2026

Banca Online 2026 tiene:

1. Landing publica por pais.
2. Planes estrategicos.
3. Configurador.
4. Paquetes Regular/Medium/Premium.
5. Descuento visual.
6. Checkout.
7. Pago unico o cuotas.
8. Stripe.
9. Gracias.
10. Admin de catalogo.

Fortaleza: es el modulo comercial mas moderno.

Debilidad: el CSS tiene muchas definiciones repetidas para `.bo-package-card`, `.bo-package-saving` y estados recomendados/seleccionados. Eso puede producir inconsistencias visuales y dificulta mantenerlo.

### 10. COS / Canal de estatus del proceso

El COS es el canal donde el cliente consulta el estatus de su proceso y donde el equipo interno puede ver informacion consolidada de cada cliente.

Actualmente tiene varias piezas importantes:

1. El cliente ve el menu "Estatus de mi Proceso" cuando `cosready = 1`, mediante el gate `ver.mi.estatus`.
2. La ruta cliente `/status` recalcula y renderiza informacion del proceso.
3. Internamente se consulta desde la ficha del cliente en `users/{id}/edit`.
4. `CosService` calcula el estado del proceso con base en negocios, datos de Monday, HubSpot/Teamleader y reglas de avance.
5. `CosHelperService` lee la estructura editable del COS desde base de datos.
6. Hay modelos dedicados: `Cos`, `CosFase`, `CosPaso`, `CosSubfase`, `CosItem`, `CosTextoAdicional` y `CosVisit`.
7. El usuario guarda cache de COS en `arraycos`, con expiracion en `arraycos_expire`.
8. `cos_visitas` registra quien visita el COS de cada cliente.
9. Hay herramientas internas para sincronizar COS, pedir revision a Sistemas y notificar cambios de estatus al cliente.
10. Existe un editor interno de textos/procesos en `admin/procesos`.

Fortaleza: el COS puede convertirse en una de las piezas mas fuertes de confianza, retencion y venta adicional. No solo informa el avance; tambien puede explicar el valor del trabajo realizado y mostrar al cliente que su expediente esta vivo.

Tambien es importante aclarar que la vista del COS ya maneja diferencias por rol: hay informacion pensada para el cliente, informacion oculta al cliente e informacion visible solo para administradores o equipo interno. Esto es correcto y debe conservarse.

Debilidades:

1. La vista de cliente y la vista interna comparten una misma base de renderizado, aunque tengan bloques condicionados por rol.
2. Conviene auditar periodicamente que los bloques internos, debug, datos de negocio, botones de sincronizacion y acciones administrativas nunca queden visibles para clientes por cambios futuros de roles/permisos.
3. `CosService` usa `array_cos()`, mientras `CosHelperService` lee la estructura editable desde BD. Esto puede generar diferencias entre lo que se edita en el panel y lo que se usa para calcular o describir el estado.
4. El editor `admin/procesos` esta bajo `auth`, pero conviene reforzarlo con permiso especifico, porque modifica textos que ve el cliente.
5. Algunos modelos del COS usan campos `orden` en controladores/migraciones, pero no todos los incluyen en `fillable`, lo cual puede afectar guardado u ordenamiento segun configuracion de Eloquent.

### 11. Seguimiento y recuperacion

Ya existe `followups:registration-payment`, que envia correos cada 15 dias a clientes con `pay = 0`.

Fortaleza: ya hay base de recuperacion automatica.

Debilidad:

1. Solo cubre registro sin pago.
2. No cubre pago sin `/getinfo`.
3. No cubre `/getinfo` sin contrato.
4. No cubre contrato sin arbol.
5. No cubre arbol incompleto.
6. El link de pago apunta genericamente a `https://app.sefaruniversal.com`, no necesariamente a un enlace profundo contextual.

### 12. Documentos y solicitudes

La app tiene un sistema de solicitudes documentales:

1. Admin solicita documento.
2. Cliente sube archivo.
3. Admin aprueba o rechaza.
4. Se guarda archivo en S3.

Fortaleza: esto puede convertirse en una experiencia de "documentos pendientes" muy poderosa para cliente.

Debilidad:

1. Esta mas integrado en la vista interna de usuarios que en una experiencia de cliente final.
2. Algunas rutas de `admin/requests` y `client/requests` no estan agrupadas claramente con `auth` y permisos en `routes/web.php`.
3. Hay inconsistencia entre ruta `no-doc` y metodo `noDocument`.

### 13. Operacion interna

La app tiene modulos internos fuertes:

1. Tareas comerciales.
2. Listas de ventas.
3. Owners de HubSpot.
4. Teamleader.
5. Facturas.
6. Reportes.
7. Documentos.
8. Noticias.
9. Canal estrategico.
10. Chat interno sobre clientes.
11. Notificaciones internas.
12. Importacion de clientes.

Esto indica que Sefar no solo tiene un portal de cliente, sino una plataforma operativa completa.

## Diagnostico general

### Lo que funciona bien

1. El flujo por fases esta bien pensado.
2. La app fuerza el orden del proceso.
3. El pago esta integrado con Stripe.
4. Existen cupones y referidos.
5. HubSpot esta integrado.
6. Monday y Teamleader estan integrados.
7. Hay roles y permisos.
8. Hay arbol genealogico propio.
9. Hay sistema de documentos y archivos.
10. Hay seguimiento automatizado para pago pendiente.
11. El COS ya funciona como canal de estatus para cliente y consola de informacion para el equipo interno.
12. Banca Online 2026 demuestra que se puede construir una experiencia mas moderna dentro del mismo proyecto.

### Lo que frena conversion

1. Las pantallas clave se sienten operativas, no comerciales.
2. El cliente no siempre entiende que gano al completar cada paso.
3. El pago inicial no vende suficientemente el valor del registro.
4. El registro tiene demasiada carga administrativa.
5. La pantalla de gracias se apoya en popup.
6. El iframe de HubSpot puede sentirse ajeno.
7. El contrato no se siente suficientemente formal.
8. El arbol no muestra progreso ni recomendaciones claras.
9. El COS tiene mucho valor, pero puede explicar mejor al cliente que significan los avances y que accion concreta debe tomar.
10. La tienda interna de servicios no explota upsell/cross-sell.
11. Falta medicion visible por etapa.

### Lo que genera riesgo tecnico o de confianza

1. Existen rutas de mantenimiento/cron expuestas en `routes/web.php`.
2. Algunas rutas sensibles no estan claramente protegidas por `auth` y permisos.
3. Hay endpoints de API sin middleware que deben depender de tokens o validaciones fuertes.
4. `checkContrato` deberia verificar firma real, idealmente por webhook.
5. El CSS de Banca Online tiene estilos repetidos que dificultan mantenimiento.
6. Hay mezcla de Tailwind, AdminLTE, Bootstrap, estilos inline, jQuery y SweetAlert, lo que aumenta inconsistencia visual.
7. El modelo `User` tiene `guarded = []`, practico pero riesgoso si no se validan bien todos los inputs.

## Recomendacion estrategica

No cambiar el proceso por fases. Ese proceso tiene sentido para el negocio.

Si cambiaria la forma en que cada fase comunica valor.

Cada fase debe comportarse como una estacion separada, pero todas deben tener una narrativa comun:

```text
1. Te registras.
2. Activas tu expediente con el pago.
3. Nos entregas informacion clave.
4. Formalizas el servicio con contrato.
5. Construyes tu base genealogica.
6. El equipo Sefar trabaja con esa informacion.
```

Esto no significa unificar pantallas. Significa que cada pantalla debe saber vender el siguiente paso.

## Mejoras recomendadas por fase

### Fase 1: Registro

Objetivo: convertir visitante/interesado en lead registrado.

Mejoras:

1. Titulo dinamico por servicio.
   - Actual: "Inicia tu analisis genealogico".
   - Mejor: "Inicia tu evaluacion para nacionalidad italiana", "Crea tu expediente inicial para nacionalidad espanola", etc.

2. Bloque breve de valor antes del formulario.
   - "Con este registro podremos identificar tu servicio, crear tu usuario y preparar el pago de activacion."

3. Reducir el campo "Referido por".
   - Mantener opciones simples: Google, redes sociales, referido, asesor, otro.
   - Si se necesita saber asesor exacto, usar parametros de URL o codigo.

4. CTA mas orientado a valor.
   - "Crear mi expediente inicial".
   - "Iniciar mi evaluacion".
   - "Continuar al pago de registro".

5. Captura de UTM/campana.
   - Guardar `utm_source`, `utm_medium`, `utm_campaign`, `ref`, `advisor`, `service`.
   - Esto permitiria saber que campañas convierten a pago.

6. Validacion menos frustrante.
   - Mensajes claros.
   - Si el usuario ya existe, llevarlo directo a login/pago con mensaje personalizado.

### Fase 2: Pago inicial `/pay`

Objetivo: convertir registro en cliente pagado.

Esta es la prioridad numero 1.

Mejoras:

1. Encabezado comercial.
   - "Activa tu registro en Sefar Universal".
   - "Este pago abre tu expediente y habilita los siguientes pasos."

2. Resumen de valor.
   - Que incluye el pago.
   - Que desbloquea.
   - Que sucede despues.

3. Secuencia visible de fases.
   - Registro completado.
   - Pago actual.
   - Informacion.
   - Contrato.
   - Arbol.

4. Resumen de compra mas claro.
   - Servicio.
   - Precio.
   - Moneda.
   - Descuento aplicado, si existe.
   - Total.

5. Cupón y referido menos dominantes.
   - Deben existir, pero no deben parecer el centro de la pantalla.
   - Mostrar como "Tienes un codigo?" desplegable o bloque secundario.

6. Seguridad visible.
   - Stripe.
   - No se almacenan tarjetas.
   - Datos protegidos.
   - Soporte disponible.

7. Bloque "Luego del pago".
   - "Completaras un formulario de datos."
   - "Firmaras tu contrato."
   - "Accederas al arbol genealogico."

8. Mejor manejo de errores.
   - Los mensajes de Stripe deben orientar al cliente.
   - Evitar mensajes como "comunicar a Sistemas" para cliente final.

### Fase 3: Confirmacion posterior al pago

Objetivo: que el cliente no se enfrie y continue inmediatamente.

Mejoras:

1. Convertir `gracias` en una pagina real, no solo SweetAlert.
2. Mostrar recibo simple.
3. Mostrar siguiente paso.
4. Tiempo estimado para completar informacion.
5. Boton fuerte: "Completar mis datos ahora".
6. Boton secundario: "Necesito ayuda".
7. Mensaje emocional: "Tu expediente ya fue activado."

### Fase 4: Informacion complementaria `/getinfo`

Objetivo: capturar datos completos para expediente y arbol.

Mejoras:

1. Encabezado claro:
   - "Completa la informacion para preparar tu expediente."

2. Explicar por que se pide.
   - "Estos datos permiten conectar tu informacion personal con el analisis genealogico y documental."

3. Tiempo estimado.
   - "Tiempo estimado: 7 a 12 minutos."

4. Soporte visible.
   - "Si no tienes un dato, completa lo que conozcas."
   - "Si tienes dudas, contactanos."

5. Mejor manejo de iframe.
   - Loader mas claro.
   - Mensaje si HubSpot no carga.
   - Boton de reintentar.

6. Reducir sensacion de repeticion.
   - Mostrar al cliente que algunos datos ya vienen precargados.
   - Evitar pedir manualmente lo que ya esta confirmado en registro/pago.
   - Si un dato se mantiene por HubSpot, marcarlo como "confirmacion" y no como nueva carga.

7. Blindar el campo de servicio.
   - `nacionalidad_solicitada` no deberia sentirse editable si ya viene del servicio comprado.
   - Si HubSpot exige el campo, enviarlo precargado/oculto desde la app.

8. Mejorar campos de hijos.
   - Cambiar `tiene_hijos_` de texto libre a select/booleano.
   - Hacer `cuantos_hijos_tiene_` dependiente de que el cliente responda que si tiene hijos.

9. Separar archivos por proposito.
   - Pasaporte: puede mantenerse aqui si es obligatorio para expediente.
   - Partida y documentos adicionales: evaluar si deben pasar al modulo propio de documentos para trazabilidad, aprobacion y rechazo.

10. Mejorar experiencia movil.
   - Revisar el campo de fecha de caducidad del pasaporte para evitar la nota "usar PC o laptop".
   - Validar que fecha, telefono y archivos funcionen bien en celular.

11. Alinear mensaje final.
   - El thank-you del HubSpot debe coincidir con la transicion real de la app.
   - Recomendado: "Informacion recibida. Ahora continua con tu contrato/arbol segun corresponda."

12. Alternativa futura.
   - Migrar gradualmente el formulario a componentes nativos Laravel y sincronizar con HubSpot por API en segundo plano.

### Fase 5: Contrato

Objetivo: formalizar la relacion.

Mejoras:

1. Pantalla formal antes del iframe:
   - Nombre del cliente.
   - Servicio contratado.
   - Estado: contrato pendiente.
   - Breve explicacion legal.

2. Mejorar iframe.
   - Altura responsiva.
   - Mejor contenedor.
   - Instrucciones de firma.

3. Verificacion real.
   - Usar webhook de Jotform o consulta API para marcar `contrato = 1`.
   - Evitar que una ruta simple marque el contrato como firmado sin comprobacion.

4. Confirmacion posterior:
   - "Contrato recibido."
   - "Ahora puedes completar tu arbol genealogico."

### Fase 6: Arbol genealogico

Objetivo: que el cliente cargue informacion completa y entienda el valor.

Mejoras:

1. Mostrar avance del arbol.
   - Personas agregadas.
   - Generaciones cubiertas.
   - Documentos cargados.
   - Campos incompletos.

2. Acciones recomendadas.
   - "Agrega tus padres."
   - "Agrega tus abuelos."
   - "Carga documento de nacimiento."
   - "Completa lugar/fecha de nacimiento."

3. Lenguaje de valor.
   - "Mientras mas datos aportes, mas solido sera el analisis."
   - "Los documentos ayudan a validar cada eslabon familiar."

4. Checklist documental.
   - Integrar `DocumentRequest` como "documentos solicitados".

5. Upsell contextual.
   - Si faltan documentos: ofrecer busqueda documental.
   - Si faltan lineas familiares: ofrecer revision genealogica.
   - Si hay dudas juridicas: ofrecer consulta.

6. Guardado y salida.
   - Antes de "Finalizar carga", mostrar resumen de lo aportado y lo pendiente.

## Mejoras por modulo

### Servicios disponibles

Situacion actual: funcional, pero visualmente basico.

Recomendacion:

1. Convertirlo en catalogo de servicios post-registro.
2. Agrupar por necesidad:
   - Consultas.
   - Documentos.
   - Genealogia.
   - Legal.
   - Vinculaciones.
3. Mostrar beneficios, no solo precio.
4. Mostrar servicios recomendados segun el estado del cliente.
5. Permitir "agregar al pago" sin friccion.
6. Si requiere agenda, mostrar horarios como tarjetas, no solo select.

### Banca Online 2026

Situacion actual: buen modulo comercial moderno.

Recomendacion:

1. Mantenerlo separado del registro principal.
2. Usarlo como referencia visual para pagos/paquetes.
3. Limpiar CSS duplicado.
4. Consolidar reglas de estilos para tarjetas.
5. Revisar textos sin acentos/encoding para profesionalizar.
6. Medir conversion por pais, plan y paquete.

### Notificaciones

Situacion actual: existe sistema de notificaciones internas.

Recomendacion:

1. Crear notificaciones para clientes por fase.
2. Notificar:
   - Pago pendiente.
   - Datos pendientes.
   - Contrato pendiente.
   - Documentos solicitados.
   - Arbol incompleto.
3. Hacer que cada notificacion tenga CTA directo.

### Seguimientos automatizados

Situacion actual: solo seguimiento de pago pendiente cada 15 dias.

Recomendacion:

1. Agregar followups por etapa:
   - `registration_payment_pending`
   - `getinfo_pending`
   - `contract_pending`
   - `tree_pending`
   - `documents_pending`
2. Frecuencia sugerida:
   - 1 hora despues si abandono en paso critico.
   - 24 horas.
   - 3 dias.
   - 7 dias.
3. Personalizar link directo a la fase.
4. Medir apertura/click si el proveedor de correo lo permite.

### Documentos

Situacion actual: sistema util pero mas interno que orientado al cliente.

Recomendacion:

1. Crear seccion cliente "Documentos solicitados".
2. Mostrar estado:
   - Pendiente de subir.
   - En revision.
   - Aprobado.
   - Rechazado.
   - No disponible.
3. Mostrar instrucciones por documento.
4. Permitir comentarios de rechazo.
5. Proteger rutas con `auth` y permisos.

### COS / Estatus del proceso

Situacion actual: el COS ya es el canal de consulta del cliente y tambien una herramienta interna para ver informacion consolidada. La vista tiene bloques condicionados por rol, con informacion oculta al cliente y elementos visibles solo para administradores/equipo interno.

Recomendacion:

1. Mantener el COS como modulo central de confianza y seguimiento.
2. Conservar la separacion por rol:
   - Cliente: fase actual, avance, explicacion clara, proximo paso y soporte.
   - Equipo interno: datos de negocio, campos de HubSpot/Teamleader/Monday, debug, sincronizacion, revision y notificacion.
   - Administradores: herramientas de edicion, auditoria y configuracion.
3. Auditar permisos y condiciones visuales para confirmar que ningun bloque interno quede visible al cliente.
4. Crear una capa visual de cliente mas limpia usando los datos ya calculados por COS.
5. Mejorar el texto de cada fase:
   - Que significa este estado.
   - Que trabajo hizo Sefar.
   - Que falta.
   - Que puede hacer el cliente.
   - Que servicio adicional puede ayudar.
6. Unificar la fuente de verdad entre `CosService` y `CosHelperService`, porque hoy el calculo usa `array_cos()` y la estructura editable se lee desde BD.
7. Revisar `fillable` de modelos `CosItem`, `CosSubfase` y `CosTextoAdicional` para incluir `orden` si el editor debe persistir ese campo.
8. Proteger `admin/procesos` con un permiso especifico, no solo `auth`.
9. Convertir `cos_visitas` en indicador comercial:
   - Clientes que revisan su estatus.
   - Clientes que no lo revisan.
   - Visitas despues de notificacion.
   - Fases que generan mas consultas.
10. Usar las notificaciones de COS para llevar al cliente directamente a `/status`.

### Chat / soporte

Situacion actual: hay burbuja Treena y chat interno sobre clientes.

Recomendacion:

1. Separar "asistente" de "soporte humano".
2. En fases criticas, mostrar soporte contextual.
3. Registrar motivo de ayuda:
   - Problema con pago.
   - Duda de documento.
   - Problema con contrato.
   - Duda del arbol.
4. Si Treena responde, debe tener limites claros y derivar a humano cuando corresponda.

## Que conviene conservar

1. Flujo por fases.
2. Redirecciones backend que fuerzan orden.
3. Integracion con HubSpot.
4. Integracion con Stripe.
5. Sistema de compras pendientes.
6. Roles y permisos Spatie.
7. Arbol genealogico propio.
8. Sistema de documentos.
9. Seguimiento de pago pendiente.
10. Banca Online como modulo separado.
11. COS como canal de estatus para cliente.
12. COS como consola interna de informacion por cliente.
13. Separacion por rol dentro del COS.
14. AdminLTE para operacion interna.
15. Livewire para paneles internos nuevos.

## Que conviene mejorar sin cambiar de raiz

1. Registro.
2. Pago inicial.
3. Pagina de gracias.
4. Getinfo.
5. Contrato.
6. Arbol.
7. Tienda de servicios.
8. Notificaciones por fase.
9. Followups por etapa.
10. COS cliente.
11. COS interno/admin.
12. Seguridad de rutas.

## Que conviene cambiar con mas decision

### 1. Contrato

Cambiar de "ruta que marca contrato firmado" a "firma confirmada por Jotform/API/webhook".

Prioridad: alta.

Motivo: seguridad juridica y confianza operativa.

### 2. Rutas de mantenimiento

Mover o proteger:

1. `key-generate`
2. `storage-link`
3. `config-cache`
4. `cache-clear`
5. `route-clear`
6. `config-clear`
7. `view-clear`
8. `/cron/scheduler-run`
9. `/cron/queue-worker`
10. `/deploy`
11. `/hubspot/sync-client-owners`

Prioridad: alta.

Motivo: riesgo operativo y reputacional.

### 3. Document request routes

Agrupar `admin/requests` con `auth` y permiso admin, y `client/requests` con `auth` + cliente + pertenencia del documento.

Prioridad: alta.

Motivo: datos personales/documentales.

### 4. COS cliente e interno

Mantener la logica de permisos por rol, pero separar mejor las responsabilidades:

1. Capa cliente: limpia, explicativa y orientada a accion.
2. Capa interna: datos operativos, debug y herramientas de sincronizacion.
3. Capa admin: edicion de textos, auditoria y configuracion.

La recomendacion no es eliminar lo interno, sino blindar la separacion y evitar que futuros cambios mezclen datos sensibles con la experiencia del cliente.

Prioridad: alta.

Motivo: el COS es un punto de confianza para cliente y una fuente de inteligencia para el equipo. Si se estructura bien, reduce ansiedad, tickets y trabajo manual.

### 5. Fuente de verdad del COS

Unificar el origen de datos entre `CosService` y `CosHelperService`.

Prioridad: media-alta.

Motivo: si el equipo edita textos/fases en BD, el calculo y la vista deben apoyarse en la misma estructura para evitar discrepancias.

### 6. CSS/UX disperso

Reducir estilos inline, SweetAlert como flujo principal y duplicaciones CSS.

Prioridad: media.

Motivo: mantenimiento y coherencia de marca.

## Roadmap recomendado

### Fase 0: seguridad y control

Duracion estimada: 1 semana.

1. Proteger rutas de mantenimiento/cron.
2. Revisar endpoints API sin auth y confirmar tokens.
3. Proteger rutas de documentos.
4. Revisar `checkContrato`.
5. Revisar rutas de Stripe/PayPal y permisos.
6. Reforzar permisos de `admin/procesos` con permiso especifico para edicion del COS.

Resultado esperado: menor riesgo antes de invertir en mas conversion.

### Fase 1: conversion del registro y pago

Duracion estimada: 1-2 semanas.

1. Redisenar `/registerv2`.
2. Redisenar `/pay`.
3. Crear pagina real de confirmacion de pago.
4. Mejorar mensajes de error.
5. Mejorar resumen de compra.
6. Agregar evento/registro de abandono.

Resultado esperado: mas clientes llegan a pago y menos abandonan al pagar.

### Fase 2: continuidad post-pago

Duracion estimada: 1-2 semanas.

1. Mejorar `/getinfo`.
2. Mejorar `/contrato`.
3. Mejorar transiciones.
4. Crear followups de informacion y contrato pendientes.
5. Agregar soporte contextual.

Resultado esperado: mas clientes completan informacion y contrato.

### Fase 3: arbol, documentos y COS

Duracion estimada: 2-3 semanas.

1. Agregar progreso del arbol.
2. Agregar checklist de datos faltantes.
3. Integrar documentos solicitados al cliente.
4. Agregar recomendaciones de proximos pasos.
5. Agregar upsells contextuales.
6. Redisenar la capa cliente del COS para que explique fase, avance, accion requerida y soporte.
7. Mantener la capa interna/admin del COS con datos tecnicos, debug, sincronizacion y notificacion.
8. Auditar visibilidad por rol dentro del COS.
9. Unificar estructura de COS editable con el calculo de `CosService`.

Resultado esperado: mas arboles completos, mejor informacion para produccion, clientes mas tranquilos y mas ventas adicionales contextualizadas.

### Fase 4: medicion y automatizacion

Duracion estimada: 2 semanas.

1. Crear tabla/eventos de etapa.
2. Dashboard de conversion por fase.
3. Followups por email/WhatsApp.
4. Reporte de abandono.
5. Atribucion por campana/asesor/servicio.
6. Reporte de visitas al COS y conversion despues de notificaciones de estatus.

Resultado esperado: decisiones comerciales basadas en datos.

## Indicadores que deberian medirse

### Conversion por fase

| Indicador | Pregunta que responde |
|---|---|
| Registros creados | Cuantos leads llegan a la app |
| Registros con compra pendiente | Cuantos llegaron a `/pay` |
| Pagos completados | Cuantos se convirtieron en cliente |
| Pago -> getinfo completado | Cuantos avanzan post-pago |
| Getinfo -> contrato firmado | Cuantos formalizan |
| Contrato -> arbol iniciado | Cuantos llegan al activo principal |
| Arbol completo | Cuantos entregan informacion util |

### Indicadores de calidad

1. Tiempo promedio entre registro y pago.
2. Tiempo promedio entre pago y getinfo.
3. Tiempo promedio entre getinfo y contrato.
4. Tiempo promedio entre contrato y primer nodo/archivo en arbol.
5. Errores de pago por tipo.
6. Cupones usados.
7. Referidos que convierten.
8. Servicios con mayor abandono.
9. Servicios con mayor venta adicional.
10. Clientes que piden soporte por fase.

### Indicadores del COS

1. Clientes con `cosready = 1`.
2. Clientes con COS disponible que nunca entraron a `/status`.
3. Visitas al COS por cliente.
4. Visitas al COS despues de una notificacion.
5. Fases del COS con mayor cantidad de consultas.
6. Fases del COS con mas solicitudes de soporte.
7. Clientes que ven COS y luego compran servicios adicionales.
8. Clientes con `arraycos_expire` vencido.
9. Diferencias detectadas entre estructura editable del COS y reglas usadas por `CosService`.
10. Revisiones internas solicitadas a Sistemas por problemas de COS.

## Cambios concretos recomendados

### Cambios de interfaz

1. Crear componente visual de fase para cliente.
2. Crear componente "proximo paso".
3. Crear componente "soporte contextual".
4. Crear componente "resumen de servicio".
5. Crear componente "confianza y seguridad".
6. Crear componente "documentos pendientes".
7. Crear capa cliente del COS con lenguaje claro.
8. Mantener capa interna/admin del COS con controles por rol.

### Cambios backend

1. Registrar etapa actual del cliente.
2. Registrar timestamps por etapa.
3. Crear followups por etapa.
4. Fortalecer firma de contrato por webhook.
5. Proteger rutas sensibles.
6. Normalizar rutas de documentos.
7. Agregar links profundos en emails.
8. Unificar `CosService` con estructura COS editable en BD.
9. Agregar `orden` a `fillable` donde aplique en modelos COS.
10. Reforzar permiso de edicion de `admin/procesos`.
11. Registrar eventos de notificacion y visita del COS.

### Cambios comerciales

1. Definir textos por servicio.
2. Definir promesa de cada fase.
3. Crear mensajes de recuperacion por fase.
4. Crear recomendaciones de servicios segun estado del cliente.
5. Medir conversion por campana y asesor.
6. Crear mensajes comerciales por fase COS.
7. Usar visitas al COS como senal de interes, ansiedad o necesidad de soporte.

## Prioridad de implementacion

| Prioridad | Accion | Impacto | Esfuerzo |
|---|---|---:|---:|
| P0 | Proteger rutas sensibles | Alto | Medio |
| P0 | Revisar firma de contrato | Alto | Medio |
| P1 | Redisenar `/pay` | Muy alto | Medio |
| P1 | Pagina real de gracias | Alto | Bajo |
| P1 | Mejorar registro | Alto | Medio |
| P1 | Eventos de abandono por fase | Alto | Medio |
| P1 | Redisenar capa cliente del COS | Alto | Medio |
| P1 | Reforzar permisos de editor COS | Alto | Bajo |
| P2 | Mejorar `/getinfo` | Alto | Medio |
| P2 | Mejorar `/contrato` | Alto | Bajo/Medio |
| P2 | Followups post-pago | Alto | Medio |
| P2 | Unificar `CosService` y estructura editable COS | Alto | Medio |
| P3 | Progreso del arbol | Medio/Alto | Medio/Alto |
| P3 | Documentos pendientes para cliente | Medio/Alto | Medio |
| P3 | Catalogo de servicios recomendado | Medio/Alto | Medio |
| P3 | Dashboard de visitas y conversion del COS | Medio/Alto | Medio |
| P4 | Limpieza CSS y componentes compartidos | Medio | Medio |

## Riesgos si no se mejora

1. Leads registrados que nunca pagan.
2. Clientes que pagan pero no completan informacion.
3. Contratos no formalizados correctamente.
4. Arboles incompletos.
5. Equipo interno gastando tiempo persiguiendo clientes manualmente.
6. Baja confianza por pantallas demasiado administrativas.
7. Dificultad para saber que campanas realmente convierten.
8. Riesgo tecnico por rutas expuestas.
9. Riesgo documental si rutas de archivos no estan bien protegidas.
10. Riesgo de discrepancias entre textos editados del COS y calculo real de estado.
11. Perdida de oportunidad comercial si el COS solo informa estatus y no guia acciones.

## Conclusion

La app tiene mucho mas valor construido del que actualmente se comunica al cliente.

No hace falta cambiar el modelo por fases. De hecho, conviene conservarlo. Lo que si hace falta es que cada fase tenga una experiencia mas clara, persuasiva y segura.

La prioridad recomendada es:

1. Seguridad de rutas y contrato.
2. Redisenar `/pay`.
3. Mejorar registro y pagina de gracias.
4. Mejorar `/getinfo` y `/contrato`.
5. Potenciar COS como canal de confianza, soporte y venta contextual.
6. Medir abandono por etapa.
7. Activar followups por etapa.
8. Potenciar arbol, documentos y servicios adicionales.

En una frase: Sefar ya tiene la maquinaria; ahora hay que convertir cada fase en una experiencia que haga sentir al cliente que esta avanzando en un proceso serio, valioso y acompanado.

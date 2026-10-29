# Tarjeta 108: cartera global y asociaciones sin IA

Revisión del 29 de octubre de 2026.

Tarea: [Revisar si desde el COS se puede obtener los clientes que tienen el certificado y si deben dinero](https://trello.com/c/oeqp4Y2S/108-revisar-si-desde-el-cos-se-puede-obtener-los-clientes-que-tienen-el-certificado-y-si-deben-dinero).

- Número de tarjeta: **108**.
- Identificador de la URL: **oeqp4Y2S**.
- ID interno de Trello: `6adf288f25d30a668d9de24b`.
- La tarjeta no tiene descripción ni checklist; su único comentario indica que se escaló para revisar la viabilidad.

## Resultado

Se implementó **Cartera y certificados**, un listado global de clientes con filtros por certificado, saldo, revisión y búsqueda, disponible en `/cos/cartera`. Su exportación CSV incluye todos los resultados filtrados, aunque la pantalla muestre 50 por página.

El listado debe cruzar expedientes y finanzas. El JSON `users.arraycos` por sí solo no permite determinar de forma fiable quién tiene certificado y cuánto debe: no guarda el detalle de deuda, puede estar vencido y agrupa resultados por servicio, perdiendo el vínculo individual con cada negocio.

El listado consulta directamente los proyectos Teamleader importados. Por eso puede obtener clientes con certificado y saldo aunque sus proyectos todavía no estén asociados a HubSpot. Para combinar las dos fuentes sin duplicar saldos, también calcula asociaciones inequívocas sin IA. Las asociaciones se revisan y guardan desde **Procesos del solicitante**, en la ventana **Estatus de Solicitante** de cada cliente.

## Asociación automática por reglas

Primero se identifica el contacto Teamleader por ID, pasaporte o correo exacto y único, por ese orden. Un pasaporte duplicado o un contacto compartido por varios usuarios se marca para revisión. No se asocian contactos por similitud de nombre.

Dentro del mismo cliente, se consideran suficientes estas coincidencias:

- Código de proceso idéntico, sin contradicción de servicio.
- Nombre completo normalizado y servicio idénticos. El servicio puede estar en el nombre del trato cuando falta su campo específico.
- Servicio idéntico e importes positivos coincidentes en dos fases.
- Nombre completo idéntico e importe positivo coincidente de una fase.

Los nombres se comparan normalizando mayúsculas, acentos y separadores. Un nombre parecido o un importe cero no bastan. Los códigos contradictorios, servicios distintos o preestablecidos distintos impiden el enlace por nombre/importes. La coincidencia debe ser única en ambas direcciones, con margen frente al segundo candidato; se evalúa el conjunto completo para evitar decisiones arbitrarias según el orden.

Las asociaciones existentes se conservan. Un proyecto reservado para otro cliente queda excluido. Guardar el lote actualiza solo `teamleader_id` y los metadatos de la decisión en la base local, bajo transacción y bloqueo por proyecto. No rellena campos remotos ni llama al servicio antiguo que puede recurrir a IA.

## Agrupación y pagos en el estatus del cliente

El panel **Procesos del solicitante** muestra juntos el negocio HubSpot y el proyecto Teamleader de cada proceso. Permite guardar las coincidencias claras del cliente sin IA y seleccionar manualmente un negocio y un proyecto de ese mismo cliente. Los registros locales con un mismo ID remoto HubSpot se consideran copias del mismo negocio; los enlaces contradictorios no se resuelven automáticamente. Guardar vuelve a abrir el estatus y recalcula los pagos.

La conciliación utiliza proceso, fase y moneda, tanto en el estatus interno como en el del cliente y en los pagos pendientes. Una obligación de 2.000 en HubSpot y su saldo de fase de 2.000 en Teamleader se muestran como **2.000**, conservando el ID del registro realmente cobrable. Dos procesos distintos o dos fases diferentes de 2.000 mantienen su total de 4.000. Los cargos genéricos sin fase o sin proceso no se agrupan por importe.

Los saldos de fase conciliados incorporan los abonos históricos y los pagos recientes del portal. Los campos antiguos del CRM no vuelven a sumar deuda sobre ese saldo, y una fase pagada no reaparece por su copia pendiente antigua. Los enlaces y formularios de cobro antiguos rechazan el registro sustituido y remiten al saldo actualizado.

Los abonos y comprobantes reales permanecen en la base. La vista elimina la repetición entre el resumen importado y los recibos que representa, preserva los abonos individuales aunque tengan el mismo importe y muestra cualquier importe histórico restante. No se borran compras ni facturas. Se mantienen las reglas existentes de fases ocultas, revisión y orden de pago.

Las coincidencias inequívocas se agrupan provisionalmente al consultar; guardar persiste el enlace. La consulta no escribe asociaciones. El nuevo guardado de asociaciones tampoco envía correos, crea compras ni actualiza los CRM externos.

## Cómo identificar el certificado

La señal directa disponible es `negocios.n4__certificado_descargado`, una fecha nullable. Debe relacionarse con `negocios.user_id` y conservar el ID del negocio, servicio y fecha. Una fecha registrada acredita el hito informado en el sistema; no verifica por sí sola que exista un archivo de certificado adjunto ni que se haya entregado al cliente.

No basta con buscar `certificadoDescargado = 1` en `arraycos`:

- Para los procesos sefardíes, el cálculo usa la fecha de certificado.
- Para Carta de Naturaleza, el mismo indicador también vale `1` cuando se envía el expediente al departamento jurídico, aunque no haya fecha de certificado.
- La ausencia de fecha o de sincronización no demuestra que la institución no haya emitido un certificado. Debe mostrarse como **sin certificado registrado** o **por verificar**.

Referencias: [comparación determinista](../app/Services/DeterministicTeamleaderDealMatcher.php), [cartera global](../app/Services/LocalCosPortfolioService.php), [CosService::calculateCertificadoStatus](../app/Services/CosService.php).

## Cómo determinar si debe dinero

La cartera muestra una fila por cliente y permite desplegar sus expedientes. Usa los proyectos Teamleader y enriquece sus importes con los datos del trato asociado o de una coincidencia inequívoca. Si hay proyectos Teamleader y tratos HubSpot sin resolver, no suma indiscriminadamente ambas fuentes: conserva el saldo conocido y marca que puede ser parcial. Los tratos HubSpot duplicados sin proyecto se dejan para revisión.

El cálculo considera las fases 1, 2 y 3, Carta de Naturaleza y CIL/FCJE. Reutiliza el analizador monetario existente, los importes administrativos, los recibos históricos sin duplicar el importe reflejado en el trato y el saldo de abonos del portal aún no reflejados en la importación. La diferencia base es `máximo(preestablecido − pagado, 0)`. Las fases con moneda pendiente de conversión, importes ilegibles o sobrepago no se incorporan al saldo conocido: aparecen para revisión. Este informe no consulta tasas de cambio ni servicios externos.

El listado distingue estos estados:

| Estado | Criterio |
| --- | --- |
| Con saldo conocido | Hay fases legibles en EUR con diferencia positiva. Puede haber además fases por revisar. |
| Sin saldo registrado | Las fases con datos evaluadas no muestran diferencia positiva. |
| Requiere revisión | Hay importes ambiguos, conversión pendiente, sobrepagos u otra incidencia que impide confirmar la cifra. |
| Sin datos suficientes | Falta el proyecto, la vinculación o la información de importes. |

Además, debe distinguirse **saldo financiero** de **saldo cobrable en el portal**. El código actual trata los diferenciales inferiores a 50 EUR como no cobrables cuando cumplen sus condiciones; una fase oculta al cliente conserva su registro financiero interno. La siguiente fase habilitada para pagar tampoco representa necesariamente toda la deuda del cliente.

No deben usarse `users.pay`, el porcentaje de avance del COS o la mera presencia de una fecha de pago para afirmar que un cliente está al día. Tampoco basta con sumar indiscriminadamente `compras.monto`: contiene saldos, pagos e importes pendientes de revisión.

Una fase oculta conserva su saldo financiero interno, pero su importe no se incorpora al cobrable del portal. Tener certificado y saldo significa que el cliente tiene al menos un expediente con fecha de certificado y saldo en alguna fase de su cartera; el detalle permite distinguir a qué expediente corresponde cada uno. No implica que todo el saldo sea del expediente certificado o exigible en este momento.

## Comprobación con la base local

Se realizaron consultas de lectura el 28 de octubre sobre la base configurada en este entorno. **Estos números describen la copia local, sin sincronización; no constituyen un padrón actualizado de producción ni un listado de cobro.**

| Comprobación | Resultado |
| --- | ---: |
| Usuarios con rol Cliente | 16.124 |
| Clientes con fecha de certificado en sus expedientes locales HubSpot/Teamleader | 1.790 |
| Clientes con saldo conocido positivo | 1.715 |
| Clientes con certificado registrado y saldo conocido positivo | 271 |
| Clientes con alguna incidencia para revisión | 3.321 |
| Nuevas asociaciones inequívocas propuestas sin IA | 101 |

Se incluyen todos los usuarios con rol Cliente, sin limitarse a quienes tienen snapshot COS o al servicio actual del perfil. Las fechas de certificado deben ser válidas, desde 1900 y no futuras. La consulta inicial que leía únicamente `negocios` encontraba 115 clientes con fecha; incluir los proyectos Teamleader permite obtener el listado global sin esperar a que todos estén vinculados a HubSpot.

Los grupos de la tabla se solapan. Un cliente con saldo conocido puede tener otras fases o asociaciones pendientes de revisión. Las cifras se obtuvieron sin guardar las 101 propuestas, sin actualizar los CRM, sin crear compras y sin enviar correos.

## Uso

Menú **Usuarios y permisos → Cartera y certificados**. Requiere permiso `crud.users.index` y un usuario interno. Ventas y coordinación mantienen el filtro por su propio owner; Administrador puede consultar el conjunto completo. La descarga aplica los mismos filtros y alcance. Se neutralizan fórmulas en los campos de texto del CSV.

El informe visible se conserva cinco minutos en caché. Para asociar se abre el cliente desde el listado y se utiliza su ventana de estatus. Guardar asociaciones recalcula las decisiones desde la base, sin confiar en IDs enviados por el navegador, descarta las que ya se hayan asociado e invalida la caché de cartera. Consultar la página o exportarla no modifica asociaciones.

También puede ejecutarse por consola:

```sh
php artisan cos:portfolio --output=outputs/cos/cartera.json
php artisan cos:portfolio --debtors-csv=outputs/cos/clientes-con-saldo.csv
php artisan cos:portfolio --debtors-csv=outputs/cos/certificados-con-saldo.csv --certified-only
php artisan cos:portfolio --apply-associations
```

El directorio de salida debe existir. `--owner=ID` limita el informe. Solo `--apply-associations` guarda enlaces locales; las otras opciones son de consulta/exportación. No se añadió una tarea programada ni se habilitó el enlace de negocios como efecto de refrescar el COS.

## Validación realizada

- `CosPaymentReconciliationTest`, `DeterministicTeamleaderDealMatcherTest`, `LocalCosPortfolioTest`, `CosPortfolioTest` y `TeamleaderProjectPaymentAnalyzerTest`: **63 pruebas y 198 aserciones satisfactorias**.
- `TeamleaderPhasePaymentRulesTest`, con Laravel inicializado para resolver su dependencia de configuración: **18 pruebas y 67 aserciones satisfactorias**. En total, **81 pruebas y 265 aserciones** relacionadas.
- Cobertura de múltiples clientes, exportación completa de más de una página, roles y owner, coincidencias ambiguas en ambas direcciones, monedas distintas, importes cero, recibos históricos, abonos recientes, fases ocultas, duplicados e idempotencia del guardado. Las pruebas impiden peticiones HTTP y verifican que no se envían correos ni se crean compras.
- Comprobación retrospectiva sobre 210 enlaces existentes, ocultando su enlace al comparador: recuperó 67, dejó 143 sin resolver automáticamente y no propuso un enlace distinto para ninguno. Esta comprobación no garantiza la corrección de todas las propuestas nuevas.
- Generación real del informe completo y de los CSV con los datos locales, sin aplicar asociaciones.
- Regresiones de deuda 2.000 + 2.000, fases y procesos distintos, pagos reales de igual importe, saldos antiguos frente a fases pagadas, historial parcialmente reflejado en recibos y rechazo del cobro de copias antiguas.
- Sintaxis PHP y compilación de las tres plantillas Blade modificadas verificadas; revisión de espacios con `git diff --check`.
- PHPUnit también advierte sobre una configuración obsoleta existente.

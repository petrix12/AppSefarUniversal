# Tarjeta 108: cartera global y asociaciones sin IA

Revisión del 28 de octubre de 2026.

Tarea: [Revisar si desde el COS se puede obtener los clientes que tienen el certificado y si deben dinero](https://trello.com/c/oeqp4Y2S/108-revisar-si-desde-el-cos-se-puede-obtener-los-clientes-que-tienen-el-certificado-y-si-deben-dinero).

- Número de tarjeta: **108**.
- Identificador de la URL: **oeqp4Y2S**.
- ID interno de Trello: `6adf288f25d30a668d9de24b`.
- La tarjeta no tiene descripción ni checklist; su único comentario indica que se escaló para revisar la viabilidad.

## Resultado

Se implementó **Cartera y certificados**, un listado global de clientes con filtros por certificado, saldo, revisión y búsqueda, disponible en `/cos/cartera`. Su exportación CSV incluye todos los resultados filtrados, aunque la pantalla muestre 50 por página.

El listado debe cruzar expedientes y finanzas. El JSON `users.arraycos` por sí solo no permite determinar de forma fiable quién tiene certificado y cuánto debe: no guarda el detalle de deuda, puede estar vencido y agrupa resultados por servicio, perdiendo el vínculo individual con cada negocio.

El listado consulta directamente los proyectos Teamleader importados. Por eso puede obtener clientes con certificado y saldo aunque sus proyectos todavía no estén asociados a HubSpot. Para combinar las dos fuentes sin duplicar saldos, también calcula asociaciones inequívocas sin IA y permite guardarlas en lote mediante **Guardar asociaciones claras**.

## Asociación automática por reglas

Primero se identifica el contacto Teamleader por ID, pasaporte o correo exacto y único, por ese orden. Un pasaporte duplicado o un contacto compartido por varios usuarios se marca para revisión. No se asocian contactos por similitud de nombre.

Dentro del mismo cliente, se consideran suficientes estas coincidencias:

- Código de proceso idéntico, sin contradicción de servicio.
- Nombre completo normalizado y servicio idénticos. El servicio puede estar en el nombre del trato cuando falta su campo específico.
- Servicio idéntico e importes positivos coincidentes en dos fases.
- Nombre completo idéntico e importe positivo coincidente de una fase.

Los nombres se comparan normalizando mayúsculas, acentos y separadores. Un nombre parecido o un importe cero no bastan. Los códigos contradictorios, servicios distintos o preestablecidos distintos impiden el enlace por nombre/importes. La coincidencia debe ser única en ambas direcciones, con margen frente al segundo candidato; se evalúa el conjunto completo para evitar decisiones arbitrarias según el orden.

Las asociaciones existentes se conservan. Un proyecto reservado para otro cliente queda excluido. Guardar el lote actualiza solo `teamleader_id` y los metadatos de la decisión en la base local, bajo transacción y bloqueo por proyecto. No rellena campos remotos ni llama al servicio antiguo que puede recurrir a IA.

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

Se realizaron consultas de lectura sobre la base configurada en este entorno. **Estos números describen la copia local, sin sincronización; no constituyen un padrón actualizado de producción ni un listado de cobro.**

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

El informe visible se conserva cinco minutos en caché. Guardar asociaciones recalcula las decisiones desde la base, sin confiar en IDs enviados por el navegador, y descarta las que ya se hayan asociado. Consultar la página o exportarla no modifica asociaciones.

También puede ejecutarse por consola:

```sh
php artisan cos:portfolio --output=outputs/cos/cartera.json
php artisan cos:portfolio --debtors-csv=outputs/cos/clientes-con-saldo.csv
php artisan cos:portfolio --debtors-csv=outputs/cos/certificados-con-saldo.csv --certified-only
php artisan cos:portfolio --apply-associations
```

El directorio de salida debe existir. `--owner=ID` limita el informe. Solo `--apply-associations` guarda enlaces locales; las otras opciones son de consulta/exportación. No se añadió una tarea programada ni se habilitó el enlace de negocios como efecto de refrescar el COS.

## Validación realizada

- `DeterministicTeamleaderDealMatcherTest`, `LocalCosPortfolioTest`, `CosPortfolioTest` y `TeamleaderProjectPaymentAnalyzerTest`: **44 pruebas y 123 aserciones satisfactorias**.
- Cobertura de múltiples clientes, exportación completa de más de una página, roles y owner, coincidencias ambiguas en ambas direcciones, monedas distintas, importes cero, recibos históricos, abonos recientes, fases ocultas, duplicados e idempotencia del guardado. Las pruebas impiden peticiones HTTP y verifican que no se envían correos ni se crean compras.
- Comprobación retrospectiva sobre 210 enlaces existentes, ocultando su enlace al comparador: recuperó 67, dejó 143 sin resolver automáticamente y no propuso un enlace distinto para ninguno. Esta comprobación no garantiza la corrección de todas las propuestas nuevas.
- Generación real del informe completo y de los CSV con los datos locales, sin aplicar asociaciones.
- PHPUnit también advierte sobre una configuración obsoleta existente.

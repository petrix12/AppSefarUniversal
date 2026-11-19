# Fases de nacionalidad copiadas en negocios de traducción

## Diagnóstico del 18 de noviembre de 2026

En la ficha 8904 había tres negocios HubSpot: nacionalidad portuguesa y dos de traducción al portugués. Los negocios de traducción son registros externos diferentes (`9413563919` y `15611284344`), no dos filas locales del mismo ID.

La consulta de sus propiedades encontró en ambos las mismas fases que en el negocio de nacionalidad `7265556921`:

- Fase 1 preestablecida: `1050€`; pagado Teamleader: `Abono 1000€ 29/01/2022`.
- Fase 2 preestablecida: `1875€/5`; pagado Teamleader: `Abono 1150$ 02/05/2022 + 750$ 30/08/2022`.
- Fase 3 preestablecida: `1875€/5`; pagado Teamleader: `1875$ 30/08/2022`.

El historial nativo de los dos tratos de traducción atribuye esos valores a **Api-Call-Sefar, 06/11/2023 a las 20:51 GMT-4**. El segundo trato había sido creado el 13/10/2023 por el workflow «Creación Deals para Otros Servicios». Son operaciones diferentes: crear el duplicado y escribir sus fases. El nombre de la integración identifica al emisor; no demuestra qué script o versión originó aquella copia.

En los campos generales, el primer trato tenía `amount=0` y el segundo no tenía valor; ninguno tenía `pagado` ni `saldo_pendiente`. La ficha COS utilizaba las propiedades de fases y las sumaba sin comprobar si correspondían al servicio: **4.800 EUR por negocio, 14.400 EUR en total**. No se encontró una tarifa predeterminada de traducción que generara esos importes.

El usuario confirmó que esas fases pertenecen exclusivamente al negocio de nacionalidad. Capturas del historial en `outputs/cos-translation-review/`.

## Corrección

`CosDealPaymentScope` excluye las fases de nacionalidad de negocios y proyectos clasificados como traducción. Se prioriza `servicio_solicitado2` sobre el campo IT antiguo: el negocio legítimo de nacionalidad también tenía ese campo antiguo con un servicio incorrecto. Solo se usa el título como alternativa cuando no hay servicio explícito.

La regla se aplica al resumen administrativo, conciliación del cliente, cartera, análisis de proyectos y creación de saldos/historial importado. Los espejos de fases sin factura se excluyen de la presentación; los recibos reales, las facturas y los cargos independientes se conservan. No se inventa un precio de traducción a partir de un valor vacío o cero.

También se impide que el enriquecimiento rellene fases de nacionalidad en traducción, tanto en el refresco como en la edición del negocio. Ni un código de proceso compartido ni importes copiados ni una sugerencia de IA permiten asociar automáticamente traducción con nacionalidad. Los enlaces de cobro antiguos verifican el servicio actual y los comprobantes con un vínculo incompatible pasan a revisión antes de escribir en cualquier CRM.

Esto no fusiona ni elimina negocios, no borra propiedades externas ni modifica importes cobrados. La limpieza histórica de HubSpot es una operación separada; el COS corregido no depende de borrar esos campos para dejar de sumarlos.

## Validación local

**154 pruebas y 721 aserciones satisfactorias** en las suites de alcance por servicio, conciliación, comparación sin IA, pagos por fase, cartera, comprobantes CRM, importación de negocios y presentación. Se reproduce el caso de tres negocios con fases iguales: el preestablecido de nacionalidad queda en 4.800 EUR una sola vez. Se comprueban además los recibos reales conservados, el bloqueo de enlaces antiguos, el rechazo de una asociación sugerida por IA y la ausencia de escrituras CRM ante un comprobante con servicio incompatible.

Los casos nuevos impiden peticiones HTTP no simuladas. Sintaxis de los 18 archivos PHP cambiados y `git diff --check` correctos. Esta validación no representa una auditoría de todos los registros históricos ni de toda la batería del repositorio.

## Despliegue y comprobación

Desplegar la actualización completa, incluido `app/Services/CosDealPaymentScope.php`, y ejecutar `php artisan config:clear`. No requiere migraciones, variables nuevas, cambios de S3 ni activar los webhooks generales. No ejecutar `config:cache`.

Abrir una ficha nueva de `/users/8904/edit`. «Negocios» debe conservar los tres registros; «Resumen de pagos por proceso» debe contar las fases de nacionalidad una sola vez, con un preestablecido de **4.800 EUR**. El pagado y el saldo se mantienen sujetos a sus fuentes y reglas de conciliación; esta corrección no declara al cliente solvente ni fija manualmente un saldo.

La corrección y sus simulaciones locales no acreditan que ya esté desplegada ni que se hayan limpiado los datos de HubSpot.

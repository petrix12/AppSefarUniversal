# Conciliación de abonos y remanentes del COS

## Caso comprobado el 17 de noviembre de 2026

En el expediente 290, el resumen mostraba 1.703,98 EUR pagados y 1.796,02 EUR pendientes sobre un preestablecido de 3.500 EUR. El texto contenía dos abonos históricos en USD, pero el primer registro histórico seguía sin convertirse. La consulta al BCE respondió correctamente en unos nueve segundos durante la comprobación, por encima del timeout anterior de cinco segundos.

La compra 7786 confirmó un abono posterior de 51,36 EUR, pagado y asociado al mismo proyecto y fase 98. Su metadata inmutable conservaba:

- `paid_amount_at_sync`: 3.448,64 EUR.
- `preestablished_amount`: 3.500 EUR.
- `balance_amount_at_sync`: 51,36 EUR.

Por tanto, el total confirmado es **3.500 EUR y el saldo pendiente es cero**. La tabla y el PDF mostraban 51 EUR porque llamaban al formateador con precisión cero.

## Comportamiento corregido

- Los abonos `teamleader_phase_installment` pagados se cruzan por usuario, proyecto Teamleader y fase, aunque no tengan `deal_id`. Otras personas, proyectos, fases y monedas no se suman.
- Cada recibo conserva el total confirmado antes del cobro. Se reconstruye un mínimo pagado a partir de esas bases y de los abonos posteriores, y se compara mediante `max` con los totales de CRM y de legado. No se añade otra vez el recibo cuando Teamleader ya lo refleja.
- Los registros calculados de saldo y los espejos históricos no se tratan como nuevos cobros.
- Se reutilizan las conversiones BCE guardadas al cobrar, comprobando moneda, fecha, importe, tasa y resultado. Si no existen, la primera consulta permite hasta 15 segundos y las tasas correctas mantienen la caché de 90 días.
- Una respuesta CSV se ordena por la fecha de observación y nunca se toma una tasa posterior al pago.
- Si falta una conversión sin evidencia de liquidación completa, el resumen muestra el importe parcial y `Pendiente de cálculo`, sin publicar la diferencia como deuda definitiva. Un recibo que acredita la liquidación completa impide que una caída del BCE recree esa deuda.
- La tabla de pagos y el comprobante muestran los céntimos. Los pagos por fase muestran su descripción en lugar del identificador técnico del servicio.

No se cambian recibos cobrados ni se ejecutan cobros, devoluciones, escrituras en CRM o cambios de S3 como parte del diagnóstico. El recibo confirmado permanece con su importe original.

Los recibos antiguos sin base `paid_amount_at_sync` no se reconstruyen adivinando coincidencias por importe o por nombre; requieren evidencias de conciliación adicionales.

## Validación

Las pruebas cubren las dos conversiones históricas, recuperación de tasas tras fallo, caché, reutilización de evidencia del recibo, CSV desordenado, liquidación con BCE caído, ausencia de `deal_id`, aislamiento de usuario/proyecto/fase, repetición del cálculo sin duplicar registros, conservación del recibo y visualización de céntimos.

La validación combinada del cambio financiero y las protecciones previas de identidad/configuración pasó **77 pruebas y 486 aserciones**. El análisis de sintaxis de los nueve archivos PHP de este cambio y `git diff --check` también pasaron.

Previsualizaciones locales generadas a partir del fragmento Blade real, con datos de prueba y los estilos AdminLTE existentes:

- `outputs/cos-payment-fix/complete.png`: total pagado 3.500 EUR, saldo cero e inclusión del abono de la App.
- `outputs/cos-payment-fix/pending.png`: una conversión incompleta no aparece como saldo definitivo.

## Producción

Durante la validación, el usuario incorporó los cambios al commit `2e51787b` (`deploy`). Después se abrió una ficha nueva del expediente 290 en producción y se verificó visualmente:

- Preestablecido: 3.500 EUR.
- Pagado / abonado: 3.500 EUR.
- Saldo pendiente: 0 EUR.
- Estado: Pagado.
- Desglose visible: 51,36 EUR pagados en la App y 3.448,64 EUR en cuotas convertidas.

La evidencia está en `outputs/cos-payment-fix/production-paid-zero-balance.png`. No se ejecutaron cobros ni modificaciones manuales de importes para obtener ese resultado. La validación del PDF con céntimos se realizó en las pruebas locales; la ficha nueva de producción confirmó el resumen y sus importes.

El cambio no requiere migraciones, variables nuevas ni ejecutar `config:cache`.

Referencias para las conversiones históricas: [Banca d'Italia / Eurosistema, 28-10-2022](https://www.bancaditalia.it/compiti/operazioni-cambi/cambio/cambi_rif_20221028/) y [23-03-2023](https://www.bancaditalia.it/compiti/operazioni-cambi/cambio/cambi_rif_20230323/).

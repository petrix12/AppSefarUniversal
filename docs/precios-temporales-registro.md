# Precios temporales de registro

En **Servicios → Programar precios temporales** (`/servicios/promociones`), el personal con permisos de consulta y edición de servicios puede seleccionar varios registros y guardar un importe temporal, una fecha de inicio y el último día incluido. **Seleccionar servicios de 299 EUR** marca esos servicios para revisarlos antes de guardar. También se puede configurar o quitar la promoción desde la edición individual de un servicio.

El importe habitual permanece en `servicios.precio`. El precio temporal y las dos fechas se guardan por separado. Las fechas abarcan días completos en `America/Caracas`, independientemente de la zona del servidor. Una promoción que termine el 5 vuelve al precio habitual a las 00:00 del día 6 en Caracas. No necesita cron ni cambiar el precio manualmente. La opción admite servicios activos en EUR del tipo `servicio`; no incluye consultas de Kalendia ni fases COS. Cada servicio recupera su propio precio habitual, que debe ser 299 EUR si ese es el importe previsto para después de la campaña.

Los registros nuevos de `/registerv2` y Fortify crean una compra pendiente con el precio vigente en el servidor. Ese importe queda guardado aunque el cliente pague después del fin de la promoción. Las compras anteriores, pagadas o pendientes, conservan su importe; programar o cancelar una promoción no las recalcula. Los clientes antiguos sin compra inicial reciben el precio vigente al abrir por primera vez el pago. El importe del navegador no decide el precio. La App conserva en `compras.metadata.pricing` el precio habitual, el precio unitario aplicado, cantidad, moneda, fecha de cálculo y las condiciones de la promoción.

El registro, el pago inicial, el catálogo de servicios y `/api/getservicio` usan el mismo precio vigente. Se eliminan los importes fijos de 99/299 EUR que podían reemplazar el catálogo en la modalidad LMD. Se conservan las variantes de hermanos y la cantidad de recursos de alzada. El cobro usa el importe guardado en la compra y el CRM recibe el total efectivamente cobrado. Los datos de tarjeta y las pasarelas existentes no cambian.

Para quitar una promoción desde **Editar servicio**, dejar vacíos su importe y ambas fechas. Para modificarla, guardar nuevamente los tres valores. Una selección con fechas inválidas, precios superiores al habitual o servicios no admitidos se rechaza completa, sin aplicar parcialmente el cambio.

## Despliegue

Subir el código y aplicar únicamente la migración nueva en la instalación correspondiente:

```sh
php artisan migrate --force --path=database/migrations/2026_11_27_150000_add_temporary_prices_to_servicios_table.php
php artisan view:clear
```

La migración agrega tres columnas opcionales; no activa promociones ni cambia importes. La campaña concreta se guarda desde administración una vez definidas sus fechas y servicios. No se debe asumir que la fecha del ejemplo es una campaña activa.

Validación aislada:

```sh
php artisan test --filter=TemporaryServicePricesTest
```

Las pruebas verifican los límites de fecha en Caracas, la vuelta al precio habitual, permisos, validación, conservación de compras y registro del precio tanto en Fortify como en `/registerv2`. Usan proveedores simulados, sin cobros ni cambios en producción.

# Copias de plataformas

La administración incorpora una importación inicial conjunta y reanudable. La pantalla **Integraciones → Copias de plataformas** muestra las conexiones configuradas, los registros guardados, el avance por recurso y la última ejecución del importador. Se mantienen los estilos y permisos existentes de AdminLTE. Solo administradores internos pueden consultar o solicitar estas copias.

| Fuente | Datos disponibles en este módulo | Estrategia |
| --- | --- | --- |
| Stripe principal | Pagos, intentos, clientes, facturas, reembolsos, suscripciones, disputas, productos y precios: 9 recursos | Copia local e información reciente mediante webhooks y consultas de ficha |
| Stripe histórico España | Los mismos 9 recursos, con credencial dedicada | Copia local de solo consulta, importación explícita |
| Kalendia | Clientes, citas, servicios, profesionales, recursos, pagos, pedidos, productos, categorías, inventario, cupones, formularios y respuestas: 13 recursos | Copia local; los eventos admitidos actualizan sus registros y las fichas permiten consultar la API |
| PayPal histórico España | Transacciones | Consulta directa por API con filtros de fechas; hasta tres años atrás y rangos de hasta 31 días |

La copia utiliza los campos permitidos de `PlatformCatalog`, cifrados en los registros de plataforma. El contenido y los límites disponibles dependen de cada API y de los permisos de la credencial. No debe presentarse como una exportación íntegra de todos los productos y campos de los proveedores. Las notas de Kalendia continúan disponibles por API desde el contacto. Los recursos sin eventos de cambio admitidos se actualizan al volver a solicitar su importación.

## Preparación

No se necesitan variables nuevas para este cambio. Se usan las conexiones existentes. Si una credencial histórica todavía no está configurada, esa fuente permanece pendiente sin utilizar claves de otra cuenta.

Esta función utiliza las tablas de administración ya incorporadas en `2026_11_22_180000_create_platform_management_tables.php`. Si esa migración aún no se aplicó, la pantalla lo indicará. Al desplegar el código actualizado, ejecutar desde la carpeta Laravel:

```bash
php artisan route:clear
php artisan config:clear
```

No ejecutar `config:cache`.

## Iniciar la copia

Desde **Integraciones → Copias de plataformas**, usar **Importar conexiones configuradas**, o entrar en una conexión y pulsar **Importar todos los recursos**. La solicitud es rápida: únicamente registra el trabajo local; las páginas se recuperan en segundo plano. PayPal permanece como fuente API bajo demanda.

También puede revisarse el plan y solicitarlo desde SSH:

```bash
# Solo planificación: no consulta APIs ni modifica registros.
php artisan platforms:import

# Solicitar todos los recursos de las conexiones configuradas.
php artisan platforms:import --queue

# Solicitar solo la cuenta principal de Stripe.
php artisan platforms:import --provider=stripe --account=main --queue
```

Los filtros `--provider=stripe`, `--provider=kalendia`, `--account=main` y `--account=spain_legacy` también permiten seleccionar grupos. Las combinaciones inexistentes se rechazan. La ausencia de una cuenta histórica opcional se informa y no impide solicitar las conexiones configuradas.

## Procesamiento y avance

El scheduler existente ejecuta `platforms:sync --limit=2` cada minuto. Cada ejecución recupera como máximo dos páginas; el límite controla las llamadas externas para que el trabajo no se cargue sobre una petición web. Cada página admite hasta 100 registros. El servidor debe seguir ejecutando el scheduler habitual de Laravel; solicitar una copia no sustituye esa ejecución.

Para comprobar manualmente un lote acotado:

```bash
php artisan platforms:sync --limit=2
```

La pantalla muestra **Pendiente**, **En curso**, **Completada** o **Requiere revisión**, sin estimar un porcentaje cuando no se conoce el total remoto. **Actualizar avance** solo vuelve a consultar la información local. La cantidad guardada cuenta identificadores únicos de cada recurso, cuenta y modo.

Al reintentar, se conserva el cursor de la última página confirmada. Una solicitud que ya está en curso no se reinicia. Si una importación falló, corregir el acceso o la causa indicada y volver a solicitar sus recursos para reanudarla. Si terminó correctamente, solicitarla otra vez inicia una actualización completa mediante upsert.

## Integridad

- La clave de cada registro combina proveedor, cuenta, modo, recurso e identificador remoto. Importar otra vez actualiza esa copia sin crear un segundo registro por el mismo objeto.
- Todas las llamadas de importación son GET. El histórico español conserva su bloqueo de escrituras incluso para superadmins. PayPal usa POST únicamente para autenticación OAuth y GET para su consulta.
- La importación no crea cuentas de acceso, compras ni comprobantes de la App, y no concede servicios ni cobra tarjetas.
- Las páginas se guardan de forma transaccional y se valida el propietario del trabajo antes de avanzar. Una página inválida o repetida queda señalada para revisión.
- Las suscripciones Stripe se consultan con `status=all`. Los precios se recorren en dos pasadas, activos e inactivos. El listado de productos ya incluye ambos estados sin añadir un filtro.
- Para Kalendia, una página corta sin información de totales no termina la importación: se sigue hasta una página vacía. Los cursores incluyen una marca para no reutilizar una numeración de páginas de otro tamaño.
- Los cursores anteriores de suscripciones o de páginas Kalendia se reinician una vez al cambiar el filtro o el tamaño de página, conservando los registros existentes. Así se evitan saltos durante la actualización del código.

La recepción de webhooks debe verificarse por separado. El botón de importación no registra destinos externos, no cambia secretos ni acredita que las entregas funcionen.

Referencias: [suscripciones Stripe](https://docs.stripe.com/api/subscriptions/list), [precios Stripe](https://docs.stripe.com/api/prices/list), [productos Stripe](https://docs.stripe.com/api/products/list), [búsqueda de transacciones PayPal](https://developer.paypal.com/api/transaction-search/v1/search-get).

# Réplica local de Monday

## Objetivo

La aplicación conserva en su propia base de datos una copia consultable de la estructura y los datos operativos de Monday. Las pantallas no tienen que esperar a Monday para leer catálogos, localizar un cliente por el enlace COS o calcular las estadísticas existentes.

Monday sigue siendo la fuente de verdad para:

- la importación inicial y la reconciliación periódica;
- los cambios recibidos por webhook;
- cualquier escritura que deba terminar en Monday.

Por tanto, la réplica elimina la dependencia de la API en las lecturas normales y permite seguir consultando el último estado conocido durante una caída, pero no permite escribir en Monday si Monday no está disponible.

## Cobertura inicial

Se replican:

- tableros y su estado;
- grupos;
- columnas, tipos y configuración;
- items activos;
- subitems;
- texto y valor JSON de todas las columnas;
- el JSON crudo de cada entidad;
- ejecuciones y errores de sincronización;
- entregas de webhooks, con deduplicación.

No se descargan todavía comentarios/updates, historial de actividad ni binarios de archivos. Los valores de columnas de archivo se conservan como datos de Monday, pero copiar el binario a S3 será una fase aparte.

`items_page` solo permite recorrer items activos. Un item que desaparece de un barrido completo se conserva localmente con borrado lógico. El webhook permite identificar el cambio rápidamente, pero una primera importación no puede descubrir items archivados o eliminados cuyos IDs nunca conoció la aplicación. En la versión `2026-10` usada por esta cuenta, la consulta puntual `items(ids: ...)` tampoco acepta el argumento `state`; por eso la ausencia se reconcilia contra la ubicación local y nunca borra físicamente la fila.

## Tablas

- `monday_boards`
- `monday_groups`
- `monday_columns`
- `monday_items`
- `monday_item_values`
- `monday_item_movements`
- `monday_sync_runs`
- `monday_webhook_events`

Los IDs externos se guardan como texto para no depender del tamaño o formato que Monday use en el futuro. Los items eliminados o archivados no se borran físicamente.

## Puesta en marcha

Configurar:

```dotenv
MONDAY_TOKEN=...
MONDAY_API_VERSION=2026-10
MONDAY_MIRROR_ENABLED=true
MONDAY_MIRROR_READS=true
MONDAY_MIRROR_PAGE_SIZE=100
MONDAY_WEBHOOK_SECRET=una-cadena-larga-aleatoria
MONDAY_QUEUE_CONNECTION=database
```

Después:

```bash
php artisan migrate
php artisan monday:sync
php artisan monday:status
```

La primera ejecución se hace directamente para poder observar cualquier error. También se puede probar un tablero antes de importar toda la cuenta:

```bash
php artisan monday:sync --board=878831315
```

Con `MONDAY_MIRROR_ENABLED=true`, el scheduler:

1. despacha una reconciliación completa cada 6 horas como red de seguridad;
2. revisa la cola `monday-sync` cada minuto, pero no consulta Monday cuando la cola está vacía;
3. evita solapamientos de workers y sincronizaciones programadas.

El servidor debe seguir ejecutando `php artisan schedule:run` cada minuto, igual que para las demás tareas programadas del proyecto.

## Webhooks

El endpoint receptor es:

```text
POST /api/webhooks/monday?token=MONDAY_WEBHOOK_SECRET
```

`APP_URL` debe ser una URL HTTPS pública. Para crear las suscripciones en todos los tableros locales activos:

```bash
php artisan monday:webhooks
```

Después de esta primera configuración, cada inventario de cuenta verifica automáticamente las suscripciones de los tableros activos. Si aparece un tablero nuevo, se crea su conjunto de webhooks sin intervención manual.

También se puede limitar la operación:

```bash
php artisan monday:webhooks --board=878831315
```

Las entregas repetidas se deduplican por `triggerUuid`. Los eventos de creación, edición, cambio de grupo o movimiento de subitem vuelven a leer el item afectado y guardan su tablero, grupo, padre y valores actuales. Cada cambio de tablero, grupo o padre queda auditado en `monday_item_movements`, indicando si lo detectó un webhook o la reconciliación. Los eventos de archivo/eliminación consultan primero el estado actual antes de aplicar borrado lógico.

Ese último punto es importante para movimientos entre tableros: Monday no publica un tipo de webhook dedicado exclusivamente a `move_item_to_board`. En la práctica pueden llegar eventos del tablero origen y del destino en cualquier orden. La réplica no confía en ese orden; consulta el item por ID y deja que su ubicación actual en Monday gane. Así, un evento tardío de eliminación del tablero origen no puede borrar la fila que ya fue reconciliada en el tablero destino. Si el movimiento crea un ID nuevo en lugar de conservarlo, el item anterior queda con borrado lógico y el nuevo se crea en el destino.

Los webhooks reducen la demora normal a segundos y son el mecanismo principal. El barrido de 6 horas solo repara una entrega perdida, descubre tableros nuevos y reconcilia cambios estructurales para los que Monday no ofrece un evento específico.

## Lecturas locales activadas

Estas funciones ya prefieren la réplica cuando el tablero terminó al menos una sincronización:

- selector de tableros, grupos y columnas (`MondayCatalogService`);
- búsqueda Monday usada por el COS (`CosMondayLookup`);
- estadísticas de Monday (`MondayController::mondayreportes`).

Si las tablas aún no existen o un tablero todavía no terminó su primera copia, los servicios de catálogo y COS conservan temporalmente su comportamiento anterior y consultan Monday. Esto permite desplegar el código antes de ejecutar la migración/importación sin romper esas pantallas.

## Operación

```bash
php artisan monday:status
```

El comando muestra cobertura y ejecuciones recientes. Los logs están en:

- `storage/logs/monday-mirror.log`
- `storage/logs/monday-worker.log`
- log general de Laravel para el detalle de cada tablero.

Una ejecución fallida no marca como eliminados los registros que no alcanzó a leer. El borrado lógico de ausentes solo ocurre después de terminar correctamente todas las páginas del tablero.

## Próximas fases

1. Copiar updates/comentarios y usuarios de Monday.
2. Descargar archivos a S3 y guardar su checksum.
3. Migrar las lecturas directas restantes a repositorios locales; las mutaciones seguirán usando un patrón outbox y reintentos.
4. Aplicar el mismo esquema a HubSpot: contactos, deals, asociaciones, propietarios, pipelines y propiedades, con webhooks más reconciliación.

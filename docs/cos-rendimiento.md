# Carga del COS

## Optimización de las consultas Monday (7 de septiembre de 2026)

Las tres rutas de búsqueda comparten `CosMondayLookup`. Consulta los 12 tableros configurados mediante peticiones HTTP concurrentes, con máximo 12 simultáneas, límite de conexión de 5 segundos y timeout de 20 segundos. No requiere colas para el paralelismo. Conserva la prioridad de la configuración cuando aparecen varias coincidencias y trata los errores o resultados incompletos como fallos, no como ausencia de cliente. Una búsqueda completa sin resultados se recuerda 60 segundos; la clave incorpora usuario, pasaporte y lista de tableros.

El refresco del snapshot reutiliza los datos del item encontrados por la búsqueda, evitando otra consulta de detalle. Las definiciones de columnas y su persistencia se actualizan una vez por hora y tablero; los datos del cliente conservan su política de actualización independiente.

### Medición real de lectura

Prueba desde el entorno local contra Monday, con un identificador sintético sin coincidencias y los mismos 12 tableros. Una pasada comparativa, sin descargar filas de clientes ni modificar Monday:

| Método | Tiempo |
| --- | ---: |
| 12 consultas secuenciales | 13,927 s |
| Una consulta con 12 aliases | 3,798 s |
| 12 consultas concurrentes (seleccionado) | 2,090 s |

La mejora observada de búsqueda es 6,67×. No equivale a un benchmark de la página completa ni demuestra 10× en producción. Las consultas concurrentes consumen las mismas 12 operaciones remotas; no se amplían los reintentos ante rate limits. Para medir el resultado global, comparar el registro `COS página renderizada` para los mismos clientes y condiciones de caché en producción.

Pruebas específicas: `CosMondayLookupTest` verifica cobertura de tableros, caché de ausencia, prioridad de coincidencias y que los fallos no se cachean como resultados vacíos.

Las rutas de estado del cliente y de ficha administrativa utilizan `ClientCosSnapshotService::forPage()`. Leen negocios, Monday y el COS almacenado sin consultar servicios externos. Si falta el snapshot, calculan una primera vista con datos locales y reglas manuales (sin OpenRouter). Este cálculo provisional no modifica la fecha de vencimiento.

Al visitar la ficha se solicita una actualización como máximo cada cinco minutos por usuario, deduplicada mediante `ShouldBeUnique`. Mientras se procesa se conserva visible el snapshot anterior. El botón de sincronización solicita la actualización en segundo plano y avisa que debe recargarse la página.

`RefreshClientCosSnapshot` utiliza explícitamente la conexión `cos`, con driver database y cola `cos-refresh`, incluso cuando la conexión global es `sync`. Actualiza el snapshot, el catálogo compartido de usuarios Monday y la importación de archivos HubSpot (una vez por hora por cliente). Las consultas a fuentes externas y la importación de documentos ya no se realizan en la petición que renderiza la ficha. Los errores de HubSpot impiden marcar como renovado el snapshot anterior.

## Operación

Requiere la tabla `jobs` ya existente y el scheduler de Laravel ejecutándose cada minuto. El scheduler inicia un worker dedicado, sin solapamientos. Al desplegar, regenerar la caché de configuración para cargar la nueva conexión.

También puede mantenerse un worker supervisado con:

```sh
php artisan queue:work cos --queue=cos-refresh --timeout=300 --tries=3
```

La conexión tiene `retry_after=360`, superior al timeout del job. Los errores del worker programado se registran en `storage/logs/cos-worker.log`. Si no funciona el scheduler/worker, la ficha sigue mostrando datos locales pero las actualizaciones permanecen pendientes en `jobs`.

## Verificación

`tests/Feature/ClientCosPageTest.php` comprueba snapshots vigentes, vencidos, primera carga sin snapshot y deduplicación real en la tabla de jobs con conexión global sync. Se prohíben peticiones HTTP durante estas pruebas. Las pruebas de CosService cubren el cálculo existente. El tiempo real en producción requiere medir ambas rutas con clientes representativos; los tiempos de las pruebas locales no son un benchmark de producción.

# Carga del COS

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

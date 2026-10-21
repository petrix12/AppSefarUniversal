# Refrescar el COS de un cliente por API

```http
POST /api/v1/clientes/cos/refresh
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{
  "email": "cliente@ejemplo.com",
  "passport": "AB123456"
}
```

Enviar `email`, `passport` o ambos; se requiere al menos uno. Primero se busca por correo entre los usuarios con rol `Cliente`. Si no hay coincidencia por correo, se busca por pasaporte. Las comparaciones son exactas, ignorando mayúsculas/minúsculas y espacios al principio o al final. No se usan coincidencias parciales. Si ambos identificadores corresponden a clientes distintos, tiene prioridad el correo y solo se actualiza ese cliente.

Si se encuentra al cliente, el refresco es síncrono: el `200` se devuelve después de consultar las fuentes, actualizar los negocios locales y guardar el COS. No necesita un worker de colas. Los parámetros `sync=false` o `force_refresh=false` no desactivan el refresco.

Si no hay coincidencia en la app, no se crea ningún usuario, no se modifican clientes ni negocios y no se consultan las fuentes externas. La respuesta también es `200`, con una alerta:

```json
{
  "message": "No se encontraron coincidencias en la app con el correo o pasaporte indicado.",
  "alert": {
    "type": "warning",
    "code": "client_not_found",
    "message": "No se encontraron coincidencias en la app con el correo o pasaporte indicado."
  },
  "data": null,
  "meta": {
    "found": false,
    "refreshed": false,
    "matched_by": null,
    "forced_refresh": false
  }
}
```

La ruta por ID sigue disponible para integraciones que ya lo conocen: `POST /api/v1/clientes/{cliente}/cos/refresh`, sin body. En esa ruta `{cliente}` debe ser el ID numérico de un usuario con rol `Cliente` y un ID ausente devuelve `404`.

## Token

Un administrador puede crearlo en **Integraciones → Tokens API** (`/admin/integrations/api-tokens`): elegir un usuario interno, asignar un nombre y marcar **Actualizar COS sin caché** (`cos:refresh`). El token se muestra una sola vez y se puede revocar desde esa pantalla. Los tokens `read`, `update` o `mcp:read` por sí solos no autorizan esta ruta. Un usuario con rol `Cliente` no puede ejecutarla, aunque su token incluya el permiso. Una sesión web tampoco sustituye al Bearer token.

También puede generarse desde `php artisan tinker`, sustituyendo `ID_USUARIO_INTERNO` por el ID real:

```php
$user = App\Models\User::findOrFail(ID_USUARIO_INTERNO);
abort_if($user->hasRole('Cliente'), 403);
$token = $user->createToken('Integración refresco COS', ['cos:refresh'], now()->addDays(90));
$token->plainTextToken;
```

Sanctum almacena el hash del token. Enviar el valor completo en la cabecera `Authorization`, por HTTPS; no en la URL.

## Ejemplo

```sh
curl --request POST 'https://app.sefaruniversal.com/api/v1/clientes/cos/refresh' \
  --header 'Authorization: Bearer <TOKEN>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{"email":"cliente@ejemplo.com","passport":"AB123456"}'
```

Cuando se actualiza el COS, la respuesta incluye:

- `data.client_id`: ID numérico del cliente para crear notas u otras operaciones; se conserva también en `data.client.id`.
- `data.client`: identificación del cliente y sus vínculos con las integraciones.
- `data.cos`, `data.cosready` y `data.arraycos_expire`: el resultado recién guardado.
- `data.negocios_count` y `data.monday`: negocios locales y resultado de Monday.
- `meta.forced_refresh: true`, `meta.sync.cache.hit: false` y `meta.sync.external: true`.
- `meta.found: true`, `meta.refreshed: true` y `meta.matched_by`: `email`, `passport` o `id`, según la coincidencia utilizada.
- `meta.sync.local_deals`: negocios recibidos, insertados, actualizados y eliminados.
- `meta.generated_at` y `meta.duration_ms`.

## Alcance del refresco

En cada llamada se omite el COS almacenado, la caché de contacto/negocios de HubSpot y la de contacto/proyectos de Teamleader. Se reconcilian los negocios en la app con la lista actual de HubSpot y se ejecuta la asociación/enriquecimiento existente con Teamleader. En el modo forzado se recorren todas las páginas de proyectos y los errores de lista o detalle se propagan.

Se vuelve a consultar el ítem de Monday vinculado al cliente. Si falta `monday_id`, se busca por el enlace del pasaporte en los tableros de `config/cos_snapshot.php`, ignorando incluso una ausencia cacheada. También se refrescan las columnas del tablero. Una ausencia confirmada limpia los datos Monday locales; una respuesta incompleta o con errores no se interpreta como ausencia.

Las definiciones del COS se leen desde la base de datos sin caché y se recalcula el resultado. Cuando las reglas requieren clasificación por IA, se omite el resultado cacheado; si la IA falla, se conserva el análisis manual existente sobre los datos actuales. Se actualizan las cachés de datos obtenidos para las lecturas habituales, pero esta ruta nunca las utiliza para saltarse el refresco.

Los límites de peticiones y las reglas de asociación automática existentes siguen vigentes. La ruta conserva el límite general de la API (60 solicitudes por minuto). La importación de archivos HubSpot y el catálogo de usuarios Monday pertenecen al job de la ficha y no forman parte de este endpoint de cálculo del COS.

## Errores

| HTTP | Significado |
| --- | --- |
| `401` | Falta el Bearer token, no es válido, venció o fue revocado; o se intentó usar una sesión web. |
| `403` | Falta `cos:refresh` o el dueño del token tiene rol `Cliente`. |
| `404` | En la ruta por ID, el cliente no existe o el usuario destino no tiene rol `Cliente`. La búsqueda sin coincidencias devuelve `200` con alerta. |
| `422` | No se envió correo ni pasaporte, o alguno de los identificadores tiene un formato inválido. |
| `429` | Se alcanzó el límite general de la API. |
| `502` | No se pudo completar el refresco; `error` es `cos_refresh_failed`. Cuando se conoce la fuente, `source` indica `hubspot`, `teamleader` o `monday`. |

Ante un fallo del refresco, no se reemplaza ni se renueva la fecha del COS anterior. Las sincronizaciones que se hayan completado antes del fallo pueden haber actualizado contactos, negocios o datos Monday: no hay una transacción distribuida entre las fuentes. Ajustar el timeout del consumidor/proxy para esperar las consultas externas.

Cuando el cliente se identificó, un fallo de refresco también incluye `data.client_id`. Si no hay coincidencias, `data` sigue siendo `null`. El [módulo y endpoint de notas](client-notes-api.md) permite usar ese ID para añadir una nota al cliente.

Una caída temporal de Teamleader, un timeout o una respuesta de autorización que no sea JSON no invalidan sus tokens almacenados ni requieren reconectar la cuenta. La respuesta distingue estos casos de un refresh token rechazado:

```json
{
  "message": "Teamleader no está disponible o no devolvió una respuesta válida. Se conserva el COS anterior; reintenta más tarde.",
  "error": "cos_refresh_failed",
  "source": "teamleader",
  "reason": "service_unavailable",
  "retryable": true,
  "action": {"type": "retry_later"},
  "data": {"client_id": 123},
  "meta": {"found": true, "refreshed": false, "matched_by": "email"}
}
```

`reason: invalid_authorization_response` también permite reintentar más tarde. Si Teamleader rechaza la autorización, se devuelve `reason: authentication_required`, `retryable: false` y `action.type: reconnect_teamleader`, con la URL de reconexión en `action.url`. No se debe reconectar por una caída temporal ni interpretar un `502` como un COS actualizado. Otros errores conservan `reason: upstream_error`; revisar el log para determinar la causa.

En n8n, el `502` detiene por defecto el flujo antes del nodo de notas. Para guardar una nota independientemente del resultado del COS, seguir la [configuración de n8n del módulo de notas](client-notes-api.md#configurar-el-flujo-en-n8n): activar **Never Error** en el nodo del COS y continuar únicamente si existe `data.client_id`.

## Verificación

```sh
php vendor/phpunit/phpunit/phpunit tests/Feature/ClientCosRefreshApiTest.php tests/Feature/CosMondayLookupTest.php
php vendor/phpunit/phpunit/phpunit tests/Unit/TeamleaderFocusProviderTest.php tests/Feature/TeamleaderAuthorizationTest.php tests/Feature/TeamleaderForcedRefreshTest.php
php vendor/phpunit/phpunit/phpunit --filter test_forced_classification_ignores_cached_ai_results_on_every_call tests/Unit/CosServiceTest.php
```

Las pruebas utilizan tokens Sanctum reales, base de datos SQLite en memoria y fuentes externas simuladas. Cubren búsqueda por correo, búsqueda por pasaporte como alternativa, prioridad del correo, normalización, ausencia sin modificaciones ni consultas externas, validación de entradas, llamadas consecutivas con cachés vigentes, reconciliación local de negocios, búsqueda Monday tras ausencia cacheada, clasificación sin caché y fallos sin renovar el COS anterior.

# Notas de clientes

El módulo de notas tiene almacenamiento propio en `client_notes`. Las notas son internas, independientes del chat, y conservan el cliente, el autor y la fecha. Se consultan y crean desde la opción **Notas** de la ficha del cliente (`/users/{id}/notes`). La pantalla requiere un usuario interno con permiso `crud.users.index` y muestra 20 notas por página, de la más reciente a la más antigua.

## Crear una nota por API

```http
POST /api/v1/clientes/{cliente}/notas
Authorization: Bearer <TOKEN>
Accept: application/json
Content-Type: application/json

{"note":"Se revisó el COS y se confirmó el estado del expediente."}
```

`{cliente}` es el ID numérico del cliente en la app. `note` es obligatorio, debe ser texto no vacío y admite hasta 10.000 caracteres. Se guarda como texto plano, conservando los saltos de línea. El cliente y el autor se determinan mediante la URL y el dueño del token; no se aceptan cambios de autor ni de cliente desde el body.

El token debe pertenecer a un usuario interno y tener el permiso `client-notes:create`, disponible como **Crear notas de clientes** en **Integraciones → Tokens API**. Para utilizar el mismo token en el flujo completo, asignar los permisos `cos:refresh` y `client-notes:create`. El permiso de refrescar COS por sí solo no permite crear notas.

```sh
curl --request POST 'https://app.sefaruniversal.com/api/v1/clientes/123/notas' \
  --header 'Authorization: Bearer <TOKEN>' \
  --header 'Accept: application/json' \
  --header 'Content-Type: application/json' \
  --data '{"note":"Se revisó el COS y se confirmó el estado del expediente."}'
```

Respuesta `201 Created`:

```json
{
  "message": "Nota creada correctamente.",
  "data": {
    "id": 25,
    "client_id": 123,
    "note": "Se revisó el COS y se confirmó el estado del expediente.",
    "author": {"id": 7, "name": "Usuario de la integración"},
    "created_at": "2026-10-21T12:00:00+00:00"
  }
}
```

No se envían mensajes, correos ni notificaciones al guardar una nota. Cada POST crea una nota nueva; si se reintenta una llamada que ya terminó correctamente, se creará otra nota.

## Encadenar con el COS

La respuesta de `POST /api/v1/clientes/cos/refresh` incluye el ID explícito en `data.client_id`, además del existente `data.client.id`. Usar ese valor como `{cliente}` al crear la nota.

Si el cliente fue identificado pero falla una fuente externa durante el refresco, la respuesta `502` también incluye `data.client_id`. Si no se encontró al cliente, se mantiene `data: null` y la alerta `client_not_found`: no se debe continuar con la creación de la nota.

Ejemplo reducido del refresco exitoso:

```json
{
  "data": {
    "client_id": 123,
    "client": {"id": 123}
  },
  "meta": {"found": true, "refreshed": true}
}
```

## Configurar el flujo en n8n

Una respuesta `cos_refresh_failed` viene del endpoint `/cos/refresh`, no del endpoint `/notas`. Si n8n se detiene en el `502` del COS, el siguiente nodo no ejecuta la petición para crear la nota y no habrá respuesta de ese nodo.

Para guardar la nota aunque el refresco del COS haya fallado, manteniendo visible el error de sincronización:

1. En el nodo **HTTP Request del COS**, abrir **Options → Response**, activar **Never Error** y seleccionar **Response Format: JSON**. Mantener desactivado **Include Response Headers and Status** para recibir el cuerpo directamente en `$json`. Estas opciones están descritas en la [documentación oficial de HTTP Request de n8n](https://docs.n8n.io/integrations/builtin/core-nodes/n8n-nodes-base.httprequest/#response).
2. Añadir una condición **IF** que compruebe que `data.client_id` existe. Si no existe, terminar esa rama sin crear notas.
3. En la rama con cliente identificado, usar otro nodo **HTTP Request**, método **POST**, con esta URL en modo **Expression**:

   ```text
   {{ 'https://app.sefaruniversal.com/api/v1/clientes/' + $json.data.client_id + '/notas' }}
   ```

4. Enviar `Authorization: Bearer <TOKEN>`, `Accept: application/json` y un body JSON con el campo `note`. El token necesita `client-notes:create`, además de `cos:refresh` para la primera llamada.
5. Comprobar que el nodo de notas devuelve `201` y `data.id`. Conservar el resultado del COS para distinguir un refresco exitoso de uno pendiente.

Si se activa **Include Response Headers and Status**, n8n devuelve el cuerpo dentro de `body`: usar `body.data.client_id` en la condición y `$json.body.data.client_id` en la URL. Activar **Never Error** permite manejar el `502`; la sincronización sigue habiendo fallado y la nota se guarda de forma independiente.

Para aislar un problema, ejecutar solo el nodo de notas con un ID de cliente conocido y el ejemplo cURL anterior. El endpoint de notas no consulta HubSpot, Teamleader ni Monday.

## Errores

| HTTP | Significado |
| --- | --- |
| `401` | Falta el Bearer token, es inválido, venció o fue revocado. Una sesión web no sustituye el token de API. |
| `403` | Falta `client-notes:create` o el dueño del token tiene rol `Cliente`. |
| `404` | El usuario destino no existe o no tiene el rol `Cliente`. |
| `422` | La nota falta, está vacía, no es texto o supera los 10.000 caracteres. |
| `429` | Se alcanzó el límite general de la API. |

## Despliegue y verificación

Crear la tabla ejecutando la migración antes de habilitar las rutas del módulo:

```sh
php artisan migrate --path=database/migrations/2026_10_21_120000_create_client_notes_table.php --force
php vendor/phpunit/phpunit/phpunit tests/Feature/ClientNotesTest.php tests/Feature/ClientCosRefreshApiTest.php
```

# Sefar Private MCP

MCP privado de App Sefar ejecutado dentro del ambiente PHP/Laravel. No usa Node, npm ni scraping web.

El deploy activa dos transportes:

- HTTP remoto para Codex: `https://app.sefaruniversal.com/mcp`
- Stdio local/Artisan para entornos donde se pueda ejecutar consola.

## Configuracion rapida para Codex

La forma recomendada es desde AdminLTE:

```text
Integraciones > MCP privado
```

La ventana permite:

- crear tokens MCP con permiso `mcp:read`;
- probar el endpoint `/mcp` desde el navegador;
- copiar la configuracion TOML;
- descargar un instalador Windows `instalar-sefar-mcp-codex.cmd` que configura `~/.codex/config.toml` y la variable de usuario `SEFAR_MCP_TOKEN`.

Codex debe quedar con una configuracion equivalente a:

```toml
[mcp_servers.sefar]
url = "https://app.sefaruniversal.com/mcp"
bearer_token_env_var = "SEFAR_MCP_TOKEN"
tool_timeout_sec = 120

[mcp_servers.sefar.tools.refrescar_cos_cliente]
approval_mode = "prompt"
```

Despues de ejecutar el instalador local, hay que cerrar y abrir Codex Desktop para que lea la variable `SEFAR_MCP_TOKEN`.

## Stdio / Artisan

El servidor tambien queda registrado como comando Artisan:

```powershell
php artisan sefar:mcp
```

El archivo `mcp/server.php` queda como wrapper compatible y ejecuta el mismo comando:

```powershell
php mcp/server.php
```

## Configuracion

Variables recomendadas en el `.env` de produccion:

```env
SEFAR_MCP_AUDIT_LOG=/ruta/segura/sefar-mcp-audit.jsonl
SEFAR_MCP_AUDIT_SECRET=un-secreto-largo-para-hmac
```

Si no se configura `SEFAR_MCP_AUDIT_LOG`, Laravel usa:

```text
storage/logs/sefar-mcp-audit.jsonl
```

Ejemplo de configuracion MCP:

```json
{
  "mcpServers": {
    "sefar": {
      "command": "php",
      "args": ["artisan", "sefar:mcp"],
      "env": {
        "APP_ENV": "production"
      }
    }
  }
}
```

Tambien se puede apuntar al wrapper:

```json
{
  "mcpServers": {
    "sefar": {
      "command": "php",
      "args": ["mcp/server.php"]
    }
  }
}
```

## Autenticacion

No hay token fijo en el codigo fuente.

Para el transporte HTTP privado, un administrador genera tokens desde AdminLTE:

```text
Integraciones > MCP privado
```

Los tokens MCP se crean con el permiso `mcp:read` y solo pueden asignarse a usuarios internos, nunca a usuarios con rol `Cliente`. En HTTP, Codex envia ese valor como Bearer Token.

La auditoria de tokens y consultas MCP se revisa desde:

```text
Integraciones > Auditoria MCP/API
```

En stdio, el usuario llama `iniciar_sesion` con credenciales Laravel. El servidor valida:

- email y password con la tabla `users`;
- codigo `two_factor_code` si el usuario tiene 2FA activo;
- que el usuario autenticado no tenga rol `Cliente`.

Si el usuario tiene rol `Cliente`, la sesion MCP se rechaza.

## Herramientas

HTTP remoto:

- `estado_mcp`: verifica token, usuario y endpoint.
- `buscar_cliente`: busca clientes por nombre, email, pasaporte, telefono o ID.
- `ver_cliente`: lee informacion basica de un cliente.
- `ver_cos_cliente`: lee `users.arraycos` si `users.arraycos_expire` sigue vigente. Si no hay cache o pasaron 5 dias, recalcula mediante `ClientCosSnapshotService`.
- `refrescar_cos_cliente`: fuerza el flujo Laravel de COS mediante `ClientCosSnapshotService`; puede actualizar `users.arraycos`, `users.arraycos_expire`, `users.cosready` y sincronizaciones necesarias.

Stdio / Artisan:

- `iniciar_sesion`: crea una sesion MCP dinamica en memoria del proceso.
- `estado_sesion`: muestra el estado de la sesion MCP actual.
- `cerrar_sesion`: cierra la sesion MCP actual.
- `buscar_cliente`: busca clientes por nombre, email, pasaporte, telefono o ID.
- `ver_cliente`: lee informacion basica de un cliente.
- `ver_cos_cliente`: lee `users.arraycos` si `users.arraycos_expire` sigue vigente. Si no hay cache o pasaron 5 dias, recalcula mediante `ClientCosSnapshotService`.
- `refrescar_cos_cliente`: fuerza el flujo Laravel de COS mediante `ClientCosSnapshotService`; puede actualizar `users.arraycos`, `users.arraycos_expire`, `users.cosready` y sincronizaciones necesarias.

El primer calculo tambien intenta vincular un contacto existente de HubSpot por email o pasaporte y trae sus negocios a la tabla local si el cliente no tenia negocios registrados. No crea contactos nuevos en HubSpot durante una consulta COS.

## Auditoria

Cada `tools/call` se registra antes y despues de ejecutarse. Si no se puede escribir auditoria, la consulta falla y no se ejecuta.

Cada evento incluye:

- usuario autenticado, roles y estado no-Cliente;
- herramienta, argumentos seguros y destino consultado;
- duracion, estado, error si falla y resumen del resultado;
- `prev_hash` y `hash` para detectar ediciones simples;
- HMAC-SHA256 si `SEFAR_MCP_AUDIT_SECRET` esta configurado.

No se registran passwords, codigos 2FA, tokens, cookies, CSRF, secretos ni headers de autorizacion; esos campos se escriben como `[REDACTED]`.

El endpoint MCP HTTP `/mcp` y las rutas REST `/api/mcp/v1` tambien usan auditoria MCP JSONL y rechazan usuarios con rol `Cliente`.

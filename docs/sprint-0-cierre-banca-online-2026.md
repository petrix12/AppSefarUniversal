# Cierre Sprint 0: Banca Online 2026

Generado: 2026-07-10

## Objetivo

Cerrar las definiciones necesarias para empezar desarrollo sin confundir el flujo base de registro con Banca Online.

## Flujo base actual

El proceso principal de registro se mantiene:

```text
/registerv2 -> /pay -> /getinfo -> /contrato -> /tree
```

Banca Online no reemplaza este recorrido. Si una activacion inicial nace desde Banca Online, debe respetar las fases posteriores actuales: pago, informacion complementaria y contrato.

## Rol de Banca Online

Banca Online es un modulo de cotizacion, seleccion de alcance y activacion.

Puede llamarse desde:

1. Fuera de la app: visitante o interesado que necesita orientacion/cotizacion.
2. Dentro de la app: candidato que continua su proceso.
3. Dentro de la app: representado que quiere cotizar o activar otros procesos/servicios.

Cuando un cliente arranca otros procesos, Banca Online debe permitir cotizar o seleccionar su cotizacion.

## Definiciones cerradas

| Perfil | Definicion | Regla tecnica inicial |
|---|---|---|
| Visitor | Persona que entra y solo ve | No hay usuario autenticado/identificado |
| Candidate | Interesado sin contrato firmado | `pay` pendiente, pago sin `/getinfo`, o `contrato != 1` |
| Represented | Cliente con relacion formal | pago completado + `contrato = 1` |

Notas:

1. `cosready = 1` no convierte a nadie en representado.
2. Pago sin contrato no es representado.
3. Representado puede tener expediente, arbol o COS pendientes, pero ya tiene contrato firmado.

## Arquitectura decidida

1. Las reglas editables se administran desde la app/base de datos.
2. No se deben crear nuevos archivos de configuracion para reglas de negocio.
3. El catalogo actual en `servicios` y `servicios.metadata` se reutiliza.
4. Las activaciones actuales en `compras` y `compras.metadata` se reutilizan.
5. Banca Online debe registrar contexto de entrada y cotizacion en metadata o en una tabla dedicada si negocio lo requiere.

## Ya implementado durante Sprint 0

1. `ClientStageResolver` para distinguir visitor, candidate y represented.
2. Integracion inicial del resolver en Banca Online.
3. Metadata inicial de etapa del cliente en compras de Banca Online.
4. Correccion de catalogo: si un paquete usa componentes desde BD, los componentes mandan sobre datos sembrados.
5. Pruebas unitarias para proteger la regla: pagado sin contrato no es representado.

## Pendientes de decision

1. Como identificar una cotizacion existente cuando el cliente entra desde dentro de la app.
2. Si la cotizacion debe vivir solo en `compras.metadata`, `servicios.metadata` o una tabla propia.
3. Si los contratos de servicios adicionales se firman antes o despues del pago.
4. Que permiso exacto controla el panel de reglas de Banca Online.
5. Textos finales de explicacion de rutas.
6. Matriz final de documentos por estrategia.

## Sprint 1 recomendado

P0:

1. Agregar contexto de entrada: `external`, `internal`, `admin_quote`, `cos`.
2. Guardar situacion actual seleccionada por el usuario.
3. Guardar contexto de cotizacion si entra desde dentro de la app.
4. Crear primera pregunta: situacion actual del expediente.
5. Respetar el flujo base cuando la activacion requiera `/getinfo` y `/contrato`.

P1:

1. Crear pantalla "Por que recomendamos esta estrategia".
2. Mostrar recomendacion principal.
3. Cambiar lenguaje visible de tienda a servicio profesional.
4. Evitar duplicidad de servicios/procesos ya activos.

P2:

1. Registrar eventos de conversion.
2. Integrar senales del COS para representados.
3. Mostrar contexto de expediente dentro de Banca Online interna.

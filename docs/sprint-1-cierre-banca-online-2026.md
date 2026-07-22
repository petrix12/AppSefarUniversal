# Cierre Sprint 1: Banca Online guiada

Generado: 2026-07-10

## Objetivo

Convertir Banca Online 2026 de un catalogo de alcances en un flujo guiado que pregunte la situacion del expediente, recomiende una estrategia, explique la recomendacion y formalice la activacion sin romper el flujo base:

```text
/registerv2 -> /pay -> /getinfo -> /contrato -> /tree
```

## Entregado

1. Perfilado operativo con `ClientStageResolver`:
   - `visitor`
   - `candidate`
   - `represented`
   - representado requiere pago base completado + contrato firmado.
2. Pregunta inicial de situacion del expediente antes de seleccionar alcance.
3. Recomendacion de estrategia segun situacion seleccionada.
4. Pantalla intermedia "Por que recomendamos esta estrategia".
5. Contexto COS visible para usuarios internos/representados sin exponer informacion administrativa oculta.
6. Metadata de activacion:
   - `entry_point`
   - `selected_case_status`
   - `quote_context`
   - `recommendation`
   - `cos_context`
7. Banca Online separada del pago de registro:
   - el cliente puede activar Banca Online aunque no haya pagado registro inicial.
   - el siguiente paso despues de pagar Banca Online apunta al flujo base que corresponda.
8. Carrito tecnico persistente:
   - guarda seleccion, correo y pago pendiente en el navegador.
   - permite retomar el `payment_url`.
   - se limpia al completar la activacion.
9. Recuperacion backend de pago pendiente:
   - si existe una activacion pendiente del mismo alcance, se reutiliza el checkout en lugar de crear otra compra pendiente.
10. Bloqueo de duplicado pagado:
   - no permite pagar dos veces el mismo tipo de estrategia para el mismo pais con el mismo correo.
11. Eventos de medicion:
   - `bo_case_status_selected`
   - `bo_nationality_selected`
   - `bo_strategy_recommended`
   - `bo_strategy_rationale_viewed`
   - `bo_activation_requested`
   - `bo_activation_payment_started`
   - `bo_activation_payment_completed`
   - `bo_activation_existing_checkout_returned`
12. Dashboard principal actualizado:
   - activaciones
   - pagos completados
   - pendientes
   - ingresos Banca
   - llegadas a pago
   - conversion de pago
   - embudo Banca Online
   - ingresos por plan
   - situaciones consultadas
   - activaciones y eventos recientes

## Pendientes que pasan a Sprint 2

1. Usar COS como recomendador profundo, no solo como contexto.
2. Mostrar "Mi expediente" completo dentro de Banca Online para representados.
3. Mostrar acciones pendientes del cliente y acciones en curso de Sefar.
4. Relacionar documentos faltantes con servicios documentales.
5. Crear catalogo de documentos por estrategia.
6. Medir documentos faltantes frecuentes.

## Pendientes que pasan a Sprint 3

1. Matriz legal final de contratos por servicio profesional.
2. Flujo contrato -> datos -> pago -> activacion por cada servicio.
3. Vincular contrato firmado con la compra/servicio activado.
4. Asignacion operativa automatica al equipo correspondiente.

## Pendientes operativos antes de dar Sprint 1 como probado en test

1. Ejecutar migraciones en ambiente test, especialmente `banca_online_events`.
2. Probar flujo completo:
   - entrada publica
   - situacion del expediente
   - recomendacion
   - seleccion de alcance
   - recuperacion de progreso
   - pago Banca Online
   - pantalla de gracias
   - siguiente paso hacia registro/getinfo/contrato segun estado.
3. Validar Stripe con claves de test.
4. Validar que el dashboard `/reportes/dashboard` recibe eventos reales.

# Cronómetro privado de carga del COS

## Acceso y uso

En la ficha `/users/{id}/edit`, la cuenta configurada ve **Cronometrar carga**. Ese botón vuelve a abrir la ficha con `?cos_timing=1`; **Volver a medir** repite la apertura y **Copiar medición** copia el informe JSON del servidor y navegador. Cerrar el cronómetro quita la medición en la siguiente apertura.

El acceso está limitado a la cuenta `sistemasccs@sefarvzla.com`, autenticada como Administrador y sin rol Cliente. Los demás administradores, empleados, clientes e invitados no reciben el panel, el JSON ni las cabeceras de diagnóstico, aunque añadan el parámetro a mano. La identidad se configura mediante `COS_TIMING_VIEWER_EMAIL` y, opcionalmente, `COS_TIMING_VIEWER_USER_ID` para fijar también el ID. Un correo de configuración vacío deniega el acceso a todos. Si hay caché de configuración, regenerarla después de ajustar estas variables.

## Qué se mide

Las etapas se presentan en orden con inicio relativo, fin, duración, número de consultas SQL y tiempo SQL. Se separan autenticación y permisos, definiciones, snapshot/encolado, genealogía, servicio, cada lectura de compras/documentos/facturas/tareas/chat/historial, búsqueda y datos locales de Teamleader, agrupación, análisis y conciliación de pagos, catálogos, formulario Monday, Formulario 001, campos adicionales, Blade y resto de middleware/auditoría.

La medición de la solicitud comienza en el primer middleware de Laravel. No incluye el arranque anterior de PHP/Laravel, la espera en el servidor web/proxy ni el tránsito de red hasta la App. El TTFB del navegador permite observar también esa espera. El tiempo SQL está incluido dentro de cada etapa; no se suma otra vez al total.

Las llamadas instrumentadas a HubSpot y al BCE se muestran por intento y con su inicio relativo. La pausa local de `hubspotThrottle` se mide por separado de la llamada a la API. Las transferencias Guzzle/cURL muestran DNS, TCP, TLS, tiempo hasta el primer byte, espera de respuesta y descarga. La espera de respuesta incluye red y procesamiento remoto: no equivale al tiempo de CPU del proveedor. En conexiones reutilizadas algunos tiempos pueden ser cero; si el transporte no proporciona datos, se muestran como no disponibles. Los detalles conservan como máximo 100 intentos y 100 transferencias por solicitud.

El navegador añade TTFB, descarga de HTML, DOM listo, primer contenido visible, carga completa y los ocho recursos más lentos. Los recursos pueden descargarse en paralelo. La medición de carga completa no incluye llamadas posteriores de polling ni el trabajo de la cola. Cuando existe una actualización del COS pendiente, el panel lo indica. En el diagnóstico privado se conserva el informe en lugar de recargar automáticamente: el sondeo indica cuándo observó que la actualización terminó, y «Volver a medir» abre el COS actualizado con otra referencia. Ese tiempo observado no es la duración exacta del worker y tiene la resolución del sondeo de cinco segundos. La recarga automática normal se mantiene para las demás aperturas.

El diagnóstico no registra SQL, bindings, URLs de API, cabeceras, credenciales ni cuerpos de respuesta. El resumen técnico se correlaciona por `X-COS-Timing-ID`, cabecera `Server-Timing` y el evento `COS carga cronometrada` en el log de Laravel. El JSON copiado tampoco incluye parámetros ni fragmentos de las URLs de recursos.

## Hallazgo inicial en producción

Caso observado el 12 de noviembre de 2026: `/users/24029/edit`, cuenta administrativa, Chrome sin limitación artificial de red y con «Disable cache» ya activado en DevTools.

| Medida de una petición observada | Tiempo |
| --- | ---: |
| Espera de respuesta del servidor | 18,04 s |
| Descarga del HTML | 2,19 ms |
| Petición del documento completa | 18,05 s |
| DOMContentLoaded | 18,96 s |
| Evento de carga del navegador | 19,09 s |

Otras peticiones del mismo documento observadas en la sesión tardaron 18,47 s y 18,04 s. La página inició además una navegación desde su propio script de actualización del COS. Esto sitúa la espera principal antes de recibir el HTML; todavía no atribuye esos segundos a una API concreta. La captura de evidencia está en `outputs/cos-timing-check/production-network.png`, fuera de Git.

La revisión del código identificó una consulta síncrona de respuestas del Formulario 001 en HubSpot durante cada apertura interna, además de operaciones locales de cruce/conciliación y posibles consultas de tasas históricas del BCE con caché. El primer log antiguo `Edit ejecutado` ocurre antes del Formulario 001 y de Blade, por lo que no basta para atribuir toda la espera. El cronómetro permite comprobar la contribución de cada paso sin cambiar la lógica de datos, cachés, colas, pagos ni Amazon S3.

## Validación y despliegue

La instrumentación utiliza `hrtime` y no añade consultas remotas para medir. Las pruebas verifican etapas y consultas, excepciones, independencia entre solicitudes, transporte HTTP sin datos sensibles y exclusión de otros administradores. El ensayo de diseño local usa datos de demostración y no representa los tiempos de producción.

Los archivos de este cambio deben desplegarse junto con sus dependencias del checkout. No requiere una migración propia. El resto del trabajo de restricciones de clientes sí incorpora una migración; consultar `docs/bloqueos-clientes.md` al desplegar todo el checkout. Los adaptadores externos permanecen pendientes de la configuración y permisos nativos documentados allí; una marca no garantiza por sí sola un bloqueo nativo del CRM.

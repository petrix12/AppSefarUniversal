# Abonos confirmados, comprobantes y enlaces CRM

## Comportamiento

Los abonos confirmados del COS (`teamleader_phase_installment`) conservan su único registro `Compras` y su `Factura` existente. La conciliación genera una referencia estable `phase-payment-ID` y guarda el estado de cada plataforma dentro de `compras.metadata.crm_payment_sync`. No crea otra compra local al reflejar el pago en un CRM.

- **HubSpot:** registra un pago externo `commerce_payments` con importe, moneda, fecha, correo y estado `succeeded`. Lo asocia al contacto, al negocio existente y a una factura de seguimiento **no cobrable** (`hs_invoice_billable=false`), con una línea por el importe y el enlace al comprobante de la App. Estas facturas no heredan automáticamente estado, número e importes: después de verificar el pago se escriben explícitamente `hs_number=APP-comprobante-recibo`, `hs_amount_billed` y `hs_invoice_status=paid`. Se verifican de nuevo. Los campos calculados `hs_amount_paid` y `hs_balance_due` son de solo lectura; si HubSpot los devuelve deben coincidir, y si no los calcula no se inventan valores. Añade los abonos de fase al campo pagado correspondiente, preservando el texto previo.
- **Teamleader:** añade el mismo abono al campo pagado del proyecto y una nota en el contacto que identifica el comprobante existente como pagado, con enlace de la App y referencias del proyecto y del negocio HubSpot. `notes.create` no admite proyectos clásicos aunque `notes.list` sí pueda leer sus notas. No emite una nueva factura fiscal en Teamleader ni inventa datos fiscales.
- **App:** el administrador ve el número del comprobante, la factura HubSpot cuando existe y el estado de ambos CRM en «Pagos realizados». La vista del solicitante mantiene su comprobante sin mostrar los estados ni los identificadores CRM internos.

La factura HubSpot sirve para reflejar ese abono ya cobrado y enlazar su comprobante original. No sustituye el proceso fiscal de facturación ni genera otro documento fiscal, cobro, email o enlace de pago. Si una misma factura App contiene varios conceptos, cada abono de fase refleja su propia asignación pagada; no se atribuye al abono el importe de otros conceptos de la factura.

### Pago de registro `/pay`

La confirmación de Stripe (Charges o PaymentIntent con estado `succeeded`), PayPal y cupón completo anota las compras existentes como `registration_payment` y las encola sin crear otra compra ni otro recibo local. Reutiliza `Factura` por la referencia de pasarela. El registro se vincula al contacto HubSpot y, si hay un único negocio inequívoco, también a ese negocio. No necesita un proyecto ni crea un negocio para forzar la coincidencia. Teamleader recibe una nota en el contacto enlazado; los campos de abonos de proyectos no se modifican por un registro.

Un cupón del 100 % conserva el precio original y su descuento, deja el importe efectivamente pagado a cero y genera una factura de importe cero; no crea un pago monetario externo inexistente. Un reintento conserva la primera fecha de confirmación. La falta de un contacto CRM no invalida el pago local: su sincronización queda pendiente para resolver el enlace. Registrar el documento de un pago no importa el perfil de un lead ni cambia la regla de clasificación por negocios.

## Protección contra duplicados y cruces

1. El registro del abono bloquea la fila del saldo y guarda recibo, reducción del saldo y estado pendiente en una transacción. Una confirmación repetida con el mismo comprobante o referencia de pasarela devuelve el recibo original, incluso si el saldo ya está pagado. No vuelve a reducirlo. Una misma referencia con otro importe se rechaza.
2. Stripe y PayPal reutilizan el comprobante local si la transacción confirmada ya tiene uno para ese cliente y método. Esto no cambia la lógica de cobro de las pasarelas ni realiza cobros adicionales.
3. Se exige un enlace local único entre cliente, proyecto Teamleader y negocio HubSpot. También se verifica el propietario del comprobante, la identidad del contacto externo y la asociación real del negocio/proyecto con ese cliente. No se enlaza por coincidencias aproximadas de nombre o importe.
4. Cada proveedor tiene un arrendamiento de procesamiento en la base de datos, checkpoints y referencias externas. Un fallo en uno no revierte ni repite el trabajo confirmado del otro.
5. Antes de crear un objeto remoto se guarda la intención de creación. Si se pierde la respuesta, se busca por la referencia exacta antes de continuar. Si el resultado sigue siendo desconocido, **no se repite el POST de creación**. Tras ocho intentos pasa a revisión. Los rechazos definitivos HTTP 400/401/403/404/422/429 permiten volver a intentar la creación después de corregir su causa.
6. No se importan como otro abono histórico las entradas Teamleader marcadas `[COS:phase-payment-ID]`. El analizador elimina los marcadores antes de extraer importes para impedir que el ID se interprete como dinero.
7. No hay observers globales que vuelvan a emitir pagos al recibir un webhook. Solo los abonos confirmados por el portal entran en este flujo; los espejos históricos y los registros calculados de saldo quedan fuera.

## Despliegue y primer caso

No requiere migraciones, cambios de S3 ni activar el procesamiento general de los webhooks. Mantener los valores actuales de `CRM_EVENT_PROCESSING_ENABLED` y `CRM_LOCAL_READS_ENABLED`.

Después de desplegar el código en la carpeta Laravel del servidor:

```bash
cd /home/xzysctv/gitproyects/app/AppSefarUniversal
php artisan config:clear
php artisan cos:sync-payment-receipts --receipt=7786
```

El último comando es **solo lectura**, sin consultas a APIs ni escrituras. Debe mostrar el abono existente de 51,36 EUR, el comprobante App 2035, el cliente 290, el negocio HubSpot 6867596984 y el proyecto Teamleader e93270e9-4065-0f1d-bc62-63e3431a1a0a. No ejecutar `config:cache`.

Para aplicar la sincronización del mismo abono, una vez comprobado el plan:

```bash
php artisan cos:sync-payment-receipts --receipt=7786 --write
```

Debe devolver `synced` para ambos proveedores y mostrar los identificadores de factura/pago de HubSpot y nota de Teamleader. Repetir ese comando conserva los IDs y no crea otro pago local. Se puede limitar a `--provider=hubspot` o `--provider=teamleader`.

La integración privada de HubSpot utilizada por la App (`HUBSPOT_KEY`) necesita permisos de lectura y escritura de pagos, facturas y líneas, lectura de contactos y lectura/escritura de negocios y sus asociaciones. El conector de esta sesión informa que las escrituras de HubSpot requieren modificar permisos y no permite leer pagos. Estos permisos del conector no acreditan los permisos de la integración privada del servidor. Si esta devuelve HTTP 401/403, el pago local permanece intacto y el motivo queda registrado para corregir los permisos y reintentar.

En la prueba de producción del 17-11-2026, el usuario confirmó el plan con las referencias anteriores. Al ejecutar `--write`, HubSpot devolvió HTTP 403 y Teamleader una excepción genérica; **la sincronización todavía no quedó confirmada**. En los registros nativos de Api-Call-Sefar (aplicación 1512588), la lectura de contacto y asociaciones tuvo HTTP 200 y la búsqueda de facturas devolvió `MISSING_SCOPES`, solicitando acceso de lectura a facturas. El usuario guardó los permisos y se verificaron de nuevo, recargando la configuración desde HubSpot: `crm.objects.invoices.read`, `crm.objects.invoices.write`, `crm.objects.commercepayments.read` y `crm.objects.commercepayments.write` ya están presentes. No se cambió `HUBSPOT_KEY`. Falta el siguiente intento del abono para confirmar los registros externos.

El siguiente intento creó la factura HubSpot **607389666816** y el pago **607390250785**, pero la factura no cobrable quedó sin campos de presentación calculados. Se corrigió el flujo para completar **esa misma factura** y reutilizar el pago. La consulta de definiciones realizada por el usuario confirmó que número, importe facturado y estado son editables y que importe pagado y saldo son de solo lectura.

Teamleader devolvió HTTP 400 en `projects.update`. El escritor ahora envía únicamente el campo del abono con `custom_fields_update_strategy=partial`, manteniendo los atributos del proyecto y sin reenviar campos personalizados vacíos o de solo lectura. La [documentación oficial](https://github.com/teamleadercrm/api/blob/master/apiary.apib#updating-collections) establece esta excepción a la sustitución completa de colecciones. La siguiente comprobación debe confirmar el resultado en producción.

El siguiente intento confirmó HubSpot como `synced`, con número `APP-2035-7786`, estado `paid` y los mismos IDs de pago/factura. Teamleader detalló que faltaban `amount` y `currency` del presupuesto. La respuesta documentada `projects.info` contiene `budget.provided` (Money), mientras que `projects.update` recibe ese Money directamente en `budget`. El constructor se corrigió para esa forma, admite el formato plano anterior, conserva el importe cero y omite presupuestos incompletos en vez de enviar un objeto vacío. La revisión específica pasó 49 pruebas. El resultado Teamleader queda pendiente del siguiente intento en producción.

Tras corregir el presupuesto, la actualización del abono en el proyecto se completó y el fallo avanzó a `notes.create`: su enumeración de tipos excluye `project` clásico. El destino de creación se corrigió al contacto verificado, incluyendo la referencia del proyecto dentro de la nota. La referencia estable permite recuperar y reutilizar la nota ante reintentos.

El diagnóstico posterior conserva también la operación, clase de excepción, código HTTP, categoría, scopes requeridos y código cURL cuando existen. De los errores Teamleader se extraen como máximo tres títulos truncados y con valores sensibles ocultos, nunca el cuerpo completo. El flujo usa lecturas y actualizaciones estrictas para conservar el estado HTTP que antes quedaba oculto en una excepción genérica.

El scheduler Laravel existente ejecuta cada minuto `cos:sync-payment-receipts --pending --write --limit=2`. Solo procesa abonos ya encolados por el nuevo checkout, con espera progresiva entre reintentos. **No sincroniza masivamente pagos anteriores:** requieren seleccionar explícitamente cada recibo con `--receipt=ID`. No se necesita un nuevo worker de colas. `COS_PAYMENT_CRM_SYNC_ENABLED=false` detiene las escrituras; los nuevos abonos conservan su estado pendiente.

Los estados quedan en `compras.metadata.crm_payment_sync.providers` y el resumen operativo en `storage/logs/cos-payment-crm.log`. No se guardan cuerpos HTTP, credenciales ni datos de tarjeta en el diagnóstico de este flujo. Si un resultado de creación sigue siendo desconocido, revisar la referencia en el CRM y resolver el enlace; no borrar la intención ni forzar otro objeto por importe o nombre.

## Fuentes y validación

Se implementa la API actual de [pagos externos de HubSpot](https://developers.hubspot.com/docs/api-reference/legacy/crm/objects/commerce-payments/guide), que permite asociar pagos completados a facturas. La [API de facturas](https://developers.hubspot.com/docs/api-reference/legacy/crm/objects/invoices/guide) describe las facturas no cobrables para seguimiento externo. Las notas de proyecto utilizan los endpoints `notes.list` y `notes.create` de la [documentación oficial de Teamleader](https://github.com/teamleadercrm/api/blob/master/apiary.apib).

Las pruebas verifican confirmaciones parciales y finales repetidas, un único comprobante por transacción, factura/pago HubSpot con importe y saldo correctos, recuperación de respuestas perdidas en ambas plataformas, ausencia de otro POST ante resultado desconocido, permisos independientes, exclusión de cuentas cruzadas, arrendamientos, lectura del eco CRM sin reimportar el abono, ejecución de solo lectura y privacidad de la vista del solicitante.

La revisión visual utiliza el fragmento Blade real y los estilos existentes con datos de prueba (`outputs/cos-payment-crm/admin.png`). No representa una factura ya creada en producción.

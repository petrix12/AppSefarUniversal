# Abonos confirmados, comprobantes y enlaces CRM

## Comportamiento

Los abonos confirmados del COS (`teamleader_phase_installment`) conservan su único registro `Compras` y su `Factura` existente. La conciliación genera una referencia estable `phase-payment-ID` y guarda el estado de cada plataforma dentro de `compras.metadata.crm_payment_sync`. No crea otra compra local al reflejar el pago en un CRM.

- **HubSpot:** registra un pago externo `commerce_payments` con importe, moneda, fecha, correo y estado `succeeded`. Lo asocia al contacto, al negocio existente y a una factura de seguimiento **no cobrable** (`hs_invoice_billable=false`), con una línea por el importe del abono y el enlace al comprobante de la App. Solo confirma la sincronización si HubSpot devuelve la factura pagada, su importe pagado correcto y saldo cero. Añade el abono al campo pagado de su fase, preservando el texto previo.
- **Teamleader:** añade el mismo abono al campo pagado del proyecto y una nota que identifica el comprobante existente como pagado, con enlace de la App y referencia del negocio HubSpot. No emite una nueva factura fiscal en Teamleader ni inventa datos fiscales.
- **App:** el administrador ve el número del comprobante, la factura HubSpot cuando existe y el estado de ambos CRM en «Pagos realizados». La vista del solicitante mantiene su comprobante sin mostrar los estados ni los identificadores CRM internos.

La factura HubSpot sirve para reflejar ese abono ya cobrado y enlazar su comprobante original. No sustituye el proceso fiscal de facturación ni genera otro documento fiscal, cobro, email o enlace de pago. Si una misma factura App contiene varios conceptos, cada abono de fase refleja su propia asignación pagada; no se atribuye al abono el importe de otros conceptos de la factura.

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

La integración privada de HubSpot utilizada por la App (`HUBSPOT_KEY`) necesita permisos de lectura y escritura de pagos, facturas y líneas, lectura de contactos y lectura/escritura de negocios y sus asociaciones. El conector de esta sesión informa que las escrituras de HubSpot requieren modificar permisos y no permite leer pagos; por tanto **no se ha realizado ni verificado la escritura real en producción**. Estos permisos del conector no acreditan los permisos de la integración privada del servidor. Si esta devuelve HTTP 401/403, el pago local permanece intacto y el motivo queda registrado para corregir los permisos y reintentar.

El scheduler Laravel existente ejecuta cada minuto `cos:sync-payment-receipts --pending --write --limit=2`. Solo procesa abonos ya encolados por el nuevo checkout, con espera progresiva entre reintentos. **No sincroniza masivamente pagos anteriores:** requieren seleccionar explícitamente cada recibo con `--receipt=ID`. No se necesita un nuevo worker de colas. `COS_PAYMENT_CRM_SYNC_ENABLED=false` detiene las escrituras; los nuevos abonos conservan su estado pendiente.

Los estados quedan en `compras.metadata.crm_payment_sync.providers` y el resumen operativo en `storage/logs/cos-payment-crm.log`. No se guardan cuerpos HTTP, credenciales ni datos de tarjeta en el diagnóstico de este flujo. Si un resultado de creación sigue siendo desconocido, revisar la referencia en el CRM y resolver el enlace; no borrar la intención ni forzar otro objeto por importe o nombre.

## Fuentes y validación

Se implementa la API actual de [pagos externos de HubSpot](https://developers.hubspot.com/docs/api-reference/legacy/crm/objects/commerce-payments/guide), que permite asociar pagos completados a facturas. La [API de facturas](https://developers.hubspot.com/docs/api-reference/legacy/crm/objects/invoices/guide) describe las facturas no cobrables para seguimiento externo. Las notas de proyecto utilizan los endpoints `notes.list` y `notes.create` de la [documentación oficial de Teamleader](https://github.com/teamleadercrm/api/blob/master/apiary.apib).

Las pruebas verifican confirmaciones parciales y finales repetidas, un único comprobante por transacción, factura/pago HubSpot con importe y saldo correctos, recuperación de respuestas perdidas en ambas plataformas, ausencia de otro POST ante resultado desconocido, permisos independientes, exclusión de cuentas cruzadas, arrendamientos, lectura del eco CRM sin reimportar el abono, ejecución de solo lectura y privacidad de la vista del solicitante.

La revisión visual utiliza el fragmento Blade real y los estilos existentes con datos de prueba (`outputs/cos-payment-crm/admin.png`). No representa una factura ya creada en producción.

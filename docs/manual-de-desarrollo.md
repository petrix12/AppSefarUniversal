# Manual de desarrollo de App Sefar

Estas reglas se aplican al crear o modificar pantallas, componentes y textos de la App, tanto en el portal de solicitantes como en el panel interno.

## Diseño de la interfaz

Respeta [sefar.css](../public/css/sefar.css) y [darkmode.css](../public/css/darkmode.css) en cada cambio. Usa los componentes, las clases y las variables visuales existentes como referencia. Por ejemplo, `--sefar-surface`, `--sefar-text` y `--sefar-border` adaptan superficies, textos y bordes al tema activo.

Cuando una pantalla necesite estilos nuevos, intégralos en el tema de Sefar y limita sus reglas al componente correspondiente. Las adaptaciones del tema oscuro deben seguir el selector `body.dark-mode` de la App. Revisa la pantalla en ambos temas y comprueba los contrastes de textos, botones, formularios, tablas y modales que hayas cambiado.

## Terminología de la App

Usa **representado** o **representados** para las personas a las que atiende Sefar Universal en toda la App, incluidos correos y notificaciones. La instrucción de marca del 28 de noviembre de 2026 amplía ese término a todos los módulos. Usa **proceso** o **expediente** según el contexto y conserva los nombres de navegación acordados.

| Dónde se usa | Texto recomendado |
| --- | --- |
| Listado de personas | Representados |
| Encabezado de la ficha del COS | Datos del representado |
| Portal de la persona solicitante | Mis procesos |
| Estado del expediente | Estatus de mi expediente |
| Acción para comenzar | Iniciar proceso |
| Vista sin expedientes | No hay procesos registrados. |

Aplica estos términos en menús, títulos, botones, mensajes de error, correos, notificaciones y documentos generados por la App. Si una pantalla muestra un nombre técnico heredado, tradúcelo en la interfaz y conserva el identificador que necesita el código. Por ejemplo, el rol interno `Cliente` sigue identificando permisos mientras la interfaz muestra **Representado**. No renombres roles, columnas, rutas, carpetas de documentos ni contratos de integración para cambiar un texto visible; `RepresentedLanguage::label()` permite traducir etiquetas heredadas al mostrarlas.

## Correos y plantillas

Los correos automáticos usan `mail.layouts.base`, con colores y tipografía derivados de `public/css/sefar.css`. `ApplyAppEmailTemplate` aplica la base a los mensajes heredados y convierte el CSS a estilos inline conservando texto plano y adjuntos. Las reglas `@media` deben incluir un espacio después de esa palabra para que el conversor no aplique los estilos móviles u oscuros al diseño de escritorio.

El generador está en **Marketing → Plantillas**, `/admin/marketing/templates`. Permite revisar y editar el prompt de marca, generar un borrador con IA, modificar textos, enlaces y bloques, revisar escritorio y móvil, guardar y volver a editar. Generar un borrador no guarda ni envía un correo. **Guardar prompt** conserva las instrucciones para el equipo en `settings`; **Restaurar prompt inicial** carga el original en el editor y requiere guardarlo para conservar esa restauración. El formato JSON de respuesta se añade por separado para que el editor pueda abrir el resultado.

En **Integraciones → Correo de la app**, `/admin/integrations/app-mail`, el administrador puede probar SES sin cambiar el proveedor activo y luego seleccionarlo tras comprobar la recepción. Las campañas conservan su configuración. Se reutilizan `MARKETING_SES_*` si no se define una pareja de credenciales `APP_MAIL_SES_*`; no se usan las credenciales generales de S3. El historial registra destinatarios, asunto y estados, sin almacenar cuerpos ni adjuntos. Una aceptación del proveedor o entrega al servidor no confirma la carpeta de destino.

Para publicar estos cambios, ejecuta `php artisan migrate --force`, `php artisan optimize:clear` y `php artisan queue:restart` después de actualizar el código. Si el despliegue utiliza caché de configuración, vuelve a ejecutar `php artisan config:cache`. No cambies `MAIL_MAILER`: el selector conserva el proveedor habitual hasta la activación administrativa.

Para recibir entregas, rebotes y quejas, configura el conjunto de configuración SES indicado en `APP_MAIL_SES_CONFIGURATION_SET` (o su equivalente de marketing) para publicar esos eventos en SNS y suscribe `https://app.sefaruniversal.com/api/marketing/ses-events`. Autoriza el ARN en `APP_MAIL_SES_SNS_TOPIC_ARNS` o en la configuración compartida de marketing. `php artisan app-mail:confirm-sns --topic=ARN` verifica una confirmación pendiente sin enviarla; añadir `--write` la confirma. La firma SNS y el tema permitido se comprueban antes de procesar eventos.

Los rebotes permanentes y las quejas suspenden el envío a esa dirección mediante `email_suppressions`, sin bloquear el acceso a la App ni añadir campos a `users`. Los rebotes transitorios se registran sin crear ese bloqueo permanente. La tabla de usuarios muestra la marca y enlaza a su revisión administrativa.

Los recordatorios de pago de registro conservan la cadencia de quince días y se limitan a tres envíos aceptados en total, contando el historial. El pago o una respuesta registrada en tareas de atención detienen el seguimiento; también se puede registrar una respuesta manualmente desde Correo de la app buscando el correo exacto. No se leen respuestas del buzón automáticamente. Alcanzar los tres recordatorios solo detiene ese seguimiento: recuperación de contraseña, comprobantes y demás notificaciones siguen disponibles si la dirección es entregable.

## Antes de entregar un cambio

- Comprueba que la pantalla respeta el diseño de Sefar en modo claro y oscuro.
- Revisa el texto visible y confirma que la persona del expediente se llama **representado** en todos los módulos.

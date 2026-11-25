# Manual de desarrollo de App Sefar

Estas reglas se aplican al crear o modificar pantallas, componentes y textos de la App, tanto en el portal de solicitantes como en el panel interno.

## Diseño de la interfaz

Respeta [sefar.css](../public/css/sefar.css) y [darkmode.css](../public/css/darkmode.css) en cada cambio. Usa los componentes, las clases y las variables visuales existentes como referencia. Por ejemplo, `--sefar-surface`, `--sefar-text` y `--sefar-border` adaptan superficies, textos y bordes al tema activo.

Cuando una pantalla necesite estilos nuevos, intégralos en el tema de Sefar y limita sus reglas al componente correspondiente. Las adaptaciones del tema oscuro deben seguir el selector `body.dark-mode` de la App. Revisa la pantalla en ambos temas y comprueba los contrastes de textos, botones, formularios, tablas y modales que hayas cambiado.

## Terminología de la App

Usa siempre **solicitante** para la persona que solicita un servicio o tiene un expediente. Usa siempre **proceso** para el expediente y su avance.

| Dónde se usa | Texto recomendado |
| --- | --- |
| Listado de personas | Solicitantes |
| Encabezado de la ficha | Datos del solicitante |
| Portal de la persona solicitante | Mis procesos |
| Estado del expediente | Estado del proceso |
| Acción para comenzar | Iniciar proceso |
| Vista sin expedientes | No hay procesos registrados. |

Aplica estos términos en menús, títulos, botones, mensajes de error, correos, notificaciones y documentos generados por la App. Si una pantalla muestra un nombre técnico heredado, tradúcelo en la interfaz y conserva el identificador que necesita el código. Por ejemplo, el rol interno `Cliente` puede seguir identificando permisos mientras la interfaz muestra **Solicitante**.

## Antes de entregar un cambio

- Comprueba que la pantalla respeta el diseño de Sefar en modo claro y oscuro.
- Revisa el texto visible y confirma que la persona se llama **solicitante** y su expediente se llama **proceso**.

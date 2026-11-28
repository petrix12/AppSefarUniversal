# Modelos de OpenRouter

El configurador está en `/admin/integrations/ai-models`, disponible desde
**Integraciones → Modelos de IA** para usuarios con `integrations.manage`.
La pestaña **Modelos de IA** también aparece en las pantallas de Integraciones.

Cada proceso guarda su modelo, respaldos, tiempo máximo y límite de salida en
`ai_process_settings`. Esos valores tienen prioridad sobre los predeterminados
de `AiProcessSettingsService`; editar el código o `.env` no reemplaza filas
guardadas. Al guardar desde el configurador se invalida la caché del proceso.

Los nueve procesos son generación de correos, atención BrightBean, asociación HubSpot/Teamleader,
unificación de datos, resúmenes de actividad, clasificación COS, chat interno,
resúmenes de despliegue y asistentes por rol.

El proceso **Generador de correos** (`email_template_generation`) utiliza por defecto
45 segundos y 4000 tokens de salida. Su modelo y respaldos se pueden ajustar en
este configurador. El prompt de marca se revisa, edita y guarda directamente en
**Marketing → Plantillas**; los cambios sin guardar se aplican al siguiente borrador
y **Guardar prompt** los conserva para futuras visitas y para el equipo. Las plantillas
generadas siguen la base de correo de Sefar y se pueden guardar y reabrir para editar.

## Configuración aplicada el 10 de noviembre de 2026

- Principal: `google/gemini-3.5-flash-lite`, el Flash-Lite de texto más reciente
  disponible en el catálogo de OpenRouter al verificarlo.
- Respaldo: `google/gemini-3.1-flash-lite`.
- Tiempo máximo por solicitud: 30 segundos.
- Salida: 4000 tokens en unificación, 800 en COS/actividad y 1200 en los demás.

Referencias: [catálogo](https://openrouter.ai/api/v1/models),
[Gemini 3.5 Flash Lite](https://openrouter.ai/google/gemini-3.5-flash-lite).
La tarifa estándar publicada es US$0,30/M de entrada y US$2,50/M de salida;
el consumo de razonamiento cuenta como salida.

Las solicitudes del código usan `reasoning.effort=minimal` y priorizan proveedores
por latencia. Gemini 3.5 Flash-Lite requiere razonamiento; no se desactiva. El
límite de tokens incluye razonamiento y texto visible: un límite demasiado bajo
puede producir una respuesta vacía. OpenRouter recibe los modelos de respaldo
para intentar otra opción ante errores o indisponibilidad del principal.

## Asistentes por rol

En `/admin/role-ai-assistants`, un modelo explícito tiene prioridad sobre el
proceso `role_assistants`. Dejarlo vacío hace que herede ese proceso; los nuevos
asistentes se crean con herencia. El listado muestra el modelo efectivo y el
formulario enlaza al configurador general. Los 18 asistentes existentes fueron
configurados explícitamente con Gemini 3.5 Flash-Lite en producción.

El cambio de modelos en producción no necesita despliegue. Las mejoras del menú,
catálogo, parámetros de razonamiento y respaldo de asistentes sí requieren
publicar el código actualizado.

# Presentación de la ficha del COS

La actualización organiza el encabezado y las pestañas de `resources/views/crud/users/edit.blade.php`. El contenido de `#mystatus`, la línea de tiempo, sus parciales y los cálculos y procesos del COS se conservan.

- Encabezado: nombre, contacto y servicio del solicitante; accesos internos a clientes y contactos vinculados.
- Navegación: estatus y cuatro áreas —Perfil, Expediente, Finanzas y Gestión interna— según las opciones que ya podía ver cada cuenta. Se muestran las pestañas del área elegida. Los IDs, destinos y condiciones originales se mantienen.
- Perfil: tarjetas de identidad, contacto y registro, con los campos adicionales organizados por secciones y búsqueda local por etiqueta. La búsqueda conserva los campos enviados al guardar. Las cuentas de consulta no muestran controles de guardado y sus campos están deshabilitados.
- Contraseñas: controles con identificadores únicos y lectura limitada al formulario correspondiente; rutas y nombres enviados sin cambios.
- Documentos, familiares, pagos, negocios y solicitudes: encabezados y tablas con desplazamiento propio en pantallas pequeñas. Los importes, acciones y reglas de negocio no se cambian.

Los recursos nuevos están acotados a los componentes de la ficha: `cos-user-workspace.css`, `cos-user-workspace.js`, `cos-user-profile.css` y `cos-user-profile.js`. Se conserva Bootstrap y jQuery existentes. Si no está disponible la mejora de navegación, permanecen las pestañas originales como alternativa funcional.

La comprobación de conservación del bloque de estatus y sus scripts asociados se hizo comparando el contenido anterior y posterior byte a byte. Las pruebas de presentación verifican destinos, visibilidad por tipo de cuenta, campos editables y consulta sin guardado. Las vistas previas utilizan datos ficticios y no representan el estatus de un cliente real.

Para desplegar solo esta presentación, subir las vistas y recursos y limpiar la configuración si se publica también el menú. No requiere migraciones ni variables propias. La administración de Stripe y Kalendia tiene requisitos de despliegue adicionales en [su guía](stripe-kalendia-administracion.md).

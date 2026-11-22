# Navegación administrativa

El menú de AdminLTE se organiza por áreas de trabajo. Cada cuenta ve únicamente las opciones que permiten sus permisos actuales. La navegación de clientes conserva sus destinos y condiciones originales.

| Área | Contenido principal |
| --- | --- |
| Clientes y COS | Clientes y expedientes, exportaciones, bloqueos y alertas. |
| Trabajo y ventas | Kanban, tareas, ventas pendientes, propuestas y referidos. |
| Agenda y consultas | Calendario de Kalendia, consultas y precios, calendarios anteriores. |
| Finanzas | Cartera y certificados, revisión de pagos, facturas, comprobantes y Stripe. |
| Servicios y difusión | Catálogo de servicios, cupones, Banca Online 2026, email marketing y noticias. |
| Genealogía y documentos | Expedientes, familiares, GEDCOM, Onidex, materiales y archivo documental. |
| Integraciones | Sincronización CRM, Teamleader, responsables de HubSpot, Monday, conexiones y API. |
| Configuración | Usuarios y permisos, superadmins, IA, configuración del COS, informes, catálogos, mantenimiento y diagnóstico. |

## Accesos que cambiaron de ubicación

- **Stripe:** Finanzas → Stripe → Verificar pagos / Historial de pagos.
- **Kalendia:** Agenda y consultas → Calendario de Kalendia / Consultas y precios.
- **Modelos de OpenRouter:** Configuración → Inteligencia artificial → Modelos de IA.
- **Roles, permisos y auditoría de accesos:** Configuración → Usuarios y permisos.
- **Herramientas de pruebas existentes:** Configuración → Pruebas y diagnóstico.

El buscador admite nombres de proveedores: `Stripe`, `Kalendia` y `OpenRouter`. Las etiquetas auxiliares de búsqueda se declaran mediante `data.search` en `config/adminlte.php` y se leen desde `public/js/admin-shell.js`.

Se unificaron los accesos repetidos al listado de clientes y a propuestas. Se retiraron las entradas de plantilla sin destino funcional; se conservaron los 101 destinos funcionales existentes. La revisión de pagos mantiene su apertura externa al COS.

## Permisos y mantenimiento

La configuración continúa en `config/adminlte.php`. Las claves estables `clients`, `work`, `agenda`, `finance`, `services`, `genealogy-documents`, `integrations` y `settings` identifican las ocho áreas.

Los gates `menu.clients` y `menu.payment-receipts` conservan las combinaciones de permisos que antes se repartían entre accesos duplicados o menús padre. Las autorizaciones de las rutas siguen vigentes. Las pruebas de navegación cubren esos casos, los destinos GET, los enlaces duplicados y la separación del portal de clientes.

Se conservaron el tema, los iconos, el acordeón y los componentes actuales de AdminLTE. Esta reorganización da ubicación a las herramientas existentes de Stripe y Kalendia; la ampliación de su administración desde la App requiere su propia implementación.

Los históricos de España se consultan desde **Finanzas → Stripe → España · Solo consulta** y **Finanzas → PayPal España · Histórico**. Exigen administrador interno y mantienen bloqueadas las escrituras, incluso para superadmins. Sus credenciales opcionales no reemplazan las de las cuentas activas; véase [la guía de integraciones](stripe-kalendia-administracion.md).

## Despliegue

Subir el código y los recursos actualizados, incluido `public/js/admin-shell.js`, y ejecutar en la carpeta Laravel:

```bash
php artisan config:clear
```

Este cambio del menú no requiere variables de entorno ni migraciones. Recargar el navegador para cargar el JavaScript actualizado.

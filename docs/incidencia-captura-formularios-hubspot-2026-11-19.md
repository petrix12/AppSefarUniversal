# COS: captura de formularios internos por HubSpot

## Evidencia de producción

La cuenta 7317 no podía actualizar su COS: `failed_jobs` mostraba una `CosIdentityConflictException` en `HubspotClientIdentityGuard.php:42`. Una cuenta adicional (19237) conservaba un ID incluido entre los alias fusionados del contacto canónico de 7317.

El usuario autorizó retirar únicamente ese vínculo de 19237 y sincronizar 7317. La edición básica confirmó el guardado del campo `hs_id` vacío. Inmediatamente después, HubSpot registró una conversión de «Unidentified Form», cambió el correo principal al de 19237 y actualizó el nombre del contacto. El refresco directo aportado por el usuario identificó ahora un conflicto de **correo** con 19237. No se desactivó la protección de identidad.

La plantilla global AdminLTE cargaba el rastreador de HubSpot en todos los formularios administrativos. La plantilla `layouts.app` lo incluía dos veces, y `layouts.guest` también lo cargaba. Esto explica que una operación local tuviera además una captura independiente en HubSpot. HubSpot documenta que las capturas de formularios externos asociadas a una misma cookie pueden atribuirse al mismo contacto aunque cambie el correo: [formularios ajenos a HubSpot](https://knowledge.hubspot.com/forms/use-non-hubspot-forms).

## Recuperación del cliente

El usuario restituyó el correo principal directamente en HubSpot; la lectura posterior confirmó la corrección. El worker pudo importar tres negocios y la ficha de 7317 comenzó a mostrar **Española Sefardi — Recurso de Alzada**, en lugar de «Muy pronto disponible».

No se anuncia que estén reconciliados todos los procesos: HubSpot devuelve cuatro negocios asociados y COS muestra tres importados. El negocio específico de Alzada requiere revisar su propiedad local histórica. La copia local de desarrollo lo vincula a otra cuenta, pero esa copia es antigua y no sustituye una comprobación de producción. No se reasignaron negocios, documentos ni proyectos automáticamente.

## Corrección preventiva preparada

Se retira el cargador de rastreo `js.hs-scripts.com` de las tres plantillas de la aplicación: AdminLTE, cuenta y acceso/recuperación. Los formularios de la App dejan de ser capturados automáticamente por ese cargador. La actualización explícita por API y los formularios HubSpot incrustados en sus pantallas específicas mantienen su implementación.

Esto retira las analíticas y demás funciones del rastreador en la App. No modifica el sitio comercial externo. No requiere migraciones ni dependencias; al desplegar se deben refrescar las vistas y recargar las páginas que todavía tengan el script anterior en memoria. La corrección local no debe anunciarse como desplegada hasta comprobar el HTML servido en producción.

## Validación

Las tres pruebas nuevas reprodujeron la presencia del rastreador antes del arreglo. Tras retirarlo, pasan 19 pruebas y 219 aserciones:

```sh
php vendor/phpunit/phpunit/phpunit \
  tests/Feature/HubspotFormCaptureIsolationTest.php \
  tests/Feature/ClientCosIdentityConflictTest.php \
  tests/Feature/PersonalDataHubspotUpdateTest.php
```

Se verifica el HTML de acceso, la plantilla administrativa que aloja formularios de terceros clientes y la plantilla de cuenta. Las pruebas existentes confirman que los conflictos siguen bloqueando las importaciones ambiguas y que la actualización explícita de HubSpot conserva su validación y sus transacciones. `git diff --check` pasa.

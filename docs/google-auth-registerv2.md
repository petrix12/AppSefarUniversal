# Inicio de sesión y registro con Google

## Revisión de Google Cloud del 27/11/2026

Se revisó el proyecto `sefar-universal-508015`, con la cuenta corporativa de Sefar, y el cliente web **Sefar App**. La marca aparece verificada, el público es **Usuarios externos** y la aplicación está **En producción**. El centro de verificación indica que no hace falta verificar acceso a datos sensibles o restringidos.

El retorno autorizado es `https://app.sefaruniversal.com/registerv2/google/callback`. La app en producción redirige a Google con ese mismo retorno y el mismo cliente, solicitando únicamente `openid profile email`, con comprobación de estado de sesión. Los orígenes registrados incluyen `https://app.sefaruniversal.com` y `https://sefaruniversal.com`. El dominio autorizado es `sefaruniversal.com`.

No se cambiaron las credenciales, los permisos ni la marca en Google Cloud. Las credenciales ya están presentes en producción, aunque no aparecen en los archivos de entorno locales disponibles. No es necesario copiar el secreto al chat. La revisión de configuración no sustituye una prueba completa de acceso y regreso desde Google.

Quedan dos detalles de consola para revisar: en **Acceso a los datos** las listas de permisos están vacías, pese a que la app solicita los tres permisos básicos; y el campo **Condiciones del servicio** apunta a `/asistencia-legal/`, una página de asistencia, en lugar de un documento de condiciones del servicio. Antes de cambiar los enlaces de una marca verificada, definir el documento correcto; un cambio de marca puede necesitar nueva verificación/publicación. [Documentación oficial de marca](https://developers.google.com/identity/protocols/oauth2/production-readiness/brand-verification).

## Comportamiento de la app

`/login` ofrece **Entrar con Google** y `/registerv2` ofrece **Continuar con Google** cuando están configuradas las tres variables. Ambos usan el mismo retorno ya autorizado; no hay que crear otro cliente OAuth para el login.

Las cuentas existentes se identifican por el identificador estable `sub` de Google, guardado en `users.google_id`, no por el nombre. Para la primera vinculación por correo se exige un perfil verificado. Gmail y Google Workspace pueden acreditar directamente el correo; otras cuentas de Google que usan un correo de terceros deben entrar una vez con la contraseña de la App para vincularlo. La vinculación se completa después del segundo factor si está activo. Una identidad de Google diferente no reemplaza otra ya vinculada. [Identidad estable y vinculación](https://developers.google.com/identity/openid-connect/openid-connect), [correos de terceros](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token).

Si la cuenta es nueva, el retorno de Google conserva el servicio y presenta el formulario con nombre y correo. No crea un usuario hasta completar los datos del registro. El correo verificado no se puede sustituir al enviar el formulario; **Usar otro correo** descarta la verificación y vuelve al registro habitual. Si no había un servicio seleccionado, se ofrece elegir uno. La verificación del formulario dura 30 minutos y sobrevive a recargar la página o a un error de validación.

Al completar el formulario se crean juntos el cliente, su vinculación con Google y la compra pendiente con el precio vigente, incluidas promociones. El navegador no puede asignar un rol interno ni declarar el registro pagado. No se envía una contraseña generada a quien usa Google. El cliente entra a la App y continúa su registro, pago o expediente según su estado habitual. Los usuarios existentes conservan sus roles y pagos. El acceso de quienes tienen segundo factor sigue pasando por el desafío de la App.

Se mantiene la validación de `state` de Socialite y no se usa `stateless()`. No se guardan tokens de Google en la cuenta o en la sesión del formulario. Los códigos y el estado OAuth se excluyen de la auditoría de URL. [Socialite](https://laravel.com/docs/12.x/socialite).

## Instalación y verificación

Conservar los valores existentes del servidor:

```dotenv
GOOGLE_CLIENT_ID=ID_DEL_CLIENTE_SEFAR_APP
GOOGLE_CLIENT_SECRET=SECRETO_EXISTENTE_DEL_CLIENTE
GOOGLE_REDIRECT_URI=https://app.sefaruniversal.com/registerv2/google/callback
```

Aplicar la migración nueva y limpiar la configuración y las vistas al publicar el código:

```sh
php artisan migrate --force --path=database/migrations/2026_11_27_170000_add_google_id_to_users_table.php
php artisan config:clear
php artisan view:clear
php artisan google:check
```

`google:check` muestra presencia de credenciales, coincidencia del retorno y estado de la migración, sin revelar el secreto ni autenticar usuarios. No ejecutar `config:cache`. El entorno local puede usar credenciales dedicadas para pruebas; no se necesitan credenciales reales para las pruebas automatizadas.

```sh
php artisan test --filter=GoogleAuthenticationTest
```

Las pruebas usan el proveedor real de Socialite con transporte HTTP simulado: verifican estado inválido, perfiles sin verificación, cuentas nuevas y existentes, conservación del precio, errores de formulario, vinculación de correos externos, segundo factor y exclusión de códigos en auditoría. Después de publicar se debe completar una prueba real de acceso con una cuenta existente y otra de registro, sin confundir la lectura de configuración con una autenticación exitosa.

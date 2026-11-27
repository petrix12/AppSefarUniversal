# Inicio de sesión y registro con Google

## Revisión de Google Cloud del 27/11/2026

Se revisó el proyecto `sefar-universal-508015`, con la cuenta corporativa de Sefar, y el cliente web **Sefar App**. La marca aparece verificada, el público es **Usuarios externos** y la aplicación está **En producción**. El centro de verificación indica que no hace falta verificar acceso a datos sensibles o restringidos.

El retorno autorizado es `https://app.sefaruniversal.com/registerv2/google/callback`. La app en producción redirige a Google con ese mismo retorno y el mismo cliente, solicitando únicamente `openid profile email`, con comprobación de estado de sesión. Los orígenes registrados incluyen `https://app.sefaruniversal.com` y `https://sefaruniversal.com`. El dominio autorizado es `sefaruniversal.com`.

Las credenciales ya están presentes en producción, aunque no aparecen en los archivos de entorno locales disponibles. No se cambiaron ni generaron credenciales. No es necesario copiar el secreto al chat. La revisión de configuración no sustituye una prueba completa de acceso y regreso desde Google.

En **Acceso a los datos** se seleccionaron únicamente los permisos básicos `openid`, `userinfo.email` y `userinfo.profile`; falta confirmar su persistencia después de guardar. En **Información de la marca**, el campo **Condiciones del servicio** se guardó con `https://sefaruniversal.com/politica-de-privacidad/`, que contiene el aviso legal y las condiciones de uso publicadas, en lugar de `/asistencia-legal/`.

El enlace corregido quedó en una versión de marca pendiente de publicación. Google rechazó la nueva verificación porque no pudo acceder a la página principal configurada, `https://app.sefaruniversal.com/`. La marca previamente verificada sigue mostrándose a los usuarios. Hay que resolver ese acceso o configurar una página principal pública apropiada y completar la verificación/publicación; guardar el formulario no publica el enlace por sí solo. [Documentación oficial de marca](https://developers.google.com/identity/protocols/oauth2/production-readiness/brand-verification).

## Comportamiento de la app

`/login` ofrece **Entrar con Google** y `/registerv2` ofrece **Continuar con Google** cuando están configuradas las tres variables. Ambos usan el mismo retorno ya autorizado; no hay que crear otro cliente OAuth para el login.

Las cuentas existentes se identifican por el identificador estable `sub` de Google, guardado en `user_google_identities.google_id`, no por el nombre. La tabla tiene un vínculo único por usuario y por identidad de Google, con comparación de identidad sensible a mayúsculas. Para la primera vinculación por correo se exige un perfil verificado. Gmail y Google Workspace pueden acreditar directamente el correo; otras cuentas de Google que usan un correo de terceros deben entrar una vez con la contraseña de la App para vincularlo. La vinculación se completa después del segundo factor si está activo. Una identidad de Google diferente no reemplaza otra ya vinculada. [Identidad estable y vinculación](https://developers.google.com/identity/openid-connect/openid-connect), [correos de terceros](https://developers.google.com/identity/gsi/web/guides/verify-google-id-token).

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
php artisan migrate --force
php artisan config:clear
php artisan view:clear
php artisan google:check
```

`google:check` muestra presencia de credenciales, coincidencia del retorno y estado de la migración, sin revelar el secreto ni autenticar usuarios. No ejecutar `config:cache`. El entorno local puede usar credenciales dedicadas para pruebas; no se necesitan credenciales reales para las pruebas automatizadas.

### Recuperación del error 1118 al migrar en producción

La primera versión de `2026_11_27_170000_add_google_id_to_users_table` añadía una columna a `users`. En producción falló con `Row size too large`, porque la fila de usuarios ya alcanzaba el límite de InnoDB. La corrección conserva el nombre de esa migración para poder reintentarla, pero crea `user_google_identities` sin alterar `users`.

Publicar juntos el código corregido y las dos migraciones de Google antes de volver a ejecutar los comandos anteriores. Laravel reintentará la migración fallida y ejecutará `2026_11_27_180000_ensure_user_google_identities_table`. La migración de precios que ya aparece como `DONE` permanece aplicada; no hace falta hacer rollback.

Si la versión anterior ya se había aplicado en otro entorno, la migración nueva crea la tabla y copia los vínculos existentes desde `users.google_id`. Conserva la columna anterior para recuperación, sin usarla en el acceso de la app. La copia puede reintentarse y rechaza conflictos en lugar de sobrescribir identidades.

```sh
php artisan test --filter="GoogleAuthenticationTest|GoogleAccountStorageTest"
```

Las pruebas usan el proveedor real de Socialite con transporte HTTP simulado: verifican estado inválido, perfiles sin verificación, cuentas nuevas y existentes, conservación del precio, errores de formulario, vinculación de correos externos, segundo factor y exclusión de códigos en auditoría. Después de publicar se debe completar una prueba real de acceso con una cuenta existente y otra de registro, sin confundir la lectura de configuración con una autenticación exitosa.

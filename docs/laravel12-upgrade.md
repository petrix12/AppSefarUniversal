# Actualización de seguridad de Laravel (noviembre de 2026)

La App pasa de Laravel 11.38.2 a 12.71.0. El mínimo permitido es 12.69,
que incluye los parches de los avisos de TokenGuard, validación de correo,
URLs temporales y la página de depuración. También se actualizan las demás
dependencias con avisos detectados en el `composer.lock` anterior.

Referencias: [TokenGuard](https://github.com/advisories/GHSA-f9x3-7vq9-cph6),
[página de depuración](https://github.com/advisories/GHSA-jh5r-qr3c-85q8),
[URLs temporales](https://github.com/advisories/GHSA-crmm-hgp2-wgrp),
[correo](https://github.com/laravel/framework/security/advisories/GHSA-5vg9-5847-vvmq)
y [guía oficial de actualización](https://laravel.com/docs/12.x/upgrade).

## Compatibilidad

- Se mantiene PHP 8.2. Composer resuelve para PHP 8.2.0, aunque el equipo de
  desarrollo utilice una versión superior.
- Carbon pasa a la versión 3. Los días de seguimiento, vencimiento de tareas
  y plazo de facturas siguen siendo enteros positivos. La importación de
  timestamps mantiene la zona horaria de la App.
- PHPUnit pasa a la versión 11 y su configuración XML se actualiza.
- El conector de BrightBean y OpenRouter conserva su configuración.

## Despliegue en OVH

Publicar `composer.json`, `composer.lock` y los demás cambios en Git antes
de ejecutar estos comandos desde la carpeta del proyecto:

```sh
cd ~/gitproyects/app/AppSefarUniversal
php artisan down
git pull --ff-only
php composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear
php artisan migrate --force
php composer.phar audit --no-dev
php artisan up
```

Ejecutar cada paso solamente si el anterior termina correctamente. El
endpoint de despliegue actual hace `git pull` y ejecuta Artisan, pero no
instala dependencias: esta actualización requiere el paso de Composer en
la terminal. `install` utiliza exactamente las versiones probadas del lock.

Comprobar que `php artisan --version` devuelve Laravel Framework 12.71.0 y
que la auditoría no devuelve avisos. Mantener `APP_DEBUG=false` en producción.
Si existen workers de cola persistentes, reiniciarlos con
`php artisan queue:restart` para que carguen las nuevas dependencias.

## Validación local

```sh
composer validate --no-check-publish
composer audit --locked
composer check-platform-reqs
php vendor/bin/phpunit tests/Unit/TokenGuardSecurityTest.php tests/Feature/FrameworkUpgradeCompatibilityTest.php tests/Feature/BrightbeanIntegrationTest.php tests/Feature/PlatformIntegrationTokensTest.php tests/Feature/RouteAuthenticationCoverageTest.php tests/Feature/WebRouteSecurityTest.php tests/Feature/ClientRouteAccessTest.php
```

Las pruebas de TokenGuard comprueban que credenciales numéricas, booleanas
o arrays, tanto en query como en JSON, no lleguen a la consulta de usuarios.
También comprueban que los tokens válidos conserven la autenticación.

Resultado local: cero avisos en `composer audit`, requisitos de plataforma
correctos y 232 pruebas seleccionadas aprobadas (1896 aserciones). La ejecución
se hizo con PHP 8.3; la resolución de dependencias se limitó a PHP 8.2.0.

La suite completa pasa de 606 a 629 casos y conserva los mismos 142 errores
y 16 fallos previos, sin nuevos casos fallidos. La mayoría de los errores
requiere la conexión Onidex que no está disponible en estas pruebas; también
hay pruebas antiguas con esquemas incompletos o expectativas desactualizadas.
PHPUnit 11 señala además una prueba antigua de Teamleader por dejar
manejadores de errores y excepciones instalados.

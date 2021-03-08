# Proyecto AdminSefar
##### https://laravel.com/docs/8.x
##### Versión: **Laravel Framework 8.31.0**
#

# Paso a paso del desarrollo del proyecto
1. Ejecutar: $ **laravel new sefar --jet**
	##### **Nota**: Seleccionamos livewire y en	**Will your application use teams? (yes/no) [no]:**
	##### Responder **no**
1. Ejecutar: $ **npm install**
1. Ejecutar: $ **npm run dev**

	### Commit 1:
	+ Ejecutar: $ **git init**
	+ Ejecutar: $ **git add .**
	+ Ejecutar: $ **git commit -m "Proyecto en blanco"**
	# ---



	## Crear un dominio local
1. Agregar el siguiente código al final del archivo **C:\Windows\System32\drivers\etc\hosts**
	>
		# Host virtual para el proyecto Sistema de Historia Clínica en Laravel (Lado del cliente) 
		127.0.0.1	sefar.test
	##### **Nota**: Editar con el block de notas en modo de administrador.

1. Agregar el siguiente código al final del archivo **C:\xampp\apache\conf\extra\httpd-vhosts.conf**
	>
		# Host virtual para el proyecto Sistema Sefar (Lado del servidor)
		<VirtualHost *:80>
			DocumentRoot "C:\xampp\htdocs\sefar\public"
			ServerName sefar.test
		</VirtualHost>
	##### **Nota**: En el archivo **C:\xampp\apache\conf\httpd.conf** las líneas:
	>
		Include conf/extra/httpd-vhosts.conf
	y
	>
		LoadModule rewrite_module modules/mod_rewrite.so		
	no deben estar comentada con #.

1. Reiniciar el servidor Apache.
	# ---



	## Ajustes iniciales
1. Crear: bd **sefar** en **MySQL**.
	##### Juego de caracters: **utf8_general_ci**
1. Configurar: **.env** con bd **sefar**
	>
		***
		DB_CONNECTION=mysql
		DB_HOST=127.0.0.1
		DB_PORT=3306
		DB_DATABASE=sefar
		DB_USERNAME=root
		DB_PASSWORD=
		***

1. Agregar el campo **passport** a la migración de tabla **users**: 
	>
		***
		public function up()
		{
			Schema::create('users', function (Blueprint $table) {
				$table->id();
				$table->string('name');
				$table->string('email')->unique();
				$table->string('passport')->nullable()->unique();
				$table->timestamp('email_verified_at')->nullable();
				$table->string('password');
				$table->rememberToken();
				$table->foreignId('current_team_id')->nullable();
				$table->text('profile_photo_path')->nullable();
				$table->timestamps();
			});
		}
		***

1. Ejecutar: $ **php artisan migrate**
1. Configurar Jetstream en: **config\jetstream.php**
	>
		***
		'features' => [
			// Features::termsAndPrivacyPolicy(),
			Features::profilePhotos(),
			// Features::api(),
			// Features::teams(['invitations' => true]),
			Features::accountDeletion(),
		],
		***
	**Nota**: Para personalizar aún más Jetstream:
	+ Ejecutar: $ **php artisan vendor:publish**
		- Seleccionar: **Tag: jetstream-views**
	+ Para que se agreguen componentes que no estaban:
		- Ejecutar: $ npm install
		- Ejecutar: $ npm run dev
	
	### Commit 2:
	+ Ejecutar: $ **git add .**
	+ Ejecutar: $ **git commit -m "Ajustes iniciales"**
	# ---



	## Laravel-permission
	##### Documentación: https://spatie.be/docs/laravel-permission/v4/introduction

1. Ejecutar: $ **composer require spatie/laravel-permission**
1. Ejecutar: $ **php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"**
1. Ejecutar: $ **php artisan migrate**
1. Añadir a la cabecera del modelo **User**:
	>
		use Spatie\Permission\Traits\HasRoles;
1. Añadir dentro de la clase del modelo **User**:
	>
		use HasRoles;

	### Commit 3:
	+ Ejecutar: $ **git add .**
	+ Ejecutar: $ **git commit -m "Laravel-permission"**
	# ---



	## Plantilla AdminLTE
	##### Documentación: https://github.com/jeroennoten/Laravel-AdminLTE
	##### Plantilla: https://adminlte.io/themes/v3/index.html

1. Integrar AdminLTE: $ **composer require jeroennoten/laravel-adminlte**
1. Ejecutar: $ **php artisan adminlte:install**
1. Crear: **resources\views\layouts\demoAdminLTE.blade.php**
	>
		@extends('adminlte::page')

		@section('title', 'Demo')

		@section('content_header')
			<h1>Demo</h1>
		@stop

		@section('content')
			<p>Demo.</p>
		@stop

		@section('css')
			<link rel="stylesheet" href="/css/admin_custom.css">
		@stop

		@section('js')
		
		@stop

	##### **Nota**: Se recomienda insertar ruta en **routes\web.php** para probar vistas:
	>
		// Para probar vistas
		Route::get('/pruebas', function () {
			return view('layouts.demoAdminLTE');
		});

	### Commit 4:
	+ Ejecutar: $ **git add .**
	+ Ejecutar: $ **git commit -m "Instalación Plantilla AdminLTE"**
	# ---



	## Adaptación del proyecto al español
	##### https://github.com/laravel-lang/lang
	##### https://github.com/jeroennoten/Laravel-AdminLTE/wiki/Translations

1. Pasar AdminLTE a español: $ **php artisan adminlte:install --only=translations**
1. Pasar Laravel a español: $ **composer require laravel-lang/lang:~7.0**
1. Copiar directorio: **vendor\laravel-lang\lang\src\es** y pegarlo en: **resources\lang**
	##### **Nota**: También esta la opción:
	+ $ composer require laraveles/spanish
    + $ php artisan laraveles:install-lang

1. Realizar todas las traducciones necesarios en **resources\lang\es.json**
1. Configurar a español **config\app.php**
	>
		***
		'locale' => 'es',
		***

	### Commit 5:
	+ Ejecutar: $ **git add .**
	+ Ejecutar: $ **git commit -m "Adaptación al español"**
	# ---


	## Seeders para prueba de roles y permisos
1. Crear seeder para roles: $ **php artisan make:seeder RoleSeeder**
1. Añadir a cabecera de **database\seeders\RoleSeeder.php**
	>
		use Spatie\Permission\Models\Role;
		use Spatie\Permission\Models\Permission;
1. Modificar el método **run** de **database\seeders\RoleSeeder.php**
	>
		***
		***
1. Crear seeder para usuarios: $ **php artisan make:seeder UserSeeder**
1. Añadir a cabecera de **database\seeders\UserSeeder.php**
	>
		use App\Models\User;

1. Modificar el método **run** de **database\seeders\UserSeeder.php**
	>
		***
		***
1. Modificar el método run **database\seeders\DatabaseSeeder.php**
	>
		***
		***
1. Ejecutar: $ **php artisan migrate:fresh --seed**
	##### Nota: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: $ **php artisan db:seed**

Commit 6:
***. Ejecutar: $ git add .
***. Ejecutar: $ git commit -m "Seeder Roles, Permisos y Usuarios"






	## Personalizar el proyecto
1. Adaptar la configuración del archivo config\adminlte.php al proyecto.
	Nota: Los icons se pueden buscar en https://fontawesome.com/icons
	También se recomienda visitar: https://www.youtube.com/playlist?list=PLZ2ovOgdI-kWTCkbH749Ukvq7FMz5ahpP



1. Modificar la ruta de inicio en **routes\web.php**
	Route::get('/', function () {
		return view('auth.login');
	});
***. Adaptar todos los archivos resources\views\auth
	a las características del proyecto
***. Crear: resources\views\inicio.blade.php
	***
	***
***. Modificar: app\Providers\RouteServiceProvider.php
	Cambiar:
		public const HOME = '/dashboard';
	por:
		public const HOME = '/inicio';
***. Crear archivo de estilos propios del proyecto: public\css\sefar.css
	***
	***



CRUD Permisos con Liveware
==========================
***. Crear grupo de rutas en routes\web.php
	// Grupo de rutas CRUD
	Route::group(['middleware' => ['auth'], 'as' => 'crud.'], function(){
	});
***. Ejecutar: $ php artisan make:model Permission
***. Modificar: app\Models\Permission.php
	***
	***
***. Ejecutar: $ php artisan make:controller PermissionController -rr
***. Modificar: app\Http\Controllers\PermissionController.php
	***
	***
***. Agregar ruta de permisos al grupo de rutas CRUD:
	Route::resource('permissions', PermissionController::class)->names('permissions')
		->middleware('can:crud.permissions.index');
***. Ejecutar: $ php artisan make:livewire permissions-table
***. Modificar: resources\views\livewire\permissions-table.blade.php
	***
	***
***. Crear los archivos para el CRUD Permisos:
	- resources\views\crud\permissions\index.blade.php
	- resources\views\crud\permissions\edit.blade.php
	- resources\views\crud\permissions\create.blade.php
***. Modificar: app\Http\Livewire\PermissionsTable.php
	***
	***

Commit 7:
***. Ejecutar: $ git add .
***. Ejecutar: $ git commit -m "CRUD Permisos"

CRUD Roles con Liveware
==========================
***. Ejecutar: $ php artisan make:model Role
***. Modificar: php artisan make:model Role
	***
	***



CRUD Usuarios con Liveware
==========================
***. Ejecutar: $ php artisan make:controller UserController -r
***. Modificar: app\Http\Controllers\UserController.php
	***
	***
***. Ejecutar: $ php artisan make:livewire users-table
***. Modificar: resources\views\livewire\users-table.blade.php
	***
	***
***. Crear los archivos para el CRUD Usuarios:
	- resources\views\crud\users\index.blade.php
	- resources\views\crud\users\edit.blade.php
	- resources\views\crud\users\create.blade.php
***. Modificar: app\Http\Livewire\UsersTable.php

Commit 8:
***. Ejecutar: $ git add .
***. Ejecutar: $ git commit -m "CRUD Usuarios"


Registro cliente
================
***. Crear vista Registro: resources\views\auth\registro.blade.php
	***
	***
***. Crear controlador Registro: $ php artisan make:controller RegistroController
***. Crear ruta en routes\web.php




# RUTAS
>
	Method      URI                               Name
	======		===								  ====
	GET|HEAD | / 
	GET|HEAD | api/user 
	GET|HEAD | dashboard                        | dashboard  
	GET|HEAD | forgot-password                  | password.request   
	POST     | forgot-password                  | password.email   
	GET|HEAD | livewire/livewire.js             |                           
	GET|HEAD | livewire/livewire.js.map         |    
	POST     | livewire/message/{name}          | livewire.message         
	GET|HEAD | livewire/preview-file/{filename} | livewire.preview-file    
	POST     | livewire/upload-file             | livewire.upload-file        
	POST     | login                            |                                 
	GET|HEAD | login                            | login                       
	POST     | logout                           | logout                      
	GET|HEAD | register                         | register                   
	POST     | register                         |                               
	POST     | reset-password                   | password.update             
	GET|HEAD | reset-password/{token}           | password.reset                 
	GET|HEAD | sanctum/csrf-cookie              |                                
	POST     | two-factor-challenge             |                           
	GET|HEAD | two-factor-challenge             | two-factor.login            
	GET|HEAD | user/confirm-password            | password.confirm            
	POST     | user/confirm-password            |                                 
	GET|HEAD | user/confirmed-password-status   | password.confirmation           
	PUT      | user/password                    | user-password.update            
	GET|HEAD | user/profile                     | profile.show                    
	PUT      | user/profile-information         | user-profile-information.update
	DELETE   | user/two-factor-authentication   |                                 
	POST     | user/two-factor-authentication   |                                 
	GET|HEAD | user/two-factor-qr-code          |                                
	POST     | user/two-factor-recovery-codes   |                                 
	GET|HEAD | user/two-factor-recovery-codes   |  
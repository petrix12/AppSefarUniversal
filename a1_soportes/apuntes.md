# Proyecto App Sefar Universal

+ https://laravel.com/docs/8.x
+ Versión: **Laravel Framework 8.31.0**

+ Servidor de producción: https://app.universalsefar.com
+ Servidor auxiliar: https://corporacioncabv.com/appsefar


## Consideraciones previas
1. Páginas principales:
	+ Laravel: https://laravel.com
	+ XAMPP: https://www.apachefriends.org/es/index.html
	+ Composer: https://getcomposer.org
	+ Git: https://git-scm.com
	+ GitHub: https://github.com
	+ Node Js: https://nodejs.org/es
	+ Tailwind CSS: https://tailwindcss.com
	+ Mailtrap: https://mailtrap.io
	+ Laravel-permission: https://spatie.be/docs/laravel-permission/v4/introduction
	+ Laravel-AdminLTE: https://github.com/jeroennoten/Laravel-AdminLTE
	+ Sweetalert: https://realrashid.github.io/sweet-alert
	+ Font Awesome: https://fontawesome.com
	+ Visual Studio Code: https://code.visualstudio.com
1. Descargar XAMPP e instalarlo.
	##### **Nota**: También se podría instalar un servidor local con Laragon. URL: https://laragon.org
1. Descargar **Composer** e instalarlo.
1. Descargar **Git** e instalarlo.
1. Descargar **Node Js** e instalarlo.
1. Crear una cuenta en GitHub.
1. Crear una cuenta en Mailtrap.
1. Iniciar servidor Apache.
1. Instalar el instalador de Laravel:
	>
		$ composer global require laravel/installer.				

## ___________________________________________________________________


## Crear proyecto App Sefar Universal
1. Crear nuevo proyecto Laravel Jetstream:
	>
		$ laravel new sefar --jet
	##### **Nota**: Seleccionamos livewire y en	**Will your application use teams? (yes/no) [no]:**
	##### Responder **no**
1. Ejecutar: 
	>
		$ npm install
1. Ejecutar: 
	>
		$ npm run dev

	### Commit 1:
	+ Crear repositorio: 
		>
			$ git init
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Proyecto en blanco"

## ___________________________________________________________________


## Crear un dominio local
1. Agregar el siguiente código al final del archivo **C:\Windows\System32\drivers\etc\hosts**
	>
		# Host virtual para el proyecto App Sefar en Laravel (Lado del cliente) 
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
	##### y
	>
		LoadModule rewrite_module modules/mod_rewrite.so		
	##### no deben estar comentada con #.
1. Reiniciar el servidor Apache.

## ___________________________________________________________________


## Ajustes iniciales
1. Crear: base de datos **sefar** en **MySQL**.
	##### **Usar**: Juego de caracters: **utf8_general_ci**
1. Configurar: **.env** con bd **sefar**
	>
		≡
		DB_CONNECTION=mysql
		DB_HOST=127.0.0.1
		DB_PORT=3306
		DB_DATABASE=sefar
		DB_USERNAME=root
		DB_PASSWORD=
		≡
1. Establecer juego de caracteres en base de datos en **config\database.php**
	>
		≡
		'connections' => [
			≡
			'mysql' => [
				≡
				'charset' => 'utf8',
				'collation' => 'utf8_general_ci',
				≡
1. Agregar los campos **user_id**, **passport**, **social_id**, **picture** y **created**  a la migración de tabla **users**: 
	>
		≡
		public function up()
		{
			Schema::create('users', function (Blueprint $table) {
				$table->id();
				$table->string('name');
				$table->string('email',175)->unique();

				$table->string('passport',175)->nullable()->unique();
				$table->integer('user_id')->nullable();
				$table->string('social_id')->nullable();
				$table->string('picture')->nullable();
				$table->dateTime('created')->nullable();
				$table->string('password_md5')->nullable();

				$table->timestamp('email_verified_at')->nullable();
				$table->string('password');
				$table->rememberToken();
				$table->foreignId('current_team_id')->nullable();
				$table->text('profile_photo_path')->nullable();
				$table->timestamps();
			});
		}
		≡
	##### **Nota**: Los campos: **user_id**, **social_id**, **picture** y **created** se incluyeron para mantener compatibilidad con la base de datos existente, se espera poder eliminar estos campos en versiones futuras.
	##### El campo **email** se redujo a **175** carácteres por problemas de compatibilidad al importar tabla a la base de datos del hosting
1. Ejecutar: 
	>
		$ php artisan migrate
1. Configurar Jetstream en: **config\jetstream.php**
	>
		≡
		'features' => [
			// Features::termsAndPrivacyPolicy(),
			Features::profilePhotos(),
			// Features::api(),
			// Features::teams(['invitations' => true]),
			Features::accountDeletion(),
		],
		≡
	**Nota**: Para personalizar aún más Jetstream:
	+ Ejecutar: 
		>
			$ php artisan vendor:publish
		- Seleccionar: **Tag: jetstream-views**
	+ Para que se agreguen componentes que no estaban:
		- Ejecutar: 
			>
				$ npm install
		- Ejecutar: 
			>
				$ npm run dev
	
	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Ajustes iniciales"

## ___________________________________________________________________


## Integrar Laravel-permission al proyecto
##### Documentación: https://spatie.be/docs/laravel-permission/v4/introduction

1. Ejecutar: 
	>
		$ composer require spatie/laravel-permission
1. Ejecutar: 
	>
		$ php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
1. Ejecutar: 
	>
		$ php artisan migrate
1. Añadir a la cabecera del modelo **User**:
	>
		use Spatie\Permission\Traits\HasRoles;
1. Añadir dentro de la clase del modelo **User**:
	>
		use HasRoles;

	### **Instrucciones básicas:**
	+ Crear un rol:
		>
			Role::create(['name' => 'admin']);
	+ Asignar un rol a un usuario:
		>
			$user = User::find(1);
			$user->assignRole('admin');
	+ Crear un permiso:
		>
			Permission::create(['name' => 'universal']);
	+ Asignar un permiso a un rol:
		>
			$role = Role::find(1);
			$role->givePermissionTo('universal');
	+ Asignar un permiso a un usuario:
		>
			$user = User::find(2);
			$user->givePermissionTo('universal');
	+ Revocar permiso a un usuario:
		>
			$user->revokePermissionTo('universal');
	+ Revocar rol a un usuario:
		>
			$user->removeRole('writer');
	+ Conocer si el usuario X tiene el rol “admin”:
		>
			$user->hasRole('admin');
	+ Conocer si el usuario X tiene el permiso “universal”:
		>
			$user->hasPermissionTo("universal");
	+ Lista de roles que posee el usuario X:
		>
			$user->getRoleNames();
	+ Lista de permisos que posee el usuario X:
		>
			$user->getAllPermissions();
1. Programar el controlador **app\Http\Controllers\Controller.php** para que inicie sesión según el rol asignado al usuario:
	>
		≡
		≡
1. Modificar ruta raíz:
	>
		Route::get('/', [Controller::class, 'index'])->name('inicio')->middleware('auth');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\Controller;

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Laravel-permission"

## ___________________________________________________________________


## Integrar plantilla AdminLTE
+ **Documentación**: https://github.com/jeroennoten/Laravel-AdminLTE
+ **Plantilla**: https://adminlte.io/themes/v3/index.html

1. Integrar AdminLTE: 
	+ $ composer require jeroennoten/laravel-adminlte
2. Publicar vistas:
    + $ php artisan adminlte:install --only=main_views
    + **Nota**: En **resources\views\vendor\adminlte\page.blade.php** es de donde se extienden las plantillas.
    + Agregar favicon de la aplicación en **resources\views\vendor\adminlte\master.blade.php**:
        ```html
        ≡
        <link rel="shortcut icon" type="image/x-icon" href="{{ asset('/favicon.ico') }}">
        ≡
        ```
3. Ejecutar: 
	+ $ php artisan adminlte:install
4. Crear plantilla modelo: **resources\views\layouts\demoAdminLTE.blade.php**:
    ```php
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
    ```
    + **Nota**: Se recomienda insertar ruta en **routes\web.php** para probar vistas:
    ```php
    // Para probar vistas
    Route::get('/pruebas', function () {
        return view('layouts.demoAdminLTE');
    });
    ```

	### Commit --:
	+ Ejecutar: $ **git add .**
	+ Ejecutar: $ **git commit -m "Instalación Plantilla AdminLTE"**
	
## ___________________________________________________________________


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
	>
		≡
		≡
1. Configurar a español **config\app.php**
	>
		≡
		'locale' => 'es',
		≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Adaptación al español"

## ___________________________________________________________________


## Seeders para prueba de roles y permisos
1. Crear seeder para roles: 
	>
		$ php artisan make:seeder RoleSeeder
1. Añadir a cabecera de **database\seeders\RoleSeeder.php**
	>
		use Spatie\Permission\Models\Role;
		use Spatie\Permission\Models\Permission;
1. Modificar el método **run** de **database\seeders\RoleSeeder.php**
	>
		≡
		≡
1. Crear seeder para usuarios: $ **php artisan make:seeder UserSeeder**
1. Añadir a cabecera de **database\seeders\UserSeeder.php**
	>
		use App\Models\User;

1. Modificar el método **run** de **database\seeders\UserSeeder.php**
	>
		≡
		≡
1. Modificar el método run **database\seeders\DatabaseSeeder.php**
	>
		≡
		≡
1. Ejecutar: 
	>
		$ php artisan migrate:fresh --seed
	##### **Nota**: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: 
	>
		$ php artisan db:seed

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Roles, Permisos y Usuarios"

## ___________________________________________________________________


## Personalizar el proyecto
1. Agregar a la cabecera del modelo **User**:
	>
		use Illuminate\Support\Facades\Auth;
1. Agregar los siguientes métodos a la clase **User**:
	>
		// Permite incorporar una imagen de usuario
		// Se debe configurar en config\adminlte.php: 'usermenu_image' => true,
		public function adminlte_image(){
			//return 'https://picsum.photos/300/300'; /* Retorna una imagen aleatoria*/
			return Auth::user()->profile_photo_url;
		}

		// Permite incorporar alguna descripción del usuario
		// Se debe configurar en config\adminlte.php: 'usermenu_desc' => ' => true,
		public function adminlte_desc(){
			return 'Aquí la información';
		}

		// Permite incorporar el perfil
		// Se debe configurar en config\adminlte.php: 'usermenu_profile_url' => true,
		public function adminlte_profile_url(){
			return 'user/profile';
		}
1. Adaptar la configuración del archivo **config\adminlte.php** al proyecto.
	##### **Iconos**: https://fontawesome.com/icons
	##### **Tutorial**: https://www.youtube.com/playlist?list=PLZ2ovOgdI-kWTCkbH749Ukvq7FMz5ahpP
	>
		≡
		≡
1. Crear archivo de estilos propios del proyecto **public\css\sefar.css**
	>
		≡
		≡
1. Agregar los estilos **public\css\sefar.css** en la sección del estilos de los archivos **archivo resources\views\layouts\guest.blade.php** y **resources\views\layouts\app.blade.php**
	>
		<link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
1. Ubicar un fabicon de la empresa, darle el nombre de **favicon.ico** y pegarlo en:
	+ public\
1. Ubicar un logo de la empresa, darle el nombre de **LogoSefar.png** y pegarlo en:
	+ public\vendor\adminlte\dist\img\
1. Crear **resources\views\layouts\logos\logo_sm.blade.php**
	>
		<img src="{{ asset('vendor\adminlte\dist\img\LogoSefar.png') }}" alt="Logo Sefar" width="50" height="50">
1. Crear **resources\views\layouts\logos\logo.blade.php**
	>
		<img src="{{ asset('vendor\adminlte\dist\img\LogoSefar.png') }}" alt="Logo Sefar" width="100" height="100">
1. Crear vista **resources\views\inicio.blade.php** para la ruta **inicio**
	>
		≡
		≡
1. Modificar la ruta de inicio en **routes\web.php**
	>
		// Vista inicio
		Route::get('/', function () {
			return view('inicio');
		})->name('inicio')->middleware('auth');
1. Adaptar todos los **archivos resources\views\auth** a las características del proyecto
	+ resources\views\auth\confirm-password.blade.php
		>
			≡
			≡
	+ resources\views\auth\forgot-password.blade.php
		>
			≡
			≡
	+ resources\views\auth\login.blade.php
		>
			≡
			≡
	+ resources\views\auth\register.blade.php
		>
			≡
			≡
	+ resources\views\auth\reset-password.blade.php
		>
			≡
			≡
	+ resources\views\auth\two-factor-challenge.blade.php
		>
			≡
			≡
	+ resources\views\auth\verify-email.blade.php
		>
			≡
			≡
1. Modificar **app\Providers\RouteServiceProvider.php**
	#### Cambiar:
	>
		public const HOME = '/dashboard';
	#### por:
	>
		public const HOME = '/';

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Proyecto personalizado"
	
## ___________________________________________________________________


## Instalar Laravel Collective para facilitar el uso de formularios
##### https://laravelcollective.com/docs/6.x/html
1. Instalar Laravel Collective:
    >
        $ composer require laravelcollective/html


## ___________________________________________________________________    

## Perfil de usuario
1. Rediseñar plantilla **resources\views\profile\update-profile-information-form.blade.php**
	>
						≡
						@if ($this->user->profile_photo_path)
							<x-jet-secondary-button type="button" class="mt-2 cfrSefar ctaSefar" wire:click="deleteProfilePhoto">
								{{ __('Remove Photo') }}
							</x-jet-secondary-button>
						@endif
						≡
				@endif
				≡
			</x-slot>

			<x-slot name="actions">
				≡
				<x-jet-button wire:loading.attr="disabled" wire:target="photo" class="cfrSefar">
					{{ __('Save') }}
				</x-jet-button>
			</x-slot>
		</x-jet-form-section>
1. Rediseñar plantilla **resources\views\profile\update-password-form.blade.php**
	>
				≡
				<x-jet-button class="cfrSefar">
					{{ __('Save') }}
				</x-jet-button>
			</x-slot>
		</x-jet-form-section>
1. Rediseñar plantilla **resources\views\profile\two-factor-authentication-form.blade.php**
	>		
		≡
		@if (! $this->enabled)
			<x-jet-confirms-password wire:then="enableTwoFactorAuthentication">
				<x-jet-button type="button" wire:loading.attr="disabled" class="cfrSefar">
					{{ __('Enable') }}
				</x-jet-button>
			</x-jet-confirms-password>
		@else
		≡
1. Rediseñar plantilla **resources\views\profile\logout-other-browser-sessions-form.blade.php**
	>	
		≡
        <div class="flex items-center mt-5">
            <x-jet-button wire:click="confirmLogout" wire:loading.attr="disabled" class="cfrSefar">
                {{ __('Log Out Other Browser Sessions') }}
            </x-jet-button>

            <x-jet-action-message class="ml-3" on="loggedOut">
                {{ __('Done.') }}
            </x-jet-action-message>
        </div>
		≡
1. Rediseñar plantilla **resources\views\navigation-menu.blade.php**
	>
		<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
			<!-- Primary Navigation Menu -->
			<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
				<div class="flex justify-between h-16">
					<div class="flex">
						<div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
							<div class="block px-2 py-4 text-xl ctvSefar">
								<strong>{{ Auth::user()->name }}</strong>
							</div>
						</div>
					</div>

					<div class="hidden sm:flex sm:items-center sm:ml-6">
						<!-- Teams Dropdown -->
						@if (Laravel\Jetstream\Jetstream::hasTeamFeatures())
							<div class="ml-3 relative">
								<x-jet-dropdown align="right" width="60">
									<x-slot name="trigger">
										<span class="inline-flex rounded-md">
											<button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-gray-50 transition ease-in-out duration-150">
												{{ Auth::user()->currentTeam->name }}

												<svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
													<path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
												</svg>
											</button>
										</span>
									</x-slot>

									<x-slot name="content">
										<div class="w-60">
											<!-- Team Management -->
											<div class="block px-4 py-2 text-xs text-gray-400">
												{{ __('Manage Team') }}
											</div>

											<!-- Team Settings -->
											<x-jet-dropdown-link href="{{ route('teams.show', Auth::user()->currentTeam->id) }}">
												{{ __('Team Settings') }}
											</x-jet-dropdown-link>

											@can('create', Laravel\Jetstream\Jetstream::newTeamModel())
												<x-jet-dropdown-link href="{{ route('teams.create') }}">
													{{ __('Create New Team') }}
												</x-jet-dropdown-link>
											@endcan

											<div class="border-t border-gray-100"></div>

											<!-- Team Switcher -->
											<div class="block px-4 py-2 text-xs text-gray-400">
												{{ __('Switch Teams') }}
											</div>

											@foreach (Auth::user()->allTeams() as $team)
												<x-jet-switchable-team :team="$team" />
											@endforeach
										</div>
									</x-slot>
								</x-jet-dropdown>
							</div>
						@endif

						<!-- Settings Dropdown -->
						<div class="ml-3 relative">
							<x-jet-dropdown align="right" width="48">
								<x-slot name="trigger">
									@if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
										<button class="flex text-sm border-2 border-transparent rounded-full focus:outline-none focus:border-gray-300 transition duration-150 ease-in-out">
											<img class="h-8 w-8 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
										</button>
									@else
										<span class="inline-flex rounded-md">
											<button type="button" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
												{{ Auth::user()->name }}

												<svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
													<path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
												</svg>
											</button>
										</span>
									@endif
								</x-slot>

								<x-slot name="content">
									<div class="border-t border-gray-100"></div>        
								</x-slot>
							</x-jet-dropdown>
						</div>
					</div>

					<!-- Hamburger -->
					<div class="-mr-2 flex items-center sm:hidden">
						<button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
							<svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
								<path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
								<path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
							</svg>
						</button>
					</div>
				</div>
			</div>

			<!-- Responsive Navigation Menu -->
			<div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">

				<!-- Responsive Settings Options -->
				<div class="pt-4 pb-1 border-t border-gray-200">
					<div class="flex items-center px-4">
						@if (Laravel\Jetstream\Jetstream::managesProfilePhotos())
							<div class="flex-shrink-0 mr-3">
								<img class="h-10 w-10 rounded-full object-cover" src="{{ Auth::user()->profile_photo_url }}" alt="{{ Auth::user()->name }}" />
							</div>
						@endif

						<div>
							<div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
							<div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
						</div>
					</div>
				</div>
			</div>
		</nav>
1. Rediseñar vista para el perfil de usuario **resources\views\profile\show.blade.php**
	>
		@extends('adminlte::page')

		@section('title', 'Usuario')

		@section('content_header')
			{{-- <h1>Perfil de usuario</h1> --}}
		@stop

		@section('content')
		<x-app-layout>
			<div>
				<div class="max-w-7xl mx-auto py-10 sm:px-6 lg:px-8">
					@if (Laravel\Fortify\Features::canUpdateProfileInformation())
						@livewire('profile.update-profile-information-form')

						<x-jet-section-border />
					@endif

					@if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::updatePasswords()))
						<div class="mt-10 sm:mt-0">
							@livewire('profile.update-password-form')
						</div>

						<x-jet-section-border />
					@endif

					@if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
						<div class="mt-10 sm:mt-0">
							@livewire('profile.two-factor-authentication-form')
						</div>

						<x-jet-section-border />
					@endif

					<div class="mt-10 sm:mt-0">
						@livewire('profile.logout-other-browser-sessions-form')
					</div>

					@if (Laravel\Jetstream\Jetstream::hasAccountDeletionFeatures())
						<x-jet-section-border />

						<div class="mt-10 sm:mt-0">
							@livewire('profile.delete-user-form')
						</div>
					@endif
				</div>
			</div>
		</x-app-layout>
		@stop

		@section('css')
			<link rel="stylesheet" href="/css/admin_custom.css">
		@stop

		@section('js')

		@stop

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Perfil de usuario"

## ___________________________________________________________________


## Integrar Sweetalert
##### https://realrashid.github.io/sweet-alert/
1. Ejecutar: 
	>
		$ composer require realrashid/sweet-alert
1. Agregar a **config\app.php** en **providers**
	>
		≡
		'providers' => [
			≡
			/*
			* Package Service Providers...
			*/
			RealRashid\SweetAlert\SweetAlertServiceProvider::class,
			≡
		],
		≡
1. Agregar a **config\app.php** en **aliases**
	>
    	≡
		'aliases' => [
			≡
			'Alert' => RealRashid\SweetAlert\Facades\Alert::class,
			≡
		],
		≡
	##### **Nota**: agregar a la cabecer del controlador a utilizar:
	>
    	use RealRashid\SweetAlert\Facades\Alert;

	##### **Nota**: insertar en la sección content de resources\views\layouts\app.blade.php
	>
		@include('sweetalert::alert', ['cdn' => "https://cdn.jsdelivr.net/npm/sweetalert2@9"])
		Nota: si falla, reemplazar por: @include('sweetalert::alert')


	### Para integrar Sweetalert2
	##### https://sweetalert2.github.io
	1. Ejecutar:
		>
			$ php artisan adminlte:plugins install
	1. Modificar en **config\adminlte.php**
		>
			≡
			'Sweetalert2' => [
				'active' => true,   /* Activamos para todas las vistas de la plantilla Sweetalert2 */
				'files' => [
					[
						'type' 		=> 'js',
						'asset' 	=> true,
						'location' 	=> 'vendor/sweetalert2/sweetalert2.all.min.js',
					],
				],
			],
			≡

	1. Ejecutar:
		>
			$ npm install sweetalert2
	1. Agregamos la siguiente instrucción al archivo **resources\js\app.js**
		>
			window.Swal = require('sweetalert2');	
	1. Ejecutamos:
		>
			$ npm run dev		
		##### **Nota**: para usarlo:
		+ Incluir en la vista luego de la sección @section('title', '***')
			>
				@section('plugins.Sweetalert2', true)
		+ Incluir el siguiente script al final de la vista para verificar que esta funcionando:
			>
				@section('js')
					<script>
						Swal.fire(
							'Good job!',
							'You clicked the button!',
							'success'
						)
					</script>
				@stop

	### Commit 9:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Integración Sweetalert"

## ___________________________________________________________________


## Verificación de email con Jetstream
##### https://dev.to/devscamp/segundo-post-de-prueba-4jf1
1. Modificar el archivo **config/fortify.php**
	>
		'features' => [
			Features::registration(),
			Features::resetPasswords(),
			Features::emailVerification(),
			Features::updateProfileInformation(),
			Features::updatePasswords(),
			Features::twoFactorAuthentication([
				'confirmPassword' => true,
        ]),
	##### Se descomentó:
	>
		// Features::emailVerification(),
1. En el modelo **User** implementar la interface **MustVerifyEmail**
	>
		class User extends Authenticatable implements MustVerifyEmail
1. Ingresar en Mailtrap (https://mailtrap.io).
1. Configurar .env con las credenciales de Mailtrap.
	>
		MAIL_MAILER=smtp
		MAIL_HOST=smtp.mailtrap.io
		MAIL_PORT=2525
		MAIL_USERNAME=7c67f786972696
		MAIL_PASSWORD=8f37b2d25228ba
		MAIL_ENCRYPTION=tls
1. Modificar variable de entorno en **.env**
	+ Cambiar **MAIL_FROM_ADDRESS=null** por **MAIL_FROM_ADDRESS=app.web@sefarvzla.com**
1. Modificar la ruta raiz en **routes\web.php**
	>
		Route::get('/', [Controller::class, 'index'])->name('inicio')->middleware(['auth', 'verified']);
1. Publicar los archivos de las notificaciones:
	>
		$ php artisan vendor:publish --tag=laravel-notifications
	##### Ahora en **resources\views\vendor\notifications\email.blade.php**, ahí podremos editar la plantilla de email.
1. Para personalizar estilos del email:
	>
		$ php artisan vendor:publish --tag=laravel-mail
	##### Ahora en "resources/views/vendor/mail/html/themes/default.css" podremos personalizar los estilos de CSS.
1. Modificar el archivo de estilo **resources\views\vendor\mail\html\themes\default.css**
	>
		≡
		.button-primary {
			background-color: rgb(121,22,15);
			border-bottom: 8px solid #2d3748;
			border-left: 18px solid #2d3748;
			border-right: 18px solid #2d3748;
			border-top: 8px solid #2d3748;
		}
		≡
		.button-success {
			background-color: rgb(121,22,15);
			border-bottom: 8px solid rgb(121,22,15);
			border-left: 18px solid rgb(121,22,15);
			border-right: 18px solid rgb(121,22,15);
			border-top: 8px solid rgb(121,22,15);
		}
		≡
1. Modificar plantilla **resources\views\vendor\mail\html\header.blade.php**
	>
		<tr>
			<td class="header">
				<a href="{{ $url }}" style="display: inline-block;">
					@if (trim($slot) === 'Laravel')
						<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
					@else
						<img src="https://app.universalsefar.com/vendor/adminlte/dist/img/LogoSefar.png" alt="Logo Sefar" width="100" height="100">
						<hr>
						{{ $slot }}
					@endif
				</a>
			</td>
		</tr>

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Verificación de email"

## ___________________________________________________________________


## CRUD Permisos
1. Crear grupo de rutas en **routes\web.php**
	>
		// Grupo de rutas CRUD
		Route::group(['middleware' => ['auth'], 'as' => 'crud.'], function(){
		});
1. Crear modelo Permission:
	>
		$ php artisan make:model Permission
1. Programar modelo Permission: **app\Models\Permission.php**
	>
		<?php

		namespace App\Models;

		use Illuminate\Database\Eloquent\Factories\HasFactory;
		use Illuminate\Database\Eloquent\Model;

		class Permission extends Model
		{
			use HasFactory;
			
			protected $fillable = [
				'name',
			];
		}
1. Crear controlador Permission:
	>
		$ php artisan make:controller PermissionController -r
1. Programar el controlador Permission **app\Http\Controllers\PermissionController.php**
	>
		≡
		≡
1. Agregar ruta de permisos al grupo de rutas CRUD:
	>
		Route::resource('permissions', PermissionController::class)->names('permissions')
			->middleware('can:crud.permissions.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\PermissionController;
1. Crear componente Livewire para Tabla Permissions: 
	>
		$ php artisan make:livewire crud/permissions-table
1. Programar controlador para la tabla Permissions: **app\Http\Livewire\Crud\PermissionsTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Permissions: **resources\views\livewire\crud\permissions-table.blade.php**
	>
		≡
		≡
1. Programar controlador Permission: **app\Http\Controllers\PermissionController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Permisos:
	- resources\views\crud\permissions\index.blade.php
		>
			≡
			≡
	- resources\views\crud\permissions\create.blade.php
		>
			≡
			≡
	- resources\views\crud\permissions\edit.blade.php
		>
			≡
			≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Permisos"

## ___________________________________________________________________


## CRUD Roles
1. Crear modelo Role:
	>
		$ php artisan make:model Role
1. Programar modelo Role: **app\Models\Role.php**
	>
		<?php

		namespace App\Models;

		use Illuminate\Database\Eloquent\Factories\HasFactory;
		use Illuminate\Database\Eloquent\Model;

		class Role extends Model
		{
			use HasFactory;

			protected $fillable = [
				'name',
			];    
		}
1. Crear controlador Role:
	>
		$ php artisan make:controller RoleController -r
1. Programar el controlador Role **app\Http\Controllers\RoleController.php**
	>
		≡
		≡
1. Agregar ruta de roles al grupo de rutas CRUD:
	>
		Route::resource('roles', RoleController::class)->names('roles')
			->middleware('can:crud.roles.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\RoleController;
1. Crear componente Livewire para Tabla Roles: 
	>
		$ php artisan make:livewire crud/roles-table
1. Programar controlador para la tabla Roles: **app\Http\Livewire\Crud\RolesTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Roles: **resources\views\livewire\crud\roles-table.blade.php**
	>
		≡
		≡
1. Programar controlador Role: **app\Http\Controllers\RoleController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Roles:
	- resources\views\crud\roles\index.blade.php
		>
			≡
			≡
	- resources\views\crud\roles\create.blade.php
		>
			≡
			≡
	- resources\views\crud\roles\edit.blade.php
		>
			≡
			≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Roles"

## ___________________________________________________________________


## CRUD Usuarios
1. Agregar el campo **passport** como campo de asignación masiva en el modelo **User**: **app\Models\User.php**
	>
		≡
		protected $fillable = [
			'name',
			'email',
			'password',
			'passport',
		];
		≡
1. Crear controlador User:
	>
		$ php artisan make:controller UserController -r
1. Programar el controlador User **app\Http\Controllers\UserController.php**
	>
		≡
		≡
1. Agregar ruta de usuarios al grupo de rutas CRUD:
	>
		Route::resource('users', UserController::class)->names('users')
			->middleware('can:crud.users.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\UserController;
1. Crear componente Livewire para Tabla Users: 
	>
		$ php artisan make:livewire crud/users-table
1. Programar controlador para la tabla Users: **app\Http\Livewire\Crud\UsersTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Users: **resources\views\livewire\crud\users-table.blade.php**
	>
		≡
		≡
1. Programar controlador User: **app\Http\Controllers\UserController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Usuarios:
	- resources\views\crud\users\index.blade.php
		>
			≡
			≡
	- resources\views\crud\users\create.blade.php
		>
			≡
			≡
	- resources\views\crud\users\edit.blade.php
		>
			≡
			≡

	### Commit 13:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Usuarios"

## ___________________________________________________________________


## CRUD Paises
1. Crear modelo Country junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Country -m -c -r
1. Preparar migración para la tabla **countries** en **database\migrations\2021_03_08_173429_create_permission_tables.php**
	>
		≡
		public function up()
		{
			Schema::create('countries', function (Blueprint $table) {
				$table->id();
				$table->string('pais');
				$table->string('store');
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD Paises en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.countries.index'])->syncRoles($rolAdministrador);
			Permission::create(['name' => 'crud.countries.create'])->syncRoles($rolAdministrador);
			Permission::create(['name' => 'crud.countries.edit'])->syncRoles($rolAdministrador);
			Permission::create(['name' => 'crud.countries.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Configurar modelo **Country** en **app\Models\Country.php**
	>
		≡
		class Country extends Model
		{
			use HasFactory;

			protected $fillable = [
				'pais',
				'store',
			];
		}
1. Agregar ruta de paises al grupo de rutas CRUD:
	>
		Route::resource('countries', CountryController::class)->names('countries')
				->middleware('can:crud.countries.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\CountryController;
1. Crear componente Livewire para Tabla Countries: 
	>
		$ php artisan make:livewire crud/countries-table
1. Programar controlador para la tabla Countries: **app\Http\Livewire\Crud\CountriesTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Country: **resources\views\livewire\crud\countries-table.blade.php**
	>
		≡
		≡
1. Programar controlador Country: **app\Http\Controllers\CountryController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Paises:
	- resources\views\crud\countries\index.blade.php
		>
			≡
			≡
	- resources\views\crud\countries\create.blade.php
		>
			≡
			≡
	- resources\views\crud\countries\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Paises.
	>
		≡
		≡


	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Paises"

## ___________________________________________________________________


## Seeders para cargar los paises iniciales
1. Crear seeder para countries: 
	>
		$ php artisan make:seeder CountrySeeder
1. Añadir a cabecera de **database\seeders\CountrySeeder.php**
	>
		use App\Models\Country;
1. Modificar el método **run** de **database\seeders\CountrySeeder.php**
	>
		≡
		≡
1. Añadir al método run de **database\seeders\DatabaseSeeder.php**
	>
		public function run()
		{
			≡
			$this->call(CountrySeeder::class);
		}
1. Crear directorio **storage\app\public\imagenes\paises** y guardar la imagenes de los paises iniciales en formato png y en baja resolución.
1. Ejecutar: 
	>
		$ php artisan migrate:fresh --seed
	##### **Nota**: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: 
	>
		$ php artisan db:seed

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Paises"

## ___________________________________________________________________


## CRUD Formatos (extensiones de archivos)
1. Crear modelo Format junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Format -m -c -r
1. Preparar migración para la tabla **formats** en **database\migrations\2021_04_12_190642_create_formats_table.php**
	>
		≡
		public function up()
		{
			Schema::create('formats', function (Blueprint $table) {
				$table->id();
				$table->string('formato');
				$table->string('ubicacion');
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD Formatos en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.formats.index'])->syncRoles($rolAdministrador);
			Permission::create(['name' => 'crud.formats.create'])->syncRoles($rolAdministrador);
			Permission::create(['name' => 'crud.formats.edit'])->syncRoles($rolAdministrador);
			Permission::create(['name' => 'crud.formats.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Configurar modelo **Format** en **app\Models\Format.php**
	>
		≡
		class Format extends Model
		{
			use HasFactory;

			protected $fillable = [
				'formato',
				'ubicacion',
			];
		}
1. Agregar ruta de formatos al grupo de rutas CRUD:
	>
		Route::resource('formats', FormatController::class)->names('formats')
				->middleware('can:crud.formats.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\FormatController;
1. Crear componente Livewire para Tabla Formats: 
	>
		$ php artisan make:livewire crud/formats-table
1. Programar controlador para la tabla Formats: **app\Http\Livewire\Crud\FormatsTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Formats: **resources\views\livewire\crud\formats-table.blade.php**
	>
		≡
		≡
1. Programar controlador Formats: **app\Http\Controllers\FormatController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Formatos:
	- resources\views\crud\formats\index.blade.php
		>
			≡
			≡
	- resources\views\crud\formats\create.blade.php
		>
			≡
			≡
	- resources\views\crud\formats\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Formatos.
	>
		≡
		≡


	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Formatos"

## ___________________________________________________________________


## Seeders para cargar los formatos iniciales
1. Crear seeder para formats: 
	>
		$ php artisan make:seeder FormatSeeder
1. Añadir a cabecera de **database\seeders\FormatSeeder.php**
	>
		use App\Models\Format;
1. Modificar el método **run** de **database\seeders\FormatSeeder.php**
	>
		≡
		≡
1. Añadir al método run de **database\seeders\DatabaseSeeder.php**
	>
		public function run()
		{
			≡
			$this->call(FormatSeeder::class);
		}
1. Crear directorio **storage\app\public\imagenes\formatos** y guardar la imagenes de los formatos iniciales en formato png y en baja resolución.
1. Ejecutar: 
	>
		$ php artisan migrate:fresh --seed
	##### **Nota**: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: 
	>
		$ php artisan db:seed

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Formatos"

## ___________________________________________________________________


## CRUD Parentescos
1. Crear modelo Parentesco junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Parentesco -m -c -r
1. Preparar migración para la tabla **parentescos** en **database\migrations\2021_03_30_013140_create_parentescos_table.php**
	>
		≡
		public function up()
		{
			Schema::create('parentescos', function (Blueprint $table) {
				$table->id();
				$table->string('Parentesco',175)->unique();
				$table->string('Inverso',175)->unique();
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD Paises en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.parentescos.index'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.parentescos.create'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.parentescos.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.parentescos.destroy'])->syncRoles($rolAdministrador, $rolGenealogista);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Configurar modelo **Parentesco** en **app\Models\Parentesco.php**
	>
		≡
		class Parentesco extends Model
		{
			use HasFactory;

			protected $fillable = [
				'Parentesco',
				'Inverso',
			];
		}
1. Agregar ruta de parentesco al grupo de rutas CRUD:
	>
		Route::resource('parentescos', ParentescoController::class)->names('parentescos')
				->middleware('can:crud.parentescos.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\ParentescoController;
1. Crear componente Livewire para Tabla Parentescos: 
	>
		$ php artisan make:livewire crud/parentescos-table
1. Programar controlador para la tabla Parentescos: **app\Http\Livewire\Crud\ParentescosTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Parentescos: **resources\views\livewire\crud\parentescos-table.blade.php**
	>
		≡
		≡
1. Programar controlador Parentesco: **app\Http\Controllers\ParentescoController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Parentescos:
	- resources\views\crud\parentescos\index.blade.php
		>
			≡
			≡
	- resources\views\crud\parentescos\create.blade.php
		>
			≡
			≡
	- resources\views\crud\parentescos\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Parentescos.
	>
		≡
		≡


	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Parentescos"

## ___________________________________________________________________



## Seeders para cargar los parentescos iniciales
1. Crear seeder para parentescos: 
	>
		$ php artisan make:seeder ParentescoSeeder
1. Añadir a cabecera de **database\seeders\ParentescoSeeder.php**
	>
		use App\Models\Parentesco;
1. Modificar el método **run** de **database\seeders\ParentescoSeeder.php**
	>
		≡
		≡
1. Añadir al método run de **database\seeders\DatabaseSeeder.php**
	>
		public function run()
		{
			≡
			$this->call(ParentescoSeeder::class);
		}
1. Ejecutar: 
	>
		$ php artisan migrate:fresh --seed
	##### **Nota**: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: 
	>
		$ php artisan db:seed

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Parentescos"

## ___________________________________________________________________



## CRUD Lado (del parentesco)
1. Crear modelo Lado junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Lado -m -c -r	
1. Preparar migración para la tabla **lados** en **database\migrations\2021_03_30_013140_create_parentescos_table.php**
	>
		≡
		public function up()
		{
			Schema::create('lados', function (Blueprint $table) {
				$table->id();
				$table->string('Lado',15)->unique();
				$table->string('Significado')->nullable();
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD Paises en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.lados.index'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.lados.create'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.lados.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.lados.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Configurar modelo **Lado** en **app\Models\Parentesco.php**
	>
		≡
		class Lado extends Model
		{
			use HasFactory;

			protected $fillable = [
				'Lado',
				'Significado',
			];
		}
1. Agregar ruta lados al grupo de rutas CRUD:
	>
		Route::resource('lados', LadoController::class)->names('lados')
				->middleware('can:crud.lados.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\LadoController;
1. Crear componente Livewire para Tabla Lados: 
	>
		$ php artisan make:livewire crud/lados-table
1. Programar controlador para la tabla Lados: **app\Http\Livewire\Crud\LadosTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Lados: **resources\views\livewire\crud\lados-table.blade.php**
	>
		≡
		≡
1. Programar controlador Lado: **app\Http\Controllers\LadoController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Lados:
	- resources\views\crud\lados\index.blade.php
		>
			≡
			≡
	- resources\views\crud\lados\create.blade.php
		>
			≡
			≡
	- resources\views\crud\lados\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Lados.
	>
		≡
		≡


	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Lados"

## ___________________________________________________________________


## Seeders para cargar los lados iniciales
1. Crear seeder para lados: 
	>
		$ php artisan make:seeder LadoSeeder
1. Añadir a cabecera de **database\seeders\LadoSeeder.php**
	>
		use App\Models\Lado;
1. Modificar el método **run** de **database\seeders\LadoSeeder.php**
	>
		≡
		≡
1. Añadir al método run de **database\seeders\DatabaseSeeder.php**
	>
		public function run()
		{
			≡
			$this->call(LadoSeeder::class);
		}
1. Ejecutar: 
	>
		$ php artisan migrate:fresh --seed
	##### **Nota**: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: 
	>
		$ php artisan db:seed

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Lados"

## ___________________________________________________________________



## CRUD Conexiones (del parentesco)
1. Crear modelo Connection junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Connection -m -c -r
1. Preparar migración para la tabla **connections** en **database\migrations\2021_03_31_003009_create_connections_table.php**
	>
		≡
		public function up()
		{
			Schema::create('connections', function (Blueprint $table) {
				$table->id();
				$table->string('Conexion',15)->unique();
				$table->string('Significado')->nullable();
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD Paises en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.connections.index'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.connections.create'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.connections.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.connections.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Configurar modelo **Connection** en **app\Models\Parentesco.php**
	>
		≡
		class Connection extends Model
		{
			use HasFactory;

			protected $fillable = [
				'Conexion',
				'Significado',
			];
		}
1. Agregar ruta connections al grupo de rutas CRUD:
	>
		Route::resource('connections', ConnectionController::class)->names('connections')
				->middleware('can:crud.connections.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\ConnectionController;
1. Crear componente Livewire para Tabla Connections: 
	>
		$ php artisan make:livewire crud/connections-table
1. Programar controlador para la tabla Connections: **app\Http\Livewire\Crud\ConnectionsTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Connections: **resources\views\livewire\crud\connections-table.blade.php**
	>
		≡
		≡
1. Programar controlador Connection: **app\Http\Controllers\ConnectionController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Connections:
	- resources\views\crud\connections\index.blade.php
		>
			≡
			≡
	- resources\views\crud\connections\create.blade.php
		>
			≡
			≡
	- resources\views\crud\connections\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Conexiones.
	>
		≡
		≡


	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Conexiones"

## ___________________________________________________________________


## Seeders para cargar las conexiones iniciales
1. Crear seeder para conexiones: 
	>
		$ php artisan make:seeder ConnectionSeeder
1. Añadir a cabecera de **database\seeders\ConnectionSeeder.php**
	>
		use App\Models\Connection;
1. Modificar el método **run** de **database\seeders\ConnectionSeeder.php**
	>
		public function run()
		{
			Connection::create(['Conexion' => 'PM','Significado' => 'Padre y Madre']);
			Connection::create(['Conexion' => 'P','Significado' => 'Padre']);
			Connection::create(['Conexion' => 'M','Significado' => 'Madre']);
			Connection::create(['Conexion' => 'APO','Significado' => 'Abuelo Paterno']);
			Connection::create(['Conexion' => 'APA','Significado' => 'Abuela Paterna']);
			Connection::create(['Conexion' => 'AMO','Significado' => 'Abuelo Materno']);
			Connection::create(['Conexion' => 'AMA','Significado' => 'Abuela Materna']);
			Connection::create(['Conexion' => 'BPPO','Significado' => 'Bisabuelo PP']);
			Connection::create(['Conexion' => 'BPPA','Significado' => 'Bisabuela PP']);
			Connection::create(['Conexion' => 'BPMO','Significado' => 'Bisabuelo PM']);
			Connection::create(['Conexion' => 'BPMA','Significado' => 'Bisabuela PM']);
			Connection::create(['Conexion' => 'BMPO','Significado' => 'Bisabuelo MP']);
			Connection::create(['Conexion' => 'BMPA','Significado' => 'Bisabuela MP']);
			Connection::create(['Conexion' => 'BMMO','Significado' => 'Bisabuelo MM']);
			Connection::create(['Conexion' => 'BMMA','Significado' => 'Bisabuela MM']);
			Connection::create(['Conexion' => 'TPPPO','Significado' => 'Tatarubuelo PPP']);
			Connection::create(['Conexion' => 'TPPPA','Significado' => 'Tatarubuela PPP']);
			Connection::create(['Conexion' => 'TPPMO','Significado' => 'Tatarubuelo PPM']);
			Connection::create(['Conexion' => 'TPPMA','Significado' => 'Tatarubuela PPM']);
			Connection::create(['Conexion' => 'TPMPO','Significado' => 'Tatarubuelo PMP']);
			Connection::create(['Conexion' => 'TPMPA','Significado' => 'Tatarubuela PMP']);
			Connection::create(['Conexion' => 'TPMMO','Significado' => 'Tatarubuelo PMM']);
			Connection::create(['Conexion' => 'TPMMA','Significado' => 'Tatarubuela PMM']);
			Connection::create(['Conexion' => 'TMPPO','Significado' => 'Tatarubuelo MPP']);
			Connection::create(['Conexion' => 'TMPPA','Significado' => 'Tatarubuela MPP']);
			Connection::create(['Conexion' => 'TMPMO','Significado' => 'Tatarubuelo MPM']);
			Connection::create(['Conexion' => 'TMPMA','Significado' => 'Tatarubuela MPM']);
			Connection::create(['Conexion' => 'TMMPO','Significado' => 'Tatarubuelo MMP']);
			Connection::create(['Conexion' => 'TMMPA','Significado' => 'Tatarubuela MMP']);
			Connection::create(['Conexion' => 'TMMMO','Significado' => 'Tatarubuelo MMM']);
			Connection::create(['Conexion' => 'TMMMA','Significado' => 'Tatarubuela MMM']);
			Connection::create(['Conexion' => 'C','Significado' => 'Cónyuge']);
			Connection::create(['Conexion' => 'ND','Significado' => 'No determinado']);
		}
1. Añadir al método run de **database\seeders\DatabaseSeeder.php**
	>
		public function run()
		{
			≡
			$this->call(ConnectionSeeder::class);
		}
1. Ejecutar: 
	>
		$ php artisan migrate:fresh --seed
	##### **Nota**: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: 
	>
		$ php artisan db:seed

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Conexiones"

## ___________________________________________________________________


## CRUD Familiares
1. Crear modelo Family junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Family -m -c -r
1. Preparar migración para la tabla **families** en **database\migrations\2021_03_30_010102_create_families_table.php**
	>
		≡
		public function up()
		{
			Schema::create('families', function (Blueprint $table) {
				$table->id();
				$table->string('IDCombinado',175)->unique();
				$table->string('IDCliente',175);
				$table->string('Cliente')->nullable();
				$table->string('IDFamiliar');
				$table->string('Familiar')->nullable();
				$table->string('Parentesco')->nullable();
				$table->string('Lado')->nullable();
				$table->string('Rama')->nullable();
				$table->text('Nota')->nullable();
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD Paises en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.families.index'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.families.create'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.families.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.families.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Configurar modelo **Family** en **app\Models\Family.php**
	>
		≡
		class Family extends Model
		{
			use HasFactory;

			protected $fillable = [
				'IDCombinado',
				'IDCliente',
				'Cliente',
				'IDFamiliar',
				'Familiar',
				'Parentesco',
				'Lado',
				'Rama',
				'Nota'
			];
		}
1. Agregar ruta families al grupo de rutas CRUD:
	>
		Route::resource('families', FamilyController::class)->names('families')
				->middleware('can:crud.families.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\FamilyController;
1. Crear componente Livewire para Tabla Families: 
	>
		$ php artisan make:livewire crud/families-table
1. Programar controlador para la tabla Families: **app\Http\Livewire\Crud\FamiliesTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Families: **resources\views\livewire\crud\families-table.blade.php**
	>
		≡
		≡
1. Programar controlador Family: **app\Http\Controllers\FamilyController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Familiares:
	- resources\views\crud\families\index.blade.php
		>
			≡
			≡
	- resources\views\crud\families\create.blade.php
		>
			≡
			≡
	- resources\views\crud\families\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Familiares.
	>
		≡
		≡


	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Familiares"


## ___________________________________________________________________


## CRUD Tipos de documentos

1. Crear modelo TFile (tipo de documentos) junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model TFile -m -c -r
1. Preparar migración para la tabla **t_files** en **database\migrations\2021_04_01_143943_create_t_files_table.php**
	>
		≡
		public function up()
		{
			Schema::create('t_files', function (Blueprint $table) {
				$table->id();
				$table->string('tipo')->unique();
				$table->string('notas')->nullable();
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD TFiles en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.t_files.index'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.t_files.create'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.t_files.edit'])->syncRoles($rolAdministrador, $rolGenealogista);
			Permission::create(['name' => 'crud.t_files.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Establecer campos de asignación masiva en el modelo **TFile** en **app\Models\TFile.php**
	>
		≡
		class TFile extends Model
		{
			use HasFactory;

			protected $fillable = [
				'tipo',
				'notas',
			];
		}
1. Agregar ruta t_files al grupo de rutas CRUD:
	>
		Route::resource('t_files', TFileController::class)->names('t_files')
				->middleware('can:crud.t_files.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\TFileController;
1. Crear componente Livewire para Tabla TFiles: 
	>
		$ php artisan make:livewire crud/t_files-table
1. Programar controlador para la tabla TFiles: **app\Http\Livewire\Crud\TFilesTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla TFiles: **resources\views\livewire\crud\t-files-table.blade.php**
	>
		≡
		≡
1. Programar controlador TFile: **app\Http\Controllers\TFileController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD TFiles:
	- resources\views\crud\t_files\index.blade.php
		>
			≡
			≡
	- resources\views\crud\t_files\create.blade.php
		>
			≡
			≡
	- resources\views\crud\t_files\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD TFiles.
	>
		≡
		≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Tipos de documentos"

## ___________________________________________________________________


## Seeders para cargar los tipos de documentos iniciales
1. Crear seeder para tipos de documentos: 
	>
		$ php artisan make:seeder TFileSeeder
1. Añadir a cabecera de **database\seeders\TFileSeeder.php**
	>
		use App\Models\TFile;
1. Modificar el método **run** de **database\seeders\TFileSeeder.php**
	>
		public function run()
		{
			TFile::create(['tipo' => 'Nacimiento','Notas' => 'Documentos relaciones con los datos de nacimiento de una persona']);
			TFile::create(['tipo' => 'Bautizo','Notas' => 'Documentos relaciones con los datos de bautizo de una persona']);
			TFile::create(['tipo' => 'Matrimonio','Notas' => 'Documentos relaciones con los datos de matrimonio de una persona']);
			TFile::create(['tipo' => 'Defunción','Notas' => 'Documentos relaciones con los datos de defunción de una persona']);
			TFile::create(['tipo' => 'Identificación','Notas' => 'Documentos relaciones con la identidad de una persona']);
			TFile::create(['tipo' => 'Filiatorio','Notas' => 'Documentos cuyo fin son expresamente migratorios']);
			TFile::create(['tipo' => 'Otros','Notas' => 'Otros tipos de documentos']);
		}
1. Añadir al método run de **database\seeders\DatabaseSeeder.php**
	>
		public function run()
		{
			≡
			$this->call(TFileSeeder::class);
		}
1. Ejecutar: 
	>
		$ php artisan migrate:fresh --seed
	##### **Nota**: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: 
	>
		$ php artisan db:seed

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Tipos de documentos"

## ___________________________________________________________________


## CRUD Almacenamiento de documentos
1. Crear modelo File junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model File -m -c -r
1. Preparar migración para la tabla **Files** en **database\migrations\2021_03_31_184533_create_files_table.php**
	>
		≡
		public function up()
		{
			Schema::create('files', function (Blueprint $table) {
				$table->id();
				$table->string('file');                     // Nombre del archivo
				$table->string('location');                 // Ubicación del archivo
				$table->string('tipo')->nullable();         // Tipo de documento
				$table->string('propietario')->nullable();  // Nombre del propietario del documento
				$table->string('IDCliente')->nullable();    // IDCliente del propietario del documento
				$table->string('notas')->nullable();        // Notas
				$table->integer('IDPersona');               // ID de persona
				$table->unsignedBigInteger('user_id');      // Relación con los usuarios
				$table->foreign('user_id')
					->references('id')
					->on('users')
					->onDelete('cascade');
				$table->timestamps();
			});
		}
		≡		
1. Establecer permisos en los seeders para el CRUD Documentos en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.files.index'])->syncRoles($rolAdministrador, $rolGenealogista,$rolDocumentalista,$rolProduccion,$rolCliente);
			Permission::create(['name' => 'crud.files.create'])->syncRoles($rolAdministrador, $rolGenealogista,$rolDocumentalista,$rolProduccion,$rolCliente);
			Permission::create(['name' => 'crud.files.edit'])->syncRoles($rolAdministrador, $rolGenealogista,$rolDocumentalista,$rolProduccion,$rolCliente);
			Permission::create(['name' => 'crud.files.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Indicar campos de asignación masiva en el modelo **File** en **app\Models\File.php**
	>
		≡
		class File extends Model
		{
			use HasFactory;

			protected $fillable = [
				'file',
				'location',
				'tipo',
				'propietario',
				'IDCliente',
				'notas',
				'IDPersona',
				'user_id'
			];

		}
1. Agregar ruta files al grupo de rutas CRUD:
	>
		Route::resource('files', FileController::class)->names('files')
				->middleware('can:crud.files.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\FileController;
1. Crear componente Livewire para Tabla Files: 
	>
		$ php artisan make:livewire crud/files-table
1. Programar controlador para la tabla Files: **app\Http\Livewire\Crud\FilesTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Files: **resources\views\livewire\crud\files-table.blade.php**
	>
		≡
		≡
1. Programar controlador File: **app\Http\Controllers\FileController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Files:
	- resources\views\crud\files\index.blade.php
		>
			≡
			≡
	- resources\views\crud\files\create.blade.php
		>
			≡
			≡
	- resources\views\crud\files\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Files.
	>
		≡
		≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Almacenamiento de documentos"





## ___________________________________________________________________


## CRUD Libros
1. Crear modelo Book junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Book -m -c -r
1. Preparar migración para la tabla **books** en **database\migrations\2021_05_08_140616_create_books_table.php**
	>
		≡
		public function up()
		{
			Schema::create('books', function (Blueprint $table) {
				$table->id();
				$table->string('id_bd',4)->nullable();      // id correspondiente en la tabla bd
				$table->string('titulo');
				$table->string('subtitulo')->nullable();
				$table->string('autor')->nullable();
				$table->string('editorial')->nullable();    // Ciudad / Editorial
				$table->string('coleccion')->nullable();    // Colección, Serie, Número
				$table->string('fecha')->nullable();      	// Fecha de edición
				$table->string('edicion')->nullable();      // Número de edición
				$table->string('paginacion')->nullable();
				$table->string('isbn')->nullable();
				$table->text('notas')->nullable();
				$table->string('enlace');                   // Enlace o url del documento
				$table->text('claves')->nullable();         // Palabras claves
				$table->string('catalogador')->nullable();  // Nombre o email del usuario que creo el documento
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD Books en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.books.index'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.books.create'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.books.edit'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.books.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. En phpMyAdmin pasar datos de la tabla existente bd a la nueva tabla books:
	+ Migrar campos:
	>
		INSERT INTO books(
			books.id_bd, 
			books.titulo,
			books.autor,
			books.editorial,
			books.coleccion,
			books.edicion,
			books.paginacion,
			books.isbn,
			books.notas,
			books.enlace,
			books.claves
		)
		SELECT 
			bd.id,
			bd.documento,
			bd.responsabilidad,
			bd.editorial,
			bd.coleccion,
			bd.edicion,
			bd.colacion,
			bd.isbn,
			bd.notas,
			bd.enlace,
			bd.busqueda
		FROM  bd
	+ Actualizar fecha de publicación con el año:
	>
		UPDATE `books` SET `fecha`='[anho_publicacion]/1/1' WHERE `id_bd` LIKE '1'
	###### Formula en Excel:
	###### ="UPDATE `books` SET `fecha`='"&T2&"/1/1' WHERE `id_bd` LIKE '"&A2&"';" (Fila 2)
	###### Donde: T[i]: anho_publicacion y A[i]: id
	+ Actualizar campos **created_at** y **updated_at** con la fecha actual:
	>
		UPDATE `books` SET `created_at` = CURRENT_TIMESTAMP;
		UPDATE `books` SET `updated_at` = CURRENT_TIMESTAMP;
1. Establecer campos de asignación masiva en el modelo **Book** en **app\Models\Book.php**
	>
		≡
		class Book extends Model
		{
			use HasFactory;

			protected $fillable = [
				'titulo',
				'subtitulo',
				'autor',
				'editorial',
				'coleccion',
				'fecha',
				'edicion',
				'paginacion',
				'isbn',
				'notas',
				'enlace',
				'claves',
				'catalogador',
			];
		}
1. Agregar ruta books al grupo de rutas CRUD:
	>
		Route::resource('books', BookController::class)->names('books')
				->middleware('can:crud.books.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\BookController;
1. Crear componente Livewire para Tabla Books: 
	>
		$ php artisan make:livewire crud/books-table
1. Programar controlador para la tabla Books: **app\Http\Livewire\Crud\BookTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Books: **resources\views\livewire\crud\books-table.blade.php**
	>
		≡
		≡
1. Programar controlador Book: **app\Http\Controllers\BookController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Books:
	- resources\views\crud\books\index.blade.php
		>
			≡
			≡
	- resources\views\crud\books\create.blade.php
		>
			≡
			≡
	- resources\views\crud\books\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Books.
	>
		≡
		≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Libros"

## ___________________________________________________________________


## CRUD Miscelaneos
1. Crear modelo Miscelaneo junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Miscelaneo -m -c -r
1. Preparar migración para la tabla **miscelaneos** en **database\migrations\2021_06_13_204842_create_miscelaneos_table.php**
	>
		≡
		public function up()
		{
			Schema::create('miscelaneos', function (Blueprint $table) {
				$table->id();
				$table->string('id_bd',4)->nullable();      // id correspondiente en la tabla bd
				$table->string('titulo');                   // Título
				$table->string('autor')->nullable();        // Autor(es)
				$table->string('publicado')->nullable();    // Publicado en
				$table->string('editorial')->nullable();    // Ciudad / Editorial
				$table->string('volumen')->nullable();      // Año / Número / Volumen
				$table->string('paginacion')->nullable();   // Paginación
				$table->string('isbn')->nullable();         // ISBN / ISSN
				$table->text('claves')->nullable();         // Palabras claves
				$table->string('enlace');                   // Enlace o url del documento
				$table->text('notas')->nullable();          // Notas
				$table->string('material')->nullable();     // Tipo de material:    - Artículo de publicación periódica
															//                      - Capítulo de libro
															//                      - Material genealógico
															//                      - Informes de Sefar
															//                      - Otros
				$table->string('catalogador')->nullable();  // Nombre o email del usuario que creo el documento
				$table->timestamps();
			});
		}
		≡

1. Establecer permisos en los seeders para el CRUD Miscelaneos en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.miscelaneos.index'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.miscelaneos.create'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.miscelaneos.edit'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.miscelaneos.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed		
1. Establecer campos de asignación masiva en el modelo **Miscelaneo** en **app\Models\Miscelaneo.php**
	>
		≡
		class Miscelaneo extends Model
		{
			use HasFactory;

			protected $fillable = [
				'titulo',
				'autor',
				'publicado',
				'editorial',
				'volumen',
				'paginacion',
				'isbn',
				'notas',
				'enlace',
				'claves',
				'material',
				'catalogador',
			];
		}
1. Agregar ruta miscelaneos al grupo de rutas CRUD:
	>
		Route::resource('miscelaneos', MiscelaneoController::class)->names('miscelaneos')
				->middleware('can:crud.miscelaneos.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\MiscelaneoController;
1. Crear componente Livewire para Tabla Miscelaneos: 
	>
		$ php artisan make:livewire crud/miscelaneos-table
1. Programar controlador para la tabla Miscelaneos: **app\Http\Livewire\Crud\MiscelaneosTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Miscelaneos: **resources\views\livewire\crud\miscelaneos-table.blade.php**
	>
		≡
		≡
1. Programar controlador Miscelaneo: **app\Http\Controllers\MiscelaneoController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Miscelaneos:
	- resources\views\crud\miscelaneos\index.blade.php
		>
			≡
			≡
	- resources\views\crud\miscelaneos\create.blade.php
		>
			≡
			≡
	- resources\views\crud\miscelaneos\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Miscelaneos.
	>
		≡
		≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Miscelaneos"

## ___________________________________________________________________


## CRUD Biblioteca
1. Crear modelo Library junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Library -m -c -r
1. Preparar migración para la tabla **libraries** en **database\migrations\2021_04_11_155101_create_libraries_table.php**
	>
		≡
		public function up()
		{
			Schema::create('libraries', function (Blueprint $table) {
				$table->id();
				$table->string('documento')->unique();              // Nombre del documento
				$table->string('formato',12)->nullable();           // Formato del documento
				$table->string('tipo',45)->nullable();              // Tipo del documento
				$table->string('fuente')->nullable();               // Fuente del documento
				$table->string('origen')->nullable();               // Origen del documento
				$table->string('ubicacion')->nullable();            // Ubicación actual del documento
				$table->string('ubicacion_ant')->nullable();        // Ubicación anterior del documento
				$table->text('busqueda')->nullable();               // Palabras que faciliten la búsqueda del documento
				$table->text('notas')->nullable();                  // Notas para el documento
				$table->string('enlace')->nullable();               // Enlace o url del documento
				$table->string('anho_ini',11)->nullable();          // Año inicial al que hacer referencia el documento
				$table->string('anho_fin',11)->nullable();          // Año final al que hacer referencia el documento
				$table->string('pais')->nullable();                 // País al que hacer referencia el documento
				$table->string('ciudad',150)->nullable();           // Ciudad al que hacer referencia el documento
				$table->dateTime('FIncorporacion')->nullable();     // Fecha de incorporación
				$table->string('responsabilidad',150)->nullable();  // Mención de responsabilidad
				$table->string('edicion',150)->nullable();          // Edición del documento
				$table->string('editorial',150)->nullable();        // Editorial, ciudad
				$table->integer('anho_publicacion')->nullable();    // Año de publicación
				$table->string('no_vol',50)->nullable();            // Número y volumen
				$table->string('coleccion',100)->nullable();        // Colección
				$table->string('colacion',50)->nullable();          // Colación
				$table->string('isbn',50)->nullable();              // ISBN o ISSN
				$table->string('serie',50)->nullable();             // Serie
				$table->string('no_clasificacion',50)->nullable();  // Número de clasificación
				$table->string('titulo_revista')->nullable();       // Título de la revista
				$table->text('resumen')->nullable();                // Resumen del documento
				$table->string('caratula_url')->nullable();         // Ubicación de la caratula
            	$table->string('usuario')->nullable();              // Nombre o email del usuario que creo el documento
				$table->timestamps();
			});
		}
		≡
1. Establecer permisos en los seeders para el CRUD Biblioteca en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡        
			Permission::create(['name' => 'crud.libraries.index'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.libraries.create'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.libraries.edit'])->syncRoles($rolAdministrador,$rolGenealogista,$rolDocumentalista);
			Permission::create(['name' => 'crud.libraries.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed
1. Configurar modelo **Library** en **app\Models\Library.php**
	>
		≡
		class Library extends Model
		{
			use HasFactory;

			protected $fillable = [
				'documento',
				'formato',
				'tipo',
				'fuente',
				'origen',
				'ubicacion',
				'ubicacion_ant',
				'busqueda',
				'notas',
				'enlace',
				'anho_ini', 
				'anho_fin',
				'pais',
				'ciudad',
				'FIncorporacion',
				'responsabilidad',
				'edicion',
				'editorial',
				'anho_publicacion',
				'no_vol',
				'coleccion',
				'colacion',
				'isbn',
				'serie',
				'no_clasificacion',
				'titulo_revista',
				'resumen',
				'caratula_url',
        		'usuario'
			];
		}
1. Agregar ruta de paises al grupo de rutas CRUD:
	>
		Route::resource('libraries', LibraryController::class)->names('libraries')
				->middleware('can:crud.libraries.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\LibraryController;
1. Crear componente Livewire para Tabla Libraries: 
	>
		$ php artisan make:livewire crud/libraries-table
1. Programar controlador para la tabla Libraries: **app\Http\Livewire\Crud\LibrariesTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Libraries: **resources\views\livewire\crud\libraries-table.blade.php**
	>
		≡
		≡
1. Programar controlador Library: **app\Http\Controllers\LibraryController.php**
	>
		≡
		≡
1. Diseñar las vistas para el CRUD Paises:
	- resources\views\crud\libraries\index.blade.php
		>
			≡
			≡
	- resources\views\crud\libraries\create.blade.php
		>
			≡
			≡
	- resources\views\crud\libraries\edit.blade.php
		>
			≡
			≡


1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Biblioteca.
	>
		≡
		≡


	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Biblioteca"


******************************************************

## Componentes de formularios modales para vistas de árboles
1. Creación de **resources\views\components\editar-persona.blade.php** para la edición de personas en el árbol.
	>
		***
1. Creación de **resources\views\components\ver-doc.blade.php** para ver documentos cargados.
	>
		***
1. Creación de **resources\views\components\cargar-doc.blade.php** para la carga de documentos.
	>
		***


## Vista árbol genealógico: **Albero**
1. Crear controlador Albero:
	>
		$ php artisan make:controller AlberoController
1. Programar controlador Albero en **app\Http\Controllers\AlberoController.php**
	>
		≡
		≡
1. Crear la vista Albero **resources\views\arboles\arbelo.blade.php**
	>
		≡
		≡
1. Crear grupo de rutas para las vistas de árboles y agregar la ruta para la vista Albero.
	>
		// Grupo de rutas para vistas de árboles genealógicos
		Route::group(['middleware' => ['auth'], 'as' => 'arboles.'], function(){
			Route::get('albero', [AlberoController::class, 'arbelo'])->name('albero.index')
				->middleware('can:genealogista');
		});
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\AlberoController;
1. Crear componente Livewire para la vista Albero: 
	>
		$ php artisan make:livewire vistas/arbol/albero-vista
1. Programar controlador para la vista Albero: **app\Http\Livewire\Vistas\Arbol\AlberoVista.php**
	>
		≡
		≡
1. Crear archivo de estilo para diagramar el árbol en **public\css\arbelo.css**
	>
		≡
		≡
1. Diseñar vista livewire para la tabla Albero: **resources\views\livewire\crud\arbelo-table.blade.php**
	>
		≡
		≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Vista Arbelo"

## ___________________________________________________________________


## Creación de enlaces simbólicos (symbolic link)
1. Crear enlace simbólico en Windows 10
	+ Ejecutar **C:\Windows\System32\cmd.exe como administrador**
	+ $ Mklink/D C:\xampp\htdocs\sefar\public\doc C:\xampp\htdocs\universalsefar.com\documentos
	+ $ Mklink/D C:\xampp\htdocs\sefar\storage\app\public\doc C:\xampp\htdocs\universalsefar.com\documentos 
		###### Mklink /D "ruta donde queremos crear el enlace" "ruta de origen de archivos"
1. Crear enlace simbólico en el hosting
	+ En el cPanel ir a **Trabajos de cron**.
	+ Ubicarse en **Añadir nuevo trabajo de cron** y luego **Configuración común**, y seleccionar **Una vez por mínuto(* * * * *)**.
	+ En **Comando:** escribir:
		* ln -s /home/pxvim6av41qx/public_html/documentos /home/pxvim6av41qx/public_html/app.universalsefar.com/public/doc
	+ Presionar **Añadir nuevo trabajo de cron** y esperar a que se ejecute la tarea.
	+ Borrar tarea una vez creado el enlace en **Trabajos de cron actuales**.
	+ Repetir el procedimiento pero ahora para:
		* ln -s /home/pxvim6av41qx/public_html/documentos /home/pxvim6av41qx/public_html/app.universalsefar.com/storage/app/public/doc



## ___________________________________________________________________


## Vista árbol genealógico: **Horizontal**
1. Crear controlador Tree:
	>
		$ php artisan make:controller TreeController
1. Programar controlador Tree en **app\Http\Controllers\TreeController.php**
	>
		≡
		≡
1. Crear la vista Tree **resources\views\arboles\tree.blade.php**
	>
		≡
		≡
1. Agregar la ruta para la vista Tree en el grupo de rutas de árboles.
	>
		// Grupo de rutas para vistas de árboles genealógicos
		Route::group(['middleware' => ['auth'], 'as' => 'arboles.'], function(){
			≡
			Route::get('tree/{IDCliente}', [TreeController::class, 'tree'])->name('tree.index')
				->middleware('can:genealogista');
		});
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\TreeController;
1. Crear componente Livewire para la vista Tree: 
	>
		$ php artisan make:livewire vistas/arbol/tree-vista
1. Programar controlador para la vista Tree: **app\Http\Livewire\Vistas\Arbol\TreeVista.php**
	>
		≡
		≡
1. Crear archivo de estilo para diagramar el árbol en **public\css\tree.css**
	>
		≡
		≡
1. Diseñar vista livewire para la tabla Trees: **resources\views\livewire\vistas\arbol\tree-vista.blade.php**
	>
		≡
		≡	

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Vista Tree"


## ___________________________________________________________________


## Vista árbol genealógico: **Vertical**
1. Crear controlador Olivo:
	>
		$ php artisan make:controller OlivoController
1. Programar controlador Tree en **app\Http\Controllers\OlivoController.php**
	>
		≡
		≡
1. Crear la vista Olivo **resources\views\arboles\olivo.blade.php**
	>
		≡
		≡
1. Agregar la ruta para la vista Olivo en el grupo de rutas de árboles.
	>
		// Grupo de rutas para vistas de árboles genealógicos
		Route::group(['middleware' => ['auth'], 'as' => 'arboles.'], function(){
			≡
			Route::get('olivo/{IDCliente}', [OlivoController::class, 'olivo'])->name('olivo.index')
				->middleware('can:genealogista');
		});
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\OlivoController;
1. Crear componente Livewire para la vista Olivo: 
	>
		$ php artisan make:livewire vistas/arbol/olivo-vista
1. Programar controlador para la vista Olivo: **app\Http\Livewire\Vistas\Arbol\OlivoVista.php**
	>
		≡
		≡
1. Crear archivo de estilo para diagramar el árbol en **public\css\olivo.css**
	>
		≡
		≡
1. Diseñar vista livewire para la tabla Olivos: **resources\views\livewire\vistas\arbol\olivo-vista.blade.php**
	>
		≡
		≡	

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Vista Olivo"


*********************************************************
	
## CRUD Agclientes
1. Crear modelo Agcliente junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Agcliente -m -c -r
1. Preparar migración para la tabla **agclientes** en **database\migrations\2021_03_23_020633_create_agclientes_table.php**
	>
		≡
		≡
1. Establecer permisos en los seeders para el CRUD Paises en **database\seeders\RoleSeeder.php**
	>   
		≡ 
		public function run()
		{
			≡
			Permission::create(['name' => 'crud.agclientes.index'])->syncRoles($rolAdministrador, $rolGenealogista, $rolCliente);
			Permission::create(['name' => 'crud.agclientes.create'])->syncRoles($rolAdministrador, $rolGenealogista, $rolCliente);
			Permission::create(['name' => 'crud.agclientes.edit'])->syncRoles($rolAdministrador, $rolGenealogista, $rolCliente);
			Permission::create(['name' => 'crud.agclientes.destroy'])->syncRoles($rolAdministrador);
			≡
		}
		≡
1. Reestablecer base de datos: 
	>
		$ php artisan migrate:fresh --seed	
1. Configurar modelo **Agcliente** en **app\Models\Agcliente.php**
	>
		≡
		class Country extends Model
		{
			use HasFactory;

			protected $fillable = [
				'IDCliente',
				'IDPersona',
				'IDPadre',
				'IDMadre',
				'Generacion',
				'Nombres',
				'Apellidos',
				'NPasaporte',
				'PaisPasaporte',
				'NDocIdent',
				'PaisDocIdent',
				'Sexo',
				'AnhoNac',
				'MesNac',
				'DiaNac',
				'LugarNac',
				'PaisNac',
				'AnhoBtzo',
				'MesBtzo',
				'DiaBtzo',
				'LugarBtzo',
				'PaisBtzo',
				'AnhoMatr',
				'MesMatr',
				'DiaMatr',
				'LugarMatr',
				'PaisMatr',
				'AnhoDef',
				'MesDef',
				'DiaDef',
				'LugarDef',
				'PaisDef',
				'Vive',
				'Observaciones',
				'Familiaridad',
				'NombresF',
				'ApellidosF',
				'ParentescoF',
				'NPasaporteF',
				'FRegistro',
				'PNacimiento',
				'LNacimiento',
				'Familiares',
				'Enlace',
				'referido',
				'FTM',
				'FUpdate',
				'Usuario',
			];
		}
1. Agregar ruta de agclientes al grupo de rutas CRUD:
	>
		Route::resource('agclientes', AgclienteController::class)->names('agclientes')
				->middleware('can:crud.agclientes.index');
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\AgclienteController;
1. Crear componente Livewire para Tabla Agclientes: 
	>
		$ php artisan make:livewire crud/agclientes-table
1. Programar controlador para la tabla Agclientes: **app\Http\Livewire\Crud\AgclientesTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla Agcliente: **resources\views\livewire\crud\agclientes-table.blade.php**
	>
		≡
		≡
1. Programar controlador Agcliente: **app\Http\Controllers\AgclienteController.php**
	>
		≡
		≡	
1. Diseñar las vistas para el CRUD Agclientes:
	- resources\views\crud\agclientes\index.blade.php
		>
			≡
			≡
	- resources\views\crud\agclientes\create.blade.php
		>
			≡
			≡
	- resources\views\crud\agclientes\edit.blade.php
		>
			≡
			≡
1. Editar **config\adminlte.php** para añadir los menú para ingresar al CRUD Agclientes.
	>
		≡
		≡


	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Agclientes"

## ___________________________________________________________________


## Seeders para prueba de agclientes
1. Crear seeder para agclientes: 
	>
		$ php artisan make:seeder AgclienteSeeder
1. Añadir a cabecera de **database\seeders\CountrySeeder.php**
	>
		use App\Models\Agcliente;
1. Modificar el método **run** de **database\seeders\AgclienteSeeder.php**
	>
		≡
		≡
1. Añadir al método run de **database\seeders\DatabaseSeeder.php**
	>
		public function run()
		{
			≡
			$this->call(AgclienteSeeder::class);
		}
1. Crear directorio **storage\app\public\imagenes\paises** y guardar la imagenes de los paises iniciales en formato png y en baja resolución.
1. Ejecutar: 
	>
		$ php artisan migrate:fresh --seed
	##### **Nota**: Para correr los seeder sin resetear la base de datos:
	+ Ejecutar: 
	>
		$ php artisan db:seed

	### Commit 15:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Paises"


## ___________________________________________________________________


## Consultar BD Onidex
1. Agregar las variables de entorno para la conexión a la base de datos **onidex** en **.env**
	>
		≡
		ONIDEX_CONNECTION=mysql
		ONIDEX_HOST=127.0.0.1
		ONIDEX_PORT=3306
		ONIDEX_DATABASE=onidex
		ONIDEX_USERNAME=root
		ONIDEX_PASSWORD=
		≡

1. Agregar a **config\database.php** los parámetros de conexión a la base de datos **onidex**
	>
		≡
	   'connections' => [
			≡
			'onidex' => [
				'driver' => 'mysql',
				'url' => env('DATABASE_URL'),
				'host' => env('ONIDEX_HOST', '127.0.0.1'),
				'port' => env('ONIDEX_PORT', '3306'),
				'database' => env('ONIDEX_DATABASE', 'forge'),
				'username' => env('ONIDEX_USERNAME', 'forge'),
				'password' => env('ONIDEX_PASSWORD', ''),
				'unix_socket' => env('ONIDEX_SOCKET', ''),
				'charset' => 'utf8',
				'collation' => 'utf8_general_ci',
				'prefix' => '',
				'prefix_indexes' => true,
				'strict' => true,
				'engine' => null,
				'options' => extension_loaded('pdo_mysql') ? array_filter([
					PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
				]) : [],
			],		   
			≡
		],
		≡
	##### **Nota**: No se crearan migraciones debido a que solo se realizaran consultas a una base de datos existente y externa para nuestro proyecto. 
	##### Para consultar conexiones múltiples en Laravel: https://styde.net/multiples-bases-de-datos-con-laravel-5-y-eloquent
1. Crear modelo Onidex:
	>
		$ php artisan make:model Onidex
1. Programar modelo Onidex: **app\Models\Onidex.php**
	>
		<?php

		namespace App\Models;

		use Illuminate\Database\Eloquent\Factories\HasFactory;
		use Illuminate\Database\Eloquent\Model;

		class Onidex extends Model
		{
			use HasFactory;

			/**
			* The database connection used by the model.
			*
			* @var string
			*/
			protected $connection = 'onidex';

			/**
			* The database table used by the model.
			*
			* @var string
			*/
			protected $table = 'onidexes';
		}
1. Crear controlador Onidex:
	>
		$ php artisan make:controller OnidexController
1. Programar controlador Onidex: app\Http\Controllers\OnidexController.php
	>
		≡
		≡
1. Crear componente Livewire para Tabla **onidexes**: 
	>
		$ php artisan make:livewire consulta/onidexes-table
1. Programar controlador para la tabla **onidexes**: **app\Http\Livewire\Consulta\OnidexesTable.php**
	>
		≡
		≡
1. Diseñar vista para la tabla **onidexes**: **resources\views\livewire\crud\users-table.blade.php**
	>
		≡
		≡
1. Crear y diseñar vista principal Onidex: **resources\views\consultas\onidex\index.blade.php**
	>
		≡
		≡
1. Crear grupo de rutas para consultas en **routes\web.php**
	>
		// Grupo de rutas para Consultas a base de datos
		Route::group(['middleware' => ['auth'], 'as' => 'consultas.'], function(){
		});
1. Agregar rutas **onidex.index** y **onidex.show** en el grupo de rutas para consultas en **routes\web.php**
	>
		Route::get('consultaodx', [OnidexController::class, 'index'])->name('onidex.index')
				->middleware('can:consultas.onidex.index');
		Route::post('consultaodx', [OnidexController::class, 'show'])->name('onidex.show')
        		->middleware('can:consultas.onidex.show');	
	##### Nota: añadir a la cabecera:
	>
		use App\Http\Controllers\OnidexController;
1. Crear y diseñar vista para mostrar registros Onidex: **resources\views\consultas\onidex\show.blade.php**
	>
		≡
		≡

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "App Consulta BD Onidex"

## ___________________________________________________________________


## Registro de clientes
1. Crear controlador para capturar parámetros GET:
	+ $ php artisan make:controller GetController
2. Archivo de prueba (resources\views\pruebas\registro.blade.php) para el traspaso de cliente de **JotForm** a **app.universalsefar.com**:
	```php
	@extends('adminlte::page')

	@section('title', 'Prueba Agclientes')

	@section('content_header')
		<h1>Generar enlace para registrar cliente</h1>
	@stop

	@section('content')
	<x-app-layout>
		<form action="{{ route('test.capturar_parametros_get') }}" method ="GET">
			<div class="shadow overflow-hidden sm:rounded-md">
				<div class="container">
					<p class="my-2 ml-2 text-bold text-blue-600">Datos Clientes:</p>
					<div class="md:flex ms:flex-wrap">
						<div class="px-1 py-2 m-2 flex-1">    {{-- pasaporte --}}
							<div>
								<label for="pasaporte" class="block text-sm font-medium text-gray-700">Pasaporte</label>
								<input value="1234567{{-- {{ old('pasaporte', $pasaporte) }} --}}" type="text" name="pasaporte" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
							</div>
						</div>

						<div class="px-1 py-2 m-2 flex-1">    {{-- nombres --}}
							<div>
								<label for="nombres" class="block text-sm font-medium text-gray-700">Nombres</label>
								<input value="Fulanito{{-- {{ old('nombres') }} --}}" type="text" name="nombres" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
							</div>
						</div>

						<div class="px-1 py-2 m-2 flex-1">    {{-- apellidos --}}
							<div>
								<label for="apellidos" class="block text-sm font-medium text-gray-700">Apellidos</label>
								<input value="Detal y Borrar{{-- {{ old('apellidos') }} --}}" type="text" name="apellidos" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
							</div>
						</div>

						<div class="px-1 py-2 m-2 flex-1">    {{-- email --}}
							<div>
								<label for="email" class="block text-sm font-medium text-gray-700">e-mail</label>
								<input value="delete.borrar@gmail.com{{-- {{ old('email') }} --}}" type="email" name="email" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
							</div>
						</div>
					</div>
					<div class="md:flex ms:flex-wrap">
						<div class="px-1 py-2 m-2 flex">    {{-- fnacimiento --}}
							<div>
								<label for="fnacimiento" class="block text-sm font-medium text-gray-700" title="Fecha de registro">Fecha de nacimiento</label>
								<input value="1977-11-03{{-- {{ old('fnacimiento') }} --}}" type="date" name="fnacimiento" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
							</div>
						</div>

						<div class="px-1 py-2 m-2 flex-1">    {{-- cnacimiento --}}
							<div>
								<label for="cnacimiento" class="block text-sm font-medium text-gray-700">Ciudad de nacimiento</label>
								<input value="Punto Fijo{{-- {{ old('cnacimiento') }} --}}" type="text" name="cnacimiento" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
							</div>
						</div>

						<div class="px-1 py-2 m-2 flex-1">    {{-- pnacimiento --}}
							<div>
								<label for="pnacimiento" class="block text-sm font-medium text-gray-700" title="País de nacimiento">País Nac.</label>
								<select name="pnacimiento" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
									<option></option>
									@foreach ($countries as $country)
										@if ('Venezuela'/* old('pnacimiento') */ == $country->pais)
											<option selected>{{ $country->pais }}</option>
										@else
											<option>{{ $country->pais }}</option> 
										@endif
									@endforeach
								</select>
							</div>
						</div>

						<div class="px-1 py-2 m-2 flex-1">    {{-- sexo --}}
							<div>
								<label for="sexo" class="block text-sm font-medium text-gray-700" title="Sexo">Sexo</label>
								<select name="sexo" autocomplete="on" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
									<option></option>
									@if ("F"/* old('sexo') */ == "M")
										<option title="Masculino" selected>M</option>
									@else
										<option title="Masculino">M</option>
									@endif
									
									@if ("F"/* old('sexo') */ == "F")
										<option title="Masculino" selected>F</option>
									@else
										<option title="Masculino">F</option>
									@endif
								</select>
							</div>
						</div>
					</div>

					<p class="my-2 ml-2 text-bold text-blue-800">Datos Familiar:</p>
					<div class="md:flex ms:flex-wrap">
						<div class="px-1 py-2 m-2 flex">    {{-- pasaporte_f --}}
							<div>
								<label for="pasaporte_f" class="block text-sm font-medium text-gray-700">Pasaporte del familiar</label>
								<input value="5555555{{-- {{ old('pasaporte_f', $pasaporte_f) }} --}}" type="text" name="pasaporte_f" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
							</div>
						</div>

						<div class="px-1 py-2 m-2 flex-1">    {{-- nombre_f --}}
							<div>
								<label for="nombre_f" class="block text-sm font-medium text-gray-700">Nombres y apellidos del familiar</label>
								<input value="Perensejo Borrar{{-- {{ old('nombre_f') }} --}}" type="text" name="nombre_f" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
							</div>
						</div>
					</div>
				</div>
				<div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
					<button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
						Generar URL
					</button>
				</div>
			</div>
		</form>
	</x-app-layout>
	@stop

	@section('css')
		<link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
	@stop

	@section('js')

	@stop
	```
1. Crear rutas para la prueba de registros de clientes y captura de parámetros get en el grupo para realizar pruebas del archivo de rutas **routes\web.php**:
	```php
	// Grupo de rutas para realizar pruebas
	Route::group(['middleware' => ['auth'], 'as' => 'test.'], function(){
		≡
		// Generar enlaces para registrar clientes
		Route::get('registro', [App\Http\Controllers\GetController::class, 'registro'])->name('registro')->middleware('can:administrador');

		// Capturar parámetros get 
		Route::get('capturar_parametros_get', [App\Http\Controllers\GetController::class, 'capturar_parametros_get'])->name('capturar_parametros_get')->middleware('can:administrador');
	});
	```
1. Programar controlador **app\Http\Controllers\GetController.php**:
	```php
	<?php

	namespace App\Http\Controllers;

	use App\Models\Country;
	use Illuminate\Http\Request;
	use RealRashid\SweetAlert\Facades\Alert;

	class GetController extends Controller
	{
		public function registro(){
			$countries = Country::all();
			return view('pruebas.registro', compact('countries'));
		}

		public function capturar_parametros_get(Request $request){
			$parametros = substr($request->fullUrl(), 41);
			Alert::info('Enlaces para registrar cliente', '
				<small>
					<p><strong>http://sefar.test/register</strong>'.$parametros.'</p>
					<br><hr><br>
					<p><strong>https://app.universalsefar.com/register</strong>'.$parametros.'</p>
				</small>'
			)->toHtml()->persistent(true);
			return back();
		}
	}
	```
1. Modificar el archivo de configuración **config\adminlte.php** para incluir ruta:
	```php
	<?php

	return [
		≡
		'menu' => [
			≡
			/* *** PRUEBAS *** */
			[
				'text'        => 'Pruebas',
				'icon'        => 'fas fa-grimace',
				'icon_color'  => 'yellow',
				'can'  => 'administrador',
				'submenu' => [
					≡
					[
						'text'          => 'Enlace para registro',
						'icon'          => 'fas fa-user-circle',
						'icon_color'    => 'green',
						'url'           => 'registro',
						'can'           => 'administrador',
					],
				],
			],
			≡
		],
		≡
	];
	```
1. Modificar vista **resources\views\auth\register.blade.php**:
	```php
	***
	```
1. Modificar método create del controlador **app\Actions\Fortify\CreateNewUser.php**:
	```php
	***
	```
	Incluir la biblioteca:
	```php
	use RealRashid\SweetAlert\Facades\Alert;
	```
1. Agregar el campo **email_verified_at** a la variable $fillable del modelo **User** app\Models\User.php
	>
		protected $fillable = [
			'name',
			'email',
			'password',
			'password_md5',
			'passport',
			'email_verified_at',
		];
1. Adaptar controlador **app\Http\Controllers\Controller.php** para administrar la vista de clientes:
	```php
	***
	```
1. Configurar **config\adminlte.php** para el menú de los clientes:
	```php
	***
	```
1. Crear controlador para administrar las vistas de clientes:
	+ $ php artisan make:controller ClienteController
1. Crear grupo de rutas para vistas de clientes en **routes\web.php**:
	```php
	***
	```
1. Modificar la vista resources\views\livewire\vistas\arbol\tree-vista.blade.php:
	```php
	≡
	<div class="container overflow-x-scroll">
		<div class="flex justify-between">
			<div class="px-4 py-2 m-2">
				{{-- ÁRBOL EXPANDIDO O COMPACTO --}}
				<div class="text-left">
					<label for="Modo" class="px-3 block text-sm font-medium text-gray-700" title="Indicar línea genealógica">Modo</label>
					<select wire:model="Modo" name="Modo"class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
						<option value="0">Expandido</option>
						<option value="1">Compacto</option>
					</select>
				</div>
			</div>
			@can('genealogista')
			<div class="px-4 py-2 m-2">
				{{-- FAMILIARES --}}
				<div class="justify-center">
					<label for="Familiares" class="px-3 block text-sm font-medium text-gray-700" title="Familiares en el proceso">Familiares</label>
					<select wire:model="IDFamiliar" style="width:450px" name="Familiares" class="w-44 mt-1 block py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
						<option value="{{ null }}">-</option>
						@foreach ($families as $family)
							<option value="{{ $family->IDFamiliar }}">{{ $family->Familiar.' - '.$family->Parentesco }}</option>
						@endforeach
					</select>
					@if($IDFamiliar)
					<div class="pt-2">
						<div class="px-4 py-3 bg-gray-50 text-left sm:px-6">
							<a href="{{ route('arboles.tree.index', $IDFamiliar) }}" target="_blank" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
								Ir a familiar seleccionado
							</a>
						</div>
					</div>
					@endif
				</div>
			</div>
			@endcan
		</div>
	</div>
	≡
	```
1. Crear un **maillable** para notificar registros y carga de árbol tanto al cliente como a Sefar:
	+ $ php artisan make:mail RegistroCliente
	+ $ php artisan make:mail RegistroSefar
    + $ php artisan make:mail CargaCliente
	+ $ php artisan make:mail CargaSefar
1. Programar el método **build** de los archivos controladores de los maillables:
	+ **app\Mail\CargaCliente.php**:
		```php
		***
		```
	+ **app\Mail\CargaSefar.php**:
		```php
		***
		```
	+ **app\Mail\RegistroCliente.php**:
		```php
		***
		```
	+ **app\Mail\RegistroSefar.php**:
		```php
		***
		```
	En todos los archivos incluir en la clase la variable publica:
		```php
		public $user;
		```	
	En todos los archivos programar el método **__construct**:
		```php
		public function __construct(User $user)
		{
			$this->user = $user;
		}	
		```
	En todos los archivos importar el modelo **User**:
		```php
		use App\Models\User;
		```
1. Diseñar las vistas respectivas para los correos de notificación:
	+ **resources\views\mail\carga-cliente.blade.php**:
		```php
		***
		```
	+ **resources\views\mail\carga-sefar.blade.php**:
		```php
		***
		```
	+ **resources\views\mail\registro-cliente.blade.php**:
		```php
		***
		```
	+ **resources\views\mail\registro-sefar.blade.php**:
		```php
		***
		```
1. Rediseñar la vista **resources\views\inicio.blade.php**:
	```php
	***
	```
1. Crear método **procesar** en el controlador **app\Http\Controllers\ClienteController.php**:
	```php
	***
	```

	### Commit --:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Carga de clientes"


## Módulo Traviesoevans
###### traviesoevans@gmail.com / sefar2021
**Tutorial**: https://www.youtube.com/watch?v=Z8Oh2O6GueQ
1. Crear rol **Traviesoevans**.
2. Otorgarle los siguientes permisos al rol **Traviesoevans**:
	+ genealogista
	+ crud.agclientes.index
	+ crud.agclientes.create
	+ crud.agclientes.edit
	+ crud.agclientes.destroy
	+ crud.files.index
	+ crud.files.create
	+ crud.files.edit
3. Modificar modelo **app\Models\Agcliente.php**:
	```php
	≡
	class Agcliente extends Model
	{
		≡
		// Filtro de búsqueda
		public function scopeBuscar($query, $search){
			≡
		}

		// Filtro para clientes referidos
		public function scopeRol($query){
        	// Clientes con el rol Traviesoevans
			if(Auth()->user()->hasRole('Traviesoevans')){
				return $query->where('referido','Travieso Evans');
			}
		}

		// Filtro para ver solo clientes
		public function scopeClientes($query, $solo_clientes){
			≡
		}
	}
	```
4. Modificar controlador **app\Http\Controllers\TreeController.php**:
	```php
	≡
	class TreeController extends Controller
	{
		public function tree($IDCliente){
			// Si el usuario tiene el rol Traviesoevans
			if(Auth()->user()->hasRole('Traviesoevans')){
				$autorizado = Agcliente::where('referido','LIKE','Travieso Evans')
					->where('IDCliente','LIKE',$IDCliente)
					->count();
				if($autorizado == 0){
					return view('crud.agclientes.index');
				}
			}
			$existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
			≡
		}
	}
	```
5. Modificar el método **store** del controlador **app\Http\Controllers\AgclienteController.php**:
	```php
    public function store(Request $request)
    {
        ≡
        $referido = Auth()->user()->getRoleNames()[0];
        if($referido == "Traviesoevans"){
            $referido = "Travieso Evans";
        }
        // Creando persona en agcliente
        ≡
    }
	```
6. Modificar el método **index** del conrolador **app\Http\Controllers\Controller.php**:
	```php
    public function index(){
        ≡
        if(Auth::user()->hasRole('Documentalista')){
            return view('crud.miscelaneos.index');
        }

        if(Auth::user()->hasRole('Traviesoevans')){
            return view('crud.agclientes.index');
        }
        ≡
    }


## Módulo Patricia Vargas Sequera
1. Crear rol **Vargassequera**.
2. Otorgarle los siguientes permisos al rol **Vargassequera**:
	+ genealogista
	+ crud.agclientes.index
	+ crud.agclientes.create
	+ crud.agclientes.edit
	+ crud.agclientes.destroy
	+ crud.files.index
	+ crud.files.create
	+ crud.files.edit
3. Modificar modelo **app\Models\Agcliente.php**:
	```php
	≡
	class Agcliente extends Model
	{
		≡
		// Filtro de búsqueda
		public function scopeBuscar($query, $search){
			≡
		}

		// Filtro para clientes referidos
		public function scopeRol($query){
        	// Clientes con el rol Traviesoevans
			≡
			// Clientes con el rol Vargassequera
			if(Auth()->user()->hasRole('Vargassequera')){
				return $query->where('referido','Patricia Vargas Sequera');
			}
		}

		// Filtro para ver solo clientes
		public function scopeClientes($query, $solo_clientes){
			≡
		}
	}
	```
4. Modificar controlador **app\Http\Controllers\TreeController.php**:
	```php
	≡
	class TreeController extends Controller
	{
		public function tree($IDCliente){
			// Si el usuario tiene el rol Traviesoevans
			≡
			// Si el usuario tiene el rol Vargassequera
			if(Auth()->user()->hasRole('Vargassequera')){
				$autorizado = Agcliente::where('referido','LIKE','Patricia Vargas Sequera')
					->where('IDCliente','LIKE',$IDCliente)
					->count();
				if($autorizado == 0){
					return view('crud.agclientes.index');
				}
			}
			$existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
			≡
		}
	}
	```
5. Modificar el método **store** del controlador **app\Http\Controllers\AgclienteController.php**:
	```php
    public function store(Request $request)
    {
        ≡
        $referido = Auth()->user()->getRoleNames()[0];
        if($referido == "Traviesoevans"){
            $referido = "Travieso Evans";
        }
        if($referido == "Vargassequera"){
            $referido = "Patricia Vargas Sequera";
        }
        // Creando persona en agcliente
        ≡
    }
	```
6. Modificar el método **index** del conrolador **app\Http\Controllers\Controller.php**:
	```php
    public function index(){
        ≡
        if(Auth::user()->hasRole('Traviesoevans')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Vargassequera')){
            return view('crud.agclientes.index');
        }
        ≡
    }
	```
	
## Módulo Badell Law
1. Crear rol **BadellLaw**.
2. Otorgarle los siguientes permisos al rol **BadellLaw**:
	+ genealogista
	+ crud.agclientes.index
	+ crud.agclientes.create
	+ crud.agclientes.edit
	+ crud.agclientes.destroy
	+ crud.files.index
	+ crud.files.create
	+ crud.files.edit
3. Modificar modelo **app\Models\Agcliente.php**:
	```php
	≡
	class Agcliente extends Model
	{
		≡
		// Filtro de búsqueda
		public function scopeBuscar($query, $search){
			≡
		}

		// Filtro para clientes referidos
		public function scopeRol($query){
        	// Clientes con el rol Traviesoevans
			≡
			// Clientes con el rol Badell Law
			if(Auth()->user()->hasRole('BadellLaw')){
				return $query->where('referido','Badell Law');
			}
		}

		// Filtro para ver solo clientes
		public function scopeClientes($query, $solo_clientes){
			≡
		}
	}
	```
4. Modificar controlador **app\Http\Controllers\TreeController.php**:
	```php
	≡
	class TreeController extends Controller
	{
		public function tree($IDCliente){
			// Si el usuario tiene el rol Traviesoevans
			≡
			// Si el usuario tiene el rol BadellLaw
			if(Auth()->user()->hasRole('BadellLaw')){
				$autorizado = Agcliente::where('referido','LIKE','Badell Law')
					->where('IDCliente','LIKE',$IDCliente)
					->count();
				if($autorizado == 0){
					return view('crud.agclientes.index');
				}
			}

			$existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
			≡
		}
	}
	```
5. Modificar el método **store** del controlador **app\Http\Controllers\AgclienteController.php**:
	```php
    public function store(Request $request)
    {
        ≡
        if($referido == "Vargassequera"){
            $referido = "Patricia Vargas Sequera";
        }
        if($referido == "BadellLaw"){
            $referido = "Badell Law";
        }

        // Creando persona en agcliente
        ≡
    }
	```
6. Modificar el método **index** del conrolador **app\Http\Controllers\Controller.php**:
	```php
    public function index(){
        ≡
        if(Auth::user()->hasRole('Vargassequera')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('BadellLaw')){
            return view('crud.agclientes.index');
        }
        ≡
    }
	```

## Módulo P & V Abogados
1. Crear rol **P&V-Abogados**.
2. Otorgarle los siguientes permisos al rol **P&V-Abogados**:
	+ genealogista
	+ crud.agclientes.index
	+ crud.agclientes.create
	+ crud.agclientes.edit
	+ crud.agclientes.destroy
	+ crud.files.index
	+ crud.files.create
	+ crud.files.edit
3. Modificar modelo **app\Models\Agcliente.php**:
	```php
	≡
	class Agcliente extends Model
	{
		≡
		// Filtro de búsqueda
		public function scopeBuscar($query, $search){
			≡
		}

		// Filtro para clientes referidos
		public function scopeRol($query){
        	// Clientes con el rol Badell Law
			≡
			// Clientes con el rol P & V Abogados
			if(Auth()->user()->hasRole('P&V-Abogados')){
				return $query->where('referido','P & V Abogados');
			}
		}

		// Filtro para ver solo clientes
		public function scopeClientes($query, $solo_clientes){
			≡
		}
	}
	```
4. Modificar controlador **app\Http\Controllers\TreeController.php**:
	```php
	≡
	class TreeController extends Controller
	{
		public function tree($IDCliente){
			// Si el usuario tiene el rol BadellLaw
			≡
			// Si el usuario tiene el rol P&V-Abogados
			if(Auth()->user()->hasRole('P&V-Abogados')){
				$autorizado = Agcliente::where('referido','LIKE','P & V Abogados')
					->where('IDCliente','LIKE',$IDCliente)
					->count();
				if($autorizado == 0){
					return view('crud.agclientes.index');
				}
			}

			$existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
			≡
		}
	}
	```
5. Modificar el método **store** del controlador **app\Http\Controllers\AgclienteController.php**:
	```php
    public function store(Request $request)
    {
        ≡
        if($referido == "BadellLaw"){
            $referido = "Badell Law";
        }
        if($referido == "P&V-Abogados"){
            $referido = "P & V Abogados";
        }

        // Creando persona en agcliente
        ≡
    }
	```
6. Modificar el método **index** del conrolador **app\Http\Controllers\Controller.php**:
	```php
    public function index(){
        ≡
        if(Auth::user()->hasRole('BadellLaw')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('P&V-Abogados')){
            return view('crud.agclientes.index');
        }
        ≡
    }
	```
8. Modificar vista **resources\views\auth\register.blade.php**:
	```php
	<?php
		// Captura de parámetros del JotForm
		≡
		if (!empty($_GET['referido'])) $referido = $_GET['referido']; else $referido = null;

		if (trim($referido) == 'P'){
			$referido = 'P & V Abogados';
		}
		≡
	?>
	≡
	```
	+ **Nota**: solo para casos en que el parámetro **referido** proveniente de **JotForm** contenga el caracter '**&**'.
9. Subir cambios a GitHub
	+ $ git add .
	+ $ git commit -m "Módulo P & V Abogados"
	+ $ git push -u origin master

## Módulo Mujica & Coto Abogados
1. Crear rol **Mujica-Coto**.
2. Otorgarle los siguientes permisos al rol **Mujica-Coto**:
	+ genealogista
	+ crud.agclientes.index
	+ crud.agclientes.create
	+ crud.agclientes.edit
	+ crud.agclientes.destroy
	+ crud.files.index
	+ crud.files.create
	+ crud.files.edit
3. Modificar modelo **app\Models\Agcliente.php**:
	```php
	≡
	class Agcliente extends Model
	{
		≡
		// Filtro de búsqueda
		public function scopeBuscar($query, $search){
			≡
		}

		// Filtro para clientes referidos
		public function scopeRol($query){
			≡
        	// Clientes con el rol P & V Abogados
			≡
			// Clientes con el rol Mujica y Coto Abogados
			if(Auth()->user()->hasRole('Mujica-Coto')){
				return $query->where('referido','Mujica y Coto Abogados');
			}
		}

		// Filtro para ver solo clientes
		public function scopeClientes($query, $solo_clientes){
			≡
		}
	}
	```
4. Modificar controlador **app\Http\Controllers\TreeController.php**:
	```php
	≡
	class TreeController extends Controller
	{
		public function tree($IDCliente){
			≡
			// Si el usuario tiene el rol P&V-Abogados
			≡
			// Si el usuario tiene el rol Mujica-Coto
			if(Auth()->user()->hasRole('Mujica-Coto')){
				$autorizado = Agcliente::where('referido','LIKE','Mujica y Coto Abogados')
					->where('IDCliente','LIKE',$IDCliente)
					->count();
				if($autorizado == 0){
					return view('crud.agclientes.index');
				}
			}

			$existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
			≡
		}
	}
	```
5. Modificar el método **store** del controlador **app\Http\Controllers\AgclienteController.php**:
	```php
    public function store(Request $request)
    {
        ≡
        if($referido == "P&V-Abogados"){
            $referido = "P & V Abogados";
        }
        if($referido == "Mujica-Coto"){
            $referido = "Mujica y Coto Abogados";
        }

        // Creando persona en agcliente
        ≡
    }
	```
6. Modificar el método **index** del conrolador **app\Http\Controllers\Controller.php**:
	```php
    public function index(){
        ≡
        if(Auth::user()->hasRole('P&V-Abogados')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Mujica-Coto')){
            return view('crud.agclientes.index');
        }
        ≡
    }
	```
7. Subir cambios a GitHub
	+ $ git add .
	+ $ git commit -m "Módulo Mujica y Coto Abogados"
	+ $ git push -u origin master

## Módulo German Fleitas
1. Crear rol **German-Fleitas**.
2. Otorgarle los siguientes permisos al rol **German-Fleitas**:
	+ genealogista
	+ crud.agclientes.index
	+ crud.agclientes.create
	+ crud.agclientes.edit
	+ crud.agclientes.destroy
	+ crud.files.index
	+ crud.files.create
	+ crud.files.edit
3. Modificar modelo **app\Models\Agcliente.php**:
	```php
	≡
	// Filtro para clientes referidos
	public function scopeRol($query){
		≡
        // Clientes con el rol German Fleitas
        if(Auth()->user()->hasRole('German-Fleitas')){
            return $query->where('referido','German Fleitas');
        }
	}
	≡
	```
4. Modificar controlador **app\Http\Controllers\TreeController.php**:
	```php
	≡
	class TreeController extends Controller
	{
		public function tree($IDCliente){
			≡
			// Si el usuario tiene el rol Mujica-Coto
			≡
			// Si el usuario tiene el rol German Fleitas
			if(Auth()->user()->hasRole('German-Fleitas')){
				$autorizado = Agcliente::where('referido','LIKE','German Fleitas')
					->where('IDCliente','LIKE',$IDCliente)
					->count();
				if($autorizado == 0){
					return view('crud.agclientes.index');
				}
			}

			$existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
			≡
		}
	}
	```
5. Modificar el método **store** del controlador **app\Http\Controllers\AgclienteController.php**:
	```php
    public function store(Request $request)
    {
        ≡
        if($referido == "Mujica-Coto"){
            $referido = "Mujica y Coto Abogados";
        }

        // Creando persona en agcliente
        ≡
    }
	```
6. Modificar el método **index** del conrolador **app\Http\Controllers\Controller.php**:
	```php
    public function index(){
        ≡
        if(Auth::user()->hasRole('German-Fleitas')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Cliente')){
            $IDCliente = Auth::user()->passport;
            return view('arboles.tree', compact('IDCliente'));
        }
        ≡
    }
	```
7. Subir cambios a GitHub
	+ $ git add .
	+ $ git commit -m "Módulo German Fleitas"
	+ $ git push -u origin master

## Módulo Soma Consultores
1. Crear rol **Soma-Consultores**.
2. Otorgarle los siguientes permisos al rol **Soma-Consultores**:
	+ genealogista
	+ crud.agclientes.index
	+ crud.agclientes.create
	+ crud.agclientes.edit
	+ crud.agclientes.destroy
	+ crud.files.index
	+ crud.files.create
	+ crud.files.edit
3. Modificar modelo **app\Models\Agcliente.php**:
	```php
	≡
	// Filtro para clientes referidos
	public function scopeRol($query){
		≡
        // Clientes con el rol Soma Consultores
        if(Auth()->user()->hasRole('Soma-Consultores')){
            return $query->where('referido','Soma Consultores');
        }
	}
	≡
	```
4. Modificar controlador **app\Http\Controllers\TreeController.php**:
	```php
	≡
	class TreeController extends Controller
	{
		public function tree($IDCliente){
			≡
			// Si el usuario tiene el rol German Fleitas
			≡
			// Si el usuario tiene el rol Soma Consultores
			if(Auth()->user()->hasRole('Soma-Consultores')){
				$autorizado = Agcliente::where('referido','LIKE','Soma Consultores')
					->where('IDCliente','LIKE',$IDCliente)
					->count();
				if($autorizado == 0){
					return view('crud.agclientes.index');
				}
			}

			$existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
			≡
		}
	}
	```
5. Modificar el método **store** del controlador **app\Http\Controllers\AgclienteController.php**:
	```php
    public function store(Request $request)
    {
        ≡
        if($referido == "Mujica-Coto"){
            $referido = "Mujica y Coto Abogados";
        }

        // Creando persona en agcliente
        ≡
    }
	```
6. Modificar el método **index** del conrolador **app\Http\Controllers\Controller.php**:
	```php
    public function index(){
        ≡
        if(Auth::user()->hasRole('Soma-Consultores')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Cliente')){
            $IDCliente = Auth::user()->passport;
            return view('arboles.tree', compact('IDCliente'));
        }
        ≡
    }
	```
7. Subir cambios a GitHub
	+ $ git add .
	+ $ git commit -m "Módulo Soma Consultores"
	+ $ git push -u origin master


## Módulo MG Tours
1. Crear rol **MG-Tours**.
2. Otorgarle los siguientes permisos al rol **MG-Tours**:
	+ genealogista
	+ crud.agclientes.index
	+ crud.agclientes.create
	+ crud.agclientes.edit
	+ crud.agclientes.destroy
	+ crud.files.index
	+ crud.files.create
	+ crud.files.edit
3. Modificar modelo **app\Models\Agcliente.php**:
	```php
	≡
	// Filtro para clientes referidos
	public function scopeRol($query){
		≡
        // Clientes con el rol MG Tours
        if(Auth()->user()->hasRole('MG-Tours')){
            return $query->where('referido','MG Tours');
        }
	}
	≡
	```
4. Modificar controlador **app\Http\Controllers\TreeController.php**:
	```php
	≡
	class TreeController extends Controller
	{
		public function tree($IDCliente){
			≡
			// Si el usuario tiene el rol Soma Consultores
			≡
			// Si el usuario tiene el rol MG Tours
			if(Auth()->user()->hasRole('MG-Tours')){
				$autorizado = Agcliente::where('referido','LIKE','MG Tours')
					->where('IDCliente','LIKE',$IDCliente)
					->count();
				if($autorizado == 0){
					return view('crud.agclientes.index');
				}
			}

			$existe = Agcliente::where('IDCliente','LIKE',$IDCliente)->where('IDPersona',1)->get();
			≡
		}
	}
	```
5. Modificar el método **store** del controlador **app\Http\Controllers\AgclienteController.php**:
	```php
    public function store(Request $request)
    {
        ≡
        if($referido == "MG-Tours"){
            $referido = "MG Tours";
        }

        // Creando persona en agcliente
        ≡
    }
	```
6. Modificar el método **index** del conrolador **app\Http\Controllers\Controller.php**:
	```php
    public function index(){
        ≡
        if(Auth::user()->hasRole('MG-Tours')){
            return view('crud.agclientes.index');
        }

        if(Auth::user()->hasRole('Cliente')){
            $IDCliente = Auth::user()->passport;
            return view('arboles.tree', compact('IDCliente'));
        }
        ≡
    }
	```
7. Subir cambios a GitHub
	+ $ git add .
	+ $ git commit -m "Módulo MG Tours"
	+ $ git push -u origin master







	≡
	```php
	```

# **********************************************
## Pendientes:
1. Pendiente

## CRUD Clientes con Liveware
1. Pendiente


## CRUD Diex con Liveware
1. Pendiente

## CRUD Maisanta con Liveware

1. Crear vista Registro: resources\views\auth\registro.blade.php
	>
		***
		***
1. Crear controlador Registro: $ php artisan make:controller RegistroController
1. Crear ruta en routes\web.php

# ///////////////////////////////////////
## Crear rutas de mantenimiento de la aplicación
1. Agregar las siguientes rutas en **routes\web.php** para poder realizarle mantenimiento a la aplicación cuando se encuentre en producción:
	>
		// RUTAS PARA EL MANTENIMIENTO DE LA APLICACIÓN EN PRODUCCIÓN
		// Ruta para ejecutar en producción: $ php artisan key:generate
		Route::get('key-generate', function(){
			Artisan::call('key:generate');
		});

		// Ruta para ejecutar en producción: $ php artisan storage:link
		Route::get('storage-link', function(){
			Artisan::call('storage:link');
		});

		// Ruta para ejecutar en producción: $ php artisan config:cache
		Route::get('config-cache', function(){
			Artisan::call('config:cache');
		});

		// Ruta para ejecutar en producción: $ php artisan cache:clear
		Route::get('cache-clear', function(){
			Artisan::call('cache:clear');
		});

		// Ruta para ejecutar en producción: $ php artisan route:clear
		Route::get('route-clear', function(){
			Artisan::call('route:clear');
		});

		// Ruta para ejecutar en producción: $ php artisan config:clear
		Route::get('config-clear', function(){
			Artisan::call('config:clear');
		});

		// Ruta para ejecutar en producción: $ php artisan view:clear
		Route::get('view-clear', function(){
			Artisan::call('view:clear');
		});	

	### Commit XX:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Rutas para mantenimiento de la app"

## Subir proyecto local a GitHub
	##### https://github.com/
1. Ejecutar:
	> 
		$ npm run production
		$ composer dumpautoload
		$ php artisan key:generate
1. Creamos un nuevo repositorio **público** con el nombre **AppSefarUniversal** en la página de GitHub.
	##### Las opciones de **Initialize this repository with** las dejamos sin marcar.
1. Ejecutamos en local:
	>
		$ git add .
		$ git commit -m "Ajustes finales"
		$ git remote add origin https://github.com/petrix12/AppSefarUniversal.git
		$ git push -u origin master

## Configurar GitHub con el hosting de GoDaddy
1. Ingresar al cPanel con https://a2plcpnl0082.prod.iad2.secureserver.net:2083/
	###### pxvim6av41qx / Cisco2019!
1. En la sección **ARCHIVOS** ir a **Git™ Version Control**
1. Crear repositorio ingresando los siguientes parámetros:
	+ Clone URL: https://github.com/petrix12/AppSefarUniversal.git
	+ Repository Path: public_html/app.universalsefar.com
	+ Repository Name: AppSefarUniversal
1. Copiar del proyecto local a la carpeta del hosting **public_html/app.universalsefar.com** los siguientes directorios:
	+ node_modules
	+ public/storage
	+ vendor
1. Copiar y pegar el archivo **.env** del local al hosting.
1. Cambiar las siguientes variables de entorno al archivo **.env**
	>
		APP_NAME="App Sefar Universal"
		APP_ENV=production
		APP_KEY=base64:LsfuS5WhYfAe/FWDLdrzXFWacnFB4EgNIHBHo8ZzOSk=
		APP_DEBUG=false
		APP_URL=https://app.universalsefar.com

		DB_CONNECTION=mysql
		DB_HOST=127.0.0.1
		DB_PORT=3306
		DB_DATABASE=sefar
		DB_USERNAME=pxvim6av41qx
		DB_PASSWORD="L5=Rj#8lW}YuK"

		ONIDEX_CONNECTION=mysql
		ONIDEX_HOST=127.0.0.1
		ONIDEX_PORT=3306
		ONIDEX_DATABASE=onidex
		ONIDEX_USERNAME=pxvim6av41qx
		ONIDEX_PASSWORD="L5=Rj#8lW}YuK"

		MAIL_MAILER=smtp
		MAIL_HOST=universalsefar.com
		MAIL_PORT=587
		MAIL_USERNAME=app@universalsefar.com
		MAIL_PASSWORD=Madrid2021!
		MAIL_ENCRYPTION=null
		MAIL_FROM_ADDRESS=app@universalsefar.com
		MAIL_FROM_NAME="${APP_NAME}"

1. Para configurar Laravel (AppSefar) con Gmail (info@sefarvzla.com)
	>
		MAIL_MAILER=smtp
		MAIL_HOST=smtp.gmail.com
		MAIL_PORT=465
		MAIL_USERNAME=info@sefarvzla.com
		MAIL_PASSWORD=tmizoofcenmauman
		MAIL_ENCRYPTION=ssl
		MAIL_FROM_ADDRESS=info@sefarvzla.com
		MAIL_FROM_NAME="${APP_NAME}"

		MAIL_MAILER=smtp
		MAIL_HOST=smtp.gmail.com
		MAIL_PORT=465
		MAIL_USERNAME=info@sefarvzla.com
		MAIL_PASSWORD=tmizoofcenmauman
		MAIL_ENCRYPTION=ssl
		MAIL_FROM_ADDRESS=info@sefarvzla.com
		MAIL_FROM_NAME="${APP_NAME}"
		
	Luego direccionar
	>
		https://app.universalsefar.com/config-clear
1. Direccionar las siguientes rutas:
	>
		https://app.universalsefar.com/storage-link
	##### Esta acción simula la instrucción artisan **$ php artisan storage:link** para crear un enlace simbólico de public a storage. Verifique que no exista carpeta o acceso directo en **public** con el nombre **storage**, de ser así, elimínelo.
		https://app.universalsefar.com/config-cache
	##### Esta acción simula la instrucción artisan **php artisan config:cache** para borrar la caché de la configuración anterior.
	
	### **Nota**: De aquí en adelante, cada vez que se realicen cambios en local se deberán seguir los siguientes pasos para que se reflejen en producción:
	+ En local ejecutar:
		>
			$ git add .
			$ git commit -m "Descripción de los cambios"
			$ git push -u origin master
	+ En el cPanel:
		- Ingresar al cPanel con https://a2plcpnl0082.prod.iad2.secureserver.net:2083/
			###### pxvim6av41qx / Cisco2019!
		- En la sección **ARCHIVOS** ir a **Git™ Version Control**.
		- Administrar el repositorio **AppSefarUniversal**.
		- Ir a la pestaña **Pull or Deploy**.
		- Presionar el botón **Update from Remote**.


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	◄ ◄ ◄ ■ ■ ■ ► ► ►
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

## RUTAS **INICIALES**
>
	Method      URI                               	Name
	======		===								  	====
	GET|HEAD	| / 								| inicio
	GET|HEAD	| api/user 							|
	GET|HEAD	| dashboard                        	| dashboard  
	GET|HEAD 	| forgot-password                  	| password.request   
	POST     	| forgot-password                  	| password.email   
	GET|HEAD 	| livewire/livewire.js             	|                           
	GET|HEAD 	| livewire/livewire.js.map         	|    
	POST     	| livewire/message/{name}          	| livewire.message         
	GET|HEAD 	| livewire/preview-file/{filename} 	| livewire.preview-file    
	POST     	| livewire/upload-file             	| livewire.upload-file        
	POST     	| login                            	|                                 
	GET|HEAD 	| login                            	| login                       
	POST     	| logout                           	| logout                      
	GET|HEAD 	| register                         	| register                   
	POST     	| register                         	|                               
	POST     	| reset-password                   	| password.update             
	GET|HEAD 	| reset-password/{token}           	| password.reset                 
	GET|HEAD 	| sanctum/csrf-cookie              	|                                
	POST     	| two-factor-challenge             	|                           
	GET|HEAD 	| two-factor-challenge             	| two-factor.login            
	GET|HEAD 	| user/confirm-password            	| password.confirm            
	POST     	| user/confirm-password            	|                                 
	GET|HEAD 	| user/confirmed-password-status   	| password.confirmation           
	PUT      	| user/password                    	| user-password.update            
	GET|HEAD 	| user/profile                     	| profile.show                    
	PUT      	| user/profile-information         	| user-profile-information.update
	DELETE   	| user/two-factor-authentication   	|                                 
	POST     	| user/two-factor-authentication   	|                                 
	GET|HEAD 	| user/two-factor-qr-code          	|                                
	POST     	| user/two-factor-recovery-codes   	|                                 
	GET|HEAD 	| user/two-factor-recovery-codes   	|  

## RUTAS **PERMISOS**
>
	Method      URI                               	Name
	======		===								  	====
	GET|HEAD 	| permissions                      	| crud.permissions.index 
	POST     	| permissions                      	| crud.permissions.store
	GET|HEAD  	| permissions/create               	| crud.permissions.create 
	GET|HEAD  	| permissions/{permission}         	| crud.permissions.show
	PUT|PATCH 	| permissions/{permission}         	| crud.permissions.update
	DELETE    	| permissions/{permission}         	| crud.permissions.destroy 
	GET|HEAD  	| permissions/{permission}/edit    	| crud.permissions.edit

## RUTAS **ROLES**
>
	Method      URI                               	Name
	======		===								  	====
	GET|HEAD 	| roles                      		| crud.roles.index 
	POST     	| roles                      		| crud.roles.store
	GET|HEAD  	| roles/create               		| crud.roles.create 
	GET|HEAD  	| roles/{role}         				| crud.roles.show
	PUT|PATCH 	| roles/{role}         				| crud.roles.update
	DELETE    	| roles/{role}         				| crud.roles.destroy 
	GET|HEAD  	| roles/{role}/edit    				| crud.roles.edit
	 
## RUTAS **USUARIOS**
>
	Method      URI                               	Name
	======		===								  	====
	GET|HEAD 	| users                      		| crud.users.index 
	POST     	| users                      		| crud.users.store
	GET|HEAD  	| users/create               		| crud.users.create 
	GET|HEAD  	| users/{user}         				| crud.users.show
	PUT|PATCH 	| users/{user}         				| crud.users.update
	DELETE    	| users/{user}         				| crud.users.destroy 
	GET|HEAD  	| users/{user}/edit    				| crud.users.edit

## RUTAS **ONIDEX**

	Method      URI                               	Name
	======		===								  	====
	GET|HEAD  	| consultaodx                      	| consultas.onidex.index


# **Notas de interes**

## Regresar a un commit anterior
1. Ver historia de commit:
	>
		$ git log --pretty=oneline
1. Seleccionar el commit al cual queremos regresar:
	>
		$ git reset --hard <commit-id>

## Para limpiar el cache
1. Ejecutar:
	>
		$ php artisan config:cache 
		$ php artisan cache:clear

## Preparando proyecto para producción
1. Limpiar el caché de la Aplicación.
 	$ php artisan cache:clear 
2. Limpiar las rutas de la Aplicación.
 	$ php artisan route:clear  
3. Limpiar las configuraciones de la Aplicación.
 	$ php artisan config:clear 
4. Limpiar las vistas de la Aplicación.
 	$ php artisan view:clear

## Crear helper personalizado
1. Crear helper **app\helper\sefar.php**
	>
		≡
		≡
1. Modificar **composer.json** para agregar el helper **app\helper\sefar.php**
	>
		≡
		"autoload": {
			"psr-4": {
				"App\\": "app/",
				"Database\\Factories\\": "database/factories/",
				"Database\\Seeders\\": "database/seeders/"
			},	
			"files": [
				"app/helper/sefar.php"
			]
		},
		≡
1. Ejecutar:
	>
		$ composer dump-autoload
1. Configurar **config\adminlte.php** para crear un menú para pruebas:
	>
		≡
		≡
1. Crear archivo de estilo **public\css\prueba_flex.css**
	>
		≡
		≡
1. Si no se reflejan los cambios ejecutar:
	>
		$ php artisan config:cache

## Configuración de conexión a MySQL Hosting
1. Configuración de **.env**:
	>
		≡
		DB_CONNECTION=mysql
		DB_HOST=107.180.2.195
		DB_PORT=3306
		DB_DATABASE=sefar
		DB_USERNAME=pxvim6av41qx
		DB_PASSWORD="L5=Rj#8lW}YuK"
		≡
	IP pública:
	+ https://www.cual-es-mi-ip.net

## Colores Sefar:
+ Rojo: R:121 G:22 B:15
+ Verde: R:22 G:43 B:27
+ Amarillo: R:247 G:176 B:52
+ Gris: R:63 G:61 B:61

## Tablas a reponer al restaurar base de datos:
+ agclientes
+ books
+ families
+ libraries

## Para publicar y personalizar páginas de errores http. 
+ $ php artisan vendor:publish --tag=laravel-errors
+ **Nota**: las vistas para manejar los errores se ubicaran en **resources\views\errors**.

## Incluir destinatarios en las notificaciones:
1. Archivos a modificar para incluir destinatarios en las notificaciones:
	+ Registro: app\Actions\Fortify\CreateNewUser.php
	+ Actualización: app\Http\Controllers\ClienteController.php

## Crear modelo:
1. Diferentes formas para crear modelos:
	+ Crear solo el modelo
		- $ php artisan make:model Model
	+ Crear el modelo con migración:
		- $ php artisan make:model Model -m
	+ Crear el modelo con migración y controlador:
		- $ php artisan make:model Model -mc
	+ Crear el modelo con migración, controlador y seeder:
		- $ php artisan make:model Model -mcs
	+ Crear el modelo con migración, controlador, seeder y factory:
		- $ php artisan make:model Model -mcsf
	+ Crear el modelo con migración con todo:
		- $ php artisan make:model Model -a

## Clonar el repositorio AppSefarUniversal desde GitHub
1. Clonar repositorio en local:
	+ $ git clone https://github.com/petrix12/AppSefarUniversal.git
	**Nota 1**: En mi caso el repositorio lo clonaré en **C:\xampp\htdocs** y luego cambiaré el nombre de **AppSefarUniversal** a **sefar**.
	**Nota 2**: Para este ejercicio se está utilizando **XAMPP** como entorno de desarrollo, en caso de utilizar otro entorno como **Laragon** o **WAMPServer** realizar las modificaciones correspondiente a cada caso.
2. Ejecutar una terminal en el proyecto recién creado y ejecutar los siguientes comandos desde esa ruta.
3. Instalar dependencias de **PHP** y **NPM**:
	+ $ composer install
	+ $ npm install
	**Nota**: en caso de presentarse vulnerabilidades ejecutar (**Revisar esta solución más a fonndo**):
	+ $ npm install -g npm@latest		(para actualizar NPM)
	+ $ npm cache clean --force			(borrar la cache de NPM)
	+ $ npm set audit false				(desactivar las auditorias de NPM)
4. En caso de no tener creada las bases de datos **sefar** y **onidex** en MySQL, entonces proceder a crearlas (juego de caracteres a utilizar para ambas: **utf8_general_ci**) y seguir los siguientes pasos:
	+ $ php artisan migrate:fresh --seed
	+ Importar a la base de datos **sefar** en local la estructura de la tabla **agclientes** desde la base de datos **sefar** del servidor de producción ya que esta tabla no se creará. En caso de querer traerte el proyecto completo entonces exportarla con sus datos.
	+ Importar a la base de datos **onidex** en local la estructura de la tabla **agclientes** desde la base de datos **sefar** del servidor de producción ya que esta tabla no se creará. En caso de querer traerte el proyecto completo entonces exportarla con sus datos.
5. Copiar la siguiente ruta de acceso relativa del servidor de producción al local:
	+ storage\app\public
	**Nota**: en caso de pretender instalar el proyecto vacio, entonces traerse solo la estructura de directorios.
6. En caso de no tener un host virtual creado para nuestro proyecto, seguir los siguientes pasos:
	1. Ejecutar el bloc de notas como administrador.
    2. Abrir el archivo: **C:\Windows\System32\drivers\etc\hosts**.
    3. En la parte final del archivo escribir:
		```
		127.0.0.1     sefar.test
		```
    4. Guardar y cerrar.
    5. Editar con el bloc de notas el archivo: **C:\xampp\apache\conf\extra\httpd-vhosts.conf**.
    6. Ir al final del archivo y anexar lo siguiente:
        + Si nunca has creado un virtual host agregar:
			```conf
			<VirtualHost *>
				DocumentRoot "C:\xampp\htdocs"
				ServerName localhost
			</VirtualHost>
			```
			**Nota**: Esta estructura se agregará una única vez.
        + Luego agregar:
			```conf
			<VirtualHost *>
			DocumentRoot "C:\xampp\htdocs\sefar\public"
			ServerName sefar.test
			<Directory "C:\xampp\htdocs\sefar\public">
				Options All
				AllowOverride All
				Require all granted
			</Directory>
			</VirtualHost>
        	```
    7. Guardar y cerrar.
    8. Apagar y encender el servidor Apache.
    **Nota 1**: ahora podemos ejecutar nuestro proyecto en el navegador introduciendo la siguiente dirección: http://sefar.test
    **Nota 2**: En caso de que no funcione el enlace, cambiar en el archivo **C:\xampp\apache\conf\extra\httpd-vhosts.conf** el segmento de código **<VirtualHost \*>** por **<VirtualHost *:80>**.
7. Crear el archivo de variables de entorno **.env** en la raíz del proyecto:
	```
	APP_NAME="App Sefar Universal"
	APP_ENV=local
	APP_KEY=
	APP_DEBUG=true
	APP_URL=http://sefar.test

	LOG_CHANNEL=stack
	LOG_LEVEL=debug

	DB_CONNECTION=mysql
	DB_HOST=127.0.0.1
	DB_PORT=3306
	DB_DATABASE=sefar
	DB_USERNAME=root
	DB_PASSWORD=

	ONIDEX_CONNECTION=mysql
	ONIDEX_HOST=127.0.0.1
	ONIDEX_PORT=3306
	ONIDEX_DATABASE=onidex
	ONIDEX_USERNAME=root
	ONIDEX_PASSWORD=

	BROADCAST_DRIVER=log
	CACHE_DRIVER=file
	QUEUE_CONNECTION=sync
	SESSION_DRIVER=database
	SESSION_LIFETIME=120

	MEMCACHED_HOST=127.0.0.1

	REDIS_HOST=127.0.0.1
	REDIS_PASSWORD=null
	REDIS_PORT=6379

	MAIL_MAILER=smtp
	MAIL_HOST=smtp.mailtrap.io
	MAIL_PORT=2525
	MAIL_USERNAME=7c67f786972696
	MAIL_PASSWORD=8f37b2d25228ba
	MAIL_ENCRYPTION=tls
	MAIL_FROM_ADDRESS=app.web@sefarvzla.com
	MAIL_FROM_NAME="${APP_NAME}"

	AWS_ACCESS_KEY_ID=
	AWS_SECRET_ACCESS_KEY=
	AWS_DEFAULT_REGION=us-east-1
	AWS_BUCKET=

	PUSHER_APP_ID=
	PUSHER_APP_KEY=
	PUSHER_APP_SECRET=
	PUSHER_APP_CLUSTER=mt1

	MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
	MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

	VAR_TMP='inicial'
	```
7. Generar clave para la variable de entorno **APP_KEY**:
	+ $ php artisan key:generate
8. Generar acceso directo (enlace simbólico) de public a storage:
	+ $ php artisan storage:link

## Deploy de App Sefar Universal en AWS:
###  Lanzar instancia en EC2:
1. Ir a https://aws.amazon.com/es
2. Ir a **Mi cuenta** > **Consola de administración de AWS** e iniciar sesión.
3. Ir al servicio **EC2** y dar clic en **Instancias New**.
4. Dar clic en el botón de **Lanzar instancia**.
5. Seleccionar:
    + Ubuntu Server 18.04 LTS (HVM), SSD Volume Type - ami-0747bdcabd34c712a (64 bits x86) / ami-08353a25e80beea3e (64 bits Arm)
6. Seleccionar el de tipo t2.2xlarge.
7. Dar clic en el botón **Siguiente: Página Configuración de los detalles de la instancia**.
8. Dejar todo como está y dar clic en el botón **Siguiente: Adición de almacenamiento**.
9. Establecer **Tamaño (GiB)** en **120**.
10. Dar clic en el botón **Siguiente: Agregar etiquetas**.
11. Dejar todo como está y dar clic en el botón **Siguiente: Página Configure Security Group**.
12. Ajustar parámetros:
    + Dejar regla: **SSH**
    + Añadir regla: **HTTP**
    + Añadir regla: **HTTPS**
    + Para la regla **SSH** establecer **Origen** como **Mi IP**
    + Para las reglas **HTTP** y **HTTPS** establecer **Origen** como **Cualquier lugar**
13. Dar clic en el botón **Revisar y lanzar**.
14. Dejar todo como está y dar clic en el botón **Lanzar**.
15. En el cuadro de diálogo para claves seleccionar **Crear un nuevo par de claves**.
	+ Nombre del par de claves: **appsefar**.
	+ Dar clic en el botón: Descargar par de claves
    **Nota**: Guardar en un lugar seguro y evitar que se suba a GitHub, en este caso lo guardaré en la raíz de la carpeta del proyecto, y editaremos el archivo .**gitignore** para evitar que se incluya en los repositorios:
	```
	/node_modules
	/public/hot
	/public/storage
	/storage/*.key
	/vendor
	/a1_soportes
	.env
	.env.backup
	.phpunit.result.cache
	docker-compose.override.yml
	Homestead.json
	Homestead.yaml
	npm-debug.log
	yarn-error.log
	appsefar.pem
	```
16. Dar clic en el botón **Lanzar instancias**.
	**Nota**: Para revisar la nueva instancia se le puede dar clic en el botón **Ver instancias**.
  
### Conectar con la instancia AWS appsefar vía SSH:
1. Para los sistemas operativos Mac o Linux ejecutar:
   + $ chmod 400 appsefar.pem
2. Para el sistema operativo Windows:
   + Ubicar el archivo **appsefar.pem** en disco, presionar sobre el el botón derecho y dar clic en **Propiedades**.
   + Seleccionar la pestaña de **Seguridad**.
   + Presionar el botón: **Opciones avanzadas**.
   + Presionar el botón: **Deshabilitar herencia**.
   + Dar clic: **Quitar todos los permisos heredados de este objeto**.
   + Copiar el nombre del **Propietario** que está entre los paréntesis del usuario (Pedro Bazo).
   + Presionar el botón: **Agregar**.
   + Dar clic: **Seleccionar una entidad de seguridad**.
   + En **Escriba el nombre de objeto para seleccionar** escribrir el nombre copiado en los pasos anteriores.
   + Presionar el botón: **Comprobar nombres**.
   + Presionar el botón: **Aceptar**.
   + Verificar que los permisos que tienen que estar habilitados son:
     - Lectura y ejecución
     - Lectura
   + Presionar el botón: **Aceptar** (Cuadro de diálog: **Entrada de permiso para appsefar.com**).
   + Presionar el botón: **Aceptar** (Cuadro de diálog: **Configuración de seguridad avanzada para appsefar.pem**).
   + Presionar el botón: **Aceptar** (Cuadro de diálog: **Propiedades: appsefar.pem**).
3. En el navegador ingresar a la instancias presionando abajo de **ID de la instancia** y dar clic en el botón **Conectar**.
4. Ir a **Cliente SSH** y copiar el comando de ejemplo: **ssh -i "appsefar.pem" ubuntu@ec2-3-239-101-245.compute-1.amazonaws.com**.
5. En local ejecutar (en la ruta que se encuentre el archivo **appsefar.pem**):
    + $ ssh -i "appsefar.pem" ubuntu@ec2-3-239-101-245.compute-1.amazonaws.com
	+ A la pregunta: Are you sure you want to continue connecting (yes/no/[fingerprint])?
      - Respondemos: yes
    **Nota**: Con esta acción hemos ingresado en el servidor de AWS.

### Configurar nuestro servidor
1. En la terminal del servidor de AWS:
    + Actualizar servidor:
        - $ sudo apt-get update
        - $ sudo apt-get upgrade
        - Cuando pregunte: Do you want to continue? [Y/n]
            * Responder: y
	+ Actualizar nuevamente el servidor:
        - $ sudo apt-get update
    + Configurar entorno para ejecutar Laravel:
        - $ sudo apt-get install software-properties-common
        - $ sudo add-apt-repository ppa:ondrej/php
        - Cuando pregunte: Press [ENTER] to continue or Ctrl-c to cancel adding it.
            * Presionamos ENTER.
        - Actualizar nuevamente el servidor:
            * $ sudo apt-get update
        - Instalar php:
            * $ sudo apt-get install php7.4
            * Cuando pregunte: Do you want to continue? [Y/n]
                * Responder: y
        - Instalar el servidor apache:
            * $ sudo apt-get install apache2
            * $ sudo apt-get install libapache2-mod-php7.4
        	**Nota**: para ver la versión de php:
              * $ php -v
        - Para saber los modulos instalados en php:
            * $ php -m
            * Resultados:
				```
				[PHP Modules]
				calendar
				Core
				ctype
				date
				exif
				FFI
				fileinfo
				filter
				ftp
				gettext
				hash
				iconv
				json
				libxml
				openssl
				pcntl
				pcre
				PDO
				Phar
				posix
				readline
				Reflection
				session
				shmop
				sockets
				sodium
				SPL
				standard
				sysvmsg
				sysvsem
				sysvshm
				tokenizer
				Zend OPcache
				zlib

				[Zend Modules]
				Zend OPcache			
				```
            **Nota**: Constrastar contra https://laravel.com/docs/8.x/deployment#server-requirements y verificar cuales son necesarias.
        - Instalar extensiones de php necesarias para Laravel:
            * $ sudo apt-get install php7.4-bcmath
            * $ sudo apt-get install php7.4-mbstring
                * En: Do you want to continue? [Y/n]
                    Responder: y
            * $ sudo apt-get install php7.4-xml
        - Instalar paquetes que necesitaremos más adelante:
            * $ sudo apt-get install unzip
            * $ sudo apt-get install php7.4-zip
                * En: Do you want to continue? [Y/n]
                    Responder: y
            * $ sudo apt-get install php7.4-mysql
            * $ sudo apt-get install php7.4-curl
2. En el navegador:
    + Ir: **Servicios** > **EC2** > **Recursos** > **Instancias**.
    + Seleccionar nuestra instancia en ejecución.
    + Ubicar: Dirección IPv4 pública: **3.239.101.245**.
    **Nota**: esta será la dirección de nuestro sitio web.
3. En la terminal del servidor de AWS:
    + Reiniciar el servidor apache:
        * $ sudo service apache2 restart
		**Nota**: Para verificar que no tengamos ningún error:
        * $ sudo service apache2 status
    + Habilitar el modulo rewrite
        * $ sudo a2enmod rewrite
    + Reiniciar el servidor apache:
        * $ sudo service apache2 restart
    + Definir punto de acceso a nuestra aplicación web:
        * Ingresar a la ruta: /var/www/html
            - $ cd /var/www/html
            **Nota**: para ver los archivos contenidos en una ruta:
            - $ ls
        * Editar el archivo index.html:
            - $ sudo nano index.html
            - Cambiar la etiqueta title del head por:
				```html
                <title>App Sefar</title>
				```
            - Para guardar, presionar:
                + Ctrl + X
                + y
                + ENTER
        * Editar archivo de configuración de punto de acceso:
            - $ sudo nano /etc/apache2/sites-enabled/000-default.conf
            - Cambiar línea:
				```
             	DocumentRoot /var/www/html
				```
                Por:
				```
            	DocumentRoot /var/www/AppSefarUniversal/public
				```
            - Para guardar, presionar:
                + Ctrl + X
                + y
                + ENTER
    + Reiniciar el servidor apache:
        * $ sudo service apache2 restart

### Instalación de Composer
1. Copiar de la página: https://getcomposer.org/download, el bloque de **Command-line installation**:
	```
	php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
	php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
	php composer-setup.php
	php -r "unlink('composer-setup.php');"
	```
2. En la terminal del servidor de AWS pegar las líneas de comandos que acabamos de copiar y presionar ENTER.
3. Realizar la instalación global de composer:
    + $ sudo mv composer.phar /usr/local/bin/composer
    **Nota 1**: Este comando se encuentra en https://getcomposer.org/doc/00-intro.md
    **Nota 2**: Para comprobar que tenemos instalado composer, ejecutar:
    + $ composer

### Clonar repositorio de GitHUb App Sefar:
1. Ir a la ruta **/var/www**:
    + $ cd /var/www
2. Clonar el repositorio del proyecto:
    + $ sudo git clone https://github.com/petrix12/AppSefarUniversal.git
3. Ir a la ruta **/var/www/AppSefarUniversal**:
    + $ cd /var/www/AppSefarUniversal
4. Para poder instalar las dependencias de **Composer**, ejecutar:
    + $ sudo chown -R ubuntu:www-data .
5. Ejecutar permisos para la carpeta de laravel:
    + $ chmod -R 755 .
    + $ chmod -R 777 ./storage
6. Instalar composer:
    + $ composer install
7. Crear el archivo **.env** a partir de **.env.example**:
    + $ cp .env.example .env
<!-- 8. Generar llave del proyecto:
    + $ php artisan key:generate -->
9. Instalar NodeJs:
    + $ sudo apt install nodejs
        * Do you want to continue? [Y/n]
            Responder: y
10. Para ver la versión de NodeJs:
    + $ nodejs -v
11. Actualizar NodeJs:
    + $ curl -sL https://deb.nodesource.com/setup_14.x | sudo -E bash -
12. Ejecutar:
    + $ sudo apt-get install -y nodejs
	**Nota**: Para ver la versión de npm:
    + $ npm -v
13. Ejecutar:
    + $ npm install
    + $ npm run dev

### Crear base de datos
1. En la página de AWS buscar el servicio **RDS** (https://console.aws.amazon.com/rds/home?region=us-east-1):
    + Dar clic en el botón: **Crear base de datos**.
    + En **Elegir un método de creación de base de datos**:
        * Seleccionar la opción: **Creación estándar**.
    + En **Opciones del motor**:
        * Seleccionar la opción: **MySQL**.
        * Seleccionar la versión: **MySQL 8.0.23**.
    + En **Plantillas**:
        * Seleccionar la opción: **Producción**.
    + En **Configuración** > **Identificador de instancias de bases de datos**:
        * Escribir: appsefar-db
    + En **Configuración** > **Configuración de credenciales** > **Nombre de usuario maestro**:
        * Escribir: appsefar_usr
    + En **Configuración** > **Configuración de credenciales** > **Generación automática de contraseña**: Seleccionar.
    + En **Conectividad** > **Acceso público**: Seleccionar **Si**.
    + En **Configuración adicional** > **Opciones de base de datos** > **Nombre de base de datos inicial**:
        * Escribir: appsefar_db
    + Presionar botón: **Crear base de datos**.
    + Cuando finalice la creación de la base de datos, presionar el botón: **View credential details** y anotar credenciales en un lugar seguro.

### Editar los "Security Group"
1. En la página de AWS seleccionar la base de datos **appsefar-db**: **Amazon RDS** > **Databases**.
2. Seleccionar **Conectividad y seguridad** > **Seguridad** > **Grupos de seguridad de la VPC** > **default (sg-09f6e23a)**.
3. Seleccionar **Grupos de seguridad** > **ID del grupo de seguridad** > **sg-09f6e23a**.
4. Seleccionar **Reglas de entrada** y presionar botón **Edit inbound rules**.
5. Presionar el botón **Agregar regla** y seleccionar el tipo **MySQL/Aurora** y seleccionar como origen **Mi IP**.
6. Presionar botón **Guardar reglas**.

### Conectar base de datos de AWS con local MySQL Workbench
1. En caso de no tener instalado **MySQL Workbench**:
	+ Ir a https://www.mysql.com/products/workbench
	+ Presionar el botón **Download Now »**.
	+ En la siguiente página presionar el botón **Download** y luego en el enlace **No thanks, just start my download**.
	+ Instalar **MySQL Workbench**.
2. En **MySQL Workbench**:
    + Seleccionar **Database** > **Manage Connections...**.
    + En el cuadro de diálogo **Manage Server Connections** suministrar la siguiente información:
        * Presionar botón: New
        * Connection Name: appsefar
        * Hostname: appsefar-db.cgfry9dn7zav.us-east-1.rds.amazonaws.com
        	**Nota**: Es el punto de enlace que aparece en la página de AWS: **RDS** > **Databases** > **appsefar-db**.
        * Port: 3306
        * Username: appsefar_usr
        * Password: ************
    + Presionar botón: **Test Connection** para comprobar el estado de la conección.
    + Presionar botón: **Close**.
    + Seleccionar **Database** > **Connect to Database**.
    + En el cuadro de diálogo **Connect to Database** seleccionamos la conección **appsefar** y presionamos el botón **OK**.
**Nota**: También se puede conectar con la extensión de VSC **MySQL cwejan**.

### Configuración de credenciales en Laravel
1. En la terminal de AWS ir a la ruta **/var/www/AppSefarUniversal**:
    + $ cd /var/www/AppSefarUniversal
2. En la terminal de AWS editar el archivo **.env**:
    + $ nano .env
3. Modificar los siguientes parámetros en el archivo **.env**:
	```
	APP_NAME="App Sefar Universal"
	APP_ENV=production
	APP_KEY=
	APP_DEBUG=false
	APP_URL=http://3.239.101.245

	LOG_LEVEL=debug

	DB_CONNECTION=mysql
	DB_HOST=appsefar-db.cgfry9dn7zav.us-east-1.rds.amazonaws.com
	DB_PORT=3306
	DB_DATABASE=appsefar_db
	DB_USERNAME=appsefar_usr
	DB_PASSWORD=4x82JCU67qOxaevDDlpK

	ONIDEX_CONNECTION=mysql
	ONIDEX_HOST=107.180.2.195
	ONIDEX_PORT=3306
	ONIDEX_DATABASE=onidex
	ONIDEX_USERNAME=pxvim6av41qx
	ONIDEX_PASSWORD="L5=Rj#8lW}YuK"

	BROADCAST_DRIVER=log
	CACHE_DRIVER=file
	QUEUE_CONNECTION=sync
	SESSION_DRIVER=database
	SESSION_LIFETIME=120

	MEMCACHED_HOST=127.0.0.1

	REDIS_HOST=127.0.0.1
	REDIS_PASSWORD=null
	REDIS_PORT=6379

	MAIL_DRIVER=smtp
	MAIL_HOST=smtp.gmail.com
	MAIL_PORT=587
	MAIL_USERNAME=info@sefarvzla.com
	MAIL_PASSWORD=nofuievtqkdfxszn
	MAIL_ENCRYPTION=tls
	MAIL_FROM_ADDRESS=info@sefarvzla.com
	MAIL_FROM_NAME="${APP_NAME}"

	AWS_ACCESS_KEY_ID=
	AWS_SECRET_ACCESS_KEY=
	AWS_DEFAULT_REGION=eu-west-1
	AWS_BUCKET=

	PUSHER_APP_ID=
	PUSHER_APP_KEY=
	PUSHER_APP_SECRET=
	PUSHER_APP_CLUSTER=mt1

	MIX_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
	MIX_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
	```
	**Nota 1**: Luego que se configure el nombre del dominio correspondiente cambiar la variable de entorno:
		```
		APP_URL=http://3.239.101.245
		```
		Por:
		```
		APP_URL=https://app.universalsefar.com
		```
	**Nota 2**: El valor de la variable de entorno **DB_HOST** proviene de la página de AWS en: **RDS** > **Databases** > **appsefar-db** en **Conectividad y seguridad** -> **Punto de enlace y puerto** -> **Punto de enlace**.
	**Nota 3**: Los valores de las variables de entorno correspondiente a **MAIL_** están configurados con la cuenta de **info@sefarvzla.com**, también se podría configurar con una cuenta de **AWS**.
4. Para guardar el archivo **.env**:
    + $ Ctrl + X
    + $ y
    + $ ENTER
5. Generar llave del proyecto:
    + $ php artisan key:generate
6. Para verificar que podemos establecer una conexión con la base de datos, ir a la terminal AWS y ejecutar:
    + $ php artisan tinker
    + >>> DB::connection()->getPdo();
    **Nota**: si el resultado de la ejecución es parecida a la que se muestra a continuación es que logramos establecer la conexión:
	```json
	=> PDO {#4731
		inTransaction: false,
		attributes: {
		CASE: NATURAL,
		ERRMODE: EXCEPTION,
		AUTOCOMMIT: 1,
		PERSISTENT: false,
		DRIVER_NAME: "mysql",
		SERVER_INFO: "Uptime: 104373  Threads: 6  Questions: 488396  Slow queries: 0  Opens: 337  Flush tables: 3  Open tables: 238  Queries per second avg: 4.679",
		ORACLE_NULLS: NATURAL,
		CLIENT_VERSION: "mysqlnd 7.4.22",
		SERVER_VERSION: "8.0.23",
		STATEMENT_CLASS: [
			"PDOStatement",
		],
		EMULATE_PREPARES: 0,
		CONNECTION_STATUS: "appsefar-db.cgfry9dn7zav.us-east-1.rds.amazonaws.com via TCP/IP",
		DEFAULT_FETCH_MODE: BOTH,
		},
	}	
	```
7. Salir de tinker:
    + >>> Ctrl + C
11. Ejecutar las migraciones en la terminal de AWS:
    + $ php artisan migrate
		+ Do you really wish to run this command? (yes/no) [no]: yes

### Fix: Configuración del servidor
1. Ingresar al archivo de configuración de apache en la terminal de AWS:
    + $ sudo nano /etc/apache2/apache2.conf

sudo nano /etc/apache2/sites-available/000-default.conf
2. Comentar las siguientes líneas con #:
	```
	# User ${APACHE_RUN_USER}
	# Group ${APACHE_RUN_GROUP}
	```
3. A continuación agregar las líneas:
	```
	User ubuntu
	Group ubuntu
	```
4. Cambiar el siguiente bloque de códgio:
	```
	<Directory />
			Options FollowSymLinks
			AllowOverride None
			Require all denied
	</Directory>

	<Directory /usr/share>
			AllowOverride None
			Require all granted
	</Directory>

	<Directory /var/www/>
			Options Indexes FollowSymLinks
			AllowOverride None
			Require all granted
	</Directory>
	```
    Por:
	```
	<Directory />
			Options FollowSymLinks
			AllowOverride All
			Require all denied
	</Directory>

	<Directory /usr/share>
			AllowOverride All
			Require all granted
	</Directory>

	<Directory /var/www/>
			Options Indexes FollowSymLinks
			AllowOverride All
			Require all granted
	</Directory>
	```
5. Para guardar los cambios:
    + $ Ctrl + X
    + $ y
    + $ ENTER
6. Habilitar modo rewrite:
    + $ sudo a2enmod rewrite
7. Reiniciar el servidor de apache:
    + $ sudo service apache2 restart
8. Para ver el estatus del servidor apache:
    + $ sudo service apache2 status

### Permitir acceso a base de datos onidex en servidor GoDaddy:
1. Ingresar al cPanel de GoDaddy:
	+ https://a2plcpnl0082.prod.iad2.secureserver.net:2083
	+ Introducir las credenciales
2. Dentro del cPanel ir a **BASES DE DATOS** > **MySQL remoto®**.
3. En **Añadir anfitrión de acceso** introducir los siguientes valores:
	+ Anfitrión (se permite el comodín %): **3.239.101.245**
	+ Comment (optional): **AWS appsefar**
4. Presionar el botón **Añadir anfitrión**.

### Configurar FileZilla con nuestra instancia EC2:
**URL**: https://filezilla-project.org
1. Ejecutar FileZilla e ir a **Edición** > **Opciones...**.
2. En el cuadro de diálogo **Opciones** en **Selecciones página:** ir a **Conexión** > **SFTP**.
3. Luego en el panel de **Claves privadas:** presionar el botón **Añadir archivo de clave...**.
4. Ubicar el archivo de credenciales **appsefar.pem** y presionar el botón **Abrir**.
5. Presionar el botón **Aceptar** para salir del cuadro de diálogo **Opciones**.
6. Ir a **Archivo** > **Gestor de sitios...**.
7. En el cuadro de diálogo **Gestor de sitios** presionar el botón **Nuevo sitio** nombrarlo **AWS App Sefar** e intruducir los siguientes parámetros:
	+ Protocolo: SFTP - SSH File Transfer Protocol
	+ Servidor: 3.239.101.245
	+ Modo de acceso: Interactivo
	+ Usuario: ubuntu
		**Nota 1**: Posibles nombres de usuarios según el sistema operativo en EC-2:
		+ Amazon Linux: ec2-user
		+ RHEL5: root o ec2-user.
		+ Ubuntu: ubuntu
		+ SUSE Linux: root . Para 
		+ Debian: admin. 
		+ Otros: consulte el proveedor de AMI.
		**Nota 2**: FileZilla determinará automáticamente la clave. No se necesitará especificarla después de haberla importardo.
8. Presionar el botón **Conectar**.

### Ejemplo de registro de un cliente con parámetros GET:
   + http://3.239.101.245/register?nombre=Detal%20y%20Borrar&cnacimiento=Punto%20Fijo&email=delete.borrar33%40gmail.com&fnacimiento=1977-11-03&nombre_f=Perensejo%20Borrar&nombres=Fulanito&pasaporte=1234567999&pasaporte_f=5555555&pnacimiento=Venezuela&sexo=M

### Actualizar proyecto en AWS desde GitHub:
+ $ ssh -i "appsefar.pem" ubuntu@ec2-3-239-101-245.compute-1.amazonaws.com
+ $ cd /var/www/AppSefarUniversal
+ $ sudo git pull
	**Nota**: en caso de necesitar forzar la actualización:
	+ $ sudo git reset --hard HEAD
	+ $ git pull
  
### Puntos finales para finalizar la migración de GoDaddy a AWS:
1. Migrar todos los registros de todas las tablas de GoDaddy a la instacia de MySQL de AWS.
3. Copiar archivos de la ruta **storage\app\public** de **GoDaddy** a **AWS**.
4. Copiar documentos.
5. Transferir nombre de dominio de GoDaddy a AWS.
6. Estudiar como guardar los documentos directamente en Google Drive.

## Configurar AWS S3
1. Ingresar en: https://aws.amazon.com/es
2. Ir a **IAM** (Administrar el acceso a los recursos de AWS): https://us-east-1.console.aws.amazon.com/iamv2/home?region=us-east-1#/home
3. Crear usuario:
    + Clic en **Usuarios** y luego en **Agregar usuarios**.
    + Nombre de usuario: **appsefar**.
    + Tipo de credenciales: **Clave de acceso: acceso mediante programación**.
    + Clic en **Siguiente: Permisos**.
    + Clic en **Asociar directamente las políticas existentes**.
    + Seleccionar el permiso:
        + AmazonS3FullAccess
    + Clic en **Siguiente: Etiquetas**.
    + Clic en **Siguiente: Revisar**.
    + Clic en **Crear un usuario**.
    + Obtener los valores:
        + **ID de clave de acceso**.
        + **Clave de acceso secreta**.
5. Crear Bucket:
    + Ir a **S3** (Almacenamiento escalable en la nube): https://s3.console.aws.amazon.com/s3/get-started?region=us-east-1
    + Clic en **Crear bucket**:
        + Nombre del bucket: **appsefar-bucket-s3**.
        + Región de AWS: **EE. UU. Est (Norte de Virginia) us-east-1**.
        + Clic en **Crear bucket**.
    + Clic en el nuevo bucket: **appsefar-bucket-s3**.
    + Clic en **Permisos**:
        + En **Propiedad de objetos** clic en **Editar**.
        + Seleccionar **ACL habilitados**.
        + En **Habilitar las ACL desactiva la configuración forzada del propietario del bucket en cuanto a la propiedad del objeto** seleccionar **Reconozco que las ACL se restaurarán.**
        + Clic en **Guardar cambios**.
    + Clic en **Permisos**:
        + En **Bloquear acceso público (configuración del bucket)** clic en **Editar**:
            + Deseleccionar **Bloquear todo el acceso público**.
            + Seleccionar: 
                + Bloquear el acceso público a buckets y objetos concedido a través de políticas de bucket y puntos de acceso públicas nuevas
                + Bloquear el acceso público y entre cuentas a buckets y objetos concedido a través de cualquier política de bucket y puntos de acceso pública
        + Clic en **Guardar cambios** y confirmar.
    + **Opcional**: En caso de querer establecer políticas de almacenamiento:
        + Clic en **Permisos**:
            + En **Política de bucket** clic en **Editar**.
            + Política:
                + Obtener **ARN del bucket**: arn:aws:s3:::appsefar-bucket-s3
                ```json
                {  
                    "Version": "2012-10-17",
                    "Statement": [
                        {
                            "Sid": "Statement1",
                            "Principal": "*",
                            "Effect": "Allow",
                            "Action": [
                                "s3:DeleteObject",
                                "s3:GetObject",
                                "s3:PutObject"
                            ],
                            "Resource": ["arn:aws:s3:::appsefar-bucket-s3/*"]   // ARN del bucket concatenado con "/*"
                        }
                    ]
                }
                ```
6. Dar valores a variables de entorno Laravel en ****:
    ```env
    ≡
    AWS_ACCESS_KEY_ID=[ID de clave de acceso]
    AWS_SECRET_ACCESS_KEY=[Clave de acceso secreta]
    AWS_DEFAULT_REGION=us-east-1
    AWS_BUCKET=[Nombre del bucket]
    ≡
    ```
7. Instalar dependencia para Amazon S3:
    + $ composer require --with-all-dependencies league/flysystem-aws-s3-v3 "1.0.29"
    + $ composer require league/flysystem-cached-adapter "~1.0"
    + **Documentación**: https://laravel.com/docs/8.x/filesystem#the-public-disk
8. Limpiar configuración Laravel:
    + $ php artisan optimize
    + $ php artisan cache:clear

## Comandos Git para crear rama y luego hacer merge en la principal
1. Realizar commit antes de empezar:
    + $ git add .
    + $ git commit -am "Estado del repositorio actual"
    + $ git push -u origin master
2. Crear rama y cambiar a ella:
    + $ git checkout -b nombre-rama
3. Confirmar los cambios en la nueva rama:
    + $ git add .
    + $ git commit -am "Confirmando los cambios"
4. Volver a la rama principal y unir los cambios:
    + $ git checkout master
    + $ git rebase nombre-rama

## Configuración del archivo **.htaccess**:
+ Configuración 1:
    ```htaccess
    # DON NOT REMOVE THIS LINE AND TEN LINES BELLOW SSL_REDIRECT:corporacioncabv.com/appsefar
    RewriteEngine on
    RewriteCond %{HTTPS} off
    RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    SetEnv ENVIRONMENT production

    RewriteRule ^(.*)$ public/$1 [L]

    # DON NOT REMOVE THIS LINE AND TEN LINES BELLOW SSL_REDIRECT:corporacioncabv.com/appsefar
    ```
+ Configuración 2:
    ```htaccess
    <IfModule mod_rewrite.c>
        RewriteEngine on
        RewriteCond %{REQUEST_URI} !^public
        RewriteRule ^(.*)$ public/$1 [L]
    </IfModule>
    ```

## Módulo cursos
+ Entidades:
    + **users** (usuarios):
        + id
        + name
        + email
        + password
    + **profiles** (profesores):
        + id
        + title
        + biography
        + website
        + socialnetwork_name
        + socialnetwork_url
    + **courses** (cursos):
        + id
        + title
        + subtitle
        + description
        + status
    + **reviews** (calificaciones):
        + id
        + comment
        + rating
    + **levels** (niveles):
        + id
        + name
    + **categories** (categorías):
        + id
        + name
    + **prices** (precios):
        + id
        + name
        + value
    + **goals** (metas):
        + id
        + name
    + **requirements** (requerimientos):
        + id
        + name
    + **audiences**:
        + id
        + name
    + **sections**:
        + id
        + name
    + **lessons**:
        + id
        + name
        + url
    + **platforms** (plataformas):
        + id
        + name
    + **descriptions** (descripciones):
        + id
        + name
+ Tablas polimorficas:
    + **resources** (recursos):
        + id
        + url
        + resourceable_id
        + resourceable_type
    + **comments** (comentarios):
        + id
        + name
        + commentable_id
        + commentable_type
    + **likes** (me gustan):
        + id
        + value (0 o 1)
        + likeable_id
        + likeable_type
    + **images** (imagenes):
        + id
        + url
        + imageable_id
        + imageable_type
+ Tablas intermedias (auxiliares):
    + course_user
    + lesson_user
+ Relaciones:
    + **1:1**:
        + profiles - users
        + descriptions - lessons
    + **1:n**:
        + users - courses
        + users - reviews
        + courses - reviews
        + levels - courses
        + categories - courses
        + prices - courses
        + courses - goals
        + courses - requirements
        + courses - audiences
        + courses - sections
        + sections - lessons
        + platforms - lessons
        + users - course_user
        + courses - course_user
        + lessons -lesson_user
        + users - lesson_user
    + **n:m**:
        + courses - users
        + lessons - users





 (4/63)

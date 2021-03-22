# Proyecto App Sefar Universal
##### https://laravel.com/docs/8.x
##### Versión: **Laravel Framework 8.31.0**
#

# Paso a paso del desarrollo del proyecto
***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE I		● ● ● ● ■ ■ ► ► ►**
***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
1. Crear proyecto: 
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

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE II		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

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
	##### y
	>
		LoadModule rewrite_module modules/mod_rewrite.so		
	##### no deben estar comentada con #.

1. Reiniciar el servidor Apache.

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE III		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

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
	## El campo **email** se redujo a **175** carácteres por problemas de compatibilidad al importar tabla a la base de datos del hosting

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
	
	### Commit 2:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Ajustes iniciales"

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE IV		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

	## Laravel-permission
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

	### Commit 3:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Laravel-permission"

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE V		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

	## Plantilla AdminLTE
	##### Documentación: https://github.com/jeroennoten/Laravel-AdminLTE
	##### Plantilla: https://adminlte.io/themes/v3/index.html

1. Integrar AdminLTE: 
	>
		$ composer require jeroennoten/laravel-adminlte
1. Ejecutar: 
	>
		$ php artisan adminlte:install
1. Crear plantilla modelo: **resources\views\layouts\demoAdminLTE.blade.php**
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
	

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE VI		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

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

	### Commit 5:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Adaptación al español"

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE VII		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

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

	### Commit 6:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Roles, Permisos y Usuarios"

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE VIII		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

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

	### Commit 7:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Proyecto personalizado"
	
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE IX		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

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

	### Commit 8:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Perfil de usuario"


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE X		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

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


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XI		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

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

	### Commit 10:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Verificación de email"


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XII		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

	## CRUD Permisos con Liveware	
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

	### Commit 11:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Permisos"


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XIII		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

	## CRUD Roles con Liveware
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

	### Commit 12:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Roles"


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XIV		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

	## CRUD Usuarios con Liveware
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


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XV		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

	## CRUD Paises con Liveware
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


	### Commit 13:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "CRUD Paises"


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XVI		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

	## Seeders para prueba de paises
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

	### Commit 15:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "Seeder Paises"

	# ///////////////////////////////////////////////



	## CRUD Agclientes
1. Crear modelo Agcliente junto con su migración y controlador y los métodos para el CRUD.
	>
		$ php artisan make:model Agcliente -m -c -r

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XVI		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***

	## Almacenamiento de documentos
1. -----------

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XVII		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***	

	## Vistas para árboles genealógicos
1. -----------

	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XVIII		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***	

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

	### Commit 14:
	+ Ejecutar:
		>
			$ git add .
	+ Crear repositorio:
		>
			$ git commit -m "App Consulta BD Onidex"


	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***
	>	**◄ ◄ ◄ ■ ■ ● ● ● ●		PARTE XVI		● ● ● ● ■ ■ ► ► ►**
	***	***	***	***	***	***	***	*** ***	***	***	***	***	***	***	***





	# **********************************************
	## CRUD Clientes con Liveware
1. Pendiente

	## CRUD Familiaridad entre Clientes con Liveware
1. Pendiente

	## CRUD Árbol Clientes con Liveware
1. Pendiente

	## CRUD Diex con Liveware
1. Pendiente

	## CRUD Maisanta con Liveware
1. Pendiente

	## Registro cliente
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
	+ Cambiar **APP_ENV=local** por **APP_ENV=production**
	+ Cambiar **APP_DEBUG=true** por **APP_DEBUG=false**
	+ Cambiar **APP_URL=http://sefar.test** por **APP_URL=https://app.universalsefar.com**
	+ Cambiar **DB_USERNAME=root** por **DB_USERNAME=pxvim6av41qx**
	+ Cambiar **DB_PASSWORD=** por **DB_PASSWORD=Cisco2019!**
	+ Cambiar **ONIDEX_USERNAME=root** por **ONIDEX_USERNAME=pxvim6av41qx**
	+ Cambiar **ONIDEX_PASSWORD=** por **ONIDEX_PASSWORD=Cisco2019!**
	+ Cambiar **MAIL_HOST=smtp.mailtrap.io** por **MAIL_HOST=universalsefar.com**
	+ Cambiar **MAIL_PORT=2525** por **MAIL_PORT=587**
	+ Cambiar **MAIL_USERNAME=7c67f786972696** por **MAIL_USERNAME=_mainaccount@universalsefar.com**
	+ Cambiar **MAIL_PASSWORD=8f37b2d25228ba** por **MAIL_PASSWORD=Cisco2019!**
	+ Cambiar **MAIL_ENCRYPTION=tls** por **MAIL_ENCRYPTION=null**
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

# RUTAS **INICIALES**
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

# RUTAS **PERMISOS**
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

# RUTAS **ROLES**
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
	 
# RUTAS **USUARIOS**
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

# RUTAS **ONIDEX**

	Method      URI                               	Name
	======		===								  	====
	GET|HEAD  	| consultaodx                      	| consultas.onidex.index

#
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

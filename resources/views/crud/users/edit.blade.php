@extends('adminlte::page')

@section('title', $user->name)

@section('content_header')

@stop

@section('content')

@php
    // Simulando un array de países (puedes llenarlo con todos los que necesites)
    $opcionesPersonas = [
        'Soporte IT', 'Crisanto Bello', 'Abel Tejeda', 'rrcastro@sefarvzla.com',
        // ...
        'Liliana Du Bois'
    ];
@endphp

<x-app-layout>
    <div>
        <div class="flex flex-col">
            <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div class="bg-gray-50">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                <span class="ctvSefar block text-indigo-600">{{ __('Edit user') }}</span>
                            </h2>
                            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                <div class="inline-flex rounded-md shadow">
                                    <a href="{{ route('crud.users.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Volver a {{ __('Users list') }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>
        <div class="card p-4">
            <ul class="nav nav-tabs" id="formTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-data-tab" data-bs-toggle="tab" data-bs-target="#personal_data" type="button" role="tab" aria-controls="personal_data" aria-selected="true">
                        Datos personales
                    </button>
                </li>
                @if(auth()->user()->roles[0]->id == 1)
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="adminchangepassword-tab" data-bs-toggle="tab" data-bs-target="#adminchangepassword" type="button" role="tab" aria-controls="adminchangepassword" aria-selected="true">
                        Contraseña
                    </button>
                </li>
                @else
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="mypassword-tab" data-bs-toggle="tab" data-bs-target="#mypassword" type="button" role="tab" aria-controls="mypassword" aria-selected="true">
                        Contraseña
                    </button>
                </li>
                @endif
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="familiars-tab" data-bs-toggle="tab" data-bs-target="#familiars" type="button" role="tab" aria-controls="familiars" aria-selected="false">
                        Familiares registrados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button" role="tab" aria-controls="payments" aria-selected="false">
                        Pagos realizados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab" aria-controls="documents" aria-selected="false">
                        Archivos Cargados
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="etiquetado-tab" data-bs-toggle="tab" data-bs-target="#etiquetado" type="button" role="tab" aria-controls="etiquetado" aria-selected="false">
                        Etiquetado
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="fcje-tab" data-bs-toggle="tab" data-bs-target="#fcje" type="button" role="tab" aria-controls="fcje" aria-selected="false">
                        FCJE
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="cil-tab" data-bs-toggle="tab" data-bs-target="#cil" type="button" role="tab" aria-controls="cil" aria-selected="false">
                        CIL
                    </button>
                </li>
            </ul>
            <div class="tab-content mt-4" id="formTabsContent">
                <!-- Primer Formulario -->
                <div class="tab-pane fade show active" id="personal_data" role="tabpanel" aria-labelledby="personal-data-tab">
                    <form action="/guardar-datos-personales" method="POST">
                        @csrf
                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">Datos Personales</span>
                        </h2>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="nombres" name="nombres" value="{{ old('nombres', $user->nombres) }}" placeholder="Ingrese su nombre">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="apellido" class="block text-sm font-medium text-gray-700">Apellido</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="apellidos" name="apellidos" value="{{ old('apellidos', $user->apellidos) }}" placeholder="Ingrese su apellido">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="pasaporte" class="block text-sm font-medium text-gray-700">Número de Pasaporte</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="passport" name="passport" value="{{ old('passport', $user->passport) }}" placeholder="Ingrese su número de pasaporte">
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="correo" class="block text-sm font-medium text-gray-700">Correo</label>
                                <input type="email" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="correo" name="correo" value="{{ old('email', $user->email) }}" placeholder="Ingrese su correo electrónico">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="telefono" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                <input type="tel" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="phone" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="Ingrese su número de teléfono">
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pay" class="block text-sm font-medium text-gray-700">{{ __('Payment status') }} del registro</label>
                                @if(auth()->user()->roles[0]->id == 1)
                                    <select name="pay" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        @if ($user->pay == 0)
                                            <option selected value=0>No ha pagado</option>
                                        @else
                                            <option value=0>No ha pagado</option>
                                        @endif

                                        @if ($user->pay == 1)
                                            <option selected value=1>Pagó</option>
                                        @else
                                            <option value=1>Pagó</option>
                                        @endif

                                        @if ($user->pay == 2)
                                            <option selected value=2>Pagó y completó información</option>
                                        @else
                                            <option value=2>Pagó y completó información</option>
                                        @endif

                                        @if ($user->pay == 3)
                                            <option selected value=3>Pagó pero no se registró en Hubspot</option>
                                        @else
                                            <option value=3>Pagó pero no se registró en Hubspot</option>
                                        @endif
                                    </select>
                                @else
                                    <p>
                                        @if ($user->pay == 0)
                                            No ha pagado
                                        @endif

                                        @if ($user->pay == 1)
                                            Pagó
                                        @endif

                                        @if ($user->pay == 2)
                                            Pagó y completó información
                                        @endif

                                        @if ($user->pay == 3)
                                            Pagó pero no se registró en Hubspot
                                        @endif
                                    </p>
                                @endif
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="servicio" class="block text-sm font-medium text-gray-700">Servicio Principal</label>
                                @if(auth()->user()->roles[0]->id == 1)
                                <select name="servicio" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option></option>
                                    @foreach ($servicios as $servicio)
                                        <option {{ $user->servicio == $servicio->id_hubspot ? 'selected' : '' }} > {{$servicio->id_hubspot}}</option>
                                    @endforeach
                                </select>
                                @else
                                    <p>
                                        {{$servicio->id_hubspot}}
                                    </p>
                                @endif
                            </div>
                        </div>
                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">Direcciones</span>
                        </h2>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pais_de_residencia" class="block text-sm font-medium text-gray-700">Pais de Residencia</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="pais_de_residencia" name="pais_de_residencia" value="{{ old('pais_de_residencia', $user->pais_de_residencia) }}" placeholder="Ingrese su Pais de Residencia">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="city" class="block text-sm font-medium text-gray-700">Ciudad de Residencia</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="city" name="city" value="{{ old('city', $user->city) }}" placeholder="Ingrese su ciudad de Residencia">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="address" class="block text-sm font-medium text-gray-700">Direccion actual</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="address" name="address" value="{{ old('address', $user->address) }}" placeholder="Ingrese su Dirección actual">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="pais_de_nacimiento" class="block text-sm font-medium text-gray-700">Pais de Nacimiento</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="pais_de_nacimiento" name="pais_de_nacimiento" value="{{ old('pais_de_nacimiento', $user->pais_de_nacimiento) }}" placeholder="Ingrese su País de Nacimiento">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="ciudad_de_nacimiento" class="block text-sm font-medium text-gray-700">Ciudad de Nacimiento</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="ciudad_de_nacimiento" name="ciudad_de_nacimiento" value="{{ old('ciudad_de_nacimiento', $user->ciudad_de_nacimiento) }}" placeholder="Ingrese su Ciudad de Nacimiento">
                            </div>
                        </div>

                        @if(auth()->user()->roles[0]->id == 1)

                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">AIV</span>
                        </h2>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n6__aiv_recibido_en_espana" class="block text-sm font-medium text-gray-700">Fecha AIV recibido en España</label>
                                <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n6__aiv_recibido_en_espana" name="n6__aiv_recibido_en_espana" value="{{ old('n6__aiv_recibido_en_espana', $user->n6__aiv_recibido_en_espana) }}" placeholder="Fecha AIV Recibido en España">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n2__aiv_notificacion_aprobado" class="block text-sm font-medium text-gray-700">AIV Notificación Aprobado</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__aiv_notificacion_aprobado" name="n2__aiv_notificacion_aprobado" value="{{ old('n2__aiv_notificacion_aprobado', $user->n2__aiv_notificacion_aprobado) }}" placeholder="Ingrese AIV Notificación Aprobado">
                            </div>
                        </div>

                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">AACS</span>
                        </h2>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n1__aacs_introducido_asociacion" class="block text-sm font-medium text-gray-700">AACS Introducido Asociacion</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__aacs_introducido_asociacion" name="n1__aacs_introducido_asociacion" value="{{ old('n1__aacs_introducido_asociacion', $user->n1__aacs_introducido_asociacion) }}" placeholder="Ingrese AACS INTRODUCIDO ASOCIACIÓN">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n2__aacs_notificacion_aprobado" class="block text-sm font-medium text-gray-700">AACS Notificacion Aprobado</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__aacs_notificacion_aprobado" name="n2__aacs_notificacion_aprobado" value="{{ old('n2__aacs_notificacion_aprobado', $user->n2__aacs_notificacion_aprobado) }}" placeholder="Ingrese AACS Notificación Aprobado">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n2__fecha_aacs_notificacion_aprobado" class="block text-sm font-medium text-gray-700">Fecha AACS Notificacion Aprobado</label>
                                <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__fecha_aacs_notificacion_aprobado" name="n2__fecha_aacs_notificacion_aprobado" value="{{ old('n2__fecha_aacs_notificacion_aprobado', $user->n2__fecha_aacs_notificacion_aprobado) }}" placeholder="Ingrese Fecha AACS Notificacion Aprobado">
                            </div>
                        </div>
                        <div class="mt-3"  style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n4__aacs_retirado_asociacion" class="block text-sm font-medium text-gray-700">AACS Retirado Asociacion</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__aacs_retirado_asociacion" name="n4__aacs_retirado_asociacion" value="{{ old('n4__aacs_retirado_asociacion', $user->n4__aacs_retirado_asociacion) }}" placeholder="Ingrese aacs retirado asociacion">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n6__aacs_recibido_en_espana" class="block text-sm font-medium text-gray-700">AACS Recibido en España</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n6__aacs_recibido_en_espana" name="n6__aacs_recibido_en_espana" value="{{ old('n6__aacs_recibido_en_espana', $user->n6__aacs_recibido_en_espana) }}" placeholder="AACS Recibido en España">
                            </div>
                        </div>

                        <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4">
                            <span class="ctvSefar block text-indigo-600">Otros datos</span>
                        </h2>
                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n1__acta_notarial" class="block text-sm font-medium text-gray-700">Acta Notarial</label>
                                <input type="text" value="{{ old('n1__acta_notarial', $user->n1__acta_notarial) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__acta_notarial" name="n1__acta_notarial" placeholder="Ingrese Acta Notarial">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n1__f__peticion_por_genealogia" class="block text-sm font-medium text-gray-700">Fecha Petición por Genealogía</label>
                                <input type="date" value="{{ old('n1__f__peticion_por_genealogia', $user->n1__f__peticion_por_genealogia) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__f__peticion_por_genealogia" name="n1__f__peticion_por_genealogia">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n1__f__solicitado_por_genealogia" class="block text-sm font-medium text-gray-700">Fecha Solicitado por Genealogía</label>
                                <input type="date" value="{{ old('n1__f__solicitado_por_genealogia', $user->n1__f__solicitado_por_genealogia) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n1__f__solicitado_por_genealogia" name="n1__f__solicitado_por_genealogia">
                            </div>
                        </div>

                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n2__f_solicitud_mayor_info" class="block text-sm font-medium text-gray-700">Fecha Solicitud Mayor Información</label>
                                <input type="date" value="{{ old('n2__f_solicitud_mayor_info', $user->n2__f_solicitud_mayor_info) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__f_solicitud_mayor_info" name="n2__f_solicitud_mayor_info">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n2__f__de_solicitud_al_cliente" class="block text-sm font-medium text-gray-700">Fecha Solicitud al Cliente</label>
                                <input type="date" value="{{ old('n2__f__de_solicitud_al_cliente', $user->n2__f__de_solicitud_al_cliente) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n2__f__de_solicitud_al_cliente" name="n2__f__de_solicitud_al_cliente">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n3__estatus_de_nacionalidad" class="block text-sm font-medium text-gray-700">Estatus de Nacionalidad</label>
                                <select class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__estatus_de_nacionalidad" name="n3__estatus_de_nacionalidad">
                                    <option value="" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === '' ? 'selected' : '' }}></option>
                                    <option value="Concedida" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === 'Concedida' ? 'selected' : '' }}>Concedida</option>
                                    <option value="En Tramitación" {{ old('n3__estatus_de_nacionalidad', $user->n3__estatus_de_nacionalidad ?? '') === 'En Tramitación' ? 'selected' : '' }}>En Tramitación</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n3__f___recordatorio_filiacion" class="block text-sm font-medium text-gray-700">Fecha Recordatorio Filiación</label>
                                <input type="date" value="{{ old('n3__f___recordatorio_filiacion', $user->n3__f___recordatorio_filiacion) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__f___recordatorio_filiacion" name="n3__f___recordatorio_filiacion">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n3__fcje_registro" class="block text-sm font-medium text-gray-700">FCJE Registro</label>
                                <input type="date" value="{{ old('n3__fcje_registro', $user->n3__fcje_registro) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__fcje_registro" name="n3__fcje_registro">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n3__fecha_de_recordatorio" class="block text-sm font-medium text-gray-700">Fecha de Recordatorio</label>
                                <input type="date" value="{{ old('n3__fecha_de_recordatorio', $user->n3__fecha_de_recordatorio) }}" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n3__fecha_de_recordatorio" name="n3__fecha_de_recordatorio">
                            </div>
                        </div>

                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n4__aacs_retirado_asociacion" class="block text-sm font-medium text-gray-700">AACS Retirado Asociación</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__aacs_retirado_asociacion" name="n4__aacs_retirado_asociacion" value="{{ old('n4__aacs_retirado_asociacion', $user->n4__aacs_retirado_asociacion ?? '') }}" placeholder="Ingrese AACS Retirado Asociación">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n4__f__entregado_genealogia" class="block text-sm font-medium text-gray-700">Fecha Entregado Genealogía</label>
                                <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__f__entregado_genealogia" name="n4__f__entregado_genealogia" value="{{ old('n4__f__entregado_genealogia', $user->n4__f__entregado_genealogia ?? '') }}">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n4__f__enviada_a_genealogia" class="block text-sm font-medium text-gray-700">Fecha Enviada a Genealogía</label>
                                <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__f__enviada_a_genealogia" name="n4__f__enviada_a_genealogia" value="{{ old('n4__f__enviada_a_genealogia', $user->n4__f__enviada_a_genealogia ?? '') }}">
                            </div>
                        </div>

                        <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="n4__fcje_certifi__descargado" class="block text-sm font-medium text-gray-700">FCJE Certificado Descargado</label>
                                <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__fcje_certifi__descargado" name="n4__fcje_certifi__descargado" value="{{ old('n4__fcje_certifi__descargado', $user->n4__fcje_certifi__descargado ?? '') }}">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n4__otros_nombres" class="block text-sm font-medium text-gray-700">Otros Nombres</label>
                                <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n4__otros_nombres" name="n4__otros_nombres" value="{{ old('n4__otros_nombres', $user->n4__otros_nombres ?? '') }}" placeholder="Ingrese Otros Nombres">
                            </div>
                            <div style="flex: 1;" class="mb-3">
                                <label for="n5__fecha_de_registro" class="block text-sm font-medium text-gray-700">Fecha de Registro</label>
                                <input type="date" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="n5__fecha_de_registro" name="n5__fecha_de_registro" value="{{ old('n5__fecha_de_registro', $user->n5__fecha_de_registro ?? '') }}">
                            </div>
                        </div>

                        @endif

                        <button type="submit" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="mypassword" role="tabpanel" aria-labelledby="mypassword-tab">
                    <form id="clientChangePasswordForm">
                        @csrf
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="password" name="password" type="password" placeholder="Ingrese su contraseña">
                            </div>
                        </div>
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="repeat_password" class="block text-sm font-medium text-gray-700">Repetir Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="repeat_password" name="repeat_password" type="password" placeholder="Repite tu contraseña">
                            </div>
                        </div>
                        <button type="button" id="clientSubmitButton" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="adminchangepassword" role="tabpanel" aria-labelledby="adminchangepassword-tab">
                    <form id="adminChangePasswordForm">
                        @csrf
                        <input type="hidden" id="id" name="id" value="{{ $user->id }}">
                        <div class="mt-2" style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <div style="flex: 1;" class="mb-3">
                                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                <input class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="password" name="password" type="password" placeholder="Ingrese su contraseña">
                            </div>
                        </div>
                        <button type="button" id="submitButton" class="cfrSefar btn btn-primary mt-3">Guardar</button>
                    </form>
                </div>

                <div class="tab-pane fade" id="familiars" role="tabpanel" aria-labelledby="familiars-tab">


                    <table id="familiarsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Nombre</th>
                                <th scope="col">Parentesco</th>
                                <th scope="col">Generacion</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $columnasparatabla as $generacion => $grupo )
                                @foreach ( $grupo as $persona)
                                    @if ($persona["showbtn"] == 2)
                                    <tr>
                                        <td>{{$persona["Nombres"]}} {{$persona["Apellidos"]}}</td>
                                        <td>{{$persona["parentesco"]}}</td>
                                        <td>{{$generacion+1}}</td>
                                    </tr>
                                    @endif
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="documents" role="tabpanel" aria-labelledby="documents-tab">
                    <table id="documentsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col">Nombre del Archivo</th>
                                <th scope="col">Ver Archivo</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $archivos as $archivo )
                                <tr>
                                    <td>{{$archivo["file"]}}</td>
                                    <td>
                                        <a href="/viewfile/{{$archivo["id"]}}" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-file"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="payments" role="tabpanel" aria-labelledby="payments-tab">

                    <table id="paymentsTable" class="min-w-full divide-y divide-gray-200 w-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col"># de Comprobante</th>
                                <th scope="col">Método de pago</th>
                                <th scope="col">Servicios contratados</th>
                                <th scope="col">Monto pagado</th>
                                <th scope="col">Ver Comprobante</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach ( $facturas as $num => $factura )
                                <tr>
                                    <td>{{$num + 1}}</td>
                                    <td>
                                        @if ($factura["met"] == "stripe")
                                            Tarjeta de Crédito/Débito
                                        @elseif ($factura["met"] == "cupon")
                                            Cupón
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $monto = 0;
                                            $totalCompras = count($factura["compras"]);
                                        @endphp
                                        @foreach($factura["compras"] as $index => $compra)
                                            @php
                                                $monto += $compra["monto"];
                                            @endphp
                                            {{$compra["servicio_hs_id"]}}
                                            @if($index < $totalCompras - 1)
                                                <br> <!-- Agregar salto de línea si no es el último -->
                                            @endif
                                        @endforeach
                                    </td>
                                    <td>
                                        {{$monto}} €
                                    </td>
                                    <td>
                                        @if(auth()->user()->roles[0]->id == 1)
                                            <a href="{{ route('viewcomprobante', ['id' => $factura['id']]) }}" target="_blank" class="btn btn-primary">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        @elseif(auth()->user()->roles[0]->id == 5)
                                            <a href="{{ route('viewcomprobantecliente', ['id' => $factura['id']]) }}" target="_blank" class="btn btn-primary">
                                                <i class="fas fa-receipt"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="tab-pane fade" id="etiquetado" role="tabpanel" aria-labelledby="etiquetado-tab">
                    <h2 class="text-1xl font-extrabold tracking-tight text-gray-900 sm:text-2xl mt-4 mb-4">
                        <span class="ctvSefar block text-indigo-600">Tablero actual: {{ $boardName }}</span>
                    </h2>

                    <form id="dynamicForm" method="POST">
                        @csrf

                        <input name='boardId' type="hidden" value='{{$boardId}}'>

                        <input name='user_id' type="hidden" value='{{$user->id}}'>
                        <!-- Ejemplo de grid con máximo 3 columnas -->

                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            @foreach ($mondayFormBuilder as $field)
                                @if (in_array($field['type'], [
                                    "name", "subtasks", "auto_number", "progress", "creation_log", "link", "integration", "item_id", "formula", "board_relation", "mirror", "email"
                                ]))
                                    @continue
                                @endif
                                @if ($field['type'] === 'long_text')
                                    <!-- Textarea abarca toda la fila -->
                                    <div style="flex: 1 1 100%;" class="mb-3">
                                        <label for="{{ $field['column_id'] }}" class="block text-sm font-medium text-gray-700">
                                            {{ $field['title'] }}
                                        </label>
                                        <textarea
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                            id="{{ $field['column_id'] }}"
                                            name="{{ $field['column_id'] }}"
                                            rows="3"
                                            placeholder="Ingrese {{ strtolower($field['title']) }}"
                                        >{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}</textarea>
                                    </div>
                                @else
                                    <!-- Otros campos -->
                                    <div style="flex: 1 1 calc(33.33% - 16px);" class="mb-3">
                                        <label for="{{ $field['column_id'] }}" class="block text-sm font-medium text-gray-700">
                                            {{ $field['title'] }}
                                        </label>

                                        @switch($field['type'])
                                            @case('text')
                                                <input
                                                    type="text"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                    placeholder="Ingrese {{ strtolower($field['title']) }}"
                                                >
                                                @break

                                            @case('date')
                                                <input
                                                    type="date"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                >
                                                @break

                                            @case('people')
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($usuariosMonday as $usuario)
                                                        <option value="{{ $usuario['email'] }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $usuario['name'] ? 'selected' : '' }}>
                                                            {{ $usuario['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @case('dropdown')
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($field['settings']['labels'] ?? [] as $option)
                                                        <option value="{{ $option['name'] }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $option['name'] ? 'selected' : '' }}>
                                                            {{ $option['name'] }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @case('status')
                                                <select
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                >
                                                    <option value="" disabled selected>Seleccione {{ strtolower($field['title']) }}</option>
                                                    @foreach ($field['settings']['labels'] ?? [] as $key => $label)
                                                        <option value="{{ $label }}"
                                                            {{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') == $label ? 'selected' : '' }}>
                                                            {{ $label }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @default
                                                <input
                                                    type="text"
                                                    class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                                    id="{{ $field['column_id'] }}"
                                                    name="{{ $field['column_id'] }}"
                                                    value="{{ old($field['column_id'], $dataMonday[$field['column_id']] ?? '') }}"
                                                    placeholder="Ingrese {{ strtolower($field['title']) }}"
                                                >
                                        @endswitch
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <!-- Botón de envío -->
                        <div class="mt-3">
                            <button type="button" id="etiquetadosend" class="bg-indigo-600 text-white px-4 py-2 rounded-md">
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <style>
    /* Estilos de la tabla y el switch */
    table.dataTable, .dataTables_scrollHeadInner {
        width: 100% !important;
    }
    table.dataTable th, table.dataTable td {
        font-size: 1rem !important;
        padding: 10px 5px;
    }
    .switch {
        position: relative;
        display: inline-block;
        width: 60px;
        height: 34px;
    }
    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .4s;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 26px;
        width: 26px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
    }
    input:checked + .slider {
        background-color: #093143 !important;
    }
    input:checked + .slider:before {
        transform: translateX(26px);
    }
    .slider.round {
        border-radius: 34px;
    }
    .slider.round:before {
        border-radius: 50%;
    }
    div.dt-row {
        margin:10px 0px;
    }
</style>
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha3/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables CSS para Bootstrap 4 -->
<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cleave.js/1.6.0/cleave.min.js"></script>
<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- DataTables CSS para Bootstrap 4 -->

<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function () {
        $('#etiquetadosend').on('click', function (e) {
            e.preventDefault(); // Previene el comportamiento predeterminado del botón

            // Obtiene los datos del formulario
            var form = $('#dynamicForm');
            var formData = new FormData(form[0]);

            // Realiza la petición AJAX
            $.ajax({
                url: '{{ route("etiquetasgenealogiamonday") }}', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese los datos
                contentType: false, // Evita que jQuery establezca el tipo de contenido automáticamente
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja la respuesta exitosa
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: 'Los cambios fueron guardados correctamente.'
                    });
                },
                error: function (xhr) {
                    // Maneja errores
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        // Evita el comportamiento predeterminado del formulario
        $('#dynamicForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#clientSubmitButton').on('click', function (e) {
            e.preventDefault(); // Evita el comportamiento predeterminado

            // Validación básica en el frontend
            var password = $('#password').val();
            var repeatPassword = $('#repeat_password').val();

            if (!password || password.length < 8) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'La contraseña debe tener al menos 8 caracteres.'
                });
                return;
            }

            if (password !== repeatPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Las contraseñas no coinciden.'
                });
                return;
            }

            // Obtén los datos del formulario
            var form = $('#clientChangePasswordForm');
            var formData = new FormData(form[0]);

            // Realiza la petición AJAX
            $.ajax({
                url: '/changepassword', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese automáticamente los datos
                contentType: false, // Evita que jQuery establezca automáticamente el Content-Type
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja una respuesta exitosa
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Contraseña actualizada',
                            text: 'La contraseña se cambió correctamente.'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Ocurrió un error al actualizar la contraseña.'
                        });
                    }
                },
                error: function (xhr) {
                    // Maneja errores en la petición
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        // Desactiva el comportamiento predeterminado del formulario en caso de envío accidental
        $('#clientChangePasswordForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#submitButton').on('click', function (e) {
            e.preventDefault(); // Evita el comportamiento predeterminado

            // Obtén los datos del formulario
            var form = $('#adminChangePasswordForm');
            var formData = new FormData(form[0]); // jQuery para acceder al formulario

            // Realiza la petición AJAX
            $.ajax({
                url: '/adminchangepassword', // Ruta al backend
                type: 'POST',
                data: formData,
                processData: false, // Evita que jQuery procese automáticamente los datos
                contentType: false, // Evita que jQuery establezca automáticamente el Content-Type
                headers: {
                    'X-CSRF-TOKEN': $('input[name="_token"]').val() // Incluye el token CSRF
                },
                success: function (response) {
                    // Maneja una respuesta exitosa
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Contraseña actualizada',
                            text: 'La contraseña se cambió correctamente.'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Ocurrió un error al actualizar la contraseña.'
                        });
                    }
                },
                error: function (xhr) {
                    // Maneja errores en la petición
                    var errors = xhr.responseJSON?.errors || {};
                    var messages = Object.values(errors).flat().join(' ') || 'Ocurrió un error inesperado.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: messages
                    });
                }
            });
        });

        // Desactiva el comportamiento predeterminado del formulario en caso de envío accidental
        $('#adminChangePasswordForm').on('submit', function (e) {
            e.preventDefault();
        });

        $('#familiarsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#paymentsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
        $('#documentsTable').DataTable({
            "language": {
                "lengthMenu": "Mostrar _MENU_ resultados por página",
                "zeroRecords": "No hay resultados",
                "info": "Página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay resultados"
            }
        });
    });
</script>

@stop

@extends('adminlte::page')

@section('title', $user->name)

@section('content_header')

@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div>
                    <!-- This example requires Tailwind CSS v2.0+ -->
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
                                                    {{ __('Users list') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- Fin --}}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-10 sm:mt-0">
        <div class="md:grid md:grid-cols-3 md:gap-6">
            <div class="md:col-span-1">
                <div class="px-4 sm:px-0">
                    <h3 class="text-lg font-medium leading-6 text-gray-900">{{ __('User information') }} <small>(ID: {{ $user->id }})</small></h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Manage user roles') }}
                    </p>
                    <div class="flex-shrink-0 h-52 w-52 mt-4">
                        <img class="h-52 w-52 rounded-full" src="{{ $user->profile_photo_url }}" alt="">
                    </div>
                </div>
            </div>
            <div class="mt-5 md:mt-0 md:col-span-2">
                <form action="{{ route('crud.users.update', $user) }}" method="POST">

                    @csrf
                    @method('put')

                    <div class="shadow overflow-hidden sm:rounded-md">
                        <div class="px-4 py-5 bg-white sm:p-6">
                            <div class="grid grid-cols-6 gap-6">
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="name" class="block text-sm font-medium text-gray-700">{{ __('User name') }}</label>
                                    <input
                                        type="text"
                                        name="name"
                                        id="name"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                        value="{{ old('name', $user->name) }}"
                                    />
                                </div>
                                @error('name')
                                    <div class="col-span-12 sm:col-span-12">
                                        <small style="color:red">*{{ $message }}*</small>
                                    </div>
                                @enderror

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="passport" class="block text-sm font-medium text-gray-700">{{ __('Passport') }}</label>
                                    <input
                                        type="text"
                                        name="passport"
                                        id="passport"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                        value="{{ old('passport', $user->passport) }}"
                                    />
                                </div>
                                @error('passport')
                                    <div class="col-span-12 sm:col-span-12">
                                        <small style="color:red">*{{ $message }}*</small>
                                    </div>
                                @enderror

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email address') }}</label>
                                    <input
                                        type="email"
                                        name="email"
                                        id="email"
                                        autocomplete="email"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                        value="{{ old('email', $user->email) }}"
                                    />
                                </div>
                                @error('email')
                                    <div class="col-span-12 sm:col-span-12">
                                        <small style="color:red">*{{ $message }}*</small>
                                    </div>
                                @enderror

                                {{-- <div class="col-span-6 sm:col-span-4">
                                    <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Update password') }}</label>
                                    <input
                                        type="paswword"
                                        name="password"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                    />
                                </div> --}}

                                <div class="col-span-6 sm:col-span-3">
                                    <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Change Password') }}</label>
                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                        placeholder="No rellenar para conservar contraseña"
                                    />
                                </div>

                                {{-- Estatus del pago --}}
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="pay" class="block text-sm font-medium text-gray-700">{{ __('Payment status') }}</label>
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
                                </div>

                                {{-- Servicio --}}
                                <div class="col-span-6 sm:col-span-3">
                                    <label for="servicio" class="block text-sm font-medium text-gray-700">{{ __('Servicio') }}</label>
                                    <select name="servicio" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                        <option></option>
                                        @php
                                            echo '<option ' . (($user->servicio == 'Española - Carta de Naturaleza') ? 'selected' : '') . '>Española - Carta de Naturaleza</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Española LMD') ? 'selected' : '') . '>Española LMD</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Española Sefardi') ? 'selected' : '') . '>Española Sefardi</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Española Sefardi - Subsanación') ? 'selected' : '') . '>Española Sefardi - Subsanación</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Italiana') ? 'selected' : '') . '>Italiana</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Ley Memoria Democratica') ? 'selected' : '') . '>Ley Memoria Democratica</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Portuguesa Sefardi') ? 'selected' : '') . '>Portuguesa Sefardi</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Portuguesa Sefardi - Subsanación') ? 'selected' : '') . '>Portuguesa Sefardi - Subsanación</option>'
                                        @endphp

                                        @php
                                            echo '<option ' . (($user->servicio == 'Análisis por semana') ? 'selected' : '') . '>Análisis por semana</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Recurso de Alzada') ? 'selected' : '') . '>Recurso de Alzada</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Gestión Documental') ? 'selected' : '') . '>Gestión Documental</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Constitución de Empresa') ? 'selected' : '') . '>Constitución de Empresa</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Representante Fiscal') ? 'selected' : '') . '>Representante Fiscal</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Codigo  Fiscal') ? 'selected' : '') . '>Codigo  Fiscal</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Apertura de cuenta') ? 'selected' : '') . '>Apertura de cuenta</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Trimestre contable') ? 'selected' : '') . '>Trimestre contable</option>'
                                        @endphp

                                        @php
                                            echo '<option ' . (($user->servicio == 'Cooperativa 10 años') ? 'selected' : '') . '>Cooperativa 10 años</option>'
                                        @endphp
                                        @php
                                            echo '<option ' . (($user->servicio == 'Cooperativa 5 años') ? 'selected' : '') . '>Cooperativa 5 años</option>'
                                        @endphp
                                    </select>
                                </div>

                                {{-- Desactivar verificación en dos pasos --}}
                                <div class="col-span-8 md:col-span-4 col-start-1">
                                    @if ($user->two_factor_secret)
                                        <input name="two_factor" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        <label for="verif2pasos" class="m-1 text-gray-500">Desactivar verificación en dos pasos</label>
                                    @else
                                        <input name="two_factor" type="checkbox" class="focus:ring-gray-300 h-4 w-4 text-gray-200 border-gray-100 rounded" checked disabled>
                                        <label for="verif2pasos" class="m-1 text-gray-300">Desactivar verificación en dos pasos</label>
                                    @endif
                                </div>
                            </div>
                            {{-- Servicios solicitados por el usuario --}}
                            <div class="container my-10">
                                <p class="my-2 block text-sm font-medium text-gray-700"><strong>Servicios Solicitados por el Usuario:<strong></p>
                                <div style="width: 95%; padding-left: 30px; font-size: 13px; font-weight: normal;">
                                @foreach ($compras as $compra)
                                    <li>
                                        @if ($compra["pagado"] == 1)
                                            <b>{{$compra["descripcion"]}} (Pagó)</b><br>
                                        @else
                                            {{$compra["descripcion"]}} (No Pagó)<br>
                                        @endif
                                    </li>
                                @endforeach
                                </div>
                            </div>
                            {{-- ROLES --}}
                            <div class="container my-10">
                                <p class="my-2 block text-sm font-medium text-gray-700"><strong>Roles del usuario:<strong></p>
                                <div class="grid grid-cols-1 xl:grid-cols-4">
                                @foreach ($roles as $role)
                                    <div class="col-span-2 sm:col-span-2">
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                @if ($user->hasRole($role->name))
                                                <input name="{{ "role" . $role->id }}" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded" checked>
                                                @else
                                                <input name="{{ "role" . $role->id }}" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                                @endif
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="{{ "role" . $role->id }}" class="font-medium text-gray-700">{{ $role->name }}</label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                </div>
                            </div>
                            {{-- PERMISOS --}}
                            <div class="container my-10">
                                <p class="my-2 block text-sm font-medium text-gray-700"><strong>Permisos del usuario:<strong></p>
                                <div class="grid grid-cols-1 xl:grid-cols-4">
                                @foreach ($permissions as $permission)
                                    <div class="col-span-2 sm:col-span-2">
                                        <div class="flex items-start">
                                            <div class="flex items-center h-5">
                                                @if ($user->hasPermissionTo($permission->name))
                                                <input name="{{ "permiso" . $permission->id }}" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded" checked>
                                                @else
                                                <input name="{{ "permiso" . $permission->id }}" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                                @endif
                                            </div>
                                            <div class="ml-3 text-sm">
                                                <label for="{{ "permiso" . $permission->id }}" class="font-medium text-gray-700">{{ $permission->name }}</label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                                </div>
                            </div>
                        </div>
                    <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                        <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            {{ __('Update') }}
                        </button>
                    </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

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
                    {{-- Ejemplo de URL a generar --}}
                    {{-- https://app.universalsefar.com/register?apellidos=XXXX&nombres=XXXX&email=XXXX&dni=XXXX&servicio=XXXX --}}
                    <div class="px-1 py-2 m-2 flex-1">    {{-- firtsname --}}
                        <div>
                            <label for="firtsname" class="block text-sm font-medium text-gray-700">Nombres</label>
                            <input value="Fulanito" type="text" name="firtsname" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div class="px-1 py-2 m-2 flex-1">    {{-- lastname --}}
                        <div>
                            <label for="lastname" class="block text-sm font-medium text-gray-700">Apellidos</label>
                            <input value="Detal y Borrar" type="text" name="lastname" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div class="px-1 py-2 m-2 flex-1">    {{-- email --}}
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">e-mail</label>
                            <input value="delete.borrar@gmail.com" type="email" name="email" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div class="px-1 py-2 m-2 flex-1">    {{-- phone --}}
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                            <input value="+584141249753" type="text" name="phone" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div class="px-1 py-2 m-2 flex-1">    {{-- numero_de_pasaporte --}}
                        <div>
                            <label for="numero_de_pasaporte" class="block text-sm font-medium text-gray-700">DNI</label>
                            <input value="BORRAR123" type="text" name="numero_de_pasaporte" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                        </div>
                    </div>

                    <div class="px-1 py-2 m-2 flex-1">    {{-- nacionalidad_solicitada --}}
                        <div>
                            <label for="nacionalidad_solicitada" class="block text-sm font-medium text-gray-700">Servicio</label>
                            <input value="1" type="text" name="nacionalidad_solicitada" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
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

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

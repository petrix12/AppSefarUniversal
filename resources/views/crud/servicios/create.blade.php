@extends('adminlte::page')

@section('title', 'Añadir Servicio')

@section('content_header')

@stop

@section('content')


<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div>
                    <div class="flex flex-col">
                        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                                <div class="bg-gray-50">
                                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                            <span class="ctvSefar block text-indigo-600">Registrar Servicio</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.servicios.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    Listar Servicios
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                                {{-- Diseñar formulario - Inicio --}}
                                <form action="{{ route('crud.servicios.store') }}" method="POST">

                                    @csrf
                                    {{-- RUTA QUE LO INVOCA --}}
                                    <div class="shadow overflow-hidden sm:rounded-md">

                                        <div class="container">
                                            <div class="md:flex ms:flex-wrap">
                                                <div class="px-1 py-2 m-2 flex-1">    {{-- id_hubspot --}}
                                                    <div>
                                                        <label for="couponcode" class="block text-sm font-medium text-gray-700">ID de Hubspot</label>
                                                        <input value="{{ old('id_hubspot') }}" type="text" name="id_hubspot" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                                        @error('id_hubspot')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="px-1 py-2 m-2 flex-1">    {{-- nombre --}}
                                                    <div>
                                                        <label for="nombre" class="block text-sm font-medium text-gray-700">Nombre del Servicio</label>
                                                        <input value="{{ old('nombre') }}" type="text" name="nombre" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                                        @error('nombre')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="px-1 py-2 m-2 flex-1">    {{-- precio --}}
                                                    <div>
                                                        <label for="expire" class="block text-sm font-medium text-gray-700">Precio en Euros</label>
                                                        <input value="{{ old('precio') }}" type="number" name="precio" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                                        @error('precio')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="px-1 py-2 m-2 flex-1">    {{-- tipov --}}
                                                    <div>
                                                        <label for="expire" class="block text-sm font-medium text-gray-700">¿Es servicio de Vinculación?</label>
                                                        <select class="form-control" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" name="tipov" required>
                                                            <option value="" disabled selected>Seleccione una opción</option>
                                                            <option value="1" {{ old('tipov') == 1 ? 'selected' : '' }}>Si</option>
                                                            <option value="0" {{ old('tipov') == 0 ? 'selected' : '' }}>No</option>
                                                        </select>
                                                        @error('tipov')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Registrar Servicio
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                {{-- Diseñar formulario - Fin --}}
                            </div>
                        </div>
                    </div>
                </div>
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
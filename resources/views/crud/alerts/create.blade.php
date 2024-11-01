@extends('adminlte::page')

@section('title', 'Añadir Alerta')

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
                                            <span class="ctvSefar block text-indigo-600">Registrar Alerta</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('alerts.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    Listar Alertas
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
                                {{-- Formulario de Alerta - Inicio --}}
                                <form action="{{ route('alerts.store') }}" method="POST" enctype="multipart/form-data">

                                    @csrf

                                    <div class="shadow overflow-hidden sm:rounded-md">
                                        <div class="container">
                                            {{-- Primera Fila: Título e Imagen --}}
                                            <div class="md:flex ms:flex-wrap">
                                                {{-- Título --}}
                                                <div class="px-1 py-2 m-2 flex-1">
                                                    <label for="title" class="block text-sm font-medium text-gray-700">Título</label>
                                                    <input value="{{ old('title') }}" type="text" name="title" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                                    @error('title')
                                                        <small style="color:red">*{{ $message }}*</small>
                                                    @enderror
                                                </div>

                                                {{-- Imagen --}}
                                                <div class="px-1 py-2 m-2 flex-1">
                                                    <label for="image" class="block text-sm font-medium text-gray-700">Imagen</label>
                                                    <input type="file" name="image" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" accept="image/*" required>
                                                    @error('image')
                                                        <small style="color:red">*{{ $message }}*</small>
                                                    @enderror
                                                </div>
                                            </div>

                                            {{-- Segunda Fila: Fechas de Inicio y Fin --}}
                                            <div class="md:flex ms:flex-wrap">
                                                {{-- Fecha de Inicio --}}
                                                <div class="px-1 py-2 m-2 flex-1">
                                                    <label for="start_date" class="block text-sm font-medium text-gray-700">Fecha de Inicio</label>
                                                    <input value="{{ old('start_date', date('Y-m-d')) }}" type="date" name="start_date" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                                    @error('start_date')
                                                        <small style="color:red">*{{ $message }}*</small>
                                                    @enderror
                                                </div>

                                                {{-- Fecha de Fin --}}
                                                <div class="px-1 py-2 m-2 flex-1">
                                                    <label for="end_date" class="block text-sm font-medium text-gray-700">Fecha de Fin</label>
                                                    <input value="{{ old('end_date') }}" type="date" name="end_date" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" required>
                                                    @error('end_date')
                                                        <small style="color:red">*{{ $message }}*</small>
                                                    @enderror
                                                </div>
                                            </div>

                                            {{-- Tercera Fila: Texto --}}
                                            <div class="md:flex ms:flex-wrap">
                                                {{-- Texto --}}
                                                <div class="px-1 py-2 m-2 flex-1">
                                                    <label for="text" class="block text-sm font-medium text-gray-700">Texto</label>
                                                    <textarea name="text" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">{{ old('text') }}</textarea>
                                                    @error('text')
                                                        <small style="color:red">*{{ $message }}*</small>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>

                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Registrar Alerta
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                {{-- Formulario de Alerta - Fin --}}
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

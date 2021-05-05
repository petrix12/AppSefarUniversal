@extends('adminlte::page')

@section('title', 'Añadir Documento')

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
                                            <span class="ctvSefar block text-indigo-600">{{ __('Add document') }}</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.libraries.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    {{ __('List of documents') }}
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
                                <form action="{{ route('crud.libraries.store') }}" method="POST">

                                    @csrf
                                    {{-- RUTA QUE LO INVOCA --}}
                                    <input type="hidden" name="urlPrevia" value="{{ redirect()->getUrlGenerator()->previous() }}">
                                    <div class="shadow overflow-hidden sm:rounded-md">
                                            <div class="container">
                                                {{-- Fila 1: Datos principales --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- documento --}}
                                                        <div>
                                                            <label for="documento" class="block text-sm font-medium text-gray-700">Documento</label>
                                                            <input value="{{ old('documento') }}" type="text" name="documento" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('documento')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- tipo --}}
                                                        <label for="tipo" class="block text-sm font-medium text-gray-700" title="Tipo de documento">Tipo</label>
                                                        <select name="tipo" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                            <option></option>
                                                            @foreach ($t_files as $t_file)
                                                                @if (old('tipo') == $t_file->tipo)
                                                                    <option selected>{{ $t_file->tipo }}</option>
                                                                @else
                                                                    <option>{{ $t_file->tipo }}</option> 
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1/2">    {{-- formato --}}
                                                        <label for="formato" class="block text-sm font-medium text-gray-700" title="Formato del documento">Formato</label>
                                                        <select name="formato" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                            <option></option>
                                                            @foreach ($formats as $format)
                                                                @if (old('formato') == $format->formato)
                                                                    <option selected>{{ $format->formato }}</option>
                                                                @else
                                                                    <option>{{ $format->formato }}</option> 
                                                                @endif
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>

                                                {{-- Fila 2 --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- fuente --}}
                                                        <div>
                                                            <label for="fuente" class="block text-sm font-medium text-gray-700">Fuente personal o institucional</label>
                                                            <input value="{{ old('fuente') }}" type="text" name="fuente" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('fuente')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- origen --}}
                                                        <div>
                                                            <label for="origen" class="block text-sm font-medium text-gray-700">Origen del documento</label>
                                                            <input value="{{ old('origen') }}" type="text" name="origen" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('origen')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 3 --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- ubicacion --}}
                                                        <div>
                                                            <label for="ubicacion" class="block text-sm font-medium text-gray-700">Ubicación en Google Drive</label>
                                                            <input value="{{ old('ubicacion') }}" type="text" name="ubicacion" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('ubicacion')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- ubicacion_ant --}}
                                                        <div>
                                                            <label for="ubicacion_ant" class="block text-sm font-medium text-gray-700">Ubicación anterior en Google Drive</label>
                                                            <input value="{{ old('ubicacion_ant') }}" type="text" name="ubicacion_ant" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('ubicacion_ant')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 4 --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- busqueda --}}
                                                        <div>
                                                            <label for="busqueda" class="block text-sm font-medium text-gray-700" title="Palabras claves para búsqueda">Palabras claves para búsqueda</label>
                                                            <textarea name="busqueda" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Escriba aquí palabras claves">{{ old('busqueda') }}</textarea>
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- notas --}}
                                                        <div>
                                                            <label for="notas" class="block text-sm font-medium text-gray-700" title="Notas">Notas</label>
                                                            <textarea name="notas" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Notas">{{ old('notas') }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 5 --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- enlace --}}
                                                        <div>
                                                            <label for="enlace" class="block text-sm font-medium text-gray-700">Enlace</label>
                                                            <input value="{{ old('enlace') }}" type="url" name="enlace" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('enlace')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
        
                                                    <div class="px-1 py-2 m-2 flex">    {{-- anho_ini --}}
                                                        <div>
                                                            <label for="anho_ini" class="block text-sm font-medium text-gray-700" title="Año inicial al que hace referencia el documento">Año inicial</label>
                                                            <input value="{{ old('anho_ini') }}" type="number" name="anho_ini" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('anho_ini')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
        
                                                    <div class="px-1 py-2 m-2 flex">    {{-- anho_fin --}}
                                                        <div>
                                                            <label for="anho_fin" class="block text-sm font-medium text-gray-700" title="Año final al que hace referencia el documento">Año final</label>
                                                            <input value="{{ old('anho_fin') }}" type="number" name="anho_fin" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('anho_fin')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Añadir documento
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
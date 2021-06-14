@extends('adminlte::page')

@section('title', 'Añadir Miscelaneo')

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
                                            <span class="ctvSefar block text-indigo-600">{{ __('Add Miscellaneou') }}</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.miscelaneos.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    {{ __('Miscellaneou list') }}
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
                                <form action="{{ route('crud.miscelaneos.store') }}" method="POST">

                                    @csrf
                                    {{-- RUTA QUE LO INVOCA --}}
                                    <input type="hidden" name="urlPrevia" value="{{ redirect()->getUrlGenerator()->previous() }}">
                                    <div class="shadow overflow-hidden sm:rounded-md">
                                            <div class="container">
                                                {{-- Fila 1: Datos principales --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- titulo --}}
                                                        <div>
                                                            <label for="titulo" class="block text-sm font-medium text-gray-700">Título</label>
                                                            <input value="{{ old('titulo') }}" type="text" name="titulo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('titulo')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- material --}}
                                                        <div>
                                                            <label for="material" class="block text-sm font-medium text-gray-700" title="Tipo de material">Tipo de material</label>
                                                            <select name="material" autocomplete="on" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @if (old('material') == "Artículo de publicación periódica")
                                                                    <option title="Artículo de publicación periódica" selected>Artículo de publicación periódica</option>
                                                                @else
                                                                    <option title="Artículo de publicación periódica">Artículo de publicación periódica</option>
                                                                @endif
                                                                
                                                                @if (old('material') == "Capítulo de libro")
                                                                    <option title="Capítulo de libro" selected>Capítulo de libro</option>
                                                                @else
                                                                    <option title="Capítulo de libro">Capítulo de libro</option>
                                                                @endif
                                                                
                                                                @if (old('material') == "Material genealógico")
                                                                    <option title="Material genealógico" selected>Material genealógico</option>
                                                                @else
                                                                    <option title="Material genealógico">Material genealógico</option>
                                                                @endif
                                                                
                                                                @if (old('material') == "Informes de Sefar")
                                                                    <option title="Informes de Sefar" selected>Informes de Sefar</option>
                                                                @else
                                                                    <option title="Informes de Sefar">Informes de Sefar</option>
                                                                @endif
                                                                
                                                                @if (old('material') == "Otros")
                                                                    <option title="Otros" selected>Otros</option>
                                                                @else
                                                                    <option title="Otros">Otros</option>
                                                                @endif
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 2 --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- autor --}}
                                                        <div>
                                                            <label for="autor" class="block text-sm font-medium text-gray-700">Autor</label>
                                                            <input value="{{ old('autor') }}" type="text" name="autor" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('autor')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- editorial --}}
                                                        <div>
                                                            <label for="editorial" class="block text-sm font-medium text-gray-700">Ciudad / Editorial</label>
                                                            <input value="{{ old('editorial') }}" type="text" name="editorial" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('editorial')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- publicado --}}
                                                        <div>
                                                            <label for="publicado" class="block text-sm font-medium text-gray-700">Lugar de publicación</label>
                                                            <input value="{{ old('publicado') }}" type="text" name="publicado" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('publicado')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 3 --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- volumen --}}
                                                        <div>
                                                            <label for="volumen" class="block text-sm font-medium text-gray-700">Año / Número / Volumen</label>
                                                            <input value="{{ old('volumen') }}" type="text" name="volumen" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('volumen')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1/2">    {{-- paginacion --}}
                                                        <div>
                                                            <label for="paginacion" class="block text-sm font-medium text-gray-700">Paginación</label>
                                                            <input value="{{ old('paginacion') }}" type="text" name="paginacion" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('paginacion')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1/2">    {{-- isbn --}}
                                                        <div>
                                                            <label for="isbn" class="block text-sm font-medium text-gray-700">ISBN / ISSN</label>
                                                            <input value="{{ old('isbn') }}" type="text" name="isbn" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('isbn')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 4 --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- claves --}}
                                                        <div>
                                                            <label for="claves" class="block text-sm font-medium text-gray-700" title="Palabras claves para búsqueda">Palabras clave</label>
                                                            <textarea name="claves" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Escriba aquí palabras claves">{{ old('claves') }}</textarea>
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
                                                </div>

                                                {{-- Fila 6 --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- catalogador --}}
                                                        <div>
                                                            <label class="block text-sm font-medium text-blue-700">Catalogado por: {{ Auth()->user()->email }}</label>
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
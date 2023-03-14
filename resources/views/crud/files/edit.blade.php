@extends('adminlte::page')

@section('title', 'Editar documento')

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
                                            <span class="ctvSefar block text-indigo-600">{{ __('Edit file') }}</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.files.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    {{ __('File list') }}
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
                                <form action="{{ route('crud.files.update', $file) }}" method="POST" enctype="multipart/form-data">

                                    @csrf
                                    @method('put')

                                    <div class="shadow overflow-hidden sm:rounded-md">
                                            <div class="container">
                                                {{-- Fila 1: Documento --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- nfile --}}
                                                        <div>
                                                            <label for="nfile" class="block text-sm font-medium text-gray-700">Nombre del documento</label>
                                                            <input value="{{ old('nfile', $file->file) }}" type="text" name="nfile" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('nfile')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    @can('administrar.documentos')
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- IDCliente --}}
                                                        <div>
                                                            <label for="IDCliente" class="block text-sm font-medium text-gray-700" title="ID de cliente a quien pertenece el documento">ID Cliente</label>
                                                            <input value="{{ old('IDCliente', $IDCliente) }}" type="text" name="IDCliente" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('IDCliente')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    @endcan

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- tipo --}}
                                                        <div>
                                                            <label for="tipo" class="block text-sm font-medium text-gray-700" title="Tipo de documento">Tipo de documento</label>
                                                            <select name="tipo" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($t_files as $t_file)
                                                                    @if (old('tipo', $file->tipo) == $t_file->tipo)
                                                                        <option selected>{{ $t_file->tipo }}</option>
                                                                    @else
                                                                        <option>{{ $t_file->tipo }}</option>
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- IDPersona --}}
                                                        <div>
                                                            <label for="IDPersona" class="block text-sm font-medium text-gray-700">Persona</label>
                                                            <select name="IDPersona" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                @for ($i = 1; $i <=31; $i++)
                                                                    @if ( old('IDPersona', $file->IDPersona) == $i)
                                                                        <option value="{{ $i }}" selected>{{ GetPersona($i) }}</option>
                                                                    @else
                                                                        <option value="{{ $i }}">{{ GetPersona($i) }}</option>
                                                                    @endif
                                                                @endfor
                                                            </select>
                                                            @error('IDPersona')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 2: Archivo --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- file --}}
                                                        <div>
                                                            <input  type="file"
                                                                    id="file"
                                                                    name="file"
                                                                    style="display: none"
                                                                    accept="application/pdf, .doc, .docx, .odf, .xls, .xlsx, .ppt, .pptx, .txt,image/*"
                                                                    {{-- value="{{ old('file', $file->location.'/'.$file->file) }}" --}}
                                                                    value="{{ old('file', storage_path().'/app/'.$file->location.'/'.$file->file) }}"
                                                                    {{-- @dump(storage_path().'/app/'.$file->location.'/'.$file->file)) --}}
                                                            />
                                                            <label for="file" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white">
                                                                <i class="fas fa-upload mr-2"></i> archivo.png
                                                            </label>
                                                            @error('file')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 3: Notas --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- notas --}}
                                                        <div>
                                                            <label for="notas" class="block text-sm font-medium text-gray-700" title="Notas">Notas</label>
                                                            <textarea name="notas" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Notas...">{{ old('notas', $file->notas) }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Actualizar archivo
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

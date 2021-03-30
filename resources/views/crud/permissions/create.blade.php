@extends('adminlte::page')

@section('title', 'Crear Permisos')

@section('content_header')
    {{-- <h1><strong>{{ __('Permisos de usuarios') }}</strong></h1> --}}
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
                                            <span class="ctvSefar block text-indigo-600">{{ __('Create permission') }}</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.permissions.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    {{ __('Permission list') }}
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                {{-- Fin --}}
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col">
                        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                                {{-- Diseñar formulario - Inicio --}}
                                <form action="{{ route('crud.permissions.store') }}" method="POST">
                                    @csrf
                                    <div class="shadow overflow-hidden sm:rounded-md">
                                        <div class="px-4 py-5 bg-white sm:p-6">
                                            <div class="grid grid-cols-6 gap-6">
                                                <div class="col-span-12 sm:col-span-12">
                                                    <label for="name" class="block text-sm font-medium text-gray-700">Nombre del permiso</label>
                                                    <input type="text" name="name" id="name" autocomplete="given-name" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                </div>
                                                @error('name')
                                                    <div class="col-span-12 sm:col-span-12">
                                                        <small style="color:red">*{{ $message }}*</small>
                                                    </div>
                                                @enderror

                                                <div class="col-span-12 sm:col-span-12">
                                                    <label for="" class="block text-sm font-medium text-gray-700" title="Indicar los roles a los cuales aplica el permiso">Roles a asignarle al permiso</label>
                                                </div>
                                                    @foreach ($roles as $role)
                                                    <div class="col-span-12 sm:col-span-12">     
                                                        <div class="flex items-start">
                                                            <div class="flex items-center h-5">
                                                                <input name="{{ "role" . $role->id }}" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                                            </div>
                                                            <div class="ml-3 text-sm">
                                                                <label for="{{ "role" . $role->id }}" class="font-medium text-gray-700">{{ $role->name }}</label>
                                                            </div>
                                                        </div> 
                                                    </div>
                                                    @endforeach      
                                            </div>
                                        </div>
                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Crear permiso
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
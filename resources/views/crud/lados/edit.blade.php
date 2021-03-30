@extends('adminlte::page')

@section('title', 'Editar lado del parentesco')

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
                                            <span class="ctvSefar block text-indigo-600">{{ __('Edit side') }}</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.lados.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    {{ __('Side list') }}
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
                                <form action="{{ route('crud.lados.update', $lado) }}" method="POST">

                                    @csrf
                                    @method('put')

                                    <div class="shadow overflow-hidden sm:rounded-md">
                                        <div class="px-4 py-5 bg-white sm:p-6">
                                            <div class="grid grid-cols-6 gap-6">
                                                <div class="col-span-12 sm:col-span-12">
                                                    <label for="Lado" class="block text-sm font-medium text-gray-700">Lado del parentesco</label>
                                                    <input  type="text" 
                                                            name="Lado"
                                                            autocomplete="on"
                                                            value="{{ old('name', $lado->Lado) }}"
                                                            class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                    />
                                                </div>
                                                @error('Lado')
                                                    <div class="col-span-12 sm:col-span-12">
                                                        <small style="color:red">*{{ $message }}*</small>
                                                    </div>
                                                @enderror

                                                <div class="col-span-12 sm:col-span-12">
                                                    <label for="Significado" class="block text-sm font-medium text-gray-700">Significado del lado del parentesco</label>
                                                    <input  type="text" 
                                                            name="Significado"
                                                            autocomplete="on"
                                                            value="{{ old('name', $lado->Significado) }}"
                                                            class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                                                    />
                                                </div>
                                                @error('Significado')
                                                    <div class="col-span-12 sm:col-span-12">
                                                        <small style="color:red">*{{ $message }}*</small>
                                                    </div>
                                                @enderror     
                                            </div>
                                        </div>
                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Actualizar lado
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
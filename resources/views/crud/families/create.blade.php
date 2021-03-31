@extends('adminlte::page')

@section('title', 'Añadir familiar')

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
                                            <span class="ctvSefar block text-indigo-600">{{ __('Add family member') }}</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.families.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    {{ __('Client family client list') }}
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
                                <form action="{{ route('crud.families.store') }}" method="POST">

                                    @csrf
                                    
                                    <div class="shadow overflow-hidden sm:rounded-md">
                                            <div class="container">
                                                {{-- Fila 1: Cliente --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex">    {{-- IDCliente --}}
                                                        <div>
                                                            <label for="IDCliente" class="block text-sm font-medium text-gray-700">ID Cliente</label>
                                                            <input value="{{ old('IDCliente') }}" type="text" name="IDCliente" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('IDCliente')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Nombres --}}
                                                        <div>
                                                            <label for="Cliente" class="block text-sm font-medium text-gray-700">Nombre del cliente</label>
                                                            <input value="{{ old('Cliente') }}" type="text" name="Cliente" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('Cliente')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 2: Familiar --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex">    {{-- IDFamiliar --}}
                                                        <div>
                                                            <label for="IDFamiliar" class="block text-sm font-medium text-gray-700">ID Familiar</label>
                                                            <input value="{{ old('IDFamiliar') }}" type="text" name="IDFamiliar" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('IDFamiliar')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Familiar --}}
                                                        <div>
                                                            <label for="Familiar" class="block text-sm font-medium text-gray-700">Nombre del familiar</label>
                                                            <input value="{{ old('Familiar') }}" type="text" name="Familiar" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('Familiar')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 3: Parentesco --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Parentesco --}}
                                                        <div>
                                                            <label for="Parentesco" class="block text-sm font-medium text-gray-700" title="Parentesco">Parentesco</label>
                                                            <select name="Parentesco" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($parentescos as $parentesco)
                                                                    @if (old('Parentesco') == $parentesco->Parentesco)
                                                                        <option selected>{{ $parentesco->Parentesco }}</option>
                                                                    @else
                                                                        <option>{{ $parentesco->Parentesco }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Lado --}}
                                                        <div>
                                                            <label for="Lado" class="block text-sm font-medium text-gray-700" title="Lado">Lado</label>
                                                            <select name="Lado" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($lados as $lado)
                                                                    @if (old('Lado') == $lado->Lado)
                                                                        <option selected>{{ $lado->Lado }}</option>
                                                                    @else
                                                                        <option>{{ $lado->Lado }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Rama --}}
                                                        <div>
                                                            <label for="Rama" class="block text-sm font-medium text-gray-700" title="Rama">Rama</label>
                                                            <select name="Rama" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($connections as $connection)
                                                                    @if (old('Rama') == $connection->Conexion)
                                                                        <option selected>{{ $connection->Conexion }}</option>
                                                                    @else
                                                                        <option>{{ $connection->Conexion }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 4: Nota --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Nota --}}
                                                        <div>
                                                            <label for="Nota" class="block text-sm font-medium text-gray-700" title="Notas">Notas</label>
                                                            <textarea name="Nota" rows="4" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Notas...">{{ old('Nota') }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Añadir familiar
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
@extends('adminlte::page')

@section('title', 'Añadir Persona')

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
                                            <span class="ctvSefar block text-indigo-600">{{ __('Add person') }}</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.agclientes.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    {{ __('List of clients and ancestors') }}
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
                                <form action="{{ route('crud.agclientes.store') }}" method="POST" enctype="multipart/form-data">

                                    @csrf
                                    
                                    <div class="shadow overflow-hidden sm:rounded-md">
                                            <div class="container">
                                                {{-- Fila 1: Datos principales --}}
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
                                                            <label for="Nombres" class="block text-sm font-medium text-gray-700">Nombres</label>
                                                            <input value="{{ old('Nombres') }}" type="text" name="Nombres" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('Nombres')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Apellidos --}}
                                                        <div>
                                                            <label for="Apellidos" class="block text-sm font-medium text-gray-700">Apellidos</label>
                                                            <input value="{{ old('Apellidos') }}" type="text" name="Apellidos" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('Apellidos')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 2: Identificación --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- IDPersona --}}
                                                        <div>
                                                            <label for="IDPersona" class="block text-sm font-medium text-gray-700">Persona</label>
                                                            <select name="IDPersona" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                @for ($i = 1; $i <=31; $i++)
                                                                    @if ( old('IDPersona') == $i)
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
                                                    
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- NPasaporte --}}
                                                        <div>
                                                            <label for="NPasaporte" class="block text-sm font-medium text-gray-700" title="Número de pasaporte">N° Pas.</label>
                                                            <input value="{{ old('NPasaporte') }}" type="text" name="NPasaporte" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- PaisPasaporte --}}
                                                        <div>
                                                            <label for="PaisPasaporte" class="block text-sm font-medium text-gray-700" title="País de emición del pasaporte">País pas.</label>
                                                            <select name="PaisPasaporte" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($countries as $country)
                                                                    @if (old('PaisPasaporte') == $country->pais)
                                                                        <option selected>{{ $country->pais }}</option>
                                                                    @else
                                                                        <option>{{ $country->pais }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- NDocIdent --}}
                                                        <div>
                                                            <label for="NDocIdent" class="block text-sm font-medium text-gray-700" title="Número de documento de identidad">N° de DI</label>
                                                            <input value="{{ old('NDocIdent') }}" type="text" name="NDocIdent" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- PaisDocIdent --}}
                                                        <div>
                                                            <label for="PaisDocIdent" class="block text-sm font-medium text-gray-700" title="País de emición del doumento de identidad">País DI</label>
                                                            <select name="PaisDocIdent" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($countries as $country)
                                                                    @if (old('PaisDocIdent') == $country->pais)
                                                                        <option selected>{{ $country->pais }}</option>
                                                                    @else
                                                                        <option>{{ $country->pais }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Sexo --}}
                                                        <div>
                                                            <label for="Sexo" class="block text-sm font-medium text-gray-700" title="Sexo">Sexo</label>
                                                            <select name="Sexo" autocomplete="on" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @if (old('Sexo') == "M")
                                                                    <option title="Masculino" selected>M</option>
                                                                @else
                                                                    <option title="Masculino">M</option>
                                                                @endif
                                                                
                                                                @if (old('Sexo') == "F")
                                                                    <option title="Masculino" selected>F</option>
                                                                @else
                                                                    <option title="Masculino">F</option>
                                                                @endif
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 3 Nacimiento --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex">    {{-- AnhoNac --}}
                                                        <div>
                                                            <label for="AnhoNac" class="block text-sm font-medium text-gray-700" title="Año de nacimiento">Año Nac.</label>
                                                            <input value="{{ old('AnhoNac') }}" min="0" max="3000" type="number" name="AnhoNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('AnhoNac')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- MesNac --}}
                                                        <div>
                                                            <label for="MesNac" class="block text-sm font-medium text-gray-700" title="Mes de nacimiento">Mes Nac.</label>
                                                            <input value="{{ old('MesNac') }}" min="1" max="12" type="number" name="MesNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('MesNac')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- DiaNac --}}
                                                        <div>
                                                            <label for="DiaNac" class="block text-sm font-medium text-gray-700" title="Día de nacimiento">Día Nac.</label>
                                                            <input value="{{ old('DiaNac') }}" min="1" max="31" type="number" name="DiaNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('DiaNac')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- LugarNac --}}
                                                        <div>
                                                            <label for="LugarNac" class="block text-sm font-medium text-gray-700" title="Lugar de nacimiento">Lugar Nac.</label>
                                                            <input value="{{ old('LugarNac') }}" type="text" name="LugarNac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- PaisNac --}}
                                                        <div>
                                                            <label for="PaisNac" class="block text-sm font-medium text-gray-700" title="País de nacimiento">País Nac.</label>
                                                            <select name="PaisNac" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($countries as $country)
                                                                    @if (old('PaisNac') == $country->pais)
                                                                        <option selected>{{ $country->pais }}</option>
                                                                    @else
                                                                        <option>{{ $country->pais }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 4 Bautizo --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex">    {{-- AnhoBtzo --}}
                                                        <div>
                                                            <label for="AnhoBtzo" class="block text-sm font-medium text-gray-700" title="Año de bautizo">Año Btzo.</label>
                                                            <input value="{{ old('AnhoBtzo') }}" min="0" max="3000" type="number" name="AnhoBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('AnhoBtzo')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- MesBtzo --}}
                                                        <div>
                                                            <label for="MesBtzo" class="block text-sm font-medium text-gray-700" title="Mes de bautizo">Mes Btzo.</label>
                                                            <input value="{{ old('MesBtzo') }}" min="1" max="12" type="number" name="MesBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('MesBtzo')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- DiaBtzo --}}
                                                        <div>
                                                            <label for="DiaBtzo" class="block text-sm font-medium text-gray-700" title="Día de bautizo">Día Btzo.</label>
                                                            <input value="{{ old('DiaBtzo') }}" min="1" max="31" type="number" name="DiaBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('DiaBtzo')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- LugarBtzo --}}
                                                        <div>
                                                            <label for="LugarBtzo" class="block text-sm font-medium text-gray-700" title="Lugar de bautizo">Lugar Btzo.</label>
                                                            <input value="{{ old('LugarBtzo') }}" type="text" name="LugarBtzo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- PaisBtzo --}}
                                                        <div>
                                                            <label for="PaisBtzo" class="block text-sm font-medium text-gray-700" title="País de bautizo">País Btzo.</label>
                                                            <select name="PaisBtzo" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($countries as $country)
                                                                    @if (old('PaisBtzo') == $country->pais)
                                                                        <option selected>{{ $country->pais }}</option>
                                                                    @else
                                                                        <option>{{ $country->pais }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 5 Matrimonio --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex">    {{-- AnhoMatr --}}
                                                        <div>
                                                            <label for="AnhoMatr" class="block text-sm font-medium text-gray-700" title="Año de matrimonio">Año Matr.</label>
                                                            <input value="{{ old('AnhoMatr') }}" min="0" max="3000" type="number" name="AnhoMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('AnhoMatr')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- MesMatr --}}
                                                        <div>
                                                            <label for="MesMatr" class="block text-sm font-medium text-gray-700" title="Mes de matrimonio">Mes Matr.</label>
                                                            <input value="{{ old('MesMatr') }}" min="1" max="12" type="number" name="MesMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('MesMatr')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- DiaMatr --}}
                                                        <div>
                                                            <label for="DiaMatr" class="block text-sm font-medium text-gray-700" title="Día de matrimonio">Día Matr.</label>
                                                            <input value="{{ old('DiaMatr') }}" min="1" max="31" type="number" name="DiaMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('DiaMatr')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- LugarMatr --}}
                                                        <div>
                                                            <label for="LugarMatr" class="block text-sm font-medium text-gray-700" title="Lugar de matrimonio">Lugar Matr.</label>
                                                            <input value="{{ old('LugarMatr') }}" type="text" name="LugarMatr" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- PaisMatr --}}
                                                        <div>
                                                            <label for="PaisMatr" class="block text-sm font-medium text-gray-700" title="País de matrimonio">País Matr.</label>
                                                            <select name="PaisMatr" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($countries as $country)
                                                                    @if (old('PaisMatr') == $country->pais)
                                                                        <option selected>{{ $country->pais }}</option>
                                                                    @else
                                                                        <option>{{ $country->pais }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 6 Defunción --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex">    {{-- AnhoDef --}}
                                                        <div>
                                                            <label for="AnhoDef" class="block text-sm font-medium text-gray-700" title="Año de defunción">Año Def.</label>
                                                            <input value="{{ old('AnhoDef') }}" min="0" max="3000" type="number" name="AnhoDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('AnhoDef')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- MesDef --}}
                                                        <div>
                                                            <label for="MesDef" class="block text-sm font-medium text-gray-700" title="Mes de defunción">Mes Def.</label>
                                                            <input value="{{ old('MesDef') }}" min="1" max="12" type="number" name="MesDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('MesDef')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- DiaDef --}}
                                                        <div>
                                                            <label for="DiaDef" class="block text-sm font-medium text-gray-700" title="Día de defunción">Día Def.</label>
                                                            <input value="{{ old('DiaDef') }}" min="1" max="31" type="number" name="DiaDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                            @error('DiaDef')
                                                                <small style="color:red">*{{ $message }}*</small>
                                                            @enderror
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- LugarDef --}}
                                                        <div>
                                                            <label for="LugarDef" class="block text-sm font-medium text-gray-700" title="Lugar de defunción">Lugar Def.</label>
                                                            <input value="{{ old('LugarDef') }}" type="text" name="LugarDef" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- PaisDef --}}
                                                        <div>
                                                            <label for="PaisDef" class="block text-sm font-medium text-gray-700" title="País de defunción">País Def.</label>
                                                            <select name="PaisDef" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @foreach ($countries as $country)
                                                                    @if (old('PaisDef') == $country->pais)
                                                                        <option selected>{{ $country->pais }}</option>
                                                                    @else
                                                                        <option>{{ $country->pais }}</option> 
                                                                    @endif
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 7 Familiar --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex">    {{-- Familiaridad --}}
                                                        <div>
                                                            <label for="Familiaridad" class="block text-sm font-medium text-gray-700" title="¿Tiene algún familiar realizando el proceso con nosotros?">Familiar</label>
                                                            <select name="Familiaridad" autocomplete="on" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                                                <option></option>
                                                                @if (old('Familiaridad') == "Si")
                                                                    <option selected>Si</option>
                                                                @else
                                                                    <option>Si</option>
                                                                @endif
                                                                
                                                                @if (old('Familiaridad') == "No")
                                                                    <option selected>No</option>
                                                                @else
                                                                    <option>No</option>
                                                                @endif
                                                            </select>
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- NombresF --}}
                                                        <div>
                                                            <label for="NombresF" class="block text-sm font-medium text-gray-700">Nombres del familiar</label>
                                                            <input value="{{ old('NombresF') }}" type="text" name="NombresF" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- ApellidosF --}}
                                                        <div>
                                                            <label for="ApellidosF" class="block text-sm font-medium text-gray-700">Apellidos del familiar</label>
                                                            <input value="{{ old('ApellidosF') }}" type="text" name="ApellidosF" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex">    {{-- ParentescoF --}}
                                                        <div>
                                                            <label for="ParentescoF" class="block text-sm font-medium text-gray-700">Parentesco</label>
                                                            <input value="{{ old('ParentescoF') }}" type="text" name="ParentescoF" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>
                                                    <div class="px-1 py-2 m-2 flex">    {{-- NPasaporteF --}}
                                                        <div>
                                                            <label for="NPasaporteF" class="block text-sm font-medium text-gray-700" title="Número de pasaporte del familiar">N° Pas. familiar</label>
                                                            <input value="{{ old('NPasaporteF') }}" type="text" name="NPasaporteF" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>
                                                </div>

                                                {{-- Fila 7 Otros --}}
                                                <div class="md:flex ms:flex-wrap">
                                                    <div class="px-1 py-2 m-2 flex">    {{-- FRegistro --}}
                                                        <div>
                                                            <label for="FRegistro" class="block text-sm font-medium text-gray-700" title="Fecha de registro">Fecha de registro</label>
                                                            <input value="{{ old('FRegistro') }}" type="date" name="FRegistro" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Enlace --}}
                                                        <div>
                                                            <label for="Enlace" class="block text-sm font-medium text-gray-700" title="Enlace">Enlace</label>
                                                            <input value="{{ old('Enlace') }}" type="url" name="Enlace" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        </div>
                                                    </div>

                                                    <div class="px-1 py-2 m-2 flex-1">    {{-- Observaciones --}}
                                                        <div>
                                                            <label for="Observaciones" class="block text-sm font-medium text-gray-700" title="Observaciones">Observaciones</label>
                                                            <textarea name="Observaciones" rows="3" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 mt-1 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Observaciones">{{ old('Observaciones') }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="px-4 py-5 bg-white sm:p-6">
                                            <div class="grid grid-cols-6 gap-6">
                                                <p>
                                                    <input id="files" type="file" name="files" style="display: none">
                                                    <label for="files" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white">
                                                        <i class="fas fa-upload mr-2"></i> [documentos].png
                                                    </label>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Añadir persona
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
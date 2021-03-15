@extends('adminlte::page')

@section('title', 'Consulta ODX')

@section('content_header')

@stop

@section('content')
<x-app-layout>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                    <form action="{{ route('consultas.onidex.show') }}" method="POST">
                        @csrf
                        <div class="flex bg-white px-4 py-3 sm:px-6">
                            <input 
                                name="search"
                                type="text" 
                                placeholder="Buscar..." 
                                class="mr-2 mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md"
                            >
                            <button type="submit" name="clear" class="py-1 px-2 mt-1 ml-2 border border-transparent rounded-md border border-transparent rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"><i class="fas fa-search"></i></button>  
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="flex flex-col">
        <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">
                    <div class="flex bg-white px-4 py-3 sm:px-6">
                        <div class="container">
                            {{-- inicio formulario --}}
                            <form action="{{ route('consultas.onidex.show') }}" method="POST">
                                @csrf
                                <div class="grid grid-cols-4 md:grid-cols-8 gap-3">
                                    {{-- Fila 1 --}}
                                    <div class="col-span-8 md:col-span-2 col-start-1">
                                        <label for="nombre1" class="block text-sm font-medium text-gray-700">1er nombre:</label>
                                        <input type="text" name="nombre1" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Primer nombre...">
                                        <input name="cbx_nombre1" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        <label for="cbx_nombre1" class="m-1 text-gray-500">Exacto</label>
                                    </div>
                                    <div class="col-span-8 md:col-span-2">
                                        <label for="nombre2" class="block text-sm font-medium text-gray-700">2do nombre:</label>
                                        <input type="text" name="nombre2" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Segundo nombre...">
                                        <input name="cbx_nombre2" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        <label for="cbx_nombre2" class="m-1 text-gray-500">Exacto</label>
                                    </div>
                                    <div class="col-span-8 md:col-span-2">
                                        <label for="apellido1" class="block text-sm font-medium text-gray-700">1er apellido:</label>
                                        <input type="text" name="apellido1" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Primer apellido...">
                                        <input name="cbx_apellido1" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        <label for="cbx_apellido1" class="m-1 text-gray-500">Exacto</label>
                                    </div>
                                    <div class="col-span-8 md:col-span-2">
                                        <label for="apellido2" class="block text-sm font-medium text-gray-700">2do apellido:</label>
                                        <input type="text" name="apellido2" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="Segundo apellido...">
                                        <input name="cbx_apellido2" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        <label for="cbx_apellido2" class="m-1 text-gray-500">Exacto</label>
                                    </div>


                                    {{-- Fila 2 --}}
                                    <div class="col-span-8 md:col-span-4 col-start-1">
                                        <input name="cbx_nombre" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        <label for="cbx_nombre" class="m-1 text-gray-500">Buscar primer nombre también en el segundo</label>
                                    </div>
                                    <div class="col-span-8 md:col-span-4">
                                        <input name="cbx_apellido" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        <label for="cbx_apellido" class="m-1 text-gray-500">Buscar primer apellido también en el segundo</label>
                                    </div>
                                    {{-- Fila 3 --}}
                                    <div class="col-span-8 md:col-span-2 col-start-1">
                                        <label for="cedula" class="block text-sm font-medium text-gray-700">Cédula de identidad:</label>
                                        <input type="text" name="cedula" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md" placeholder="N° de cédula de identidad...">
                                        <input name="cbx_cedula" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded" checked>
                                        <label for="cbx_cedula" class="m-1 text-gray-500">Exacto</label>
                                    </div>
                                    <div class="col-span-8 md:col-span-2">
                                        <label for="nacion" class="block text-sm font-medium text-gray-700">Nación de origen:</label>
                                        <select name="nacion" autocomplete="country" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                            <option></option>
                                            <option value="Alemania">Alemania</option> 
                                            <option value="Argentina">Argentina</option> 
                                            <option value="Australia">Australia</option> 
                                            <option value="Brasil">Brasil</option> 
                                            <option value="Chile">Chile</option> 
                                            <option value="China">China</option> 
                                            <option value="Colombia">Colombia</option> 
                                            <option value="Costa Rica">Costa Rica</option> 
                                            <option value="Cuba">Cuba</option> 
                                            <option value="Curazao">Curazao</option> 
                                            <option value="Ecuador">Ecuador</option> 
                                            <option value="El Salvador">El Salvador</option> 
                                            <option value="España">España</option> 
                                            <option value="Estados Unidos (USA)">Estados Unidos (USA)</option> 
                                            <option value="Francia">Francia</option> 
                                            <option value="Groenlandia">Groenlandia</option> 
                                            <option value="Guinea Bissau">Guinea Bissau</option> 
                                            <option value="Guyana">Guyana</option> 
                                            <option value="Haití">Haití</option> 
                                            <option value="Holanda">Holanda</option> 
                                            <option value="Italia">Italia</option> 
                                            <option value="Jamaica">Jamaica</option> 
                                            <option value="Jordania">Jordania</option> 
                                            <option value="Líbano">Líbano</option> 
                                            <option value="Lituania">Lituania</option> 
                                            <option value="México">México</option> 
                                            <option value="Nicaragua">Nicaragua</option>
                                            <option value="Perú">Perú</option> 
                                            <option value="Polonia">Polonia</option> 
                                            <option value="Portugal">Portugal</option> 
                                            <option value="Puerto Rico">Puerto Rico</option> 
                                            <option value="República Dominicana">República Dominicana</option> 
                                            <option value="Rumanía">Rumanía</option> 
                                            <option value="Rusia">Rusia</option> 
                                            <option value="Siria">Siria</option> 
                                            <option value="Sudáfrica">Sudáfrica</option> 
                                            <option value="Suecia">Suecia</option> 
                                            <option value="Suiza">Suiza</option> 
                                            <option value="Trinidad y Tobago">Trinidad y Tobago</option> 
                                            <option value="Uruguay">Uruguay</option> 
                                            <option value="Venezuela">Venezuela</option> 
                                            <option value="Yugoslavia">Yugoslavia</option>
                                        </select>
                                    </div>
                                    <div class="col-span-8 md:col-span-4">
                                        <label for="fec_nac" class="block text-sm font-medium text-gray-700">Fecha de nacimiento:</label>
                                        <input type="date" name="fec_nac" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                        <input name="cbx_anho" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded" checked>
                                        <label for="cbx_anho" class="m-1 text-gray-500">Año Exacto</label>
                                        <input name="cbx_mes" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded" checked>
                                        <label for="cbx_mes" class="m-1 text-gray-500">Mes Exacto</label>
                                        <input name="cbx_dia" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded" checked>
                                        <label for="cbx_dia" class="m-1 text-gray-500">Día Exacto</label>
                                    </div>
                                    {{-- Fila 4 --}}
                                    <div class="col-span-8 col-start-1">
                                        <input name="rangofecha" type="checkbox" class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                        <label for="rangofecha" class="m-1 text-gray-500">
                                            Buscar por rango de fechas de nacimiento
                                        </label>
                                    </div>
                                    {{-- Fila 5 --}}
                                    <div class="col-span-8 md:col-span-4 col-start-1">
                                        <label for="fechainicial" class="block text-sm font-medium text-gray-700">Fecha inicial:</label>
                                        <input type="date" name="fechainicial" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>
                                    <div class="col-span-8 md:col-span-4">
                                        <label for="fechafinal" class="block text-sm font-medium text-gray-700">Fecha final:</label>
                                        <input type="date" name="fechafinal" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                    </div>

                                    <div class="col-span-2 col-start-7">
                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" title="Busqueda avanzada">
                                                <i class="fas fa-binoculars"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        {{-- fin formulario --}}
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
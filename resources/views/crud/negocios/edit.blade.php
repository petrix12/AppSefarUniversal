@extends('adminlte::page')

@section('title', 'Negocio')

@section('content_header')
    {{-- <h1><strong>{{ __('Permisos de usuarios') }}</strong></h1> --}}
@stop

@section('content')
<x-app-layout>
    <div>
        <div class="flex flex-col">
            <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div class="bg-gray-50">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                <span class="ctvSefar block text-indigo-600">Editar Negocio</span>
                            </h2>
                            <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                <div class="inline-flex rounded-md shadow">
                                    <a href="/users/{{$deal_db->user_id}}/edit" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                        Volver a información del Usuario
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>

        <div class="card p-4">
            <div class="tab-content mt-4" id="formTabsContent">

                <div class="mt-3" style="display: flex; gap: 16px; flex-wrap: wrap;">
                    <div style="flex: 1;" class="mb-3">
                        <label for="servicio_solicitado" class="block text-sm font-medium text-gray-700">Servicio Solicitado</label>
                        <input type="text" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" id="servicio_solicitado" name="servicio_solicitado" value="{{ old('servicio_solicitado', $user->servicio_solicitado) }}" placeholder="Ingrese su nombre">
                    </div>
                </div>

                <pre>@php print_r(json_decode($deal_db)); @endphp</pre>
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

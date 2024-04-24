@extends('adminlte::page')

@section('title', 'Buscar en Stripe')

@section('content_header')

@stop

@section('content')

<x-app-layout>
	<div class="flex flex-col">
	    <div class="">
	        <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
	            {{-- Inicio --}}
	            <div >
	                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
	                    <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
	                        <span class="ctvSefar block text-indigo-600">Exportar Gedcom</span>
	                    </h2>
	                </div>
	            </div>
	            {{-- Fin --}}
	        </div>
	    </div>
	</div>
    <center>

		@can('descargarGedcom')
	        <div class="px-4 py-2 m-2">
	            {{-- FAMILIARES --}}
	            <div class="justify-center">
	                <a href="{{route('getGedcomGlobal')}}" class="cfrSefar downloadgedcom inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
	                    <b>Descargar Gedcom Global</b>
	                </a>
	            </div>
	        </div>
	    @endcan
	</center>

	<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

</x-app-layout>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

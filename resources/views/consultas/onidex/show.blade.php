@extends('adminlte::page')

@section('title', 'Resultados de búsqueda')

@section('content_header')

@stop

@section('content')
<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                @livewire('consulta.onidexes-table',   [
                    'search' => $search,
                    'nombre1' => $nombre1,
                    'nombre2' => $nombre2,
                    'apellido1' => $apellido1,
                    'apellido2' => $apellido2,
                    'cedula' => $cedula,
                    'nacion' => $nacion,
                    'cbx_nombre1' => $cbx_nombre1,
                    'cbx_nombre2' => $cbx_nombre2,
                    'cbx_apellido1' => $cbx_apellido1,
                    'cbx_apellido2' => $cbx_apellido2,
                    'cbx_nombre' => $cbx_nombre,
                    'cbx_apellido' => $cbx_apellido,
                    'cbx_cedula' => $cbx_cedula,
                    'fec_nac' => $fec_nac,
                    'cbx_anho' => $cbx_anho,
                    'cbx_mes' => $cbx_mes,
                    'cbx_dia' => $cbx_dia,
                    'rangofecha' => $rangofecha,
                    'fechainicial' => $fechainicial,
                    'fechafinal' => $fechafinal
                ])
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
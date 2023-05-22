@extends('adminlte::page')

@section('title', 'Servicios de Vinculaciones')

@section('content_header')
    
@stop

@section('content')

<x-app-layout>

    <style>
        .hidden, .border-gray-100 {
            display: none!important;
        }
    </style>
    <div class="flex flex-col">
        <div class="">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                {{-- Inicio --}}
                <div class="bg-gray-50">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">Servicios de Vinculaciones</span>
                        </h2>
                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                            <div class="inline-flex rounded-md shadow">
                                <a href="/pay" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                    Regresar a Ventana de Pago
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Fin --}}
            </div>
        </div>
    </div>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style type="text/css">
        .grid-container {
            display: grid;
            max-width: 100%;
            grid-template-columns: repeat(4, 260px);
            justify-content: space-between;
            align-items: center;
            grid-gap: 50px;
            padding-top: 20px;
        }
    
        .grid-item {
            border-radius: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: rgb(121,22,15) !important;
            width: 240px!important;
            height: 240px!important;
            font-size: 1rem;
            border: none;
            outline-color: #00A8EF;
            cursor: pointer;
            transform: scale(0.9);
            transition: 0.5s;
            padding: 25px;
        }

        .grid-item:hover {
            transform: scale(1);
            background: rgb(173, 78, 71) !important;
        }
    </style>

    <div class="grid-container">
        <?php
            foreach ($servicios as $servicio) {
                $helper = 1;
                foreach ($compras as $compra){
                    if ($compra->servicio_hs_id == $servicio->id_hubspot){
                        $helper = 0;
                    }
                }
                if ($helper == 1) {                
        ?>
        <a class="grid-item" href="{{ route('cliente.regvinculaciones', ['id' => $servicio->id_hubspot]) }}">
            <center>
                <p style="margin: 0; font-size: 28px; color: white; width: 100%; line-height: 1;">{{$servicio->nombre}}</p>
                <p style="margin: 0; font-size: 34px; color: #EDD175; width: 100%;"><b>{{$servicio->precio}}€</b></p>
            </center>
        </a>
        <?php
                }
            }
        ?>
    </div>
</x-app-layout>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

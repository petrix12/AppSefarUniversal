@extends('adminlte::page')

@section('title', 'Añadir Cupón')

@section('content_header')

@stop

@section('content')


<?php


$permitted_chars = 'QWERTYUPASDFGHJKLZXCVBNM';

$permitted_nums = '0123456789';


function generate_string($input, $strength = 16) {
    $input_length = strlen($input);
    $random_string = '';
    for($i = 0; $i < $strength; $i++) {
        $random_character = $input[mt_rand(0, $input_length - 1)];
        $random_string .= $random_character;
    }
 
    return $random_string;
}

?>


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
                                            <span class="ctvSefar block text-indigo-600">Registrar Cupón</span>
                                        </h2>
                                        <div class="mt-8 flex lg:mt-0 lg:flex-shrink-0">
                                            <div class="inline-flex rounded-md shadow">
                                                <a href="{{ route('crud.coupons.index') }}" class="cfrSefar inline-flex items-center justify-center px-5 py-3 border border-transparent text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                                                    Listar Cupones
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
                                <form action="{{ route('crud.coupons.store') }}" method="POST">

                                    @csrf
                                    {{-- RUTA QUE LO INVOCA --}}
                                    <div class="shadow overflow-hidden sm:rounded-md">

                                        <?php

                                            $id = generate_string($permitted_chars, 3).generate_string($permitted_nums, 3);

                                            $dateplusseven = date('Y-m-d', strtotime(date('Y-m-d'). ' + 7 days'));

                                        ?>

                                        <div class="container">
                                            <div class="md:flex ms:flex-wrap">
                                                <div class="px-1 py-2 m-2 flex-1">    {{-- couponcode --}}
                                                    <div>
                                                        <label for="couponcode" class="block text-sm font-medium text-gray-700">Código</label>
                                                        <input value="{{ old('couponcode', $id) }}" readonly type="text" name="couponcode" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        @error('couponcode')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="px-1 py-2 m-2 flex-1">    {{-- percentage --}}
                                                    <div>
                                                        <label for="percentage" class="block text-sm font-medium text-gray-700">Porcentaje de Descuento</label>
                                                        <input value="{{ old('percentage') }}" type="number" name="percentage" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        @error('percentage')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="px-1 py-2 m-2 flex-1">    {{-- expire --}}
                                                    <div>
                                                        <label for="expire" class="block text-sm font-medium text-gray-700">Fecha de Vencimiento (opcional)</label>
                                                        <input value="{{ old('expire', $dateplusseven) }}" type="date" name="expire" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        @error('expire')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="container">
                                            <div class="md:flex ms:flex-wrap">
                                                <div class="px-1 py-2 m-2 flex-1">    {{-- solicitante --}}
                                                    <div>
                                                        <label for="solicitante" class="block text-sm font-medium text-gray-700">Solicitado por</label>
                                                        <input value="{{ old('solicitante') }}" type="text" name="solicitante" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        @error('solicitante')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="px-1 py-2 m-2 flex-1">    {{-- cliente --}}
                                                    <div>
                                                        <label for="cliente" class="block text-sm font-medium text-gray-700">Cliente</label>
                                                        <input value="{{ old('cliente') }}" type="text" name="cliente" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        @error('cliente')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>

                                                <div class="px-1 py-2 m-2 flex-1">    {{-- motivo --}}
                                                    <div>
                                                        <label for="motivo" class="block text-sm font-medium text-gray-700">Motivo del Cupón</label>
                                                        <input value="{{ old('motivo') }}" type="text" name="motivo" class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md">
                                                        @error('motivo')
                                                            <small style="color:red">*{{ $message }}*</small>
                                                        @enderror
                                                    </div>
                                                </div>
                                            </div>
                                        </div>                                  
                                        
                                        <div class="px-4 py-3 bg-gray-50 text-right sm:px-6">
                                            <button type="submit" class="cfrSefar inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                Registrar Cupón
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
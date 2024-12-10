@extends('adminlte::page')

@section('title', 'Reporte Anual')

@section('content_header')
@stop

@section('content')
    <x-app-layout>
        <div class="flex flex-col">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                {{-- Inicio --}}
                <div>
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">Reportes Anuales</span>
                        </h2>
                    </div>
                </div>
                {{-- Fin --}}
            </div>
        </div>
        <center>
            <form action="{{ route('getreporteanual') }}" method="POST" class="max-w-md mt-8">
                @csrf
                <div class="mb-4">
                    <label for="anio" class="block text-gray-700 text-sm font-bold mb-2">Selecciona un año:</label>
                    <select id="anio" name="anio" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        @php
                            $anioActual = date('Y');
                        @endphp
                        @for ($anio = $anioActual; $anio >= 2015; $anio--) {{-- Cambia 2000 por el primer año necesario --}}
                            <option value="{{ $anio }}">{{ $anio }}</option>
                        @endfor
                    </select>
                </div>

                <div class="flex items-center justify-center">
                    <button id="submitButton" type="submit" class="cfrSefar text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Generar Reporte
                    </button>
                </div>
            </form>
        </center>

        <!-- SweetAlert2 -->
        <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <!-- Validación del Formulario -->
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                // Agregar controlador de evento para el envío del formulario
                const form = document.querySelector('form');
                form.addEventListener('submit', function (event) {
                    const anioSelect = document.getElementById('anio');
                    if (anioSelect.value.trim() === '') {
                        event.preventDefault(); // Evitar que el formulario se envíe
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Por favor, selecciona un año.',
                        });
                    }
                });
            });
        </script>
    </x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
    <!-- Puedes agregar scripts adicionales aquí si es necesario -->
@stop

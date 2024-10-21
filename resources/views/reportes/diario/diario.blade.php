@extends('adminlte::page')

@section('title', 'Reporte Diario')

@section('content_header')
@stop

@section('content')
    <x-app-layout>
        <!-- Flatpickr CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

        <!-- Flatpickr JS -->
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

        <div class="flex flex-col">
            <div class="">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div >
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                <span class="ctvSefar block text-indigo-600">Reportes Diarios</span>
                            </h2>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>
        <center>
            <form action="{{ route('getreportediario') }}" method="POST" class="max-w-md mt-8">
                @csrf
                <div class="mb-4">
                    <label for="fecha" class="block text-gray-700 text-sm font-bold mb-2">Fecha:</label>
                    <input type="text" id="fecha" name="fecha" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
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

        <!-- Flatpickr Initialization and Form Validation -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Inicializar Flatpickr
                flatpickr("#fecha", {
                    dateFormat: "Y-m-d",
                    defaultDate: "{{ now()->format('Y-m-d') }}",
                    maxDate: "{{ now()->format('Y-m-d') }}",
                    locale: {
                        firstDayOfWeek: 1,
                        weekdays: {
                            shorthand: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                            longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                        },
                        months: {
                            shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                            longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                        },
                    },
                });

                // Agregar controlador de evento para el envío del formulario
                const form = document.querySelector('form');
                form.addEventListener('submit', function(event) {
                    const fechaInput = document.getElementById('fecha');
                    if (fechaInput.value.trim() === '') {
                        event.preventDefault(); // Evitar que el formulario se envíe
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Por favor, seleccione una fecha.',
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

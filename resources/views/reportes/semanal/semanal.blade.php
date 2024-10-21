@extends('adminlte::page')

@section('title', 'Reporte Semanal')

@section('content_header')
@stop

@php
    // Obtener la fecha de hoy
    $hoy = now();

    // Calcular el lunes de la semana actual
    $lunesActual = $hoy->copy()->startOfWeek(); // Asume que el lunes es el inicio de semana
    $domingoActual = $lunesActual->copy()->addDays(6); // Añade 6 días para obtener el domingo

    // Formatear las fechas en dd-mm-yyyy para JavaScript
    $lunesFormato = $lunesActual->format('Y/m/d');
    $domingoFormato = $domingoActual->format('d/m/Y');
@endphp

@section('content')
    <x-app-layout>
        <!-- Flatpickr CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

        <!-- Flatpickr JS -->
        <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
        <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/minMaxTimePlugin.js"></script>

        <div class="flex flex-col">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div>
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">Reportes Semanal</span>
                        </h2>
                    </div>
                </div>
            </div>
        </div>
        <center>
            <form action="{{ route('getreportesemanal') }}" method="POST" class="max-w-md mt-8">
                @csrf
                <!-- Campo visible para la fecha de inicio -->
                <div class="mb-4">
                    <label for="fecha" class="block text-gray-700 text-sm font-bold mb-2">Fecha (Lunes):</label>
                    <input type="text" id="fecha" name="fecha" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>

                <!-- Campo oculto para la fecha de fin (Domingo) -->
                <input type="hidden" id="fecha_fin" name="fecha_fin">

                <div class="flex items-center justify-center">
                    <button id="submitButton" type="submit" class="cfrSefar text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Generar Reporte
                    </button>
                </div>
            </form>
        </center>

        <!-- SweetAlert2 -->
        <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <!-- Flatpickr Initialization with MinMaxTimePlugin -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const lunesFormato = "{{ $lunesFormato }}";
                const domingoFormato = "{{ $domingoFormato }}";

                // Iniciar flatpickr para seleccionar la fecha del lunes
                flatpickr("#fecha", {
                    dateFormat: "d/m/Y",
                    defaultDate: lunesFormato,
                    locale: {
                        firstDayOfWeek: 1, // Lunes como primer día
                        weekdays: {
                            shorthand: ['Do', 'Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa'],
                            longhand: ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
                        },
                        months: {
                            shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                            longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                        },
                    },
                    disable: [
                        function(date) {
                            return date.getDay() !== 1; // Solo habilitar lunes
                        }
                    ],
                    onReady: function(selectedDates, dateStr, instance) {
                        // Al cargar la página, asignar el valor inicial al campo de fecha con el rango de lunes a domingo
                        document.getElementById('fecha').value = `del lunes ${instance.formatDate(new Date(lunesFormato), 'd/m/Y')} al domingo ${domingoFormato}`;
                        document.getElementById('fecha_fin').value = domingoFormato;
                    },
                    onChange: function(selectedDates) {
                        if (selectedDates.length > 0) {
                            const selectedDate = selectedDates[0];

                            // Calcular la fecha del domingo
                            const endDate = new Date(selectedDate);
                            endDate.setDate(selectedDate.getDate() + 6);

                            // Formatear las fechas en dd-mm-yyyy
                            const options = { day: '2-digit', month: '2-digit', year: 'numeric' };
                            const formattedMonday = selectedDate.toLocaleDateString('es-ES', options);
                            const formattedSunday = endDate.toLocaleDateString('es-ES', options);

                            // Actualizar los campos de fecha
                            document.getElementById('fecha').value = `del lunes ${formattedMonday} al domingo ${formattedSunday}`;
                            document.getElementById('fecha_fin').value = formattedSunday;
                        }
                    }
                });

                // Validación y SweetAlert para verificar la selección de fecha
                const form = document.querySelector('form');
                form.addEventListener('submit', function(event) {
                    const fechaInput = document.getElementById('fecha');
                    if (fechaInput.value.trim() === '') {
                        event.preventDefault();
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
    <!-- Scripts adicionales si son necesarios -->
@stop

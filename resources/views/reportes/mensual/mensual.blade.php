@extends('adminlte::page')

@section('title', 'Reporte Mensual')

@section('content_header')

@stop

@section('content')
    <x-app-layout>
        <!-- Flatpickr CSS -->
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/style.css">


<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/plugins/monthSelect/index.js"></script>

        <div class="flex flex-col">
            <div class="">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div>
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                <span class="ctvSefar block text-indigo-600">Reportes Mensuales</span>
                            </h2>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>
        <center>
            <form action="{{ route('getreportemensual') }}" method="POST" class="max-w-md mt-8">
                @csrf
                <div class="mb-4">
                    <label for="mes" class="block text-gray-700 text-sm font-bold mb-2">Mes:</label>
                    <input type="text" id="mes" name="mes" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="Seleccione el mes">
                </div>

                <div class="flex items-center justify-center">
                    <button type="submit" class="cfrSefar text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Generar Reporte
                    </button>
                </div>
            </form>
        </center>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                flatpickr("#mes", {
                    defaultDate: new Date(),
                    plugins: [
                        new monthSelectPlugin({
                            shorthand: false, // Mostrar el mes en formato completo
                            dateFormat: "F \\de Y", // Formato para el valor almacenado (puedes ajustar según tus necesidades)
                            altFormat: "F \\de Y", // Formato alternativo para mostrar el mes completo y año
                        })
                    ],
                    locale: {
                        firstDayOfWeek: 1,
                        months: {
                            shorthand: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'],
                            longhand: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                        },
                    },
                });
            });
        </script>

        <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    </x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

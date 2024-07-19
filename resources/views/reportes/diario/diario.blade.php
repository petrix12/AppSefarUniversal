@extends('adminlte::page')

@section('title', 'Reporte Diario')

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
                        <label for="dia" class="block text-gray-700 text-sm font-bold mb-2">Día:</label>
                        <select name="dia" id="dia" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            @for ($i = 1; $i <= now()->daysInMonth; $i++)
                                <option value="{{ $i }}" {{ $i == now()->day ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="mes" class="block text-gray-700 text-sm font-bold mb-2">Mes:</label>
                        <select name="mes" id="mes" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            @for ($i = 1; $i <= 12; $i++)
                                <option value="{{ $i }}" {{ $i == now()->month ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="mb-6">
                        <label for="año" class="block text-gray-700 text-sm font-bold mb-2">Año:</label>
                        <input type="number" name="año" id="año" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" min="{{ now()->subYears(10)->year }}" max="{{ now()->year }}" value="{{ now()->year }}">
                    </div>

                    <div class="flex items-center justify-center">
                        <button type="submit" class="cfrSefar text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Generar Reporte
                        </button>
                    </div>
                </form>
            </center>

            <script>
                // Actualizar el campo "día" cuando cambia el mes
                const mesSelect = document.getElementById('mes');
                const diaSelect = document.getElementById('dia');

                mesSelect.addEventListener('change', () => {
                    const año = document.getElementById('año').value;
                    const mes = mesSelect.value;
                    const diasEnMes = new Date(año, mes, 0).getDate();

                    diaSelect.innerHTML = ''; // Limpiar opciones existentes

                    for (let i = 1; i <= diasEnMes; i++) {
                        const option = document.createElement('option');
                        option.value = i;
                        option.text = i;
                        diaSelect.add(option);
                    }
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

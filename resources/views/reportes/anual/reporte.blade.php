@extends('adminlte::page')

@section('title', 'Reporte Anual')

@section('content_header')
@stop

@php
    $actualanio = $anio;
@endphp

@section('content')
    <x-app-layout>
        <div class="flex flex-col">
            <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                <div>
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                        <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                            <span class="ctvSefar block text-indigo-600">Reporte Anual - {{ $actualanio }}</span>
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <center>
            <div class="flex justify-between max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8">
                <!-- Botón de año anterior -->
                <button onclick="navigateToReport(-1)" class="cfrSefar text-white bg-indigo-600 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Año Anterior
                </button>

                <!-- Input para seleccionar un año -->
                <select id="anioSelectorUp" onchange="goToReport('anioSelectorUp')" class="cfrSefar text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    @php
                        $anioActual = date('Y');
                    @endphp
                    @for ($anio = $anioActual; $anio >= 2015; $anio--)
                        <option value="{{ $anio }}" <?php if($actualanio == $anio) {echo("selected");} ?> >{{ $anio }}</option>
                    @endfor
                </select>

                <!-- Botón de año siguiente -->
                <button onclick="navigateToReport(1)" class="cfrSefar text-white bg-indigo-600 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Año Siguiente
                </button>
            </div>
        </center>

        <div class="card p-4">
            <h3 class="mb-5">Usuarios registrados durante el año: {{ $usuariosRegistrados }}</h3>
            <div class="chart-container" style="display: flex; justify-content: space-around; gap: 20px;">
                <div class="chart">
                    <h3>{{ $anioAnterior }}</h3><br>
                    <div class="bar-container">
                        <div class="bar" style="width: {{ $datosgraficosporcentaje['anio_anterior']['promedio']*3 }}%;"></div>
                        <div class="bar-label" style="left: {{ $datosgraficosporcentaje['anio_anterior']['promedio'] }}%;">
                            {{ $datosgraficosporcentaje['anio_anterior']['promedio'] }}%
                        </div>
                    </div>
                    <br>
                    <p>
                        <strong>Mínimo:</strong> {{ $datosgraficos['anio_anterior']['minimo'] }}<br>
                        <strong>Máximo:</strong> {{ $datosgraficos['anio_anterior']['maximo'] }}<br>
                        <strong>Promedio:</strong> {{ $datosgraficos['anio_anterior']['promedio'] }}<br>
                        <strong>Total Registrados:</strong> {{ $datosgraficos['anio_anterior']['total'] }}<br>
                    </p>
                </div>
                <div class="chart">
                    <h3>{{ $actualanio }}</h3><br>
                    <div class="bar-container">
                        <div class="bar" style="width: {{ $datosgraficosporcentaje['anio_actual']['promedio']*3 }}%;"></div>
                        <div class="bar-label" style="left: {{ $datosgraficosporcentaje['anio_actual']['promedio'] }}%;">
                            {{ $datosgraficosporcentaje['anio_actual']['promedio'] }}%
                        </div>
                    </div>
                    <br>
                    <p>
                        <strong>Mínimo:</strong> {{ $datosgraficos['anio_actual']['minimo'] }}<br>
                        <strong>Máximo:</strong> {{ $datosgraficos['anio_actual']['maximo'] }}<br>
                        <strong>Promedio:</strong> {{ $datosgraficos['anio_actual']['promedio'] }}<br>
                        <strong>Total Registrados:</strong> {{ $datosgraficos['anio_actual']['total'] }}<br>
                    </p>
                </div>
            </div>
        </div>

        <div class="card p-4">
            <center>
                <h3 style="margin-bottom: 1rem;">Gráfico Comparativo de Registros ({{ $actualanio }} vs {{ $anioAnterior }})</h3>
                <div id="chart_div" style="height: auto;">
                    <img id="dailygraph" src="{{ $chartUrl }}">
                    <img id="nightlygraph" src="{{ $chartNight }}" style="display: none;">
                </div>
            </center>
        </div>

        <div class="card p-4">
            <center>
                <h3 style="margin-bottom: 1rem;">Usuarios Registrados por Servicio</h3>
            </center>
            <div class="table-responsive">
                <table class="table" style="    margin: 10px auto !important; width:90%!important;">
                    <thead class="theadreport">
                        <tr>
                            <th>Servicio</th>
                            <th>Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuariosPorServicioAnioActual as $servicio => $cantidad)
                            <tr>
                                <td>{{ $servicio }}</td>
                                <td>{{ $cantidad }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-4">
            <center>
                <h3 style="margin-bottom: 1rem;">Usuarios registrados por estatus:</h3>
            </center>
            <div class="table-responsive">
                <table class="table" style="margin: 10px auto !important; width:50%!important;">
                    <thead class="theadreport">
                        <tr>
                            <th>Estatus</th>
                            <th>Cantidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($estatusCount as $estatus => $cantidad)
                            <tr>
                                <td>{{ $estatus }}</td>
                                <td>{{ $cantidad }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-4">
            <center>
                <h3 style="margin-bottom: 1rem;">Pagos registrados durante el año (Stripe)</h3>
            </center>
            @php
                $totalMontoStripe = array_sum($facturas);
            @endphp
            <center>
                <div class="table-responsive">
                    <table class="table" style="margin:0 auto; width:50%!important;">
                        <thead class="theadreport">
                            <tr>
                                <th>Servicio</th>
                                <th>Monto Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($facturas as $servicio => $monto)
                                <tr>
                                    <td>{{ $servicio }}</td>
                                    <td>{{ $monto }}€</td>
                                </tr>
                            @endforeach
                            <tr class="theadreport">
                                <td><strong>Total General:</strong></td>
                                <td><strong>{{ $totalMontoStripe }}€</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p><small>* Solo se consideran los pagos hechos a través de la pasarela de pago de <a href="https://app.sefaruniversal.com" target="_blank">app.sefaruniversal.com</a></small></p>
            </center>
        </div>

        <div class="card p-4">
            <center>
                <h3 style="margin-bottom: 1rem;">Pagos del año con Cupon</h3>
            </center>
            @php
                $totalMontoCupones = array_sum($facturasCupones);
            @endphp
            <center>
                <div class="table-responsive">
                    <table class="table" style="margin:0 auto; width:50%!important;">
                        <thead class="theadreport">
                            <tr>
                                <th>Servicio</th>
                                <th>Monto Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($facturasCupones as $servicio => $monto)
                                <tr>
                                    <td>{{ $servicio }}</td>
                                    <td>{{ $monto }}€</td>
                                </tr>
                            @endforeach
                            <tr class="theadreport">
                                <td><strong>Total General:</strong></td>
                                <td><strong>{{ $totalMontoCupones }}€</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p><small>* Solo se consideran los pagos hechos a través de la pasarela de pago de <a href="https://app.sefaruniversal.com" target="_blank">app.sefaruniversal.com</a></small></p>
                <p><small>* Solo se consideran descuentos del 100%</small></p>
            </center>
        </div>

        <center>
            <div class="flex justify-between max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8">
                <!-- Botón de año anterior -->
                <button onclick="navigateToReport(-1)" class="cfrSefar text-white bg-indigo-600 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Año Anterior
                </button>

                <!-- Input para seleccionar un año -->
                <select id="anioSelectorDown" onchange="goToReport('anioSelectorDown')" class="cfrSefar text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    @php
                        $anioActual = date('Y');
                    @endphp
                    @for ($anio = $anioActual; $anio >= 2015; $anio--)
                        <option value="{{ $anio }}" <?php if($actualanio == $anio) {echo("selected");} ?>>{{ $anio }}</option>
                    @endfor
                </select>

                <!-- Botón de año siguiente -->
                <button onclick="navigateToReport(1)" class="cfrSefar text-white bg-indigo-600 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                    Año Siguiente
                </button>
            </div>
        </center>

        <script>
            function navigateToReport(offset) {
                // Obtener el valor actual del selector de años
                const selectUp = document.getElementById('anioSelectorUp');
                const selectDown = document.getElementById('anioSelectorDown');

                // Convertir el valor actual a un número
                let currentYear = parseInt(selectUp.value);

                // Ajustar el año según el offset
                let newYear = currentYear + offset;

                // Asegurar que el año esté dentro del rango permitido
                if (newYear < 2015 || newYear > new Date().getFullYear()) {
                    return; // No hacer nada si el año está fuera de rango
                }

                // Actualizar ambos selectores
                selectUp.value = newYear;
                selectDown.value = newYear;

                // Llamar a goToReport para enviar el formulario
                goToReport('anioSelectorUp');
            }

            function goToReport(inputId) {
                const year = document.getElementById(inputId).value;
                if (year) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = "{{ route('getreporteanual') }}";

                    const csrfToken = document.createElement('input');
                    csrfToken.type = 'hidden';
                    csrfToken.name = '_token';
                    csrfToken.value = "{{ csrf_token() }}";

                    const yearInput = document.createElement('input');
                    yearInput.type = 'hidden';
                    yearInput.name = 'anio';
                    yearInput.value = year;

                    form.appendChild(csrfToken);
                    form.appendChild(yearInput);

                    document.body.appendChild(form);
                    form.submit();
                }
            }
        </script>
    </x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style type="text/css">
        .theadreport{
            background-color: #093143;
            color: white;
        }
        h3{
            font-weight: bold!important;
            font-size: 1.4rem!important;
        }
        .chart-container {
            display: flex;
            width: 100%;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #ccc;
        }
        .chart {
            width: 50%;
            text-align: center;
            padding: 1rem;
            margin-top: 1rem;
        }
        .bar-container {
            position: relative;
            height: 40px;
            background: linear-gradient(to right, #1bc900, #fcd703, #d1200d);
            border-radius: 70px;
        }
        .bar {
            height: 100%;
            background-color: blue;
            position: absolute;
            top: 0;
            border-radius: 70px;
        }
        .bar-label {
            position: absolute;
            top: -20px;
            color: #333333;
            font-weight: bold;
        }
        .addborderbottom {
            border-bottom: 2px solid #666666!important;
        }
        .table{
            margin: 10px 0px !important;
        }
    </style>
@stop

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleMode = () => {
                const daily = document.getElementById('dailygraph');
                const nightly = document.getElementById('nightlygraph');
                if (daily.style.display === 'none') {
                    daily.style.display = 'block';
                    nightly.style.display = 'none';
                } else {
                    daily.style.display = 'none';
                    nightly.style.display = 'block';
                }
            };
        });
    </script>
@stop

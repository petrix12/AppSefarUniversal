@extends('adminlte::page')

@section('title', 'Reporte')

@section('content_header')

@stop

@section('content')
    <x-app-layout>

        <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

        <script type="text/javascript">
            const fechaActual = new Date("{{$peticion['año']}}-{{$peticion['mes']}}-{{$peticion['dia']}}");

            function navigateToReport(dias) {
                fechaActual.setDate(fechaActual.getDate() + dias);
                const nuevoDia = fechaActual.getDate();
                const nuevoMes = fechaActual.getMonth() + 1; // Los meses en JavaScript son de 0-11
                const nuevoAño = fechaActual.getFullYear();

                // Actualiza los campos del formulario oculto
                document.getElementById('hiddenDia').value = nuevoDia;
                document.getElementById('hiddenMes').value = nuevoMes;
                document.getElementById('hiddenAño').value = nuevoAño;

                // Envía el formulario
                document.getElementById('dateForm').submit();
            }
        </script>

        <form id="dateForm" action="{{ route('getreportediario') }}" method="POST" style="display: none;">
            @csrf
            <input type="hidden" name="dia" id="hiddenDia" value="{{$peticion['dia']}}">
            <input type="hidden" name="mes" id="hiddenMes" value="{{$peticion['mes']}}">
            <input type="hidden" name="año" id="hiddenAño" value="{{$peticion['año']}}">
        </form>

        <div class="flex flex-col">
            <div class="">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div >
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <center>
                                <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                    <span class="ctvSefar block text-indigo-600">Reporte Diario - {{$peticion["dia"]}}/{{$peticion["mes"]}}/{{$peticion["año"]}}</span>
                                </h2>
                            </center>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>

        <div class="flex justify-between max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8">
            <!-- Botón de día anterior -->
            <button onclick="navigateToReport(-1)" class="cfrSefar text-white bg-indigo-600 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Día Anterior
            </button>

            <!-- Botón de día siguiente -->
            <button onclick="navigateToReport(1)" class="cfrSefar text-white bg-indigo-600 font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                Día Siguiente
            </button>
        </div>
        
        <div class="card p-4">
            <center>
                <h3 style="margin-bottom: 0rem;">Usuarios registrados en el día: {{$registrosHoy}}</h3>
            </center>
            
            <div class="chart-container">
                <div class="chart">
                    <h3>Mes Actual - {{$peticion["año"]}}</h3><br>
                    <div class="bar-container">
                        <div class="bar" style="width: {{$datosgraficosporcentaje['mes_actual']['promedio']}}%;"></div>
                        <div class="bar-label" style="left: {{$datosgraficosporcentaje['mes_actual']['promedio']}}%;">{{$datosgraficosporcentaje['mes_actual']['promedio']}}%</div>
                    </div>
                    <br>
                    <p>
                        <strong>Mínimo:</strong> {{$datosgraficos['mes_actual']['minimo']}}<br>
                        <strong>Máximo:</strong> {{$datosgraficos['mes_actual']['maximo']}}<br>
                        <strong>Promedio:</strong> {{$datosgraficos['mes_actual']['promedio']}}<br>
                        <strong>Total Registrados (Mes Actual - {{$peticion["año"]}}):</strong> {{$datosgraficos['mes_actual']['total']}}<br>
                    </p>
                </div>
                <div class="chart">
                    <h3>Mes Anterior - {{$peticion["año"]}}</h3><br>
                    <div class="bar-container">
                        <div class="bar" style="width: {{$datosgraficosporcentaje['mes_anterior']['promedio']}}%;"></div>
                        <div class="bar-label" style="left: {{$datosgraficosporcentaje['mes_anterior']['promedio']}}%;">{{$datosgraficosporcentaje['mes_anterior']['promedio']}}%</div>
                    </div>
                    <br>
                    <p>
                        <strong>Mínimo:</strong> {{$datosgraficos['mes_anterior']['minimo']}}<br>
                        <strong>Máximo:</strong> {{$datosgraficos['mes_anterior']['maximo']}}<br>
                        <strong>Promedio:</strong> {{$datosgraficos['mes_anterior']['promedio']}}<br>
                        <strong>Total Registrados (Mes Anterior - {{$peticion["año"]}}):</strong> {{$datosgraficos['mes_anterior']['total']}}<br>
                    </p>
                </div>
            </div>
            <div class="chart-container">
                <div class="chart">
                    <h3>Mes Actual - {{$peticion["año"] - 1}}</h3><br>
                    <div class="bar-container">
                        <div class="bar" style="width: {{$datosgraficosporcentaje['mes_actual_aa']['promedio']}}%;"></div>
                        <div class="bar-label" style="left: {{$datosgraficosporcentaje['mes_actual_aa']['promedio']}}%;">{{$datosgraficosporcentaje['mes_actual_aa']['promedio']}}%</div>
                    </div>
                    <br>
                    <p>
                        <strong>Mínimo:</strong> {{$datosgraficos['mes_actual_aa']['minimo']}}<br>
                        <strong>Máximo:</strong> {{$datosgraficos['mes_actual_aa']['maximo']}}<br>
                        <strong>Promedio:</strong> {{$datosgraficos['mes_actual_aa']['promedio']}}<br>
                        <strong>Total Registrados (Mes Actual - {{$peticion["año"] - 1}}):</strong> {{$datosgraficos['mes_actual_aa']['total']}}<br>
                    </p>
                </div>
                <div class="chart">
                    <h3>Mes Anterior - {{$peticion["año"] - 1}}</h3><br>
                    <div class="bar-container">
                        <div class="bar" style="width: {{$datosgraficosporcentaje['mes_anterior_aa']['promedio']}}%;"></div>
                        <div class="bar-label" style="left: {{$datosgraficosporcentaje['mes_anterior_aa']['promedio']}}%;">{{$datosgraficosporcentaje['mes_anterior_aa']['promedio']}}%</div>
                    </div>
                    <br>
                    <p>
                        <strong>Mínimo:</strong> {{$datosgraficos['mes_anterior_aa']['minimo']}}<br>
                        <strong>Máximo:</strong> {{$datosgraficos['mes_anterior_aa']['maximo']}}<br>
                        <strong>Promedio:</strong> {{$datosgraficos['mes_anterior_aa']['promedio']}}<br>
                        <strong>Total Registrados (Mes Anterior - {{$peticion["año"] - 1}}):</strong> {{$datosgraficos['mes_anterior_aa']['total']}}<br>
                    </p>
                </div>
            </div>

            <div style="width:100%">
                <center>
                    <style>
                        #dailygraph{
                            display: block!important;height: 100%
                        }

                        #nightlygraph{
                            display: none!important;height: 100%
                        }
                    </style>
                    <div id="chart_div" style="height: 500px;">
                        <img id="dailygraph" src="{{$chartUrl}}">
                        <img id="nightlygraph" src="{{$chartNight}}">
                    </div>
                </center>
            </div>
        </div>

        <div class="card p-4">
            <center>
                <h3 style="margin-bottom: 0rem;">Usuarios registrados:</h3><br>
            </center>
            <div class="table-responsive">
                <table class="table" style="margin:0">
                    <thead class="theadreport">
                        <tr>
                            <th>Nombre</th>
                            <th>Apellido</th>
                            <th>Servicio</th>
                            <th>Estado de Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($usuariosHoy as $usuario)
                        <tr>
                            <td>{{ $usuario->nombres }}</td>
                            <td>{{ $usuario->apellidos }}</td>
                            <td>{{ $usuario->compras->pluck('servicio_hs_id')->join(', ') }}</td>
                            <td>
                                @if ($usuario->pay == 0)
                                    No ha pagado
                                @elseif ($usuario->pay == 1)
                                    Pagó pero no completó información
                                @elseif ($usuario->pay == 2)
                                    @if ($usuario->contrato == 0)
                                        Pagó y completó información
                                    @elseif ($usuario->contrato == 1)
                                        Pagó, completó información y firmó contrato
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
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
    </style>
@stop

@section('js')

@stop

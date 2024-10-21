@extends('adminlte::page')

@section('title', 'Reporte')

@section('content_header')

@stop

@section('content')
@php
    // Configura la localización a español
    setlocale(LC_TIME, 'es_ES', 'Spanish_Spain', 'es_ES.UTF-8');

    // Convertimos el número del mes actual en su nombre en español
    $nombreMesActual = ucfirst(strftime('%B', mktime(0, 0, 0, $peticion["mes"], 10)));

    // Calculamos el mes anterior (si es enero, va a diciembre del año anterior)
    $mesAnterior = $peticion["mes"] - 1;
    if ($mesAnterior < 1) {
        $mesAnterior = 12;
    }
    $nombreMesAnterior = ucfirst(strftime('%B', mktime(0, 0, 0, $mesAnterior, 10)));
@endphp
    <x-app-layout>
        <div class="flex flex-col">
            <div class="">
                <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                    {{-- Inicio --}}
                    <div >
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:py-6 lg:px-8 lg:flex lg:items-center lg:justify-between">
                            <center>
                                <h2 class="text-3xl font-extrabold tracking-tight text-gray-900 sm:text-4xl">
                                    <span class="ctvSefar block text-indigo-600">Reporte Mensual - {{$nombreMes}}</span>
                                </h2>
                            </center>
                        </div>
                    </div>
                    {{-- Fin --}}
                </div>
            </div>
        </div>


        <center>
            <div class="card p-4">
                <h3>Usuarios registrados en el mes: {{$registrosHoy}}</h3>
                <div class="chart-container">
                    <div class="chart">
                        <h3>{{$nombreMesAnterior}} - {{$peticion["año"]}}</h3><br>
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
                    <div class="chart">
                        <h3>{{$nombreMesActual}} - {{$peticion["año"]}}</h3><br>
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

                </div>
                <div class="chart-container">

                    <div class="chart">
                        <h3>{{$nombreMesAnterior}} - {{$peticion["año"] - 1}}</h3><br>
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
                    <div class="chart">
                        <h3>{{$nombreMesActual}} - {{$peticion["año"] - 1}}</h3><br>
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
                </div>
            </div>

            <div class="card p-4">
                <center>
                    <h3 style="margin-bottom: 1rem;">Registros de Usuarios en los Últimos 30 Días</h3>
                </center>
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
                    <h3 style="margin-bottom: 1rem;">Usuarios registrados por estatus:</h3>
                </center>
                <div class="table-responsive">
                    <table class="table" style="margin:0 auto; width:50%!important;">
                        <thead class="theadreport">
                            <tr>
                                <th>Estatus</th>
                                <th>Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // Agrupar usuarios por su estatus de pago
                                $estatusCount = [
                                    'No ha pagado' => $usuariosHoy->where('pay', 0)->count(),
                                    'Pagó pero no completó información' => $usuariosHoy->where('pay', 1)->count(),
                                    'Pagó y completó información, pero no firmó contrato' => $usuariosHoy->where('pay', 2)->where('contrato', 0)->count(),
                                    'Pagó, completó información y firmó contrato' => $usuariosHoy->where('pay', 2)->where('contrato', 1)->count(),
                                ];
                            @endphp

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
                    <h3 style="margin-bottom: 1rem;">Cantidad de usuarios registrados por servicio:</h3></center>
                    <div class="table-responsive">
                        <table class="table" style="margin:0 auto; width:50%!important;">
                            <thead class="theadreport">
                                <tr>
                                    <th>Servicio</th>
                                    <th>Cantidad de Usuarios Registrados</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($usuariosPorServicio as $servicio => $cantidad)
                                <tr>
                                    <td>{{ $servicio }}</td>
                                    <td>{{ $cantidad }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <p><small>* Un usuario puede contratar multiples servicios en Sefar Universal, por lo que puede ser que hay mas elementos en esta tabla que en la tabla de Usuarios Registrados por Estatus</small></p>
                </center>
            </div>

            <div class="card p-4">
                <center>
                    <h3 style="margin-bottom: 1rem;">Monto Acumulado Pagado por Servicio:</h3>
                </center>
                @php
                    $totalMonto = array_sum($facturas);
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
                                <td><strong>{{ $totalMonto }}€</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p><small>* Solo se consideran los pagos hechos a través de la pasarela de pago de <a href="https://app.sefaruniversal.com" target="_blank">app.sefaruniversal.com</a></small></p>
                </center>
            </div>
        </center>

        <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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

@stop

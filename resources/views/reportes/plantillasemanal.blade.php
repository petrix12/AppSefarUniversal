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

    function mostrarGraficoQuickChart($url) {
        // Intentar obtener la imagen
        $imageData = file_get_contents($url);

        // Verificar si la solicitud fue exitosa
        if ($imageData !== false) {
            // Crear una imagen temporal
            $tmpfile = tempnam(sys_get_temp_dir(), 'chart');
            file_put_contents($tmpfile, $imageData);

            // Mostrar la imagen
            echo '<img src="' . $tmpfile . '" alt="Gráfico Diario" style="width: 100%; height: auto;">';
        } else {
            echo '<p>No se pudo cargar el gráfico. Por favor, inténtalo más tarde.</p>';
        }
    }
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Semanal</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
        }

        body:before {
            display: block;
            position: fixed;
            top: -1in; right: -1in; bottom: -1in; left: -1.5in;
            background-image: url('{{ public_path("/img/reportes/semanal.png") }}');
            background-size: cover;
            background-repeat: no-repeat;
            content: "";
            z-index: -1000;
        }

        @page {
            margin: 2cm 1.8cm 2cm 2.8cm!important;
            background-image: ;
            background-repeat: no-repeat;
            background-position: center;
            background-size: cover;
        }

        .logo {
            width: 5cm;
        }

        body {
            font-family: 'Arial', sans-serif;
        }

        .card {
            border: 0;
        }

        .chart-container {
            margin-top: 20px;
            width: 50%;
        }

        .bar-container {
            background-color: #f4f4f4;
            border: 1px solid #ccc;
            width: 100%;
            height: 24px;
            position: relative;
        }

        .bar {
            background-color: #093143 !important;
            color: rgba(255, 255, 255, .9);
            height: 100%;
        }

        .bar-label {
            position: absolute;
            top: 0;
            color: black;
        }

        .theadreport {
            background-color: #093143 !important;
            color: rgba(255, 255, 255, .9);
            color: white;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="card">
            <center>
                <img class='logo' src='{{ public_path("/img/logonormal.png") }}' />
                <h2>Reporte Semanal - {{$fechaInicioFormato}} - {{$fechaFinFormato}}</h2>
            </center>
            <h3>Usuarios registrados en la semana: {{$registrosHoy}}</h3>
            <table style="border: none;">
                <tr>
                    <td>
                        <center>
                        <h3>{{$nombreMesAnterior}} - {{$peticion["año"]}}</h3>
                        <div class="bar-container">
                            <div class="bar" style="width: {{$datosgraficosporcentaje['mes_anterior']['promedio']}}%;"></div>
                            <div class="bar-label" style="left: {{$datosgraficosporcentaje['mes_anterior']['promedio']}}%;">{{$datosgraficosporcentaje['mes_anterior']['promedio']}}%</div>
                        </div>
                        <p>
                            <strong>Mínimo:</strong> {{$datosgraficos['mes_anterior']['minimo']}}<br>
                            <strong>Máximo:</strong> {{$datosgraficos['mes_anterior']['maximo']}}<br>
                            <strong>Promedio:</strong> {{$datosgraficos['mes_anterior']['promedio']}}<br>
                            <strong>Total (Mes Anterior - {{$nombreMesAnterior}}):</strong> {{$datosgraficos['mes_anterior']['total']}}<br>
                        </p>
                        </center>
                    </td>
                    <td>
                        <center>
                        <h3>{{$nombreMesActual}} - {{$peticion["año"]}}</h3>
                        <div class="bar-container">
                            <div class="bar" style="width: {{$datosgraficosporcentaje['mes_actual']['promedio']}}%;"></div>
                            <div class="bar-label" style="left: {{$datosgraficosporcentaje['mes_actual']['promedio']}}%;">{{$datosgraficosporcentaje['mes_actual']['promedio']}}%</div>
                        </div>
                        <p>
                            <strong>Mínimo:</strong> {{$datosgraficos['mes_actual']['minimo']}}<br>
                            <strong>Máximo:</strong> {{$datosgraficos['mes_actual']['maximo']}}<br>
                            <strong>Promedio:</strong> {{$datosgraficos['mes_actual']['promedio']}}<br>
                            <strong>Total ({{$nombreMesActual}} - {{$peticion["año"]}}):</strong> {{$datosgraficos['mes_actual']['total']}}<br>
                        </p>
                        </center>
                    </td>
                </tr>
                <tr>
                    <td>
                        <center>
                            <h3>{{$nombreMesAnterior}} - {{$peticion["año"] - 1}}</h3>
                            <div class="bar-container">
                                <div class="bar" style="width: {{$datosgraficosporcentaje['mes_anterior']['promedio']}}%;"></div>
                                <div class="bar-label" style="left: {{$datosgraficosporcentaje['mes_anterior']['promedio']}}%;">{{$datosgraficosporcentaje['mes_anterior']['promedio']}}%</div>
                            </div>
                            <p>
                                <strong>Mínimo:</strong> {{$datosgraficos['mes_anterior_aa']['minimo']}}<br>
                                <strong>Máximo:</strong> {{$datosgraficos['mes_anterior_aa']['maximo']}}<br>
                                <strong>Promedio:</strong> {{$datosgraficos['mes_anterior_aa']['promedio']}}<br>
                                <strong>Total ({{$nombreMesAnterior}} - {{$peticion["año"] - 1}}):</strong> {{$datosgraficos['mes_anterior_aa']['total']}}<br>
                            </p>
                        </center>
                    </td>
                    <td>
                        <center>
                            <h3>{{$nombreMesActual}} - {{$peticion["año"] - 1}}</h3>
                            <div class="bar-container">
                                <div class="bar" style="width: {{$datosgraficosporcentaje['mes_actual']['promedio']}}%;"></div>
                                <div class="bar-label" style="left: {{$datosgraficosporcentaje['mes_actual']['promedio']}}%;">{{$datosgraficosporcentaje['mes_actual']['promedio']}}%</div>
                            </div>
                            <p>
                                <strong>Mínimo:</strong> {{$datosgraficos['mes_actual_aa']['minimo']}}<br>
                                <strong>Máximo:</strong> {{$datosgraficos['mes_actual_aa']['maximo']}}<br>
                                <strong>Promedio:</strong> {{$datosgraficos['mes_actual_aa']['promedio']}}<br>
                                <strong>Total ({{$nombreMesActual}} - {{$peticion["año"] - 1}}):</strong> {{$datosgraficos['mes_actual_aa']['total']}}<br>
                            </p>
                        </center>
                    </td>
                </tr>
            </table>
        </div>
        <div style="page-break-before: always;"></div>
        <div class="card">
        <center><img class='logo' src='{{ public_path("/img/logonormal.png") }}' /></center>
            <div style="text-align: center;">
                <img src="{{$chartUrl}}" alt="Gráfico Diario" style="width: 100%; height: auto;">
            </div>
        </div>
        <div style="page-break-before: always;"></div>
        <div class="card">
        <center><img class='logo' src='{{ public_path("/img/logonormal.png") }}' />
            <h3>Usuarios registrados:</h3></center>
            <table>
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
                        <td>
                            @php
                                $servicioHsIds = $usuario->compras->pluck('servicio_hs_id')->join(', ');
                            @endphp

                            {{ $servicioHsIds ? $servicioHsIds : $usuario->servicio }}
                        </td>
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
        <div style="page-break-before: always;"></div>
        <div class="card">
        <center><img class='logo' src='{{ public_path("/img/logonormal.png") }}' />
            <h3>Cantidad de usuarios registrados por servicio:</h3></center>
            <table>
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
    </div>
</body>
</html>

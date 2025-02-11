@php
    $actualanio = $anio;
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte Anual</title>
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
            background-image: url('{{ public_path("/img/reportes/anual.png") }}');
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
            padding: 6px 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="content">
        <center>
            <div class="card">
                <center>
                    <img class='logo' src='{{ public_path("/img/logonormal.png") }}' />
                    <h2>Reporte Anual - {{$anio}}</h2>
                </center>
                <h3>Usuarios registrados durante el año: {{ $usuariosRegistrados }}</h3>
                <table style="border: none;">
                    <tr>
                        <td style="width:50%">
                            <center>
                            <h3>{{$anioAnterior}}</h3>
                            <div class="bar-container">
                                <div class="bar" style="width: {{$datosgraficosporcentaje['anio_anterior']['promedio']}}%;"></div>
                                <div class="bar-label" style="left: {{$datosgraficosporcentaje['anio_anterior']['promedio']}}%;">{{$datosgraficosporcentaje['anio_anterior']['promedio']}}%</div>
                            </div>
                            <p>
                                <strong>Mínimo:</strong> {{$datosgraficos['anio_anterior']['minimo']}}<br>
                                <strong>Máximo:</strong> {{$datosgraficos['anio_anterior']['maximo']}}<br>
                                <strong>Promedio:</strong> {{$datosgraficos['anio_anterior']['promedio']}}<br>
                                <strong>Total {{$anioAnterior}}:</strong> {{$datosgraficos['anio_anterior']['total']}}<br>
                            </p>
                            </center>
                        </td>
                        <td style="width:50%">
                            <center>
                            <h3>{{$anio}}</h3>
                            <div class="bar-container">
                                <div class="bar" style="width: {{$datosgraficosporcentaje['anio_actual']['promedio']}}%;"></div>
                                <div class="bar-label" style="left: {{$datosgraficosporcentaje['anio_actual']['promedio']}}%;">{{$datosgraficosporcentaje['anio_actual']['promedio']}}%</div>
                            </div>
                            <p>
                                <strong>Mínimo:</strong> {{$datosgraficos['anio_actual']['minimo']}}<br>
                                <strong>Máximo:</strong> {{$datosgraficos['anio_actual']['maximo']}}<br>
                                <strong>Promedio:</strong> {{$datosgraficos['anio_actual']['promedio']}}<br>
                                <strong>Total {{$anio}}:</strong> {{$datosgraficos['anio_actual']['total']}}<br>
                            </p>
                            </center>
                        </td>
                    </tr>
                </table>
                <h3 style="margin-top:25px;">Gráfico Comparativo de Registros ({{ $anio }} vs {{ $anioAnterior }})</h3>
                <img src="{{ $chartUrl }}" alt="Gráfico Comparativo" style="width:100%;">
            </div>

            <div style="page-break-before: always;"></div>

            <div class="card">
                <center>
                    <img class='logo' src='{{ public_path("/img/logonormal.png") }}' />
                </center>
                <h3>Usuarios registrados por estatus</h3>
                <table class="table">
                    <thead>
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

            <div style="page-break-before: always;"></div>

            <div class="card">
                <center>
                    <img class='logo' src='{{ public_path("/img/logonormal.png") }}' />
                </center>
                <h3>Usuarios registrados por servicio</h3>
                <table class="table">
                    <thead>
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
                <p><small>* Un usuario puede contratar multiples servicios en Sefar Universal, por lo que puede ser que hay mas elementos en esta tabla que en la tabla de Usuarios Registrados por Estatus</small></p>
            </div>

            <div style="page-break-before: always;"></div>

            <div class="card">
                <center>
                    <img class='logo' src='{{ public_path("/img/logonormal.png") }}' />
                </center>
                <h3>Pagos registrados durante el año (Stripe y Paypal)</h3>
                <table class="table">
                    <thead>
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
                        <tr>
                            <td><strong>Total General:</strong></td>
                            <td><strong>{{ array_sum($facturas) }}€</strong></td>
                        </tr>
                    </tbody>
                </table>
                <p><small>* Solo se consideran los pagos hechos a través de la pasarela de pago de <a href="https://app.sefaruniversal.com" target="_blank">app.sefaruniversal.com</a></small></p>
            </div>

            <div style="page-break-before: always;"></div>

            <div class="card">
                <center>
                    <img class='logo' src='{{ public_path("/img/logonormal.png") }}' />
                </center>
                <h3>Pagos registrados con Cupon</h3>
                <table class="table">
                    <thead>
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
                        <tr>
                            <td><strong>Total General:</strong></td>
                            <td><strong>{{ array_sum($facturasCupones) }}€</strong></td>
                        </tr>
                    </tbody>
                </table>
                <p><small>* Solo se consideran los pagos hechos a través de la pasarela de pago de <a href="https://app.sefaruniversal.com" target="_blank">app.sefaruniversal.com</a></small></p>
                <p><small>* Solo se consideran descuentos del 100%</small></p>
            </div>
        </center>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Comprobante #{{$datos_factura[0]["id"]}}</title>
</head>

<style>
    html, body {
        font-family: Arial, Helvetica, sans-serif;
    }
    @page{
        margin-top: 150px; /* create space for header */
        margin-bottom: 50px; /* create space for footer */
        page-break-inside: avoid;
    }
    main {
        page-break-inside: avoid;
        page-break-after: avoid;
        page-break-before: avoid;
    }
    .styled-table {
        page-break-inside: avoid;
        border-collapse: collapse;
        font-size: 0.9em;
        margin: 10px 0px;
        width: 100%;
    }
    .styled-table thead tr {
        background-color: var(--main-bg-color) !important;
        color: #ffffff;
        text-align: left;
    }
    .styled-table th,
    .styled-table td {
        padding: 8px 11px;
    }
    .styled-table tbody tr {
        border-bottom: 1px solid #dddddd;
    }

    .styled-table tbody tr:nth-of-type(even) {
        background-color: #f3f3f3;
    }

    .styled-table tbody tr:last-of-type {
        background-color: var(--main-bg-color) !important;
    }
    footer {
        position: fixed; 
        bottom: 1px;
        margin-bottom: -30px;
    }
    header{
        position: fixed;
        left: 0px;
        right: 0px;
        margin-top: -100px;
        page-break-after: avoid;
    }
</style>

<body>
    <header>
        <img src="{{asset('/img/logonormal.png')}}" style="height:10%;">
    </header>

    <footer style="text-align: right;">
        {{$datos_factura[0]["hash_factura"]}}
    </footer>
    <main>
            <b>Nombre de Cliente:</b> {{$datos_factura[0]["name"]}}<br>
            <b>Número de Pasaporte:</b> {{$datos_factura[0]["passport"]}}<br>
            <b>Correo Electrónico:</b> {{$datos_factura[0]["email"]}}<br>
            <b>Teléfono:</b> {{$datos_factura[0]["phone"]}}<br>
            <b>Fecha de Registro:</b> {{$datos_factura[0]["fecha_de_registro"]}}<br>
            @if($datos_factura[0]["met"]=="stripe")
                <b>Forma de Pago:</b> Stripe - Tarjeta de Crédito o Débito<br>
            @elseif($datos_factura[0]["met"]=="cupon")
                <b>Forma de Pago:</b> Cupón<br>
            @endif
            
            <p style="width: 100%; text-align: right;"><b>Fecha de Comprobante:</b> <?php echo(date("d-m-Y", strtotime($datos_factura[0]["created_at"]))); ?><br></p>
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Costo(€)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php

                    $total = 0;

                    foreach ($productos as $key => $compra) {
                    ?>

                        <tr>
                            <td style="">{{$compra["descripcion"]}}</td>
                            <td style="">{{$compra["monto"]}}€</td>
                        </tr>

                    <?php
                        $total = $total + $compra["monto"];
                    }

                    ?>
                    <tr>
                        <td style="font-weight: bold; text-align: right;">TOTAL:</td>
                        <td style="font-weight: bold;">{{$total}}€</td>
                    </tr>
                </tbody>
            </table>
    </main>
</body>
</html>
@extends('adminlte::page')

@section('title', 'Estatus del Proceso')

@section('content_header')

@stop

@section('content')

    <div style="padding:30px 50px;">
        <div class="card" style="padding:35px;">
            <center>
                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
                <h4>Datos del Usuario</h4>
                <table style="min-width: 98%; max-width: 98%;">
                    <tr>
                        <td class="title">Nombre</td>
                        <td>{{ ucwords(mb_strtolower($user["name"])) }}</td>
                        <td class="title">Pasaporte</td>
                        <td>{{ $user["passport"] }}</td>
                    </tr>
                    <tr>
                        <td class="title">Correo</td>
                        <td>{{ $user["email"] }}</td>
                        <td class="title">Teléfono</td>
                        <td>{{ $user["phone"] }}</td>
                    </tr>
                    <tr>
                        <td class="title">Fecha de Registro</td>
                        <td>
                            {{ date('d-m-Y', strtotime($user["created_at"])) }}
                        </td>
                        <td class="title">Referido por</td>
                        <td>
                            @foreach ($referidosHS as $referido)
                                @if ($referido["correo"]==$user["referido_por"])
                                    {{ $referido["nombre"] }}
                                    @php
                                        break;
                                    @endphp
                                @endif
                            @endforeach
                        </td>
                    </tr>
                </table>
                
            <br>

            <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
            
            <br>
            
            <h4>Estatus de Procesos Activos</h4>

            </center>
            @if (count($dealsData) > 0)

                @foreach ($dealsData as $deal)
                <center>
                    @if($deal["properties"]["pipeline"] == 94794)
                    <table style="min-width: 98%; max-width: 98%; margin-bottom: 15px;">
                        <tr>
                            <td class="title">Servicio Solicitado</td>
                            <td>
                                @foreach ($servicioHS as $servicio)
                                    @if ($servicio["id_hubspot"]==$user["servicio"])
                                        {{ $servicio["nombre"] }}
                                        @php
                                            break;
                                        @endphp
                                    @endif
                                @endforeach
                            </td>
                        </tr>
                        <tr>
                            <td class="title">Nombre del Proceso (Cliente)</td>
                            <td>
                                {{ eliminarrepetidos(ucwords(mb_strtolower($deal["properties"]["dealname"]))) }}
                            </td>
                        </tr>
                        <tr>
                            <td class="title">Identificador del Proceso</td>
                            <td>
                                {{ $deal["id"] }}
                            </td>
                        </tr>
                        <tr>
                            <td class="title">Estatus del Proceso</td>
                            <td>                                
                                <?php

                                    $porcentaje= 0;

                                    if ($user["pay"] > 0) {
                                        $porcentaje= 9.09/2;
                                    }

                                    if ($user["pay"] > 1) {
                                        $porcentaje= $porcentaje + 9.09/2;
                                    }
                                    
                                    if ($porcentaje>9){
                                        if ($deal["dealstage"]["id"] == "429097" || $deal["dealstage"]["id"] == 429097 || $deal["dealstage"]["id"] == "53192618" || $deal["dealstage"]["id"] == 53192618 ){
                                            $porcentaje= 18.18;
                                            echo("En proceso: Análisis Genealógico");
                                        }

                                        if ($deal["dealstage"]["id"] == "429099" || $deal["dealstage"]["id"] == 429099 || $deal["dealstage"]["id"] == "429098" || $deal["dealstage"]["id"] == 429098) {
                                            $porcentaje= 27.27;
                                            echo("En proceso: Presupuesto");
                                        }

                                        if ($deal["dealstage"]["id"] == "429100" || $deal["dealstage"]["id"] == 429100) {
                                            $porcentaje= 36.36;
                                            echo("En proceso: Informe Genealógico");
                                        }

                                        if ($deal["dealstage"]["id"] == "429101" || $deal["dealstage"]["id"] == 429101) {
                                            $porcentaje= 0.0;
                                            echo("Detenido");
                                        }

                                        if ($deal["dealstage"]["id"] == "68064396" || $deal["dealstage"]["id"] == 68064396) {
                                            $porcentaje= 45.45;
                                            echo("En proceso: Documentos Filiatorios");
                                        }

                                        if ($deal["dealstage"]["id"] == "68064397" || $deal["dealstage"]["id"] == 68064397) {
                                            $porcentaje= 54.54;
                                            echo("En proceso: Certificados CIL");
                                        }

                                        if ($deal["dealstage"]["id"] == "68064398" || $deal["dealstage"]["id"] == 68064398) {
                                            $porcentaje= 63.63;
                                            echo("En proceso: Documentos Legalizados y Apostillados");
                                        }

                                        if ($deal["dealstage"]["id"] == "68064399" || $deal["dealstage"]["id"] == 68064399) {
                                            $porcentaje= 72.72;
                                            echo("En proceso: Revisión de Expediente");
                                        }

                                        if ($deal["dealstage"]["id"] == "68064400" || $deal["dealstage"]["id"] == 68064400) {
                                            $porcentaje= 81.81;
                                            echo("En proceso: Expediente consignado ante el ente gubernamental");
                                        }

                                        if ($deal["dealstage"]["id"] == "68064401" || $deal["dealstage"]["id"] == 68064401) {
                                            $porcentaje= 90.90;
                                            echo("En proceso: Resolución en Espera");
                                        }
                                    } else {
                                        if($user["pay"] == 0){

                                ?>

                                    Falta pago del registro. <a href="/pay">Click aquí</a>

                                <?php

                                        } else if ($user["pay"] == 1) {
                                ?>

                                    Falta completar registro. <a href="/getinfo">Click aquí</a>

                                <?php
                                        }
                                    }

                                    
                                ?>
                            </td>
                        </tr>
                    </table>
                    <br>

                </center>

                <style>
                    .percentp{
                        max-width:  09.09%;
                        width:  09.09%;
                    }

                    .textpercentp{
                        max-width:  18.18%;
                        width:  18.18%;
                        font-size: 14px;
                        text-align: center;
                        padding: 0px 4px;
                    }

                    .textpercentp{
                        max-width:  18.18%;
                        width:  18.18%;
                        font-size: 14px;
                        text-align: center;
                        padding: 0px 4px;
                    }

                    .helperprocess{
                        display: flex;
                    }
                </style>

                <div class="progresscontainer" style="position: relative; height: 180px;">
                    <div class="progress" style="position:absolute;">
                        <div class="progress-bar" style="width:<?php echo($porcentaje); ?>%;">
                        </div>
                    </div>
                    <div class="helperprocess" style="position:absolute; width: 100%; margin-top: 1px;">
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                    </div>
                    <div class="helperprocess" style="position: absolute; margin-top: 47px; width: 100%;">
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Registro
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Presupuesto
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Documentos Filiatorios
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Documentos Legalizados y Apostillados
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Expediente consignado ante el ente gubernamental
                            </div>
                        </div>
                    </div>
                    <div class="helperprocess" style="position: absolute; margin-top: 137px;  width: 100%;">
                        <div style="width:9.10%;">
                            
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Preanalisis
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Informe genealógico
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Certificados CIL
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Revisión de Expediente
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Resolución en Espera
                            </div>
                        </div>
                    </div>
                </div>

                <center>
                    <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
                </center>

                <br>
                    @endif
                @endforeach
            @else
                <center>
                    <table style="min-width: 98%; max-width: 98%; margin-bottom: 15px;">
                        <tr>
                            <td class="title">Servicio Solicitado</td>
                            <td>
                                @foreach ($servicioHS as $servicio)
                                    @if ($servicio["id_hubspot"]==$user["servicio"])
                                        {{ $servicio["nombre"] }}
                                        @php
                                            break;
                                        @endphp
                                    @endif
                                @endforeach
                            </td>
                        </tr>
                        <tr>
                            <td class="title">Estatus del Proceso</td>
                            <td>
                                    En proceso: 
                                    
                                    <?php

                                        $porcentaje= 0;

                                        if ($user["pay"] > 0) {
                                            $porcentaje= 9.09/2;
                                        }

                                        if ($user["pay"] > 1) {
                                            $porcentaje= $porcentaje + 9.09/2;
                                        }
                                        
                                        if($user["pay"] == 0){

                                    ?>

                                        Falta pago del registro. <a href="/pay">Click aquí</a>

                                    <?php

                                        } else if ($user["pay"] == 1) {
                                    ?>

                                        Falta completar registro. <a href="/getinfo">Click aquí</a>

                                    <?php

                                        }

                                    ?>
                            </td>
                        </tr>
                    </table>
                    <br>

                </center>

                <style>
                    .percentp{
                        max-width:  09.09%;
                        width:  09.09%;
                    }

                    .textpercentp{
                        max-width:  18.18%;
                        width:  18.18%;
                        font-size: 14px;
                        text-align: center;
                        padding: 0px 4px;
                    }

                    .textpercentp{
                        max-width:  18.18%;
                        width:  18.18%;
                        font-size: 14px;
                        text-align: center;
                        padding: 0px 4px;
                    }

                    .helperprocess{
                        display: flex;
                    }
                </style>

                <div class="progresscontainer" style="position: relative; height: 180px;">
                    <div class="progress" style="position:absolute;">
                        <div class="progress-bar" style="width:<?php echo($porcentaje); ?>%;">
                        </div>
                    </div>
                    <div class="helperprocess" style="position:absolute; width: 100%; margin-top: 1px;">
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 45px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                    </div>
                    <div class="helperprocess" style="position: absolute; margin-top: 47px; width: 100%;">
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Registro
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Presupuesto
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Documentos Filiatorios
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Documentos Legalizados y Apostillados
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Expediente consignado ante el ente gubernamental
                            </div>
                        </div>
                    </div>
                    <div class="helperprocess" style="position: absolute; margin-top: 137px;  width: 100%;">
                        <div style="width:9.10%;">
                            
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Preanalisis
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Informe genealógico
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Certificados CIL
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Revisión de Expediente
                            </div>
                        </div>
                        <div class="textpercentp">
                            <div style="width:100%;">
                                Resolución en Espera
                            </div>
                        </div>
                    </div>
                </div>

                <center>
                    <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
                </center>

                <br>
            @endif
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        table,tr,td{
            border-collapse: 0;
            border: 1px black solid;
        }
        td {
            padding: 7px 5px;
        }
        .title{
            background-color: #E2E2E2;
            font-weight: bold;
        }
        .progress {
            height: 35px;
            width: 100%;
            border: 1px solid #ffffff;
            border-radius: 5px;
        }
         
        .progress-bar {
            height: 100%;
            display: flex;
            align-items: center;
            transition: width 0.25s;
            border-radius: 5px;
        }
         
        .progress-bar-text {
            margin-left: 10px;
            font-weight: bold;
            color: #ffffff;
        }
    </style>
@stop

@section('js')

@stop

<?php

function eliminarrepetidos($texto){
    $array_palabras = explode(" ", $texto);
    $array_palabras_unicas = array_unique($array_palabras);
    $cadena_sin_repetidos = implode(" ", $array_palabras_unicas);
    return $cadena_sin_repetidos;
}

?>
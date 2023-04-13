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
                @if ( count($dealsData) > 0 )
                <br>

                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
                
                <br>
                
                <h4>Estatus de Procesos Activos</h4>
                @endif

                @foreach ($dealsData as $deal)
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
                                @if ($deal["dealstage"]["metadata"]["isClosed"] == "false")
                                    En proceso: 
                                    @if ( $deal["dealstage"]["id"] == "53192618" || $deal["dealstage"]["id"] == "429097" )
                                        Análisis Genealógico
                                    @else
                                        {{ $deal["dealstage"]["label"] }}
                                    @endif
                                    @if (isset($deal["dealstage"]["estatus_proceso"]))
                                        <br>{{ $deal["dealstage"]["estatus_proceso"] }}
                                    @endif
                                @else
                                    @if ($deal["dealstage"]["label"] == "Perdido")
                                        <a style="color: red;">Detenido</a>
                                    @else
                                        <a style="color: green;">Completado</a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    </table>
                    <br>

                    <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
                    
                    <br>

                    <?php

                        $porcentaje= 0;

                        if ($user["pay"] > 0) {
                            $porcentaje= 9.09/2;
                        }

                        if ($user["pay"] > 1) {
                            $porcentaje= $porcentaje + 9.09/2;
                        }

                        
                        if ($porcentaje>9){
                            if ($deal["dealstage"]["id"] == "429097" || $deal["dealstage"]["id"] == 429097){
                                $porcentaje= $porcentaje + 9.09;
                            }

                            if ($deal["dealstage"]["id"] == "429099" || $deal["dealstage"]["id"] == 429099) {
                                $porcentaje= $porcentaje + 9.09;
                            }
                        }

                        
                    ?>

                    <h4>Progreso del Usuario</h4>

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

                <div class="progresscontainer" style="position: relative;">
                    <div class="progress">
                        <div class="progress-bar" style="width:<?php echo($porcentaje); ?>%;">
                        </div>
                    </div>
                    <div class="helperprocess">
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 35px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 35px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 35px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 35px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 35px;">
                            </div>
                        </div>
                        <div class="percentp">
                            <div style="width:100%; border-right: 1px solid black; height: 135px;">
                            </div>
                        </div>
                    </div>
                    <div class="helperprocess" style="margin-top: -100px;">
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
                    <div class="helperprocess" style="margin-top: 40px;">
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
                    @endif
                @endforeach
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
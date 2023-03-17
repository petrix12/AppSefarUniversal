@extends('adminlte::page')

@section('title', 'Cupones')

@section('content_header')

@stop

@section('content')

    <div style="padding:30px 50px;">
        <div class="card" style="padding:35px;">
            <center>
                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
                <h1>Estatus del Proceso</h1>
                <h4>Datos del Usuario</h4>
                <table style="min-width: 80%; max-width: 98%;">
                    <tr>
                        <td class="title">Nombre</td>
                        <td>{{ ucwords(strtolower($user["name"])) }}</td>
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
                        <td class="title">Fecha de Nacimiento</td>
                        <td>
                            @if(isset($user["date_of_birth"]))
                                {{ date('d-m-Y', strtotime($user["date_of_birth"])) }}
                            @else
                                @if(isset($user["results"][0]["properties"]["date_of_birth"]))
                                    {{ date('d-m-Y', strtotime($user["results"][0]["properties"]["date_of_birth"])) }}
                                @endif
                            @endif
                        </td>
                        <td class="title">País de Nacimiento</td>
                        <td>{{ucwords(strtolower($user["pais_de_nacimiento"]))}}</td>
                    </tr>
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
                        <td class="title">Fecha de Registro</td>
                        <td>
                            {{ date('d-m-Y', strtotime($user["created_at"])) }}
                        </td>
                    </tr>
                    <tr>
                        <td class="title">Pago Inicial</td>
                        <td>
                            @if ($user["pay"]>0)
                                El usuario ya pagó.
                                @if(isset($user["pago_cupon"]) && $user["pago_cupon"]!="")
                                    @if(isset($user["id_pago"]))
                                        Utilizó un cupón de descuento
                                    @else
                                        Utilizó un cupón.
                                    @endif
                                @endif
                            @else
                                El usuario no ha pagado.<br><a href="/pay">Click aquí para pagar.</a>
                            @endif
                        </td>
                        <td class="title">Completar información</td>
                        <td>
                            @if ($user["pay"]>1)
                                El usuario ya completó su información.
                            @else
                                El usuario no completó su información.<br><a href="/getinfo">Click aquí para completarla.</a>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td class="title">Link del Arbol</td>
                        <td>
                            <a href='/tree/{{$user["passport"]}}'>Ir a mi arbol.</a>
                        </td>
                        <td class="title">Familiares registrados</td>
                        <td>
                            {{ count($familiaresR)-1 }}
                        </td>
                    </tr>
                    <tr>
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

                @foreach ($dealsData as $deal)
                    @if($deal["properties"]["pipeline"] == 94794)
                    <table style="min-width: 80%; max-width: 98%; margin-bottom: 15px;">
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
                                {{ $deal["properties"]["dealname"] }}
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
                                    En proceso: {{ $deal["dealstage"]["label"] }}
                                @else
                                    @if ($deal["dealstage"]["label"] == "Perdido")
                                        <a style="color: red;">Detenido</a>
                                    @else
                                        <a style="color: green;">Completado</a>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td class="title">Porcentaje del Proceso</td>
                            <td>
                                @if ($deal["dealstage"]["metadata"]["isClosed"] == "false")
                                    <div class="progress">
                                        <div class="progress-bar" style="background-color: green;width:<?php echo($deal["dealstage"]["metadata"]["probability"]*100); ?>%;">
                                            <span class="progress-bar-text"><?php echo($deal["dealstage"]["metadata"]["probability"]*100); ?>%</span>
                                        </div>
                                    </div>
                                @else
                                    @if ($deal["dealstage"]["label"] == "Perdido")
                                        <div class="progress">
                                            <div class="progress-bar" style="background-color: red; width:<?php echo($deal["dealstage"]["metadata"]["probability"]*100); ?>%;">
                                                <span class="progress-bar-text"><?php echo($deal["dealstage"]["metadata"]["probability"]*100); ?>%</span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="progress">
                                            <div class="progress-bar" style="background-color: Green; width:<?php echo($deal["dealstage"]["metadata"]["probability"]*100); ?>%;">
                                                <span class="progress-bar-text"><?php echo($deal["dealstage"]["metadata"]["probability"]*100); ?>%</span>
                                            </div>
                                        </div>
                                    @endif
                                @endif
                            </td>
                        </tr>
                    </table>
                    @endif
                @endforeach

            </center>
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
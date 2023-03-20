@extends('adminlte::page')

@section('title', 'Estatus del Proceso')

@section('content_header')

@stop

@section('content')

    <div style="padding:30px 50px;">
        <div class="card" style="padding:35px;">
            <center>
                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
                <h1>Estatus del Proceso</h1>
                <h4>Datos del Usuario</h4>
                <table style="min-width: 98%; max-width: 98%;">
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
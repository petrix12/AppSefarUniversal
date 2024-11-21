@extends('adminlte::page')

@section('title', 'Estatus del Proceso')

@section('content_header')

@stop

@section('content')

    <div style="padding:30px 50px;">
        <div class="card" style="padding:35px;">
            <center>
                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
            </center>
           {{ json_encode($TLcontact) }}
        </div>
    </div>

    <div style="padding:30px 50px;">
        <div class="card" style="padding:35px;">
            <center>
                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
            </center>
           {{ json_encode($HScontact) }}
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

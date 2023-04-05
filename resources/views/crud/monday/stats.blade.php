@extends('adminlte::page')

@section('title', 'Monday Estadísticas')

@section('content_header')

@stop

@section('content')

<script
src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js">
</script>

<style>
    table, tr, td{
        border-collapse: collapse; 
        border: 1px solid black;
    }
</style>

<script>
    var colors = [
        "#ff5252",
        "#f0c755",
        "#ff6138",
        "#440505",
        "#ffbe00",
        "#d95100",
        "#d50000",
        "#fff176",
        "#ff8c00",
        "#c62828",
        "#f2d03b",
        "#de6d00",
        "#4c1b1b",
        "#ffdc00",
        "#d23600",
    ];
</script>

<x-app-layout>
    <div class="card" style="padding: 25px; margin: 20px 15%;">

        <center>
            <h1 style="font-weight: bold;">Tablas de Genealogía</h1>

            @foreach ($stats as $key => $value)
                @php
                    $total = 0;
                @endphp

                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">
                
                <h3 style="font-weight: bold;">{{$key}}</h3>

                <table style="min-width: 60%; margin-top: 5px;">
                    @foreach ($value as $key1 => $value1)
                        <tr>
                            <td style="padding: 4px 7px;">
                                <b>
                                    @if($key1 == '' || !isset($key1))
                                        Sin Estatus
                                    @else
                                        {{$key1}}
                                    @endif
                                </b>
                            </td>
                            <td style="text-align: center; padding: 4px 7px; width: 20%;">{{$value1}}</td>
                        </tr>
                        @php
                            $total = $total + $value1;
                        @endphp
                    @endforeach
                    <tr>
                        <td style="padding: 4px 7px;">
                            Total de Clientes en <b>{{$key}}</b>:
                        </td>
                        <td style="text-align: center; padding: 4px 7px; width: 20%;">{{$total}}</td>
                    </tr>
                </table>

                <br>

                <div style="min-width: 100%;max-width: 100%;"><canvas id="{{str_replace(' ', '', $key)}}"></canvas></div>

                <script>

                    var data = {
                        labels: [
                            <?php
                                foreach ($value as $key1 => $value1){
                                    echo('"');
                                    if($key1 == '' || !isset($key1)){
                                        echo("Sin Estatus");
                                    }
                                    else {
                                        echo($key1);
                                    }
                                    echo('", ');
                                }
                            ?>
                        ],
                        datasets: [{
                            label: '{{$key}}',
                            data: [
                                <?php
                                    foreach ($value as $key1 => $value1){
                                        echo $value1 . ",";
                                    }
                                ?>
                            ],
                            backgroundColor: [
                                <?php
                                    $i = 0;
                                    foreach ($value as $key1 => $value1){
                                        echo("colors[" . $i . "], ");
                                        $i++;
                                    }
                                ?>
                            ],
                            hoverOffset: 2
                        }]
                    };

                    var config = {
                        type: 'doughnut',
                        data: data,
                    };

                    new Chart("{{str_replace(' ', '', $key)}}", config);
                </script>

                <br>

            @endforeach

            <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">

            <br>

            <h1 style="font-weight: bold;">Etiquetado Ventas Sefar</h1>

            @foreach ($eventas as $key => $value)
                @php
                    $total = 0;
                @endphp

                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">

                <table style="min-width: 60%; margin-top: 5px;">
                    @foreach ($value as $key1 => $value1)
                        <tr>
                            <td style="padding: 4px 7px;">
                                <b>
                                    @if($key1 == '' || !isset($key1))
                                        Sin Estatus
                                    @else
                                        {{$key1}}
                                    @endif
                                </b>
                            </td>
                            <td style="text-align: center; padding: 4px 7px; width: 20%;">{{$value1}}</td>
                        </tr>
                        @php
                            $total = $total + $value1;
                        @endphp
                    @endforeach
                    <tr>
                        <td style="padding: 4px 7px;">
                            Total de Clientes en <b>{{$key}}</b>:
                        </td>
                        <td style="text-align: center; padding: 4px 7px; width: 20%;">{{$total}}</td>
                    </tr>
                </table>

                <br>

                <div style="min-width: 100%;max-width: 100%;"><canvas id="{{str_replace(' ', '', $key)}}"></canvas></div>

                <script>

                    var data = {
                        labels: [
                            <?php
                                foreach ($value as $key1 => $value1){
                                    echo('"');
                                    if($key1 == '' || !isset($key1)){
                                        echo("Sin Estatus");
                                    }
                                    else {
                                        echo($key1);
                                    }
                                    echo('", ');
                                }
                            ?>
                        ],
                        datasets: [{
                            label: '{{$key}}',
                            data: [
                                <?php
                                    foreach ($value as $key1 => $value1){
                                        echo $value1 . ",";
                                    }
                                ?>
                            ],
                            backgroundColor: [
                                <?php
                                    $i = 0;
                                    foreach ($value as $key1 => $value1){
                                        echo("colors[" . $i . "], ");
                                        $i++;
                                    }
                                ?>
                            ],
                            hoverOffset: 2
                        }]
                    };

                    var config = {
                        type: 'doughnut',
                        data: data,
                    };

                    new Chart("{{str_replace(' ', '', $key)}}", config);
                </script>

                <br>

            @endforeach

        </center>

    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
@extends('adminlte::page')

@section('title', 'Monday Estadísticas')

@section('content_header')

@stop

@section('content')
<style>
    table, tr, td{
        border-collapse: collapse; 
        border: 1px solid black;
    }
</style>

<x-app-layout>
    <div class="card" style="padding: 25px; margin: 20px 15%;">

        <center>

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

            @endforeach

                <img src="/vendor/adminlte/dist/img/LogoSefar.png" style="width:50px;">

        </center>

    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
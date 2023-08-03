@extends('adminlte::page')

@section('title', 'Monday Estadísticas')

@section('content_header')

@stop

@section('content')

<script
src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js">
</script>

<x-app-layout>
    <div class="card" style="padding: 25px; margin: 20px 15%;">

        <center>
            <div style="width: 80%;">
                <h4>Pasaporte del Cliente</h4><br>
                @csrf
                <input type="text" placeholder="Número de Pasaporte del Cliente">
                <input type="button" value="Enviar a Monday">
            </div>
        </center>

    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
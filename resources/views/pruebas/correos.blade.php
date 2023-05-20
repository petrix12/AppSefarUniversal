@extends('adminlte::page')

@section('title', 'Pruebas Correos')

@section('content_header')
    <h1>Pruebas de Correos</h1>
@stop

@section('content')
    <form action='{{route("sendcorreo")}}' method="POST">
        @csrf
        <div class="container" style="padding:20px 50px">
            <div class="card" style="padding: 40px;     display: -webkit-box;">
                <input type="email" name="email" class="form-control" id="exampleInputEmail1" aria-describedby="emailHelp" placeholder="Ingresar Correo" style="width: 75%;">
                <button type="submit" class="btn btn-primary" style="width: 25%;">Enviar Correo de Prueba</button>
            </div>
        </div>
    </form>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css\prueba_flex.css') }}">
@stop

@section('js')

@stop
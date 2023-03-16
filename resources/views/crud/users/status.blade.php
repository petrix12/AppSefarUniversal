@extends('adminlte::page')

@section('title', 'Cupones')

@section('content_header')

@stop

@section('content')


    {{ $user }}

    <br><br>

    {{ json_encode($contactHS) }}

    <br><br>

    {{ json_encode($dealsData) }}


@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop
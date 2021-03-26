@extends('adminlte::page')

@section('title', 'Prueba Agclientes')

@section('content_header')
    <h1>Prueba Agclientes</h1>
@stop

@section('content')
    <p>Prueba Agclientes.</p>
    {{-- {{ $agclientes->find(2)->Nombre }} --}}
    @dump($agclientes->find(104))
@stop

@section('css')
@stop

@section('js')

@stop
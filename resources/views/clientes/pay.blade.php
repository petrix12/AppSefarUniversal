@extends('adminlte::page')

@section('title', 'Pago')

@section('content_header')
    <h1>Realizar pago</h1>
@stop

@section('content')
    <div class="container m-3">
        <script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>
        <script>
        hbspt.forms.create({
            region: "na1",
            portalId: "20053496",
            formId: "56685521-d9ee-48cc-b4ec-b31b3d00dbaa"
        });
        </script>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

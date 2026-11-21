@extends('adminlte::page')

@section('title', 'Contrato')

@section('content_header')
    <h1>Contrato</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    @if(\App\Services\RegistrationMeetingService::supportsUser(auth()->user()))
        <div class="alert alert-info" role="status">
            Al completar el contrato, continuarás con la agenda de la reunión incluida en tu servicio.
        </div>
    @endif

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <iframe style="border: none;" width="100%" height="500px" src="https://www.jotform.com/SfarVzla/isp---firma-de-contrato?nombres={{auth()->user()->nombres}}&nac_solicitada={{auth()->user()->servicio}}&apellidos={{auth()->user()->apellidos}}&nro_pasaporte={{auth()->user()->passport}}&nroDe={{auth()->user()->email}}"></iframe>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

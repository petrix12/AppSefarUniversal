@extends('adminlte::page')

@section('title', 'Contrato')

@section('content_header')
    <h1>Contrato</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <script type="text/javascript" src="https://form.jotform.com/jsform/231384136753659"></script>

    <script>
        $('#231384136753659').on('load',function(){
            var inputText = $('#231384136753659').contents().find('#input_67');
            var inputText2 = $('#231384136753659').contents().find('#input_68');
            var inputText3 = $('#231384136753659').contents().find('#input_329');
            var inputText4 = $('#231384136753659').contents().find('#input_330');
            inputText.val('{{auth()->user()->nombres}}').change();
            inputText2.val('{{auth()->user()->apellidos}}').change();
            inputText3.val('{{auth()->user()->passport}}').change();
            inputText4.val('{{auth()->user()->servicio}}').change();
        });
    </script>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

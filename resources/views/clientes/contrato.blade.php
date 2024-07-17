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
        document.addEventListener('DOMContentLoaded', function() {
          var iframe = document.getElementById('231384136753659');

          iframe.addEventListener('load', function() {

              var inputText = iframe.contentWindow.document.getElementById('input_67');
              var inputText2 = iframe.contentWindow.document.getElementById('input_68');
              var inputText3 = iframe.contentWindow.document.getElementById('input_329');
              var inputText4 = iframe.contentWindow.document.getElementById('input_330');

              console.log(inputText); // Para depuración

              inputText.value = '{{auth()->user()->nombres}}';
              inputText.dispatchEvent(new Event('change')); // Activar evento change
              inputText2.value = '{{auth()->user()->apellidos}}';
              inputText2.dispatchEvent(new Event('change'));
              inputText3.value = '{{auth()->user()->passport}}';
              inputText3.dispatchEvent(new Event('change'));
              inputText4.value = '{{auth()->user()->servicio}}';
              inputText4.dispatchEvent(new Event('change'));
              
          });
        });
    </script>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

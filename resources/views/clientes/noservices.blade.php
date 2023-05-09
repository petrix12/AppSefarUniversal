@extends('adminlte::page')

@section('title', 'Completar información')

@section('content_header')
    <h1>Completar información</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script type="text/javascript">
        Swal.fire({
            icon: 'error',
            title: '¡Uy!',
            html: '<p>No tiene ningún proceso/servicio para pagar en estos momentos.</p><p>Será redirigido a nuestra página principal.</p>',
                showDenyButton: false,
                confirmButtonText: 'Continuar',
                denyButtonText: 'Volver a la Página Principal',
        }).then((result) => {
            window.location.replace("https://www.sefaruniversal.com");
        });
    </script>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

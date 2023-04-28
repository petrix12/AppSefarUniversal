@extends('adminlte::page')

@section('title', 'Completar información')

@section('content_header')
    <h1>Completar información</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    @if (auth()->user()->pay == 2 || auth()->user()->pay == '2')
        @csrf
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script type="text/javascript">
            Swal.fire({
                icon: 'success',
                title: '¡Pago procesado correctamente!',
                html: '<p>En Sefar Universal estamos muy complacido por tenerlo entre nuestros clientes.</p><p>En las proximas horas, estaremos comunicándonos con usted para atender su solicitud de <b>{{auth()->user()->servicio}}</b>.</p><p>Por favor presione el botón de <strong>Continuar</strong> para que sea redirigido a nuestra plataforma de carga genealógica.</p><small><p><strong>Nota: </strong>Si ya usted era cliente antiguo de Sefar Universal, toda su información del genealógica estará disponible para a continuación.</p></small></span>',
                    showDenyButton: false,
                    confirmButtonText: 'Continuar',
                    denyButtonText: 'Volver a la Página Principal',
            }).then((result) => {
                /* Read more about isConfirmed, isDenied below */
                if (result.isDenied) {
                    window.location.replace("https://www.sefaruniversal.com");
                } else {
                    window.location.replace("/tree");
                }
            });
        </script>
    @else
        @csrf
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.2/jquery.min.js" integrity="sha512-tWHlutFnuG0C6nQRlpvrEhE4QpkG1nn2MOUMWmUeRePl4e3Aki0VB6W1v3oLjFtd0hVOtRQ9PHpSfN6u6/QXkQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script type="text/javascript">
            Swal.fire({
                icon: 'success',
                title: '¡Pago procesado correctamente!',
                html: '<p>En Sefar Universal estamos muy complacido por tenerlo entre nuestros clientes.</p><p>Para continuar con el proceso es muy importante que nos suministre en la medida de los posible toda la información de sus ancestros.</p><p>Por favor presione el botón de <strong>Continuar</strong> para que sea redirigido a nuestra plataforma de carga genealógica.</p><small><p><strong>Nota: </strong>tenga en cuenta que mientras más información genealógica aporte, más fácil y rápido será su proceso de estudio.</p></small></span>',
                    showDenyButton: false,
                    confirmButtonText: 'Continuar',
                    denyButtonText: 'Volver a la Página Principal',
            }).then((result) => {
                /* Read more about isConfirmed, isDenied below */
                if (result.isDenied) {
                    window.location.replace("https://www.sefaruniversal.com");
                } else {
                    window.location.replace("/getinfo");
                }
            });
        </script>
    @endif

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

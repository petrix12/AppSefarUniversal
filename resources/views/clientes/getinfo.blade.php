@extends('adminlte::page')

@section('title', 'Completar información')

@section('content_header')
    <h1>Completar información</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')
    @if(session("status")=="exito")
        <script type="text/javascript">
            Swal.fire({
                icon: 'success',
                title: '¡Pago procesado correctamente!',
                showConfirmButton: false,
                timer: 2500
            });
        </script>
    @endif

    <div class="container m-3">
        {{-- Ley MD --}}
        {{-- <script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>
        <script>
        hbspt.forms.create({
            region: "na1",
            portalId: "20053496",
            formId: "ae73e323-14a8-40f4-a20c-4a33a30aabde"
        });
        </script> --}}

        <script charset="utf-8" type="text/javascript" src="//js.hsforms.net/forms/embed/v2.js"></script>
        <script>
          hbspt.forms.create({
            region: "na1",
            portalId: "20053496",
            formId: "ae73e323-14a8-40f4-a20c-4a33a30aabde",
            onFormReady: function($form){
               $('input[name="firstname"]').val('Brian').change();
        }


          });
        </script>

        {{-- Resto de los servicios --}}
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')

@stop

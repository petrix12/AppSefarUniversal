@extends('adminlte::page')

@section('title', 'Arreglar Pasaportes Incorrectos')

@section('content_header')

@stop

@section('content')
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    @if(session("error"))
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: '¡Aviso!',
                html: '{!! session("error") !!}'
            });
        </script>
    @endif

    @if(session("success"))
        <script type="text/javascript">
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                html: '{!! session("success") !!}'
            });
        </script>
    @endif
    <div style="padding: 80px;">
        <div class="card" style="padding:40px;">
            <form action="{{ route('fixpassportprocess') }}" method="POST" class="require-validation" data-cc-on-file="false" id="payment-form">
                <div class="container" style="width:100%;">
                    @csrf
                    <div class="mb-0">
                        <center>
                            <h2 style="padding:10px 0px; color:#12313a; font-weight: bold;">Arreglar Pasaportes Incorrectos</h2>
                        </center>

                        <div class='row' style="width:100%;">
                            <div class='mt-2' style="width: calc(100%/2); padding-right: 3px;">
                                <label class='control-label'>Número de pasaporte erroneo</label> <input autocomplete='off'
                                    class='form-control' placeholder='E123456789'
                                    type='text' name='oldpass' required>
                                <small>Este es el pasaporte incorrecto, que contiene toda la información del cliente.</small>
                            </div>
                            <div class='mt-2' style="width: calc(100%/2); padding-left: 3px;">
                                <label class='control-label'>Número de pasaporte correcto</label> <input autocomplete='off'
                                    class='form-control' placeholder='E123456789'
                                    type='text' name='newpass' required>
                                <small>Este es el pasaporte correcto, a donde va a migrar toda la información del cliente.</small>
                            </div>
                        </div>
                    </div>

                    <div class='row' style="justify-content: center; display: flex; margin-top:2rem;">
                        <button class="btn btn-primary" type="submit">Realizar pago</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
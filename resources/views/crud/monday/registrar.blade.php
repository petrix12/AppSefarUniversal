@extends('adminlte::page')

@section('title', 'Monday Estadísticas')

@section('content_header')

@stop

@section('content')

<script
src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js">
</script>

<script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<x-app-layout>

    
    @if(session("status")=="error")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'No se encontró a ningún usuario con el pasaporte ingresado',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if(session("status")=="ok")
        <script type="text/javascript">
            Swal.fire({
                icon: 'success',
                title: 'Cliente registrado en Monday',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    @if(session("status")=="error2")
        <script type="text/javascript">
            Swal.fire({
                icon: 'error',
                title: 'El cliente no ha rellenado el segundo formulario de Hubspot (completación)',
                showConfirmButton: false,
                timer: 5000
            });
        </script>
    @endif

    <div class="card" style="padding: 25px; margin: 20px 15%;">

        <center>
            <form action="{{ route('registrarMD') }}" method="POST">
                <div style="width: 80%;">
                    <h4>Pasaporte del Cliente</h4><br>
                    @csrf
                    <input type="text" name="passport" placeholder="Número de Pasaporte del Cliente">
                    <button class="btn btn-warning">Enviar a Monday</button>
                </div>
            </form>
            
        </center>

    </div>
</x-app-layout>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
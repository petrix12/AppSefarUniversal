@extends('adminlte::page')

@section('title', 'Registrar Hermanos')

@section('content_header')
    <h1>Registrar Hermanos</h1>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

@section('content')

    @if(session('error'))
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            html: `{!! addslashes(session('error')) !!}`,
            confirmButtonText: 'OK'
        });
    </script>
    @endif

    @if(session('success'))
    <script>
        Swal.fire({
            icon: 'success',
            title: '¡Gracias!',
            html: `{!! addslashes(session('success')) !!}`,
            confirmButtonText: 'OK'
        });
    </script>
    @endif

    <div class="card w-100 p-4">
        <form method="POST" action="{{route('registrarhermanoscliente')}}">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="nombres">Nombre <span class="required">*</span></label>
                        <input type="text" id="nombres" name="nombres" class="form-control" value="{{ old('nombres') }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="apellidos">Apellido <span class="required">*</span></label>
                        <input type="text" id="apellidos" name="apellidos" class="form-control" value="{{ old('apellidos') }}">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="email">Correo <span class="required">*</span></label>
                        <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="phone">Teléfono <span class="required">*</span></label>
                        <input type="tel" id="phone" name="phone" class="form-control" value="{{ old('phone') }}" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="passport">Número de Pasaporte <span class="required">*</span></label>
                        <input type="text" id="passport" name="passport" class="form-control" value="{{ old('passport') }}" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="pais_de_nacimiento">País de nacimiento <span class="required">*</span></label>
                        <input type="text" id="pais_de_nacimiento" name="pais_de_nacimiento" class="form-control" value="{{ old('pais_de_nacimiento') }}" required>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="acepto_comunicaciones" required {{ old('acepto_comunicaciones') ? 'checked' : '' }}> Acepto recibir otras comunicaciones de Sefar Universal <span class="required">*</span>
                    </label>
                </div>
            </div>
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="acepto_procesamiento" required {{ old('acepto_procesamiento') ? 'checked' : '' }}> Acepto permitir a Sefar Universal almacenar y procesar mis datos personales <span class="required">*</span>
                    </label>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Enviar</button>
        </form>
    </div>

    @if (sizeof($hermanos)>0)

        <br>

        <h3>Hermanos Registrados</h3>

        <div id="tablecontainer" style="width: 100%;">
            <table id="example" class="table table-striped" style="width: 100%;">
                <thead>
                    <tr>
                        <th>
                            Nombre
                        </th>
                        <th>
                            Correo Hermano
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($hermanos as $hermano)
                        @if ($hermano["hermano"])
                        <tr>
                            <td>
                                {{$hermano["hermano"]["name"]}}
                            </td>
                            <td>
                                {{$hermano["hermano"]["email"]}}
                            </td>
                        </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

    @else
        <br>
        <h4>No has registrado a ningún hermano, {{$usermain[0]["nombres"]}}</h4>
    @endif

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <style>
        .required{
            color: red;
        }
    </style>
@stop

@section('js')
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" language="javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <script src="//cdn.jsdelivr.net/npm/sweetalert2@11"></script>
@stop

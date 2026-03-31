@extends('adminlte::page')

@section('title', 'Contrato coordinador')

@section('content_header')
    <h1>Contrato coordinador</h1>
@stop

@section('content')
    <div class="row justify-content-center">
        <div class="col-md-10">
            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            <div class="card card-primary">
                <div class="card-header">
                    <h3 class="card-title">Firma obligatoria</h3>
                </div>

                <div class="card-body">
                    <p>
                        Debes firmar este contrato para continuar usando la plataforma.
                    </p>

                    @php
                        // Reemplaza TU_FORM_ID por el id real del formulario
                        // Cambia "email" por el nombre interno real del campo en Jotform si es distinto
                        $jotformUrl = 'https://form.jotform.com/250133837112043?email=' . urlencode($email);
                    @endphp

                    <iframe
                        id="JotFormIFrame"
                        title="Contrato Coordinador"
                        src="{{ $jotformUrl }}"
                        style="width:100%; height:800px; border:none;"
                    ></iframe>
                </div>
            </div>
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop

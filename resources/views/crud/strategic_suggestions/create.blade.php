@extends('adminlte::page')

@php
    $isAdmin = auth()->user()->hasAnyRole(['Administrador']);
    $isCoordVentas = auth()->user()->hasRole('Coord. Ventas');
@endphp

@section('title', $isAdmin ? 'Registrar propuesta estratégica' : 'Nueva propuesta estratégica')

@section('content_header')
    <h1>{{ $isAdmin ? 'Registrar propuesta estratégica' : 'Nueva propuesta estratégica' }}</h1>
@stop

@section('content')
<div class="container-fluid">
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Hay errores en el formulario.</strong>
            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if($isCoordVentas)
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-2">
                    Utiliza este espacio para compartir ideas, observaciones, oportunidades de mejora o propuestas estratégicas.
                </p>
                <p class="mb-0">
                    El contenido será visible únicamente para ti y para el equipo directivo autorizado.
                </p>
            </div>
        </div>
    @endif

    @if($isAdmin)
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-0">
                    Estás registrando una propuesta estratégica desde un perfil administrativo.
                </p>
            </div>
        </div>
    @endif

    <div class="card">
        <form action="{{ route('strategic-suggestions.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="card-body">
                <div class="form-group">
                    <label>Asunto</label>
                    <input
                        type="text"
                        name="subject"
                        class="form-control"
                        value="{{ old('subject') }}"
                        maxlength="255"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>Propuesta / sugerencia</label>
                    <textarea
                        name="message"
                        rows="8"
                        class="form-control"
                        required
                    >{{ old('message') }}</textarea>
                </div>

                <div class="form-group">
                    <label>Adjuntos</label>
                    <input
                        type="file"
                        name="attachments[]"
                        class="form-control"
                        multiple
                    >
                    <small class="text-muted">
                        Formatos permitidos: pdf, jpg, jpeg, png, doc, docx, xls, xlsx, zip, txt. Máximo 10MB por archivo.
                    </small>
                </div>
            </div>

            <div class="card-footer d-flex justify-content-between flex-wrap">
                <a href="{{ route('strategic-suggestions.index') }}" class="btn btn-secondary mb-2 mb-md-0">
                    Volver
                </a>

                <button type="submit" class="btn btn-primary">
                    {{ $isAdmin ? 'Guardar propuesta' : 'Enviar propuesta' }}
                </button>
            </div>
        </form>
    </div>
</div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

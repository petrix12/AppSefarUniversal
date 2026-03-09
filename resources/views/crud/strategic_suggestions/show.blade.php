@extends('adminlte::page')

@php
    $isAdmin = auth()->user()->hasAnyRole(['Administrador', 'Admin']);
    $isCoordVentas = auth()->user()->hasRole('Coord. Ventas');
@endphp

@section('title', $isAdmin ? 'Gestión de propuesta estratégica' : 'Mi propuesta estratégica')

@section('content_header')
    <h1>
        {{ $isAdmin ? 'Gestión de propuesta' : 'Mi propuesta estratégica' }}
        #{{ $suggestion->id }}
    </h1>
@stop

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

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

    {{-- Encabezado contextual según rol --}}
    @if($isCoordVentas)
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-0">
                    Este hilo es privado. Solo tú y el equipo directivo autorizado pueden ver el contenido y las respuestas asociadas a esta propuesta.
                </p>
            </div>
        </div>
    @endif

    @if($isAdmin)
        <div class="card mb-3">
            <div class="card-body">
                <p class="mb-0">
                    Estás visualizando una propuesta estratégica enviada por un integrante de Coordinación de Ventas.
                </p>
            </div>
        </div>
    @endif

    {{-- Tarjeta principal --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
            <strong>{{ $suggestion->subject }}</strong>

            @php
                $badge = match($suggestion->status) {
                    'recibida' => 'secondary',
                    'en_revision' => 'warning',
                    'respondida' => 'info',
                    'cerrada' => 'success',
                    default => 'secondary'
                };
            @endphp

            <span class="badge badge-{{ $badge }}">{{ $suggestion->statusLabel() }}</span>
        </div>

        <div class="card-body">
            @if($isAdmin)
                <p><strong>Coord. Ventas:</strong> {{ $suggestion->user->name ?? 'N/A' }}</p>
                <p><strong>Correo:</strong> {{ $suggestion->user->email ?? 'N/A' }}</p>
            @endif

            <p><strong>Fecha:</strong> {{ optional($suggestion->submitted_at)->format('Y-m-d H:i') }}</p>

            @if($suggestion->last_reply_at)
                <p><strong>Última respuesta:</strong> {{ optional($suggestion->last_reply_at)->format('Y-m-d H:i') }}</p>
            @endif

            <hr>

            <div style="white-space: pre-line;">{{ $suggestion->message }}</div>

            @php
                $initialAttachments = $suggestion->attachments->whereNull('reply_id');
            @endphp

            @if($initialAttachments->count())
                <hr>
                <h5>Adjuntos iniciales</h5>
                <ul class="mb-0">
                    @foreach($initialAttachments as $attachment)
                        <li>
                            <a href="{{ $attachment->downloadUrl() }}">
                                {{ $attachment->original_name }}
                            </a>
                            <small class="text-muted">
                                ({{ number_format($attachment->size / 1024, 2) }} KB)
                            </small>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- Solo admin puede cambiar estado --}}
    @if($isAdmin)
        <div class="card mb-3">
            <div class="card-header">
                <strong>Actualizar estado</strong>
            </div>
            <div class="card-body">
                <form action="{{ route('strategic-suggestions.update', $suggestion) }}" method="POST" class="form-inline">
                    @csrf
                    @method('PUT')

                    <select name="status" class="form-control mr-2">
                        <option value="recibida" {{ $suggestion->status === 'recibida' ? 'selected' : '' }}>Recibida</option>
                        <option value="en_revision" {{ $suggestion->status === 'en_revision' ? 'selected' : '' }}>En revisión</option>
                        <option value="respondida" {{ $suggestion->status === 'respondida' ? 'selected' : '' }}>Respondida</option>
                        <option value="cerrada" {{ $suggestion->status === 'cerrada' ? 'selected' : '' }}>Cerrada</option>
                    </select>

                    <button type="submit" class="btn btn-warning">
                        Actualizar estado
                    </button>
                </form>
            </div>
        </div>
    @endif

    {{-- Hilo --}}
    <div class="card mb-3">
        <div class="card-header">
            <strong>
                {{ $isAdmin ? 'Hilo de respuestas internas' : 'Conversación privada' }}
            </strong>
        </div>

        <div class="card-body">
            @forelse($suggestion->replies as $reply)
                <div class="border rounded p-3 mb-3 {{ $reply->is_admin_reply ? 'bg-light' : '' }}">
                    <div class="d-flex justify-content-between flex-wrap">
                        <div>
                            <strong>{{ $reply->user->name ?? 'Usuario' }}</strong>

                            @if($reply->is_admin_reply)
                                <span class="badge badge-primary ml-1">Equipo interno</span>
                            @else
                                <span class="badge badge-secondary ml-1">Coord. Ventas</span>
                            @endif
                        </div>

                        <small class="text-muted">{{ $reply->created_at->format('Y-m-d H:i') }}</small>
                    </div>

                    <div class="mt-2" style="white-space: pre-line;">{{ $reply->message }}</div>

                    @if($reply->attachments->count())
                        <div class="mt-2">
                            <strong>Adjuntos:</strong>
                            <ul class="mb-0">
                                @foreach($reply->attachments as $attachment)
                                    <li>
                                        <a href="{{ $attachment->downloadUrl() }}">
                                            {{ $attachment->original_name }}
                                        </a>
                                        <small class="text-muted">
                                            ({{ number_format($attachment->size / 1024, 2) }} KB)
                                        </small>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @empty
                <p class="text-muted mb-0">
                    {{ $isAdmin ? 'Aún no hay respuestas registradas.' : 'Aún no hay respuestas en esta propuesta.' }}
                </p>
            @endforelse
        </div>
    </div>

    {{-- Formulario respuesta --}}
    <div class="card">
        <form action="{{ route('strategic-suggestions.reply', $suggestion) }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="card-header">
                <strong>
                    {{ $isAdmin ? 'Responder propuesta' : 'Agregar mensaje' }}
                </strong>
            </div>

            <div class="card-body">
                @if($isCoordVentas)
                    <div class="alert alert-light border">
                        Puedes responder aquí para ampliar tu propuesta, aclarar detalles o adjuntar información adicional.
                    </div>
                @endif

                @if($isAdmin)
                    <div class="alert alert-light border">
                        Tu respuesta quedará visible únicamente para este Coord. Ventas dentro de este hilo privado.
                    </div>
                @endif

                <div class="form-group">
                    <label>Mensaje</label>
                    <textarea name="message" rows="5" class="form-control" required>{{ old('message') }}</textarea>
                </div>

                <div class="form-group">
                    <label>Adjuntos</label>
                    <input type="file" name="attachments[]" class="form-control" multiple>
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
                    {{ $isAdmin ? 'Enviar respuesta' : 'Enviar mensaje' }}
                </button>
            </div>
        </form>
    </div>

    {{-- Solo admin elimina --}}
    @if($isAdmin)
        <div class="mt-3">
            <form action="{{ route('strategic-suggestions.destroy', $suggestion) }}" method="POST" onsubmit="return confirm('¿Seguro que desea eliminar esta propuesta?');">
                @csrf
                @method('DELETE')

                <button type="submit" class="btn btn-danger">
                    Eliminar propuesta
                </button>
            </form>
        </div>
    @endif
</div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

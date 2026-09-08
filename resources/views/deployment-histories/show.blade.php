@extends('adminlte::page')

@section('title', 'Detalle de actualización')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">{{ $deploymentHistory->version ?: 'Actualización sin versión' }}</h1>
            <p class="text-muted mb-0">{{ $deploymentHistory->deployed_at?->format('d/m/Y H:i:s') }}</p>
        </div>
        <a href="{{ route('deployment-histories.index') }}" class="btn btn-outline-secondary">Volver al histórico</a>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-header"><h3 class="card-title">Texto del correo de actualización</h3></div>
        <div class="card-body">
            <div style="white-space: pre-wrap; font-family: inherit;">{{ $deploymentHistory->summary ?: 'No se generó un texto para el correo.' }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Información de la actualización</h3></div>
        <div class="card-body">
            <p class="mb-2"><strong>Versión:</strong> {{ $deploymentHistory->version ?: 'Sin versión' }}</p>
            <p class="mb-2"><strong>Correo:</strong> {{ $deploymentHistory->mail_sent ? 'Enviado' : 'Texto registrado' }}</p>
            <p class="mb-0"><strong>Modelo:</strong> {{ $deploymentHistory->model_used ?: 'Resumen local' }}</p>
        </div>
    </div>
@stop

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
    @php
        $badge = match($deploymentHistory->status) {
            'success' => 'success',
            'warning' => 'warning',
            default => 'danger',
        };
        $statusLabel = match($deploymentHistory->status) {
            'success' => 'Exitoso',
            'warning' => 'Advertencia',
            default => 'Fallido',
        };
    @endphp

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Resumen enviado por correo</h3></div>
                <div class="card-body">
                    <div style="white-space: pre-wrap; font-family: inherit;">{{ $deploymentHistory->summary ?: 'No se generó un resumen.' }}</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h3 class="card-title">Resultado</h3></div>
                <div class="card-body">
                    <p><strong>Estado:</strong> <span class="badge badge-{{ $badge }}">{{ $statusLabel }}</span></p>
                    <p><strong>Correo:</strong> {{ $deploymentHistory->mail_sent ? 'Enviado' : 'No enviado' }}</p>
                    <p><strong>Modelo:</strong> {{ $deploymentHistory->model_used ?: 'Resumen local' }}</p>
                    <p class="mb-1"><strong>Commit anterior:</strong></p>
                    <code>{{ $deploymentHistory->before_commit ?: '—' }}</code>
                    <p class="mb-1 mt-3"><strong>Commit desplegado:</strong></p>
                    <code>{{ $deploymentHistory->after_commit ?: '—' }}</code>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Detalles técnicos</h3></div>
        <div class="card-body">
            <h5>Migraciones — código {{ $deploymentHistory->migrate_exit_code ?? '—' }}</h5>
            <pre class="bg-light p-3 rounded">{{ $deploymentHistory->migrate_output ?: 'Sin salida.' }}</pre>

            <h5 class="mt-4">Limpieza de caché — código {{ $deploymentHistory->optimize_exit_code ?? '—' }}</h5>
            <pre class="bg-light p-3 rounded">{{ $deploymentHistory->optimize_output ?: 'Sin salida.' }}</pre>

            <h5 class="mt-4">Git pull</h5>
            <pre class="bg-light p-3 rounded">{{ $deploymentHistory->git_output ?: 'Sin salida.' }}</pre>

            @if($deploymentHistory->mail_error)
                <h5 class="mt-4 text-danger">Error del correo</h5>
                <pre class="bg-light p-3 rounded">{{ $deploymentHistory->mail_error }}</pre>
            @endif
        </div>
    </div>
@stop

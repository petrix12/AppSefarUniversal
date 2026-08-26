@extends('adminlte::page')

@section('title', 'Actualizaciones de la app')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Actualizaciones de la app</h1>
            <p class="text-muted mb-0">Histórico de despliegues, migraciones y notificaciones.</p>
        </div>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row mb-4">
                <div class="col-md-7 mb-2">
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Buscar por versión, resumen o commit"
                    >
                </div>
                <div class="col-md-3 mb-2">
                    <select name="status" class="form-control">
                        <option value="">Todos los estados</option>
                        <option value="success" {{ $status === 'success' ? 'selected' : '' }}>Exitoso</option>
                        <option value="warning" {{ $status === 'warning' ? 'selected' : '' }}>Con advertencias</option>
                        <option value="failed" {{ $status === 'failed' ? 'selected' : '' }}>Fallido</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-primary btn-block" type="submit">Filtrar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Versión</th>
                            <th>Estado</th>
                            <th>Resumen</th>
                            <th>Migración</th>
                            <th>Correo</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deployments as $deployment)
                            @php
                                $badge = match($deployment->status) {
                                    'success' => 'success',
                                    'warning' => 'warning',
                                    default => 'danger',
                                };
                                $statusLabel = match($deployment->status) {
                                    'success' => 'Exitoso',
                                    'warning' => 'Advertencia',
                                    default => 'Fallido',
                                };
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ $deployment->deployed_at?->format('d/m/Y H:i') }}</td>
                                <td><code>{{ $deployment->version ?: 'Sin versión' }}</code></td>
                                <td><span class="badge badge-{{ $badge }}">{{ $statusLabel }}</span></td>
                                <td style="min-width: 320px;">{{ \Illuminate\Support\Str::limit($deployment->summary ?: 'Sin resumen.', 180) }}</td>
                                <td>
                                    <span class="badge badge-{{ $deployment->migrate_exit_code === 0 ? 'success' : 'danger' }}">
                                        Código {{ $deployment->migrate_exit_code ?? '—' }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-{{ $deployment->mail_sent ? 'success' : 'warning' }}">
                                        {{ $deployment->mail_sent ? 'Enviado' : 'No enviado' }}
                                    </span>
                                </td>
                                <td class="text-right">
                                    <a href="{{ route('deployment-histories.show', $deployment) }}" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">Todavía no hay despliegues registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $deployments->links() }}
        </div>
    </div>
@stop

@extends('adminlte::page')

@section('title', 'Actualizaciones de la app')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Actualizaciones de la app</h1>
            <p class="text-muted mb-0">Histórico de actualizaciones y del texto incluido en sus correos.</p>
        </div>
    </div>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" class="row mb-4">
                <div class="col-md-10 mb-2">
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        class="form-control"
                        placeholder="Buscar por versión o texto del correo"
                    >
                </div>
                <div class="col-md-2 mb-2">
                    <button class="btn btn-primary btn-block" type="submit">Buscar</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Versión</th>
                            <th>Texto del correo de actualización</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($deployments as $deployment)
                            <tr>
                                <td class="text-nowrap">{{ $deployment->deployed_at?->format('d/m/Y H:i') }}</td>
                                <td><code>{{ $deployment->version ?: 'Sin versión' }}</code></td>
                                <td style="min-width: 420px;">{{ \Illuminate\Support\Str::limit($deployment->summary ?: 'No se generó un texto para el correo.', 240) }}</td>
                                <td class="text-right">
                                    <a href="{{ route('deployment-histories.show', $deployment) }}" class="btn btn-sm btn-outline-primary">Ver texto</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-5">Todavía no hay actualizaciones registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{ $deployments->links() }}
        </div>
    </div>
@stop

@extends('adminlte::page')

@section('title', 'Exportar clientes')

@section('content_header')
    <h1>Exportar clientes</h1>
@stop

@section('content')
    @if($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif

    <p class="text-muted">Descarga los datos de los clientes según su pago de registro y contrato.</p>

    {{-- Summary cards wrap from one column to two and then four; the data table scrolls within its own container. --}}
    <div class="row">
        @foreach($groups as $group => $label)
            <div class="col-sm-6 col-xl-3 mb-3">
                <a href="{{ route('admin.client-exports.index', ['group' => $group]) }}"
                   class="card h-100 mb-0 {{ $filters['group'] === $group ? 'border-primary' : '' }} text-body">
                    <div class="card-body">
                        <div class="h3 mb-2">{{ number_format((int) $counts->{$group === 'all' ? 'total' : $group}) }}</div>
                        <div>{{ $label }}</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header"><h2 class="card-title">Seleccionar clientes</h2></div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.client-exports.index') }}">
                <div class="row align-items-end">
                    <div class="col-lg-5 form-group">
                        <label for="export-group">Estado del registro</label>
                        <select id="export-group" name="group" class="form-control">
                            @foreach($groups as $group => $label)
                                <option value="{{ $group }}" @selected($filters['group'] === $group)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-5 form-group">
                        <label for="export-search">Buscar cliente</label>
                        <input id="export-search" name="q" type="search" maxlength="200" class="form-control"
                               value="{{ $filters['q'] }}" placeholder="Nombre, correo, teléfono o documento">
                    </div>
                    <div class="col-lg-2 form-group">
                        <button type="submit" class="btn btn-primary">Aplicar filtros</button>
                    </div>
                </div>
            </form>

            <div class="d-flex flex-wrap align-items-center" style="gap: 0.75rem;">
                <a href="{{ route('admin.client-exports.download', ['group' => $filters['group'], 'q' => $filters['q'], 'format' => 'xlsx']) }}" class="btn btn-success">
                    <i class="fas fa-file-excel mr-1" aria-hidden="true"></i> Descargar Excel (.xlsx)
                </a>
                <a href="{{ route('admin.client-exports.download', ['group' => $filters['group'], 'q' => $filters['q'], 'format' => 'csv']) }}" class="btn btn-outline-success">
                    <i class="fas fa-file-csv mr-1" aria-hidden="true"></i> Descargar CSV
                </a>
                <a href="{{ route('admin.client-exports.index') }}" class="btn btn-link">Limpiar filtros</a>
            </div>
            <p class="text-muted mt-3 mb-0">La descarga incluye los <strong>{{ number_format($clients->total()) }}</strong> clientes encontrados, aunque estén en varias páginas. Incluye datos de contacto, documento, servicio, PAY, CONTRATO y fecha de registro.</p>
            <small class="text-muted">Los grupos usan exactamente los estados indicados. «Todos los clientes» también incluye otros estados o registros sin estado de pago.</small>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title">Vista previa de los clientes</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive" role="region" aria-label="Vista previa de clientes" tabindex="0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Cliente</th>
                            <th scope="col">Correo</th>
                            <th scope="col">Teléfono</th>
                            <th scope="col">Documento</th>
                            <th scope="col">Servicio</th>
                            <th scope="col">PAY</th>
                            <th scope="col">CONTRATO</th>
                            <th scope="col">Estado del registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $client)
                            <tr>
                                <td class="text-nowrap">{{ $client->name }}<small class="d-block text-muted">ID {{ $client->id }}</small></td>
                                <td>{{ $client->email }}</td>
                                <td>{{ $client->phone }}</td>
                                <td>{{ $client->passport }}</td>
                                <td>{{ $client->servicio }}</td>
                                <td>{{ $client->pay ?? 'Sin estado' }}</td>
                                <td>{{ $client->getRawOriginal('contrato') ?? 'Sin estado' }}</td>
                                <td>{{ \App\Exports\ClientRegistrationExport::registrationStatus($client) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted p-4">No hay clientes que coincidan con los filtros.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">{{ $clients->links() }}</div>
    </div>
@stop

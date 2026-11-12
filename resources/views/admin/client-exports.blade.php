@extends('adminlte::page')

@section('title', 'Exportar clientes')

@section('content_header')
    <h1>Exportar clientes</h1>
@stop

@section('content')
    <p class="text-muted">Los expedientes bloqueados quedan excluidos de la vista previa y las descargas.</p>
    <div class="client-exports">
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif

        <p class="text-muted">Descarga los datos de los clientes según su pago de registro y contrato.</p>

        @php
            $summaryLabels = [
                'all' => ['Todos los clientes', 'Todos los estados'],
                'unpaid' => ['Sin pago de registro', 'PAY = 0'],
                'paid' => ['Registro pagado', 'PAY = 1'],
                'complete' => ['Registro completo', 'PAY = 2 · CONTRATO = 1'],
            ];
        @endphp

        {{-- Keep the existing one/two/four-column cards; only the table scrolls horizontally. --}}
        <div class="row">
            @foreach($groups as $group => $label)
                <div class="col-sm-6 col-xl-3 mb-3">
                    <a href="{{ route('admin.client-exports.index', ['group' => $group]) }}"
                       class="card client-export-summary h-100 mb-0"
                       @if($filters['group'] === $group) aria-current="true" @endif>
                        <div class="card-body">
                            <div class="client-export-summary-top">
                                <strong class="client-export-count">{{ number_format((int) $counts->{$group === 'all' ? 'total' : $group}) }}</strong>
                                @if($filters['group'] === $group)
                                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                                @endif
                            </div>
                            <span class="client-export-summary-label">{{ $summaryLabels[$group][0] }}</span>
                            <small class="client-export-note">{{ $summaryLabels[$group][1] }}</small>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Filtros y descarga</h2></div>
            <div class="card-body">
                {{-- Fields wrap according to the available space, including beside the AdminLTE sidebar. --}}
                <form method="GET" action="{{ route('admin.client-exports.index') }}" class="client-export-form">
                    <div class="client-export-field">
                        <label for="export-group">Estado del registro</label>
                        <select id="export-group" name="group" class="form-control">
                            @foreach($groups as $group => $label)
                                <option value="{{ $group }}" @selected($filters['group'] === $group)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="client-export-field">
                        <label for="export-search">Buscar cliente</label>
                        <input id="export-search" name="q" type="search" maxlength="200" class="form-control"
                               value="{{ $filters['q'] }}" placeholder="Nombre, correo, teléfono o documento">
                    </div>
                    <div class="sefar-actions client-export-filter-actions">
                        <button type="submit" class="btn sefar-button cfrSefar">Aplicar filtros</button>
                        <a href="{{ route('admin.client-exports.index') }}" class="client-export-reset">Limpiar filtros</a>
                    </div>
                </form>

                <div class="client-export-downloads">
                    <div class="client-export-result">
                        <strong>{{ number_format($clients->total()) }} {{ $clients->total() === 1 ? 'cliente encontrado' : 'clientes encontrados' }}</strong>
                        <p class="client-export-note mb-0">La descarga incluye todos los resultados, aunque estén en varias páginas.</p>
                    </div>
                    <div class="sefar-actions">
                        <a href="{{ route('admin.client-exports.download', ['group' => $filters['group'], 'q' => $filters['q'], 'format' => 'xlsx']) }}" class="btn sefar-button cfrSefar">
                            <i class="fas fa-file-excel" aria-hidden="true"></i> Descargar Excel (.xlsx)
                        </a>
                        <a href="{{ route('admin.client-exports.download', ['group' => $filters['group'], 'q' => $filters['q'], 'format' => 'csv']) }}" class="btn sefar-button cfrSefar">
                            <i class="fas fa-file-csv" aria-hidden="true"></i> Descargar CSV
                        </a>
                    </div>
                </div>
                <p class="client-export-note mt-3 mb-1">Incluye datos de contacto, documento, servicio, PAY, CONTRATO y fecha de registro.</p>
                <p class="client-export-note mb-0">«Todos los clientes» también incluye otros estados o registros sin estado de pago.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Vista previa de los clientes</h2></div>
            <div class="card-body p-0">
                <div class="table-responsive" role="region" aria-label="Vista previa de clientes" tabindex="0">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Cliente</th>
                                <th scope="col">Correo</th>
                                <th scope="col">Teléfono</th>
                                <th scope="col">Documento</th>
                                <th scope="col">Servicio</th>
                                <th scope="col" class="text-center">PAY</th>
                                <th scope="col" class="text-center">CONTRATO</th>
                                <th scope="col">Estado del registro</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clients as $client)
                                <tr>
                                    <td class="text-nowrap">{{ $client->name }}<small class="d-block text-muted">ID {{ $client->id }}</small></td>
                                    <td>{{ $client->email }}</td>
                                    <td class="text-nowrap">{{ filled($client->phone) ? $client->phone : '—' }}</td>
                                    <td>{{ filled($client->passport) ? $client->passport : '—' }}</td>
                                    <td>{{ filled($client->servicio) ? $client->servicio : '—' }}</td>
                                    <td class="text-center">{{ $client->pay ?? 'Sin estado' }}</td>
                                    <td class="text-center">{{ $client->getRawOriginal('contrato') ?? 'Sin estado' }}</td>
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
    </div>
@stop

@section('css')
    <style>
        /* Reuse Sefar's palette, surfaces and button classes; scope layout changes to this page. */
        .client-exports .client-export-summary {
            position: relative;
            color: var(--sefar-text);
            text-decoration: none;
        }

        .client-export-summary[aria-current="true"]::before {
            position: absolute;
            inset: 0 0 auto;
            height: 3px;
            background: var(--sefar-accent);
            content: '';
        }

        .client-export-summary:focus-visible,
        .client-exports .sefar-button:focus-visible,
        .client-export-reset:focus-visible {
            outline: 2px solid var(--sefar-accent);
            outline-offset: 3px;
        }

        .client-export-summary .card-body {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            padding: 1.15rem 1.25rem;
        }

        .client-export-summary-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .client-export-count {
            color: var(--sefar-text);
            font-size: 1.8rem;
            line-height: 1.2;
        }

        .client-export-summary-top > i {
            color: var(--sefar-accent);
        }

        .client-export-summary-label {
            font-weight: 700;
        }

        .client-export-note {
            color: var(--sefar-muted);
            font-size: 0.875rem;
            line-height: 1.55;
        }

        .client-export-form,
        .client-export-downloads {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .client-export-form {
            align-items: flex-end;
        }

        .client-export-field {
            flex: 1 1 18rem;
            min-width: 0;
        }

        .client-exports .form-control {
            height: 3rem;
            font-size: 1rem;
        }

        .client-exports .sefar-button {
            min-height: 3rem;
            padding: 0.75rem 1.25rem;
            font-size: 1rem;
            font-weight: 500;
        }

        .client-export-reset {
            display: inline-flex;
            align-items: center;
            min-height: 3rem;
            color: var(--sefar-text);
            text-decoration: underline;
        }

        .client-export-downloads {
            align-items: center;
            justify-content: space-between;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--sefar-border);
        }

        .client-export-result {
            flex: 1 1 20rem;
            min-width: 0;
        }

        .client-exports .table td {
            vertical-align: middle;
        }

        .client-exports .pagination {
            flex-wrap: wrap;
            gap: 0.25rem;
            margin: 0;
        }

        .client-exports .page-link {
            min-width: 2.75rem;
            min-height: 2.75rem;
        }
    </style>
@stop

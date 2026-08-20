@extends('adminlte::page')

@section('title', 'Auditoria MCP y Tokens')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <h1>Auditoria MCP y Tokens</h1>
        @include('admin.integrations._tabs')
    </div>
@stop

@section('content')
    <div class="row">
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-primary"><i class="fas fa-clipboard-list"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Eventos</span>
                    <span class="info-box-number">{{ $summary['total'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-info"><i class="fas fa-key"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Tokens</span>
                    <span class="info-box-number">{{ $summary['tokens'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-success"><i class="fas fa-network-wired"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">MCP HTTP</span>
                    <span class="info-box-number">{{ $summary['mcp_http'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box">
                <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Errores</span>
                    <span class="info-box-number">{{ $summary['errors'] }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-primary">
        <div class="card-header">
            <h3 class="card-title mb-0">
                <i class="fas fa-filter mr-1"></i> Filtros
            </h3>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('admin.integrations.audit.index') }}">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <label for="source">Fuente</label>
                        <select id="source" name="source" class="form-control">
                            <option value="all" @selected($source === 'all')>Todas</option>
                            <option value="tokens" @selected($source === 'tokens')>Tokens</option>
                            <option value="mcp_http" @selected($source === 'mcp_http')>MCP HTTP</option>
                            <option value="mcp_stdio" @selected($source === 'mcp_stdio')>MCP stdio</option>
                        </select>
                    </div>
                    <div class="col-md-5 mb-2">
                        <label for="q">Buscar</label>
                        <input type="text" id="q" name="q" class="form-control" value="{{ $query }}" placeholder="email, ruta, evento, cliente, token...">
                    </div>
                    <div class="col-md-2 mb-2">
                        <label for="limit">Limite</label>
                        <select id="limit" name="limit" class="form-control">
                            @foreach([100, 250, 500, 1000] as $option)
                                <option value="{{ $option }}" @selected($limit === $option)>{{ $option }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <button class="btn btn-primary btn-block">
                            <i class="fas fa-search mr-1"></i> Filtrar
                        </button>
                    </div>
                </div>
            </form>
            <div class="small text-muted mt-2 audit-path">{{ $auditPath }}</div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">
                <i class="fas fa-stream mr-1"></i> Eventos recientes
            </h3>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 audit-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Fuente</th>
                            <th>Evento</th>
                            <th>Actor</th>
                            <th>Destino</th>
                            <th>Ruta/Herramienta</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($events as $index => $event)
                            @php
                                $sourceLabels = [
                                    'tokens' => ['Tokens', 'badge-info'],
                                    'mcp_http' => ['MCP HTTP', 'badge-success'],
                                    'mcp_stdio' => ['MCP stdio', 'badge-primary'],
                                    'other' => ['Otro', 'badge-secondary'],
                                ];
                                [$sourceLabel, $sourceClass] = $sourceLabels[$event['source']] ?? $sourceLabels['other'];
                                $status = $event['status'];
                            @endphp
                            <tr>
                                <td class="text-nowrap">{{ $event['timestamp'] ? \Carbon\Carbon::parse($event['timestamp'])->format('d/m/Y H:i:s') : '-' }}</td>
                                <td><span class="badge {{ $sourceClass }}">{{ $sourceLabel }}</span></td>
                                <td><code>{{ $event['event'] }}</code></td>
                                <td class="audit-wrap">{{ $event['actor_label'] }}</td>
                                <td class="audit-wrap">{{ $event['target_label'] }}</td>
                                <td class="audit-wrap">{{ $event['route_or_tool'] ?? '-' }}</td>
                                <td>
                                    @if($status === 'error')
                                        <span class="badge badge-danger">Error</span>
                                    @elseif($status === 'ok')
                                        <span class="badge badge-success">OK</span>
                                    @else
                                        <span class="badge badge-light">-</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#auditEvent{{ $index }}">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                            <tr class="collapse" id="auditEvent{{ $index }}">
                                <td colspan="8">
                                    <pre class="audit-json mb-0">{{ json_encode($event['raw'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted p-4">No hay eventos para los filtros seleccionados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop

@section('css')
    <style>
        .audit-path,
        .audit-wrap {
            word-break: break-word;
        }

        .audit-table th {
            white-space: nowrap;
        }

        .audit-json {
            background: var(--sefar-primary-soft);
            border: 1px solid var(--sefar-border);
            border-radius: var(--sefar-radius);
            color: var(--sefar-text);
            font-size: .82rem;
            max-height: 420px;
            overflow: auto;
            padding: 1rem;
            white-space: pre-wrap;
        }
    </style>
@stop

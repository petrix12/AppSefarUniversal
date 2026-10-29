@extends('adminlte::page')

@section('title', 'Cartera y certificados')

@section('content_header')
    <h1>Cartera y certificados</h1>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    <p class="text-muted">Listado global de clientes con los datos importados disponibles. Actualizado al calcular este informe: {{ $report['generated_at'] }}.</p>
    <div class="row">
        @foreach([
            'clients' => 'Clientes', 'with_known_debt' => 'Con saldo conocido',
            'with_certificate_and_known_debt' => 'Con certificado y saldo', 'requiring_review' => 'Requieren revisión'
        ] as $key => $label)
            <div class="col-6 col-lg-3 mb-3">
                <div class="card h-100"><div class="card-body"><div class="text-muted">{{ $label }}</div><strong class="h3">{{ number_format($report['summary'][$key], 0, ',', '.') }}</strong></div></div>
            </div>
        @endforeach
    </div>
    <div class="alert alert-info">
        El saldo conocido suma únicamente fases con importes legibles en EUR. Las fases con monedas pendientes de conversión, sobrepagos u otras incidencias quedan para revisión. Sin datos no significa sin deuda. El certificado se identifica por su fecha registrada.
    </div>
    <div class="card"><div class="card-body">
        <form method="GET" class="row align-items-end">
            <div class="form-group col-md-3">
                <label for="portfolio-balance">Saldo</label>
                <select id="portfolio-balance" name="balance" class="form-control">
                    @foreach(['debt' => 'Con saldo conocido', 'review' => 'Requieren revisión', 'no_recorded_balance' => 'Sin saldo registrado', 'no_data' => 'Sin datos suficientes', 'all' => 'Todos'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['balance'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="portfolio-certificate">Certificado</label>
                <select id="portfolio-certificate" name="certificate" class="form-control">
                    @foreach(['all' => 'Todos', 'yes' => 'Con fecha registrada', 'no' => 'Sin fecha registrada'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['certificate'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-4"><label for="portfolio-search">Cliente, pasaporte o correo</label><input id="portfolio-search" name="q" value="{{ $filters['q'] }}" class="form-control"></div>
            <div class="form-group col-md-2"><button class="btn btn-primary btn-block">Filtrar</button></div>
        </form>
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3" style="gap: .75rem">
            <span>{{ number_format($clients->total(), 0, ',', '.') }} clientes encontrados</span>
            <a class="btn btn-outline-primary" href="{{ route('crud.cos.portfolio', array_merge($filters, ['export' => 'csv'])) }}">Descargar lista completa CSV</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Cliente</th><th>Certificado</th><th class="text-right">Saldo conocido</th><th>Estado y expedientes</th></tr></thead>
                <tbody>
                    @forelse($clients as $client)
                        <tr>
                            <td><a href="{{ route('crud.users.edit', $client['client_id']) }}">{{ $client['name'] }}</a><div class="small text-muted">{{ $client['passport'] }} · {{ $client['email'] }}</div></td>
                            <td>{{ $client['has_certificate'] ? 'Registrado' : 'Sin fecha registrada' }}<div class="small text-muted">{{ implode(', ', $client['certificate_dates']) }}</div></td>
                            <td class="text-right text-nowrap"><strong>{{ number_format($client['known_balance_eur'], 2, ',', '.') }} EUR</strong><div class="small text-muted">Cobrable: {{ number_format($client['collectible_balance_eur'], 2, ',', '.') }} EUR</div></td>
                            <td>
                                <span class="badge {{ $client['review_reasons'] ? 'badge-warning' : 'badge-light' }}">{{ \App\Services\LocalCosPortfolioService::STATUS_LABELS[$client['status']] }}</span>
                                <details><summary>{{ count($client['cases']) }} expediente(s)</summary>
                                    @foreach($client['cases'] as $case)
                                        <div class="mt-2"><strong>{{ $case['title'] }}</strong><div class="small">Saldo conocido: {{ number_format($case['known_balance_eur'], 2, ',', '.') }} EUR · Certificado: {{ $case['certificate_date'] ?? 'Sin fecha registrada' }}</div><div class="small text-muted">Último cambio local: {{ $case['imported_at'] ?? 'Sin fecha disponible' }}</div></div>
                                    @endforeach
                                </details>
                                @foreach($client['review_reasons'] as $reason)<div class="small text-muted">{{ $reason }}</div>@endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">No hay clientes con estos filtros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $clients->links() }}
    </div></div>
    <p class="text-muted">Para agrupar negocios de HubSpot con proyectos de Teamleader, abre el cliente y utiliza «Procesos del solicitante» en su ventana de estatus.</p>
@stop

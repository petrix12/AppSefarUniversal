@extends('adminlte::page')
@section('title', 'Revisión de pagos')
@section('content_header')
    <h1>Revisión de pagos</h1>
@stop
@section('content')
    <div class="card">
        <div class="card-header"><h2 class="card-title">{{ $user->name }}</h2></div>
        <div class="card-body">
            @if($report)
                <p class="text-muted">Sobrepagos detectados en la última conciliación del COS: {{ $report['generated_at'] }}. Este informe conserva los importes de esa revisión; abre de nuevo la ficha para actualizarlos.</p>
                <p>Total detectado: <strong>{{ number_format(collect($report['rows'])->sum('overpaid_amount'), 2, ',', '.') }} EUR</strong></p>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Proceso</th><th>Fase</th><th>Fuente</th><th class="text-right">Preestablecido</th><th class="text-right">Pagado</th><th class="text-right">Sobrepago</th></tr></thead>
                        <tbody>
                            @forelse($report['rows'] as $row)
                                <tr><td>{{ $row['project_title'] ?? '—' }}</td><td>{{ $row['payment_label'] ?? '—' }}</td><td>{{ $row['source'] ?? 'Teamleader' }}</td>
                                    @foreach(['effective_preestab_amount', 'effective_paid_amount', 'overpaid_amount'] as $key)
                                        <td class="text-right text-nowrap">{{ number_format($row[$key] ?? 0, 2, ',', '.') }} EUR</td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No se detectaron sobrepagos en esa conciliación.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @else
                <p class="text-muted">Abre la ficha del cliente para generar la conciliación. Después vuelve a esta ventana. Los informes se conservan durante 24 horas.</p>
            @endif
            <a class="btn sefar-button cfrSefar" href="{{ route('crud.users.edit', $user) }}#mystatus" target="_blank" rel="noopener noreferrer">Abrir ficha del cliente <i class="fas fa-external-link-alt ml-1" aria-hidden="true"></i></a>
        </div>
    </div>
@stop

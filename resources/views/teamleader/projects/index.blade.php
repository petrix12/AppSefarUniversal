@extends('adminlte::page')

@section('title', 'Proyectos | Teamleader')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <small class="text-muted text-uppercase font-weight-bold">Teamleader</small>
            <h1 class="m-0">Proyectos</h1>
        </div>
        <span class="badge badge-primary" style="font-size: 0.9rem; padding: 8px 16px;">
            {{ $paymentTotals['projects'] }} proyectos con importes
        </span>
    </div>
@stop

@section('content')

    {{-- FILTROS --}}
    <div class="card card-outline card-primary mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('teamleader.projects.index') }}">
                <div class="row align-items-end">

                    <div class="col-md-3 mb-2">
                        <label class="text-muted small mb-1">Buscar por nombre</label>
                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            placeholder="Ej: María García..."
                            class="form-control form-control-sm"
                        >
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="text-muted small mb-1">Estado</label>
                        <select name="status" class="form-control form-control-sm">
                            <option value="">Todos</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="text-muted small mb-1">Tipo de cliente</label>
                        <select name="customer_type" class="form-control form-control-sm">
                            <option value="">Todos</option>
                            <option value="contact" {{ request('customer_type') === 'contact' ? 'selected' : '' }}>Contacto</option>
                            <option value="company" {{ request('customer_type') === 'company' ? 'selected' : '' }}>Empresa</option>
                        </select>
                    </div>

                    <div class="col-md-2 mb-2">
                        <label class="text-muted small mb-1">Auditoría de pagos</label>
                        <select name="payment_audit" class="form-control form-control-sm">
                            <option value="all" {{ request('payment_audit', 'all') === 'all' ? 'selected' : '' }}>
                                Con ambos importes
                            </option>
                            <option value="overpaid" {{ request('payment_audit') === 'overpaid' ? 'selected' : '' }}>
                                Pagado supera preestablecido
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-2">
                        <label class="text-muted small mb-1">&nbsp;</label>
                        <div class="d-flex">
                            <button type="submit" class="btn btn-primary btn-sm mr-2">
                                <i class="fas fa-search mr-1"></i> Filtrar
                            </button>
                            <a href="{{ route('teamleader.projects.index') }}" class="btn btn-secondary btn-sm">
                                <i class="fas fa-times mr-1"></i> Limpiar
                            </a>
                        </div>
                    </div>

                </div>
            </form>
        </div>
    </div>

    {{-- RESUMEN DE TODOS LOS RESULTADOS FILTRADOS --}}
    <div class="row mb-3">
        <div class="col-md-3 col-6">
            <div class="small-box bg-info">
                <div class="inner">
                    <h4>{{ number_format($paymentTotals['preestab_amount'], 2, ',', '.') }} EUR</h4>
                    <p>Preestablecido</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="small-box bg-success">
                <div class="inner">
                    <h4>{{ number_format($paymentTotals['paid_amount'], 2, ',', '.') }} EUR</h4>
                    <p>Pagado / abonado</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="small-box bg-warning">
                <div class="inner">
                    <h4>{{ number_format($paymentTotals['balance_amount'], 2, ',', '.') }} EUR</h4>
                    <p>Por cobrar</p>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="small-box {{ $paymentTotals['rows_to_review'] > 0 ? 'bg-danger' : 'bg-secondary' }}">
                <div class="inner">
                    <h4>{{ $paymentTotals['rows_to_review'] }}</h4>
                    <p>Fases a corregir</p>
                </div>
            </div>
        </div>
    </div>
    <p class="text-muted small mt-n2 mb-3">
        Totales de las {{ $paymentTotals['rows'] }} fases filtradas en {{ $paymentTotals['projects'] }} proyectos.
        Un pago superior al monto preestablecido se marca como inconsistencia; nunca como deuda de la empresa con el cliente.
    </p>

    {{-- TABLA --}}
    <div class="card card-outline card-primary">
        <div class="card-body p-0">
            <div class="table-responsive">
            <table class="table table-hover table-striped mb-0 text-nowrap">
                <thead class="thead-light">
                    <tr>
                        <th>Proyecto</th>
                        <th>Estado</th>
                        <th>Fase</th>
                        <th class="text-right">Preestablecido</th>
                        <th class="text-right">Pagado / abonado</th>
                        <th class="text-right">Resultado</th>
                        <th>Validación</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($projects as $row)
                        @php
                            $project = $row['project'];
                            $phase = $row['phase'];
                            $badgeColors = [
                                'active'    => 'success',
                                'on_hold'   => 'warning',
                                'cancelled' => 'danger',
                                'done'      => 'primary',
                                'completed' => 'primary',
                            ];
                            $badge = $badgeColors[$project->status] ?? 'secondary';
                            $exceedsPreestablished = $phase['overpaid_amount'] > 0.01;
                        @endphp
                        <tr class="{{ $exceedsPreestablished ? 'table-danger' : '' }}">

                            <td>
                                <span class="font-weight-bold">{{ $project->title }}</span>
                                <br>
                                <small class="text-muted" style="font-family: monospace; font-size: 0.7rem;">
                                    {{ $project->id }}
                                </small>
                            </td>

                            <td>
                                <span class="badge badge-{{ $badge }}">
                                    {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                                </span>
                                <br><small class="text-muted text-capitalize">{{ $project->customer_type ?? '—' }}</small>
                            </td>

                            <td class="font-weight-bold text-center">
                                {{ $phase['payment_label'] ?? 'Fase ' . ($phase['phase'] ?? '-') }}
                            </td>

                            <td class="text-right">
                                <strong>{{ number_format($phase['effective_preestab_amount'], 2, ',', '.') }} EUR</strong>
                                <br><small class="text-muted text-wrap d-inline-block" style="max-width: 220px;">
                                    Original: {{ $phase['preestab_raw'] ?: '—' }}
                                </small>
                            </td>

                            <td class="text-right text-success">
                                <strong>{{ number_format($phase['effective_paid_amount'], 2, ',', '.') }} EUR</strong>
                                <br><small class="text-muted text-wrap d-inline-block" style="max-width: 220px;">
                                    Original: {{ $phase['paid_raw'] ?: '—' }}
                                </small>
                            </td>

                            <td class="text-right font-weight-bold">
                                @if($exceedsPreestablished)
                                    <span class="text-danger">
                                        Exceso de {{ number_format($phase['overpaid_amount'], 2, ',', '.') }} EUR
                                    </span>
                                @else
                                    <span class="text-dark">
                                        Por cobrar: {{ number_format($phase['balance_amount'], 2, ',', '.') }} EUR
                                    </span>
                                @endif
                            </td>

                            <td>
                                @if($exceedsPreestablished)
                                    <span class="badge badge-danger">
                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                        Corregir en Teamleader
                                    </span>
                                @else
                                    <span class="badge badge-success">Consistente</span>
                                @endif
                            </td>

                            <td class="text-right">
                                <a href="{{ route('teamleader.projects.show', $project->id) }}"
                                   class="btn btn-xs btn-outline-primary">
                                    <i class="fas fa-eye mr-1"></i> Ver
                                </a>
                                <a href="https://focus.teamleader.eu/web/projects/{{ $project->id }}"
                                   target="_blank" rel="noopener"
                                   class="btn btn-xs {{ $exceedsPreestablished ? 'btn-danger' : 'btn-outline-secondary' }} ml-1">
                                    <i class="fas fa-external-link-alt mr-1"></i> Teamleader
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fas fa-folder-open fa-2x mb-2 d-block"></i>
                                No hay fases con monto preestablecido y monto pagado para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        @if($projects->hasPages())
            <div class="card-footer">
                {{ $projects->links() }}
            </div>
        @endif
    </div>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop

{{-- resources/views/tl/invoices/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Facturas TL')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1><i class="fas fa-file-invoice-dollar mr-2"></i>Facturas Teamleader</h1>
        <div>
            @if($lastSync)
                <small class="text-muted mr-3">
                    Último sync: <strong>{{ $lastSync->finished_at?->diffForHumans() ?? 'en curso...' }}</strong>
                    — {{ number_format($lastSync->processed) }} procesadas
                </small>
            @endif
        </div>
    </div>
@endsection

@section('content')

{{-- Tarjetas resumen --}}
<div class="row mb-3">
    @php
        $cards = [
            ['label' => 'Borrador',     'key' => 'draft',       'color' => 'secondary', 'icon' => 'edit'],
            ['label' => 'Pendientes',   'key' => 'outstanding', 'color' => 'warning',   'icon' => 'clock'],
            ['label' => 'Vencidas',     'key' => 'late',        'color' => 'danger',    'icon' => 'exclamation-triangle'],
            ['label' => 'Pagadas',      'key' => 'matched',     'color' => 'success',   'icon' => 'check-circle'],
        ];
    @endphp

    @foreach($cards as $card)
    <div class="col-6 col-md-3">
        <div class="small-box bg-{{ $card['color'] }}">
            <div class="inner">
                <h3>{{ number_format($totals[$card['key']]) }}</h3>
                <p>{{ $card['label'] }}</p>
            </div>
            <div class="icon"><i class="fas fa-{{ $card['icon'] }}"></i></div>
        </div>
    </div>
    @endforeach
</div>

<div class="card card-outline card-primary">
    <div class="card-header">
        <form method="GET" action="{{ route('teamleader.invoices.index') }}" class="form-inline flex-wrap" style="gap:.5rem">
            <input
                type="text"
                name="search"
                value="{{ request('search') }}"
                class="form-control form-control-sm"
                placeholder="Número, cliente..."
                style="min-width:220px"
            >

            <select name="status" class="form-control form-control-sm">
                <option value="">Todos los estados</option>
                <option value="draft"       {{ request('status') === 'draft'       ? 'selected' : '' }}>Borrador</option>
                <option value="outstanding" {{ request('status') === 'outstanding' ? 'selected' : '' }}>Pendiente</option>
                <option value="matched"     {{ request('status') === 'matched'     ? 'selected' : '' }}>Pagada</option>
                <option value="late"        {{ request('status') === 'late'        ? 'selected' : '' }}>Vencida</option>
            </select>

            <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm" title="Desde">
            <input type="date" name="to"   value="{{ request('to') }}"   class="form-control form-control-sm" title="Hasta">

            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fas fa-search"></i> Filtrar
            </button>
            <a href="{{ route('teamleader.invoices.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-times"></i>
            </a>
        </form>

        <div class="card-tools">
            <span class="badge badge-info">{{ number_format($invoices->total()) }} facturas</span>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Vencimiento</th>
                        <th>Sin IVA</th>
                        <th>Con IVA</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($invoices as $invoice)
                    @php
                        $badgeColor = match($invoice->status) {
                            'matched'     => 'success',
                            'outstanding' => 'warning',
                            'late'        => 'danger',
                            'draft'       => 'secondary',
                            default       => 'light',
                        };
                        $badgeLabel = match($invoice->status) {
                            'matched'     => 'Pagada',
                            'outstanding' => 'Pendiente',
                            'late'        => 'Vencida',
                            'draft'       => 'Borrador',
                            default       => $invoice->status,
                        };
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('teamleader.invoices.show', $invoice->id) }}" class="font-weight-bold">
                                {{ $invoice->invoice_number ?? '(Sin número)' }}
                            </a>
                        </td>
                        <td>{{ $invoice->customer_name ?? '—' }}</td>
                        <td><small>{{ $invoice->invoice_date?->format('d/m/Y') ?? '—' }}</small></td>
                        <td>
                            <small class="{{ $invoice->is_overdue ? 'text-danger font-weight-bold' : '' }}">
                                {{ $invoice->expiry_date?->format('d/m/Y') ?? '—' }}
                            </small>
                        </td>
                        <td>
                            @if($invoice->total_price_excl_tax)
                                {{ number_format($invoice->total_price_excl_tax, 2) }}
                                <small class="text-muted">{{ $invoice->currency }}</small>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if($invoice->total_price_incl_tax)
                                <strong>{{ number_format($invoice->total_price_incl_tax, 2) }}</strong>
                                <small class="text-muted">{{ $invoice->currency }}</small>
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="badge badge-{{ $badgeColor }}">{{ $badgeLabel }}</span></td>
                        <td>
                            <a href="{{ route('teamleader.invoices.show', $invoice->id) }}"
                               class="btn btn-xs btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                            @if($invoice->status === 'matched')
                                <a href="{{ route('tl.invoices.pdf', $invoice->id) }}"
                                target="_blank"
                                class="btn btn-xs btn-outline-danger">
                                    <i class="fas fa-file mr-1"></i>
                                </a>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                            No se encontraron facturas
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($invoices->hasPages())
    <div class="card-footer">
        {{ $invoices->links() }}
    </div>
    @endif
</div>
@endsection
@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop

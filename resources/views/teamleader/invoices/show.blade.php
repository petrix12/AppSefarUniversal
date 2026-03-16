{{-- resources/views/tl/invoices/show.blade.php --}}
@extends('adminlte::page')

@section('title', 'Factura ' . ($invoice->invoice_number ?? 'Borrador'))

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>
            <a href="{{ route('tl.invoices.index') }}" class="btn btn-sm btn-secondary mr-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            Factura {{ $invoice->invoice_number ?? '(Borrador)' }}
        </h1>
        <a href="{{ $invoice->raw_data['web_url'] ?? '#' }}" target="_blank" class="btn btn-sm btn-outline-info">
            <i class="fas fa-external-link-alt mr-1"></i> Ver en Teamleader
        </a>
    </div>
@endsection

@section('content')
<div class="row">

    {{-- Columna izquierda --}}
    <div class="col-md-4">

        {{-- Estado y totales --}}
        <div class="card card-primary card-outline">
            <div class="card-body text-center">
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
                <span class="badge badge-{{ $badgeColor }} px-3 py-2" style="font-size:1rem">
                    {{ $badgeLabel }}
                </span>
                <h2 class="mt-3 mb-0">
                    {{ number_format($invoice->total_price_incl_tax ?? 0, 2) }}
                    <small class="text-muted">{{ $invoice->currency }}</small>
                </h2>
                <small class="text-muted">Total con IVA</small>
            </div>
            <div class="card-footer p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Sin IVA</span>
                        <strong>{{ number_format($invoice->total_price_excl_tax ?? 0, 2) }} {{ $invoice->currency }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Fecha factura</span>
                        <span>{{ $invoice->invoice_date?->format('d/m/Y') ?? '—' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Vencimiento</span>
                        <span class="{{ $invoice->is_overdue ? 'text-danger font-weight-bold' : '' }}">
                            {{ $invoice->expiry_date?->format('d/m/Y') ?? '—' }}
                        </span>
                    </li>
                    @if($invoice->paid_date)
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Pagada el</span>
                        <span class="text-success">{{ $invoice->paid_date->format('d/m/Y') }}</span>
                    </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- Cliente --}}
        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-user mr-1"></i> Cliente</h6></div>
            <div class="card-body">
                <strong>{{ $invoice->customer_name ?? '—' }}</strong><br>
                @if($contact)
                    <small class="text-muted">
                        <a href="{{ route('tl.contacts.show', $contact->id) }}">
                            <i class="fas fa-link mr-1"></i> Ver contacto
                        </a>
                    </small><br>
                    @if($contact->email)
                        <small><i class="fas fa-envelope mr-1"></i> {{ $contact->email }}</small>
                    @endif
                @endif
                @if($company)
                    <small class="text-muted">
                        <i class="fas fa-building mr-1"></i> {{ $company->name }}
                    </small>
                @endif
            </div>
        </div>

        {{-- Proyecto vinculado --}}
        @if($project)
        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-project-diagram mr-1"></i> Proyecto</h6></div>
            <div class="card-body">
                <strong>{{ $project->title ?? '—' }}</strong>
                <span class="badge badge-secondary ml-1">{{ $project->status }}</span>
            </div>
        </div>
        @endif

        {{-- Notas de crédito --}}
        @if($creditNotes->isNotEmpty())
        <div class="card card-outline card-warning">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-file-alt mr-1"></i> Notas de crédito</h6>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($creditNotes as $cn)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $cn->credit_note_number ?? '—' }}</span>
                        <strong>{{ number_format($cn->total_price_incl_tax ?? 0, 2) }} {{ $cn->currency }}</strong>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

    </div>

    {{-- Columna derecha --}}
    <div class="col-md-8">

        {{-- Líneas de factura --}}
        <div class="card card-outline card-primary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-list mr-1"></i> Líneas de factura</h6></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Descripción</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Precio unit.</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $currentSection = null; @endphp
                            @foreach($invoice->invoice_lines ?? [] as $line)
                                @if(($line['_section'] ?? null) && $line['_section'] !== $currentSection)
                                    @php $currentSection = $line['_section']; @endphp
                                    <tr class="bg-light">
                                        <td colspan="4" class="font-weight-bold text-muted">
                                            <i class="fas fa-folder-open mr-1"></i> {{ $currentSection }}
                                        </td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>
                                        <strong>{{ $line['description'] ?? '—' }}</strong>
                                        @if(!empty($line['extended_description']))
                                            <br><small class="text-muted">{{ Str::limit($line['extended_description'], 100) }}</small>
                                        @endif
                                    </td>
                                    <td class="text-right">{{ $line['quantity'] ?? 1 }}</td>
                                    <td class="text-right">
                                        {{ number_format($line['unit_price']['amount'] ?? 0, 2) }}
                                        <small class="text-muted">{{ $line['unit_price']['currency'] ?? '' }}</small>
                                    </td>
                                    <td class="text-right">
                                        <strong>{{ number_format($line['total']['tax_inclusive']['amount'] ?? 0, 2) }}</strong>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="thead-light">
                            <tr>
                                <td colspan="3" class="text-right font-weight-bold">Total (con IVA)</td>
                                <td class="text-right font-weight-bold">
                                    {{ number_format($invoice->total_price_incl_tax ?? 0, 2) }}
                                    {{ $invoice->currency }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Nota interna --}}
        @if(!empty($invoice->raw_data['note']))
        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-sticky-note mr-1"></i> Nota interna</h6></div>
            <div class="card-body">
                <pre class="mb-0" style="font-size:.85rem;white-space:pre-wrap">{{ $invoice->raw_data['note'] }}</pre>
            </div>
        </div>
        @endif

        {{-- Custom fields --}}
        @php
            $cfs = collect($invoice->custom_fields ?? [])->filter(fn($cf) => !empty(trim((string)($cf['value'] ?? ''))));
        @endphp
        @if($cfs->isNotEmpty())
        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-list mr-1"></i> Campos personalizados</h6></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <tbody>
                        @foreach($cfs as $cf)
                        <tr>
                            <td><code style="font-size:.75rem">{{ $cf['definition']['id'] }}</code></td>
                            <td>{{ is_array($cf['value']) ? json_encode($cf['value']) : $cf['value'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>
</div>
@endsection

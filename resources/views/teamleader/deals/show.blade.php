{{-- resources/views/teamleader/deals/show.blade.php --}}
@extends('adminlte::page')

@section('title', $deal->title ?: 'Negocio Teamleader')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap:.75rem">
        <h1 class="mb-0">
            <a href="{{ $contact ? route('teamleader.contacts.show', $contact->id) : route('teamleader.contacts.index') }}" class="btn btn-sm btn-secondary mr-2">
                <i class="fas fa-arrow-left"></i>
            </a>
            {{ $deal->title ?: 'Negocio Teamleader' }}
        </h1>

        <div class="d-flex align-items-center" style="gap:.5rem">
            @if(!empty($deal->raw_data['web_url']))
                <a href="{{ $deal->raw_data['web_url'] }}" target="_blank" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-external-link-alt mr-1"></i> Ver en Teamleader
                </a>
            @endif
        </div>
    </div>
@endsection

@section('content')
@php
    $badgeColor = match($deal->status) {
        'open' => 'info',
        'won' => 'success',
        'lost' => 'danger',
        default => 'secondary',
    };

    $customFields = collect($deal->custom_fields ?? [])
        ->filter(function ($field) {
            $value = $field['value'] ?? null;
            return $value !== null && $value !== '' && $value !== [];
        });
@endphp

<div class="row">
    <div class="col-md-4">
        <div class="card card-primary card-outline">
            <div class="card-body text-center">
                <span class="badge badge-{{ $badgeColor }} px-3 py-2" style="font-size:1rem">
                    {{ $deal->status ?: 'Sin estado' }}
                </span>
                <h2 class="mt-3 mb-0">
                    {{ number_format($deal->amount ?? 0, 2) }}
                    <small class="text-muted">{{ $deal->currency }}</small>
                </h2>
                <small class="text-muted">Valor estimado</small>
            </div>
            <div class="card-footer p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">ID Teamleader</span>
                        <code>{{ $deal->id }}</code>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Creado</span>
                        <span>{{ $deal->tl_created_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Actualizado</span>
                        <span>{{ $deal->tl_updated_at?->format('d/m/Y H:i') ?? '-' }}</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">Cierre estimado</span>
                        <span>{{ $deal->estimated_closing_date?->format('d/m/Y') ?? '-' }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-user mr-1"></i> Cliente</h6></div>
            <div class="card-body">
                @if($contact)
                    <a href="{{ route('teamleader.contacts.show', $contact->id) }}" class="font-weight-bold">
                        {{ $contact->full_name ?: '(Sin nombre)' }}
                    </a>
                    @if($contact->email)
                        <br><small class="text-muted">{{ $contact->email }}</small>
                    @endif
                @elseif($company)
                    <strong>{{ $company->name ?: 'Empresa' }}</strong>
                    @if($company->email)
                        <br><small class="text-muted">{{ $company->email }}</small>
                    @endif
                @else
                    <span class="text-muted">Sin cliente asociado</span>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card card-outline card-primary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-file-invoice-dollar mr-1"></i> Facturas</h6></div>
            <div class="card-body p-0">
                @if($invoices->isNotEmpty())
                    <table class="table table-sm table-hover mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Factura</th>
                                <th>Estado</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoices as $invoice)
                                <tr>
                                    <td>
                                        <a href="{{ route('teamleader.invoices.show', $invoice->id) }}" class="font-weight-bold">
                                            {{ $invoice->invoice_number ?: $invoice->id }}
                                        </a>
                                        <br><small class="text-muted">{{ $invoice->invoice_date?->format('d/m/Y') ?? '-' }}</small>
                                    </td>
                                    <td>{{ $invoice->status ?: '-' }}</td>
                                    <td class="text-right">{{ number_format($invoice->total_price_incl_tax ?? 0, 2) }} {{ $invoice->currency }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted text-center py-3 mb-0">Sin facturas vinculadas</p>
                @endif
            </div>
        </div>

        <div class="card card-outline card-secondary">
            <div class="card-header"><h6 class="mb-0"><i class="fas fa-paperclip mr-1"></i> Documentos</h6></div>
            <div class="card-body p-0">
                @if($documents->isNotEmpty())
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                            @foreach($documents as $document)
                                <tr>
                                    <td>
                                        <i class="fas fa-file mr-1 text-secondary"></i>
                                        <strong>{{ $document->name ?: 'Archivo' }}</strong>
                                        @if($document->extension)
                                            <span class="badge badge-light border">{{ strtoupper($document->extension) }}</span>
                                        @endif
                                        <br><small class="text-muted">{{ $document->readable_size }}</small>
                                    </td>
                                    <td class="text-right">
                                        @if($document->downloaded && $document->s3_path)
                                            <a href="{{ route('teamleader.documents.download', $document->id) }}" target="_blank" class="btn btn-xs btn-outline-primary">
                                                <i class="fas fa-download mr-1"></i> Descargar
                                            </a>
                                        @else
                                            <a href="{{ route('teamleader.documents.index', ['search' => $document->id]) }}" class="btn btn-xs btn-outline-secondary">
                                                <i class="fas fa-search mr-1"></i> Ver
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted text-center py-3 mb-0">Sin documentos vinculados</p>
                @endif
            </div>
        </div>

        @if($customFields->isNotEmpty())
            <div class="card card-outline card-secondary">
                <div class="card-header"><h6 class="mb-0"><i class="fas fa-list mr-1"></i> Campos personalizados</h6></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                            @foreach($customFields as $field)
                                @php
                                    $definitionId = $field['definition']['id'] ?? null;
                                    $label = $definitionId ? ($definitions->get($definitionId)->label ?? $definitionId) : 'Campo';
                                    $value = $field['value'] ?? null;
                                @endphp
                                <tr>
                                    <td class="text-muted" style="width:35%">{{ $label }}</td>
                                    <td>{{ is_array($value) ? json_encode($value) : $value }}</td>
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

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop
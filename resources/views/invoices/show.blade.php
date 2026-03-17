{{-- resources/views/invoices/show.blade.php --}}
@extends('adminlte::page')

@section('title', 'Factura ' . $invoice->invoice_number)

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Factura <span class="text-muted">{{ $invoice->invoice_number }}</span></h1>
        <div>
            <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-warning btn-sm">
                <i class="fas fa-edit mr-1"></i> Editar
            </a>
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-primary btn-sm" target="_blank">
                <i class="fas fa-file-pdf mr-1"></i> PDF
            </a>
        </div>
    </div>
@stop

@section('content')

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="row">

        {{-- ── CLIENTE ──────────────────────────────────────────────────── --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Cliente</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted pl-3" style="width:40%">Nombre</td>
                            <td class="font-weight-bold">{{ $invoice->customer_name }}</td>
                        </tr>
                        @if($invoice->customer_vat)
                        <tr>
                            <td class="text-muted pl-3">NIF / Pasaporte</td>
                            <td>{{ $invoice->customer_vat }}</td>
                        </tr>
                        @endif
                        @if($invoice->customer_email)
                        <tr>
                            <td class="text-muted pl-3">Email</td>
                            <td>{{ $invoice->customer_email }}</td>
                        </tr>
                        @endif
                        @if($invoice->customer_address)
                        <tr>
                            <td class="text-muted pl-3">Dirección</td>
                            <td>{{ $invoice->customer_address }}</td>
                        </tr>
                        @endif
                        @if($invoice->customer_country)
                        <tr>
                            <td class="text-muted pl-3">País</td>
                            <td>{{ $invoice->customer_country }}</td>
                        </tr>
                        @endif
                        @if($invoice->aa)
                        <tr>
                            <td class="text-muted pl-3">A/A</td>
                            <td>{{ $invoice->aa }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- ── DETALLES ─────────────────────────────────────────────────── --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Detalles</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted pl-3" style="width:40%">Fecha</td>
                            <td class="font-weight-bold">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                        </tr>
                        @if($invoice->expiry_date)
                        <tr>
                            <td class="text-muted pl-3">Vencimiento</td>
                            <td>{{ $invoice->expiry_date->format('d/m/Y') }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted pl-3">Moneda</td>
                            <td>{{ $invoice->currency }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Estado</td>
                            <td>
                                @php
                                    $badges = [
                                        'draft' => 'secondary',
                                        'sent'  => 'info',
                                        'paid'  => 'success',
                                    ];
                                    $labels = [
                                        'draft' => 'Borrador',
                                        'sent'  => 'Enviada',
                                        'paid'  => 'Pagada',
                                    ];
                                @endphp
                                <span class="badge badge-{{ $badges[$invoice->status] ?? 'secondary' }}">
                                    {{ $labels[$invoice->status] ?? $invoice->status }}
                                </span>
                            </td>
                        </tr>
                        @if($invoice->payment_terms)
                        <tr>
                            <td class="text-muted pl-3">Condiciones de pago</td>
                            <td>
                                @php
                                    $terms = [
                                        'immediate' => 'Inmediato',
                                        '14_days'   => '14 días',
                                        '30_days'   => '30 días',
                                        '60_days'   => '60 días',
                                        '90_days'   => '90 días',
                                    ];
                                @endphp
                                {{ $terms[$invoice->payment_terms] ?? $invoice->payment_terms }}
                            </td>
                        </tr>
                        @endif
                        @if($invoice->payment_method)
                        <tr>
                            <td class="text-muted pl-3">Forma de pago</td>
                            <td>
                                @php
                                    $methods = [
                                        'transfer' => 'Transferencia',
                                        'cash'     => 'Efectivo',
                                        'card'     => 'Tarjeta',
                                        'bizum'    => 'Bizum',
                                        'paypal'   => 'PayPal',
                                    ];
                                @endphp
                                {{ $methods[$invoice->payment_method] ?? $invoice->payment_method }}
                            </td>
                        </tr>
                        @endif
                        @if($invoice->bank_account)
                        <tr>
                            <td class="text-muted pl-3">Cuenta bancaria</td>
                            <td>{{ $invoice->bank_account === 'standard' ? 'Estándar' : $invoice->bank_account }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted pl-3">Creada por</td>
                            <td>{{ $invoice->user->name ?? '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Fecha creación</td>
                            <td>{{ $invoice->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── GESTIÓN INTERNA ──────────────────────────────────────────── --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Gestión interna</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted pl-3" style="width:40%">Captador</td>
                            <td>{{ $invoice->captador->name ?? '— Sin asignar —' }}</td>
                        </tr>
                        @if($invoice->sales_team)
                        <tr>
                            <td class="text-muted pl-3">Equipo de ventas</td>
                            <td>{{ $invoice->sales_team }}</td>
                        </tr>
                        @endif
                        @if($invoice->send_email)
                        <tr>
                            <td class="text-muted pl-3">Enviar correo a</td>
                            <td>{{ $invoice->send_email }}</td>
                        </tr>
                        @endif
                        @if($invoice->product_service)
                        <tr>
                            <td class="text-muted pl-3">Producto / Servicio</td>
                            <td>
                                @php
                                    $products = [
                                        'española_lmd'       => 'Española LMD',
                                        'española_sefardi'   => 'Española Sefardí',
                                        'portuguesa_sefardi' => 'Portuguesa Sefardí',
                                        'italiana'           => 'Italiana',
                                        'gestion_documental' => 'Gestión Documental',
                                        'otros'              => 'Otros',
                                    ];
                                @endphp
                                {{ $products[$invoice->product_service] ?? $invoice->product_service }}
                            </td>
                        </tr>
                        @endif
                    </table>
                </div>
            </div>
        </div>

        {{-- ── DEPÓSITOS Y PAGOS ────────────────────────────────────────── --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Depósitos y pagos</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted pl-3" style="width:40%">No. Dep. Cliente</td>
                            <td>{{ $invoice->deposit_number_client ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">No. Dep. Sefar</td>
                            <td>{{ $invoice->deposit_number_sefar ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Pagado por</td>
                            <td>{{ $invoice->paid_by ?: '—' }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── LÍNEAS ───────────────────────────────────────────────────── --}}
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Líneas</h3></div>
                <div class="card-body p-0">
                    <table class="table table-striped table-bordered mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>Descripción</th>
                                <th class="text-right">Cantidad</th>
                                <th class="text-right">Precio unit.</th>
                                <th class="text-right">IVA</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->lines as $line)
                            <tr>
                                <td>{{ $line->description }}</td>
                                <td class="text-right">{{ $line->quantity }}</td>
                                <td class="text-right">{{ number_format($line->unit_price, 2) }}</td>
                                <td class="text-right">{{ $line->tax_rate }}%</td>
                                <td class="text-right font-weight-bold">
                                    {{ number_format($line->total, 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="thead-light">
                            <tr>
                                <td colspan="4" class="text-right text-muted">Subtotal</td>
                                <td class="text-right font-weight-bold">
                                    {{ number_format($invoice->total_excl_tax, 2) }} {{ $invoice->currency }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-right text-muted">IVA</td>
                                <td class="text-right font-weight-bold">
                                    {{ number_format($invoice->total_tax, 2) }} {{ $invoice->currency }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="4" class="text-right"><strong>Total</strong></td>
                                <td class="text-right">
                                    <strong>
                                        {{ number_format($invoice->total_incl_tax, 2) }} {{ $invoice->currency }}
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- ── NOTAS ────────────────────────────────────────────────────── --}}
        @if($invoice->notes)
        <div class="col-12">
            <div class="card card-outline card-secondary">
                <div class="card-header"><h3 class="card-title">Notas</h3></div>
                <div class="card-body">
                    <p class="mb-0">{{ $invoice->notes }}</p>
                </div>
            </div>
        </div>
        @endif

    </div>{{-- /row --}}

    <a href="{{ route('invoices.index') }}" class="btn btn-secondary btn-sm mb-4">
        <i class="fas fa-arrow-left mr-1"></i> Volver al listado
    </a>

@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

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
            <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-primary btn-sm">
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

        {{-- Cliente --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Cliente</h3>
                </div>
                <div class="card-body">
                    <p class="font-weight-bold mb-1">{{ $invoice->customer_name }}</p>
                    @if($invoice->customer_vat)
                        <p class="text-muted mb-1">{{ $invoice->customer_vat }}</p>
                    @endif
                    @if($invoice->customer_email)
                        <p class="text-muted mb-1">{{ $invoice->customer_email }}</p>
                    @endif
                    @if($invoice->customer_address)
                        <p class="text-muted mb-1">{{ $invoice->customer_address }}</p>
                    @endif
                    @if($invoice->customer_country)
                        <p class="text-muted mb-0">{{ $invoice->customer_country }}</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Detalles --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Detalles</h3>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-borderless mb-0">
                        <tr>
                            <td class="text-muted pl-3">Fecha</td>
                            <td class="font-weight-bold">{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                        </tr>
                        @if($invoice->expiry_date)
                        <tr>
                            <td class="text-muted pl-3">Vencimiento</td>
                            <td class="font-weight-bold">{{ $invoice->expiry_date->format('d/m/Y') }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted pl-3">Moneda</td>
                            <td class="font-weight-bold">{{ $invoice->currency }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Estado</td>
                            <td>
                                @php $badges = ['draft'=>'secondary','sent'=>'info','paid'=>'success']; @endphp
                                <span class="badge badge-{{ $badges[$invoice->status] ?? 'secondary' }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Creada por</td>
                            <td>{{ $invoice->user->name }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        {{-- Líneas --}}
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header">
                    <h3 class="card-title">Líneas</h3>
                </div>
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
                                <td class="text-right font-weight-bold">{{ number_format($line->total, 2) }}</td>
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
                                <td class="text-right"><strong>{{ number_format($invoice->total_incl_tax, 2) }} {{ $invoice->currency }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        {{-- Notas --}}
        @if($invoice->notes)
        <div class="col-12">
            <div class="card card-outline card-secondary">
                <div class="card-header">
                    <h3 class="card-title">Notas</h3>
                </div>
                <div class="card-body">
                    <p class="mb-0">{{ $invoice->notes }}</p>
                </div>
            </div>
        </div>
        @endif

    </div>

    <a href="{{ route('invoices.index') }}" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left mr-1"></i> Volver
    </a>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
@stop

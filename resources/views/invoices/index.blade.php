{{-- resources/views/invoices/index.blade.php --}}
@extends('adminlte::page')

@section('title', 'Facturas')

@section('content_header')
    <div class="d-flex justify-content-between align-items-center">
        <h1>Facturas</h1>
        <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm">
            <i class="fas fa-plus mr-1"></i> Nueva factura
        </a>
    </div>
@stop

@section('content')
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table id="invoices-table" class="table table-striped table-hover mb-0">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Vence</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoices as $invoice)
                        <tr>
                            <td class="font-weight-bold">{{ $invoice->invoice_number }}</td>
                            <td>{{ $invoice->customer_name }}</td>
                            <td>{{ $invoice->invoice_date->format('d/m/Y') }}</td>
                            <td>{{ $invoice->expiry_date?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ number_format($invoice->total_incl_tax, 2) }} {{ $invoice->currency }}</td>
                            <td>
                                @php
                                    $badges = [
                                        'draft' => 'secondary',
                                        'sent'  => 'info',
                                        'paid'  => 'success',
                                    ];
                                @endphp
                                <span class="badge badge-{{ $badges[$invoice->status] ?? 'secondary' }}">
                                    {{ ucfirst($invoice->status) }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-xs btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-xs btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-xs btn-primary">
                                    <i class="fas fa-file"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $invoices->links() }}
        </div>
    </div>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
    <script>
        $('#invoices-table').DataTable({
            order: [[2, 'desc']],
            language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
        });
    </script>
@stop

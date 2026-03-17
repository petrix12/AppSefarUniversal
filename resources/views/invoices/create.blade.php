{{-- resources/views/invoices/create.blade.php --}}
@extends('adminlte::page')

@section('title', 'Nueva Factura')

@section('content_header')
    <h1>Nueva Factura</h1>
@stop

@section('content')
    <form method="POST" action="{{ route('invoices.store') }}" x-data="invoiceForm()">
        @csrf

        {{-- Número --}}
        <div class="callout callout-info">
            <h5>Número de factura: <strong class="font-monospace">{{ $nextNumber }}</strong></h5>
        </div>

        <div class="row">

            {{-- Cliente --}}
            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Datos del cliente</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" name="customer_name" value="{{ old('customer_name') }}"
                                   class="form-control @error('customer_name') is-invalid @enderror">
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="customer_email" value="{{ old('customer_email') }}"
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label>NIF / VAT</label>
                            <input type="text" name="customer_vat" value="{{ old('customer_vat') }}"
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Dirección</label>
                            <input type="text" name="customer_address" value="{{ old('customer_address') }}"
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label>País</label>
                            <input type="text" name="customer_country" value="{{ old('customer_country') }}"
                                   class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Detalles --}}
            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Detalles</h3>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Fecha de factura *</label>
                            <input type="date" name="invoice_date" value="{{ old('invoice_date', date('Y-m-d')) }}"
                                   class="form-control @error('invoice_date') is-invalid @enderror">
                            @error('invoice_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label>Fecha de vencimiento</label>
                            <input type="date" name="expiry_date" value="{{ old('expiry_date') }}"
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Moneda *</label>
                            <select name="currency" class="form-control">
                                <option value="EUR" {{ old('currency', 'EUR') == 'EUR' ? 'selected' : '' }}>EUR €</option>
                                <option value="USD" {{ old('currency') == 'USD' ? 'selected' : '' }}>USD $</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Estado *</label>
                            <select name="status" class="form-control">
                                <option value="draft" {{ old('status', 'draft') == 'draft' ? 'selected' : '' }}>Borrador</option>
                                <option value="sent"  {{ old('status') == 'sent'  ? 'selected' : '' }}>Enviada</option>
                                <option value="paid"  {{ old('status') == 'paid'  ? 'selected' : '' }}>Pagada</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Notas</label>
                            <textarea name="notes" rows="4" class="form-control"
                                      placeholder="Notas opcionales...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Líneas --}}
            <div class="col-12">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">Líneas de factura</h3>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width:40%">Descripción</th>
                                    <th style="width:12%">Cantidad</th>
                                    <th style="width:15%">Precio unit.</th>
                                    <th style="width:12%">IVA %</th>
                                    <th style="width:15%">Total</th>
                                    <th style="width:6%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(line, index) in lines" :key="index">
                                    <tr>
                                        <td>
                                            <input type="text" :name="`lines[${index}][description]`"
                                                   x-model="line.description"
                                                   placeholder="Descripción"
                                                   class="form-control form-control-sm">
                                        </td>
                                        <td>
                                            <input type="number" :name="`lines[${index}][quantity]`"
                                                   x-model="line.quantity" @input="calcLine(index)"
                                                   step="0.01" min="0"
                                                   class="form-control form-control-sm">
                                        </td>
                                        <td>
                                            <input type="number" :name="`lines[${index}][unit_price]`"
                                                   x-model="line.unit_price" @input="calcLine(index)"
                                                   step="0.01" min="0"
                                                   class="form-control form-control-sm">
                                        </td>
                                        <td>
                                            <div class="input-group input-group-sm">
                                                <input type="number" :name="`lines[${index}][tax_rate]`"
                                                       x-model="line.tax_rate" @input="calcLine(index)"
                                                       step="0.01" min="0" max="100"
                                                       class="form-control form-control-sm">
                                                <div class="input-group-append">
                                                    <span class="input-group-text">%</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" readonly
                                                   :value="line.total.toFixed(2)"
                                                   class="form-control form-control-sm bg-light font-weight-bold">
                                        </td>
                                        <td class="text-center">
                                            <button type="button" @click="removeLine(index)"
                                                    x-show="lines.length > 1"
                                                    class="btn btn-xs btn-danger">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <button type="button" @click="addLine()" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus mr-1"></i> Agregar línea
                        </button>
                        <div class="text-right">
                            <table class="table table-sm table-borderless mb-0" style="width:280px">
                                <tr>
                                    <td class="text-muted">Subtotal</td>
                                    <td class="text-right font-weight-bold" x-text="subtotal().toFixed(2) + ' €'"></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">IVA</td>
                                    <td class="text-right font-weight-bold" x-text="totalTax().toFixed(2) + ' €'"></td>
                                </tr>
                                <tr class="border-top">
                                    <td><strong>Total</strong></td>
                                    <td class="text-right"><strong x-text="totalIncl().toFixed(2) + ' €'"></strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('invoices.index') }}" class="btn btn-secondary mr-2">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> Crear factura
            </button>
        </div>

    </form>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
@stop

@section('js')
    <script>
        function invoiceForm() {
            return {
                lines: [{ description: '', quantity: 1, unit_price: 0, tax_rate: 0, total: 0 }],
                addLine() {
                    this.lines.push({ description: '', quantity: 1, unit_price: 0, tax_rate: 0, total: 0 });
                },
                removeLine(index) {
                    this.lines.splice(index, 1);
                },
                calcLine(index) {
                    const l = this.lines[index];
                    l.total = parseFloat(l.quantity || 0) * parseFloat(l.unit_price || 0);
                },
                subtotal() {
                    return this.lines.reduce((s, l) => s + parseFloat(l.total || 0), 0);
                },
                totalTax() {
                    return this.lines.reduce((s, l) => s + (parseFloat(l.total || 0) * (parseFloat(l.tax_rate || 0) / 100)), 0);
                },
                totalIncl() {
                    return this.subtotal() + this.totalTax();
                }
            }
        }
    </script>
@stop

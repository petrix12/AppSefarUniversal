{{-- resources/views/invoices/create.blade.php --}}
@extends('adminlte::page')

@section('title', 'Nueva Factura')

@section('content_header')
    <h1>Nueva Factura</h1>
@stop

@section('content')
    <form method="POST" action="{{ route('invoices.store') }}" id="invoice-form">
        @csrf

        {{-- Número --}}
        <div class="callout callout-info">
            <h5>Número de factura: <strong>{{ $nextNumber }}</strong></h5>
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
                            <label>Buscar usuario existente</label>
                            <select id="user-selector" name="customer_user_id"
                                    class="form-control" style="width:100%">
                                <option value="">— Seleccionar cliente —</option>
                            </select>
                            <small class="text-muted">
                                Al seleccionar, se rellenan los campos automáticamente.
                            </small>
                        </div>

                        <hr>

                        <div class="form-group">
                            <label>Nombre *</label>
                            <input type="text" name="customer_name" id="customer_name"
                                   value="{{ old('customer_name') }}"
                                   class="form-control @error('customer_name') is-invalid @enderror">
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="customer_email" id="customer_email"
                                   value="{{ old('customer_email') }}"
                                   class="form-control">
                        </div>

                        <div class="form-group">
                            <label>NIF / Pasaporte</label>
                            <input type="text" name="customer_vat" id="customer_vat"
                                   value="{{ old('customer_vat') }}"
                                   class="form-control">
                            <small id="vat-missing" class="text-warning d-none">
                                <i class="fas fa-exclamation-triangle"></i>
                                El cliente no tiene pasaporte registrado. Se guardará al crear la factura.
                            </small>
                        </div>

                        <div class="form-group">
                            <label>Dirección</label>
                            <input type="text" name="customer_address" id="customer_address"
                                   value="{{ old('customer_address') }}"
                                   class="form-control">
                            <small id="address-missing" class="text-warning d-none">
                                <i class="fas fa-exclamation-triangle"></i>
                                Este campo no estaba en el perfil del cliente. Se guardará al crear la factura.
                            </small>
                        </div>

                        <div class="form-group">
                            <label>País</label>
                            <input type="text" name="customer_country" id="customer_country"
                                   value="{{ old('customer_country') }}"
                                   class="form-control">
                            <small id="country-missing" class="text-warning d-none">
                                <i class="fas fa-exclamation-triangle"></i>
                                Este campo no estaba en el perfil del cliente. Se guardará al crear la factura.
                            </small>
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
                            <input type="date" name="invoice_date"
                                   value="{{ old('invoice_date', date('Y-m-d')) }}"
                                   class="form-control @error('invoice_date') is-invalid @enderror">
                            @error('invoice_date')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label>Fecha de vencimiento</label>
                            <input type="date" name="expiry_date"
                                   value="{{ old('expiry_date') }}"
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
                            <tbody id="lines-body">
                                {{-- JS inserta filas aquí --}}
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <button type="button" id="add-line" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus mr-1"></i> Agregar línea
                        </button>
                        <div class="text-right">
                            <table class="table table-sm table-borderless mb-0" style="width:280px">
                                <tr>
                                    <td class="text-muted">Subtotal</td>
                                    <td class="text-right font-weight-bold" id="summary-subtotal">0.00 €</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">IVA</td>
                                    <td class="text-right font-weight-bold" id="summary-tax">0.00 €</td>
                                </tr>
                                <tr class="border-top">
                                    <td><strong>Total</strong></td>
                                    <td class="text-right">
                                        <strong id="summary-total">0.00 €</strong>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>{{-- /row --}}

        {{-- Acciones --}}
        <div class="d-flex justify-content-end">
            <a href="{{ route('invoices.index') }}" class="btn btn-secondary mr-2">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i> Crear factura
            </button>
        </div>

    </form>
@stop

@section('css')
    <link rel="stylesheet" href="{{ asset('css/sefar.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap4-theme@1.0.0/dist/select2-bootstrap4.min.css">
@stop

@section('js')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// ── Gestión de líneas ─────────────────────────────────────────────────
var lineIndex = 0;

function addLine(data) {
    data = data || { description: '', quantity: 1, unit_price: 0, tax_rate: 0 };
    var i = lineIndex++;
    var total = parseFloat(data.quantity || 0) * parseFloat(data.unit_price || 0);

    var tr = document.createElement('tr');
    tr.id  = 'line-' + i;
    tr.innerHTML =
        '<td>' +
            '<input type="text" name="lines[' + i + '][description]" ' +
                   'value="' + escHtml(data.description) + '" ' +
                   'placeholder="Descripción" class="form-control form-control-sm">' +
        '</td>' +
        '<td>' +
            '<input type="number" name="lines[' + i + '][quantity]" ' +
                   'value="' + data.quantity + '" ' +
                   'step="0.01" min="0" class="form-control form-control-sm line-qty">' +
        '</td>' +
        '<td>' +
            '<input type="number" name="lines[' + i + '][unit_price]" ' +
                   'value="' + data.unit_price + '" ' +
                   'step="0.01" min="0" class="form-control form-control-sm line-price">' +
        '</td>' +
        '<td>' +
            '<div class="input-group input-group-sm">' +
                '<input type="number" name="lines[' + i + '][tax_rate]" ' +
                       'value="' + data.tax_rate + '" ' +
                       'step="0.01" min="0" max="100" class="form-control form-control-sm line-tax">' +
                '<div class="input-group-append">' +
                    '<span class="input-group-text">%</span>' +
                '</div>' +
            '</div>' +
        '</td>' +
        '<td>' +
            '<input type="text" readonly value="' + total.toFixed(2) + '" ' +
                   'class="form-control form-control-sm bg-light font-weight-bold line-total">' +
        '</td>' +
        '<td class="text-center">' +
            '<button type="button" class="btn btn-xs btn-danger btn-remove-line">' +
                '<i class="fas fa-times"></i>' +
            '</button>' +
        '</td>';

    document.getElementById('lines-body').appendChild(tr);
    updateRemoveButtons();
    recalculate();
}

function escHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

function recalcTr(tr) {
    var qty   = parseFloat(tr.querySelector('.line-qty').value   || 0);
    var price = parseFloat(tr.querySelector('.line-price').value || 0);
    var total = qty * price;
    tr.querySelector('.line-total').value = total.toFixed(2);
}

function recalculate() {
    var rows     = document.querySelectorAll('#lines-body tr');
    var subtotal = 0;
    var tax      = 0;

    rows.forEach(function (tr) {
        var total   = parseFloat(tr.querySelector('.line-total').value || 0);
        var taxRate = parseFloat(tr.querySelector('.line-tax').value   || 0);
        subtotal += total;
        tax      += total * (taxRate / 100);
    });

    var currency = document.querySelector('[name="currency"]').value === 'USD' ? ' $' : ' €';
    document.getElementById('summary-subtotal').textContent = subtotal.toFixed(2) + currency;
    document.getElementById('summary-tax').textContent      = tax.toFixed(2)      + currency;
    document.getElementById('summary-total').textContent    = (subtotal + tax).toFixed(2) + currency;
}

function updateRemoveButtons() {
    var btns = document.querySelectorAll('.btn-remove-line');
    btns.forEach(function (btn) {
        btn.style.display = btns.length > 1 ? 'inline-block' : 'none';
    });
}

// ── Eventos de líneas ─────────────────────────────────────────────────
document.getElementById('add-line').addEventListener('click', function () {
    addLine();
});

document.getElementById('lines-body').addEventListener('input', function (e) {
    var tr = e.target.closest('tr');
    if (!tr) return;
    recalcTr(tr);
    recalculate();
});

document.getElementById('lines-body').addEventListener('click', function (e) {
    var btn = e.target.closest('.btn-remove-line');
    if (!btn) return;
    var rows = document.querySelectorAll('#lines-body tr');
    if (rows.length <= 1) return;
    btn.closest('tr').remove();
    updateRemoveButtons();
    recalculate();
});

document.querySelector('[name="currency"]').addEventListener('change', recalculate);

// ── Primera línea al cargar ───────────────────────────────────────────
addLine();

// ── Select2 ──────────────────────────────────────────────────────────
$(document).ready(function () {

    $('#user-selector').select2({
        theme: 'bootstrap4',
        placeholder: '— Buscar por nombre, email o pasaporte —',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route('invoices.user-search') }}',
            dataType: 'json',
            delay: 300,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; },
            cache: true
        }
    });

    $('#user-selector').on('select2:select', function (e) {
        var userId = e.params.data.id;

        $.getJSON('{{ url('invoices-user-data') }}/' + userId, function (data) {
            $('#customer_name').val(data.customer_name);
            $('#customer_email').val(data.customer_email);
            $('#customer_vat').val(data.customer_vat);
            $('#customer_address').val(data.customer_address);
            $('#customer_country').val(data.customer_country);

            $('#vat-missing').toggleClass('d-none',     !data.missing.passport);
            $('#address-missing').toggleClass('d-none', !data.missing.address);
            $('#country-missing').toggleClass('d-none', !data.missing.pais_de_residencia);

        }).fail(function () {
            toastr.error('No se pudo obtener los datos del cliente.');
        });
    });

    $('#user-selector').on('select2:clear', function () {
        $('#customer_name, #customer_email, #customer_vat, #customer_address, #customer_country').val('');
        $('#vat-missing, #address-missing, #country-missing').addClass('d-none');
    });

});
</script>
@stop

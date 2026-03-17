{{-- resources/views/invoices/create.blade.php --}}
@extends('adminlte::page')

@section('title', 'Nueva Factura')

@section('content_header')
    <h1>Nueva Factura</h1>
@stop

@section('content')
<form method="POST" action="{{ route('invoices.store') }}" id="invoice-form">
    @csrf

    <div class="callout callout-info">
        <h5>Número de factura: <strong>{{ $nextNumber }}</strong></h5>
    </div>

    <div class="row">

        {{-- ── CLIENTE ──────────────────────────────────────────────────── --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Datos del cliente</h3></div>
                <div class="card-body">

                    <div class="form-group">
                        <label>Buscar usuario existente</label>
                        <select id="user-selector" name="customer_user_id"
                                class="form-control" style="width:100%">
                            <option value="">— Seleccionar cliente —</option>
                        </select>
                        <small class="text-muted">Al seleccionar, se rellenan los campos automáticamente.</small>
                    </div>

                    <hr>

                    <div class="form-group">
                        <label>Nombre *</label>
                        <input type="text" name="customer_name" id="customer_name"
                               value="{{ old('customer_name') }}"
                               class="form-control @error('customer_name') is-invalid @enderror">
                        @error('customer_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="customer_email" id="customer_email"
                               value="{{ old('customer_email') }}" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>NIF / Pasaporte</label>
                        <input type="text" name="customer_vat" id="customer_vat"
                               value="{{ old('customer_vat') }}" class="form-control">
                        <small id="vat-missing" class="text-warning d-none">
                            <i class="fas fa-exclamation-triangle"></i>
                            El cliente no tiene pasaporte registrado.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Dirección</label>
                        <input type="text" name="customer_address" id="customer_address"
                               value="{{ old('customer_address') }}" class="form-control">
                        <small id="address-missing" class="text-warning d-none">
                            <i class="fas fa-exclamation-triangle"></i>
                            Este campo no estaba en el perfil del cliente.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>País</label>
                        <input type="text" name="customer_country" id="customer_country"
                               value="{{ old('customer_country') }}" class="form-control">
                        <small id="country-missing" class="text-warning d-none">
                            <i class="fas fa-exclamation-triangle"></i>
                            Este campo no estaba en el perfil del cliente.
                        </small>
                    </div>

                    <div class="form-group">
                        <label>A/A <small class="text-muted">(A la atención de)</small></label>
                        <input type="text" name="aa" value="{{ old('aa') }}"
                               class="form-control" placeholder="Nombre de contacto...">
                    </div>

                </div>
            </div>
        </div>

        {{-- ── DETALLES ─────────────────────────────────────────────────── --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Detalles</h3></div>
                <div class="card-body">

                    <div class="form-group">
                        <label>Fecha de factura *</label>
                        <input type="date" name="invoice_date"
                               value="{{ old('invoice_date', date('Y-m-d')) }}"
                               class="form-control @error('invoice_date') is-invalid @enderror">
                        @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-group">
                        <label>Fecha de vencimiento</label>
                        <input type="date" name="expiry_date"
                               value="{{ old('expiry_date') }}" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Moneda *</label>
                        <select name="currency" id="currency" class="form-control">
                            <option value="EUR" {{ old('currency','EUR')=='EUR'?'selected':'' }}>EUR €</option>
                            <option value="USD" {{ old('currency')=='USD'?'selected':'' }}>USD $</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Estado *</label>
                        <select name="status" class="form-control">
                            <option value="draft" {{ old('status','draft')=='draft'?'selected':'' }}>Borrador</option>
                            <option value="sent"  {{ old('status')=='sent' ?'selected':'' }}>Enviada</option>
                            <option value="paid"  {{ old('status')=='paid' ?'selected':'' }}>Pagada</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Condiciones de pago</label>
                        <select name="payment_terms" class="form-control">
                            <option value="">— Elegir —</option>
                            <option value="immediate" {{ old('payment_terms')=='immediate'?'selected':'' }}>Inmediato</option>
                            <option value="14_days"   {{ old('payment_terms')=='14_days'  ?'selected':'' }}>14 días</option>
                            <option value="30_days"   {{ old('payment_terms')=='30_days'  ?'selected':'' }}>30 días</option>
                            <option value="60_days"   {{ old('payment_terms')=='60_days'  ?'selected':'' }}>60 días</option>
                            <option value="90_days"   {{ old('payment_terms')=='90_days'  ?'selected':'' }}>90 días</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Forma de pago *</label>
                        <select name="payment_method" class="form-control">
                            <option value="">— Elegir —</option>
                            <option value="transfer" {{ old('payment_method')=='transfer'?'selected':'' }}>Transferencia</option>
                            <option value="cash"     {{ old('payment_method')=='cash'    ?'selected':'' }}>Efectivo</option>
                            <option value="card"     {{ old('payment_method')=='card'    ?'selected':'' }}>Tarjeta</option>
                            <option value="bizum"    {{ old('payment_method')=='bizum'   ?'selected':'' }}>Bizum</option>
                            <option value="paypal"   {{ old('payment_method')=='paypal'  ?'selected':'' }}>PayPal</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Cuenta bancaria</label>
                        <select name="bank_account" class="form-control">
                            <option value="standard" {{ old('bank_account','standard')=='standard'?'selected':'' }}>Estándar</option>
                            <option value="other"    {{ old('bank_account')=='other'               ?'selected':'' }}>Otra</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Notas</label>
                        <textarea name="notes" rows="3" class="form-control"
                                  placeholder="Notas opcionales...">{{ old('notes') }}</textarea>
                    </div>

                </div>
            </div>
        </div>

        {{-- ── GESTIÓN INTERNA ──────────────────────────────────────────── --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Gestión interna</h3></div>
                <div class="card-body">

                    <div class="form-group">
                        <label>Captador</label>
                        <select name="captador_id" class="form-control">
                            <option value="">— Pendiente de asignar —</option>
                            @foreach($captadores as $c)
                                <option value="{{ $c->id }}"
                                    {{ old('captador_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Equipo de ventas</label>
                        <input type="text" name="sales_team" value="{{ old('sales_team') }}"
                               class="form-control" placeholder="Nombre del equipo...">
                    </div>

                    <div class="form-group">
                        <label>Enviar correo a</label>
                        <input type="text" name="send_email" value="{{ old('send_email') }}"
                               class="form-control" placeholder="email@ejemplo.com">
                    </div>

                    <div class="form-group">
                        <label>Producto / Servicio</label>
                        <select name="product_service" class="form-control">
                            <option value="">— Elegir —</option>
                            <option value="española_lmd"        {{ old('product_service')=='española_lmd'        ?'selected':'' }}>Española LMD</option>
                            <option value="española_sefardi"    {{ old('product_service')=='española_sefardi'    ?'selected':'' }}>Española Sefardí</option>
                            <option value="portuguesa_sefardi"  {{ old('product_service')=='portuguesa_sefardi'  ?'selected':'' }}>Portuguesa Sefardí</option>
                            <option value="italiana"            {{ old('product_service')=='italiana'            ?'selected':'' }}>Italiana</option>
                            <option value="gestion_documental"  {{ old('product_service')=='gestion_documental'  ?'selected':'' }}>Gestión Documental</option>
                            <option value="otros"               {{ old('product_service')=='otros'               ?'selected':'' }}>Otros</option>
                        </select>
                    </div>

                </div>
            </div>
        </div>

        {{-- ── DEPÓSITOS Y PAGOS ────────────────────────────────────────── --}}
        <div class="col-md-6">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Depósitos y pagos</h3></div>
                <div class="card-body">

                    <div class="form-group">
                        <label>No. Depósito Cliente</label>
                        <input type="text" name="deposit_number_client"
                               value="{{ old('deposit_number_client') }}"
                               class="form-control" placeholder="Número de depósito del cliente">
                    </div>

                    <div class="form-group">
                        <label>No. Depósito Sefar</label>
                        <input type="text" name="deposit_number_sefar"
                               value="{{ old('deposit_number_sefar') }}"
                               class="form-control" placeholder="Número de depósito Sefar">
                    </div>

                    <div class="form-group">
                        <label>Pagado por</label>
                        <input type="text" name="paid_by"
                               value="{{ old('paid_by') }}"
                               class="form-control" placeholder="Nombre de quien pagó">
                    </div>

                </div>
            </div>
        </div>

        {{-- ── LÍNEAS ───────────────────────────────────────────────────── --}}
        <div class="col-12">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title">Líneas de factura</h3></div>
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
                        <tbody id="lines-body"></tbody>
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
                                <td class="text-right"><strong id="summary-total">0.00 €</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>{{-- /row --}}

    <div class="d-flex justify-content-end mb-4">
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
var lineIndex = 0;

function addLine(data) {
    data = data || { description: '', quantity: 1, unit_price: 0, tax_rate: 0 };
    var i     = lineIndex++;
    var total = parseFloat(data.quantity || 0) * parseFloat(data.unit_price || 0);
    var tr    = document.createElement('tr');
    tr.id     = 'line-' + i;
    tr.innerHTML =
        '<td><input type="text" name="lines[' + i + '][description]" value="' + escHtml(data.description) + '" placeholder="Descripción" class="form-control form-control-sm"></td>' +
        '<td><input type="number" name="lines[' + i + '][quantity]" value="' + data.quantity + '" step="0.01" min="0" class="form-control form-control-sm line-qty"></td>' +
        '<td><input type="number" name="lines[' + i + '][unit_price]" value="' + data.unit_price + '" step="0.01" min="0" class="form-control form-control-sm line-price"></td>' +
        '<td><div class="input-group input-group-sm"><input type="number" name="lines[' + i + '][tax_rate]" value="' + data.tax_rate + '" step="0.01" min="0" max="100" class="form-control form-control-sm line-tax"><div class="input-group-append"><span class="input-group-text">%</span></div></div></td>' +
        '<td><input type="text" readonly value="' + total.toFixed(2) + '" class="form-control form-control-sm bg-light font-weight-bold line-total"></td>' +
        '<td class="text-center"><button type="button" class="btn btn-xs btn-danger btn-remove-line"><i class="fas fa-times"></i></button></td>';
    document.getElementById('lines-body').appendChild(tr);
    updateRemoveButtons();
    recalculate();
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function recalcTr(tr) {
    var qty   = parseFloat(tr.querySelector('.line-qty').value   || 0);
    var price = parseFloat(tr.querySelector('.line-price').value || 0);
    tr.querySelector('.line-total').value = (qty * price).toFixed(2);
}

function recalculate() {
    var rows = document.querySelectorAll('#lines-body tr');
    var subtotal = 0, tax = 0;
    rows.forEach(function(tr) {
        var total   = parseFloat(tr.querySelector('.line-total').value || 0);
        var taxRate = parseFloat(tr.querySelector('.line-tax').value   || 0);
        subtotal += total;
        tax      += total * (taxRate / 100);
    });
    var sym = document.getElementById('currency').value === 'USD' ? ' $' : ' €';
    document.getElementById('summary-subtotal').textContent = subtotal.toFixed(2) + sym;
    document.getElementById('summary-tax').textContent      = tax.toFixed(2)      + sym;
    document.getElementById('summary-total').textContent    = (subtotal + tax).toFixed(2) + sym;
}

function updateRemoveButtons() {
    var btns = document.querySelectorAll('.btn-remove-line');
    btns.forEach(function(btn) {
        btn.style.display = btns.length > 1 ? 'inline-block' : 'none';
    });
}

document.getElementById('add-line').addEventListener('click', function() { addLine(); });

document.getElementById('lines-body').addEventListener('input', function(e) {
    var tr = e.target.closest('tr');
    if (tr) { recalcTr(tr); recalculate(); }
});

document.getElementById('lines-body').addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-remove-line');
    if (!btn) return;
    if (document.querySelectorAll('#lines-body tr').length <= 1) return;
    btn.closest('tr').remove();
    updateRemoveButtons();
    recalculate();
});

document.getElementById('currency').addEventListener('change', recalculate);

addLine();

$(document).ready(function() {
    $('#user-selector').select2({
        theme: 'bootstrap4',
        placeholder: '— Buscar por nombre, email o pasaporte —',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
            url: '{{ route('invoices.user-search') }}',
            dataType: 'json',
            delay: 300,
            data: function(params) { return { q: params.term }; },
            processResults: function(data) { return { results: data.results }; },
            cache: true
        }
    });

    $('#user-selector').on('select2:select', function(e) {
        var userId = e.params.data.id;
        $.getJSON('{{ url('invoices-user-data') }}/' + userId, function(data) {
            $('#customer_name').val(data.customer_name);
            $('#customer_email').val(data.customer_email);
            $('#customer_vat').val(data.customer_vat);
            $('#customer_address').val(data.customer_address);
            $('#customer_country').val(data.customer_country);
            $('#vat-missing').toggleClass('d-none',     !data.missing.passport);
            $('#address-missing').toggleClass('d-none', !data.missing.address);
            $('#country-missing').toggleClass('d-none', !data.missing.pais_de_residencia);
        }).fail(function() { toastr.error('No se pudo obtener los datos del cliente.'); });
    });

    $('#user-selector').on('select2:clear', function() {
        $('#customer_name,#customer_email,#customer_vat,#customer_address,#customer_country').val('');
        $('#vat-missing,#address-missing,#country-missing').addClass('d-none');
    });
});
</script>
@stop

@extends('adminlte::page')

@section('title', $quote->exists ? 'Editar presupuesto CRM' : 'Nuevo presupuesto CRM')

@section('content_header')
    <h1><i class="fas fa-file-invoice-dollar mr-2 text-primary"></i>{{ $quote->exists ? 'Editar presupuesto '.$quote->quote_number : 'Nuevo presupuesto' }}</h1>
@stop

@section('content')
    <div class="card card-outline card-primary"><div class="card-body">
        <form method="POST" action="{{ $action }}" id="crm-quote-form">
            @csrf @if($method !== 'POST') @method($method) @endif
            @include('crm.partials.contact-picker', ['leadId' => $quote->lead_id, 'clientId' => $quote->client_id])
            <div class="form-row">
                <div class="form-group col-md-8"><label>Nombre del presupuesto</label><input name="title" class="form-control" value="{{ old('title', $quote->title) }}" required maxlength="255">@error('title')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-2"><label>Moneda</label><input name="currency" class="form-control text-uppercase" value="{{ old('currency', $quote->currency ?: 'EUR') }}" maxlength="3" required></div>
                <div class="form-group col-md-2"><label>Válido hasta</label><input type="date" name="valid_until" class="form-control" value="{{ old('valid_until', optional($quote->valid_until)->format('Y-m-d')) }}"></div>
                <div class="form-group col-md-8"><label>Descripción</label><textarea name="description" class="form-control" rows="2">{{ old('description', $quote->description) }}</textarea></div>
                <div class="form-group col-md-2"><label>Impuesto (%)</label><input type="number" name="tax_percent" class="form-control" min="0" max="100" step="0.01" value="{{ old('tax_percent', $quote->subtotal > 0 ? round(((float) $quote->tax_amount / (float) $quote->subtotal) * 100, 2) : 0) }}"></div>
                <div class="form-group col-md-2"><label>Estado</label><select name="status" class="form-control">@foreach($statuses as $key => $label)<option value="{{ $key }}" @selected(old('status', $quote->status) === $key)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <div class="d-flex align-items-center justify-content-between mb-2"><h5 class="mb-0">Conceptos</h5><button type="button" class="btn btn-sm btn-outline-primary" id="add-quote-item"><i class="fas fa-plus mr-1"></i>Añadir concepto</button></div>
            @error('items')<small class="text-danger d-block mb-2">{{ $message }}</small>@enderror
            <div id="quote-items">
                @foreach(old('items', $items) as $index => $item)
                    <div class="form-row quote-item align-items-end">
                        <div class="form-group col-md-6"><label>Descripción</label><input name="items[{{ $index }}][description]" class="form-control" value="{{ $item['description'] ?? '' }}" required></div>
                        <div class="form-group col-md-2"><label>Cantidad</label><input type="number" name="items[{{ $index }}][quantity]" class="form-control quote-quantity" min="0.01" step="0.01" value="{{ $item['quantity'] ?? 1 }}" required></div>
                        <div class="form-group col-md-3"><label>Precio unitario</label><input type="number" name="items[{{ $index }}][unit_price]" class="form-control quote-price" min="0" step="0.01" value="{{ $item['unit_price'] ?? 0 }}" required></div>
                        <div class="form-group col-md-1"><button type="button" class="btn btn-outline-danger remove-quote-item" aria-label="Quitar concepto"><i class="fas fa-trash"></i></button></div>
                    </div>
                @endforeach
            </div>
            <div class="text-right border-top pt-3 mb-3"><div>Subtotal: <strong id="quote-subtotal">0</strong></div><div>Impuestos: <strong id="quote-tax">0</strong></div><h4>Total: <strong id="quote-total">0</strong></h4><small class="text-muted">Los totales se calculan también en el servidor al guardar.</small></div>
            <a href="{{ route('crm.quotes.index') }}" class="btn btn-outline-secondary">Volver</a><button class="btn btn-primary">Guardar presupuesto</button>
        </form>
    </div></div>
@stop

@section('js')
<script>
(() => {
    const container = document.getElementById('quote-items');
    const itemMarkup = index => `<div class="form-row quote-item align-items-end"><div class="form-group col-md-6"><label>Descripción</label><input name="items[${index}][description]" class="form-control" required></div><div class="form-group col-md-2"><label>Cantidad</label><input type="number" name="items[${index}][quantity]" class="form-control quote-quantity" min="0.01" step="0.01" value="1" required></div><div class="form-group col-md-3"><label>Precio unitario</label><input type="number" name="items[${index}][unit_price]" class="form-control quote-price" min="0" step="0.01" value="0" required></div><div class="form-group col-md-1"><button type="button" class="btn btn-outline-danger remove-quote-item" aria-label="Quitar concepto"><i class="fas fa-trash"></i></button></div></div>`;
    const recalc = () => {
        const subtotal = [...container.querySelectorAll('.quote-item')].reduce((sum, row) => sum + (Number(row.querySelector('.quote-quantity').value) || 0) * (Number(row.querySelector('.quote-price').value) || 0), 0);
        const tax = subtotal * (Number(document.querySelector('[name="tax_percent"]').value) || 0) / 100;
        const currency = (document.querySelector('[name="currency"]').value || 'EUR').toUpperCase();
        const format = value => `${value.toFixed(2)} ${currency}`;
        document.getElementById('quote-subtotal').textContent = format(subtotal);
        document.getElementById('quote-tax').textContent = format(tax);
        document.getElementById('quote-total').textContent = format(subtotal + tax);
    };
    document.getElementById('add-quote-item').addEventListener('click', () => {
        const index = Math.max(-1, ...[...container.querySelectorAll('[name^="items["]')].map(input => Number(input.name.match(/items\[(\d+)\]/)?.[1] ?? -1))) + 1;
        container.insertAdjacentHTML('beforeend', itemMarkup(index));
        recalc();
    });
    container.addEventListener('click', event => {
        const button = event.target.closest('.remove-quote-item');
        if (!button) return;
        if (container.querySelectorAll('.quote-item').length > 1) button.closest('.quote-item').remove();
        recalc();
    });
    document.getElementById('crm-quote-form').addEventListener('input', recalc);
    recalc();
})();
</script>
@stop

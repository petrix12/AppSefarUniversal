<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    @page {
        margin: 28px;
    }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 12px;
        color: #222;
        background: #fff;
    }

    .page-frame {
        border: 2px solid #1a3a5c;
        padding: 18px;
        min-height: 100%;
    }

    /* ── SELLO PAGADO ── */
    .stamp-paid {
        position: fixed;
        top: 38px;
        right: 42px;
        border: 4px solid #2e7d32;
        color: #2e7d32;
        font-size: 24px;
        font-weight: bold;
        padding: 8px 18px;
        border-radius: 4px;
        opacity: 0.75;
        transform: rotate(-15deg);
        letter-spacing: 2px;
        z-index: 999;
    }

    /* ── CABECERA ── */
    .header {
        width: 100%;
        margin-bottom: 24px;
        padding-bottom: 14px;
        border-bottom: 2px solid #d9e2ec;
    }

    .header table {
        width: 100%;
        border-collapse: collapse;
    }

    .logo {
        width: 190px;
        max-height: 70px;
        object-fit: contain;
    }

    .company-info {
        text-align: right;
        font-size: 12px;
        color: #444;
        line-height: 1.6;
    }

    .company-info strong {
        font-size: 15px;
        color: #111;
        display: block;
        margin-bottom: 4px;
    }

    .company-tagline {
        font-style: italic;
        color: #888;
        font-size: 11px;
        margin-top: 6px;
    }

    /* ── TÍTULO FACTURA ── */
    .invoice-title {
        background-color: #1a3a5c;
        color: #fff;
        text-align: center;
        font-size: 22px;
        font-weight: bold;
        letter-spacing: 3px;
        padding: 10px 0;
        margin-bottom: 18px;
        border: 1px solid #10263d;
    }

    /* ── BLOQUE CLIENTE + DATOS ── */
    .meta-block {
        width: 100%;
        margin-bottom: 20px;
    }

    .meta-block table {
        width: 100%;
        border-collapse: collapse;
    }

    .client-box {
        background: #f4f7fb;
        border: 1px solid #d9e2ec;
        border-left: 5px solid #1a3a5c;
        padding: 12px 14px;
        width: 55%;
        vertical-align: top;
    }

    .client-box .label {
        font-size: 10px;
        color: #777;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 5px;
    }

    .client-box .name {
        font-size: 15px;
        font-weight: bold;
        color: #1a3a5c;
        margin-bottom: 4px;
    }

    .client-box .vat {
        font-size: 12px;
        color: #555;
        margin-top: 2px;
    }

    .invoice-meta {
        width: 40%;
        vertical-align: top;
        text-align: right;
    }

    .invoice-meta table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #d9e2ec;
    }

    .invoice-meta td {
        padding: 7px 8px;
        font-size: 12px;
        border-bottom: 1px solid #e8eef5;
    }

    .invoice-meta tr:last-child td {
        border-bottom: none;
    }

    .invoice-meta td:first-child {
        color: #666;
        text-align: left;
        width: 45%;
        background: #f9fbfd;
    }

    .invoice-meta td:last-child {
        font-weight: bold;
        color: #222;
        text-align: right;
    }

    .invoice-meta .num-factura td:last-child {
        font-size: 15px;
        color: #1a3a5c;
    }

    /* ── TABLA DE LÍNEAS ── */
    .lines-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        border: 1px solid #d9e2ec;
    }

    .lines-table thead tr {
        background-color: #1a3a5c;
        color: #fff;
    }

    .lines-table thead th {
        padding: 9px 8px;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: left;
        border: 1px solid #294a6c;
    }

    .lines-table thead th.right {
        text-align: right;
    }

    .lines-table tbody tr {
        border-bottom: 1px solid #e8eef5;
    }

    .lines-table tbody tr:nth-child(even) {
        background: #f9fbfd;
    }

    .lines-table tbody td {
        padding: 10px 8px;
        vertical-align: top;
        font-size: 12px;
        border: 1px solid #edf2f7;
    }

    .lines-table tbody td.right {
        text-align: right;
    }

    .desc-main {
        font-weight: bold;
        color: #1a3a5c;
        margin-bottom: 4px;
        font-size: 12px;
    }

    .desc-extended {
        font-size: 11px;
        color: #666;
        line-height: 1.5;
        font-style: italic;
    }

    /* ── TOTALES ── */
    .totals-wrapper {
        width: 100%;
        margin-top: 8px;
    }

    .totals-wrapper table {
        width: 100%;
        border-collapse: collapse;
    }

    .totals-spacer {
        width: 55%;
        vertical-align: top;
    }

    .totals-box {
        width: 45%;
        vertical-align: top;
    }

    .totals-box table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #d9e2ec;
    }

    .totals-box tr {
        border-bottom: 1px solid #e8eef5;
    }

    .totals-box td {
        padding: 8px 9px;
        font-size: 12px;
    }

    .totals-box td:first-child {
        color: #555;
    }

    .totals-box td:last-child {
        text-align: right;
        font-weight: bold;
    }

    .totals-box .row-highlight {
        background: #f4f7fb;
    }

    .totals-box .row-total {
        background: #1a3a5c;
        color: #fff !important;
    }

    .totals-box .row-total td {
        color: #fff !important;
        font-size: 13px;
        padding: 10px 9px;
    }

    /* ── NOTA IVA ── */
    .tax-note {
        margin-top: 18px;
        font-size: 11px;
        color: #666;
        font-style: italic;
        border-top: 1px solid #d9e2ec;
        padding-top: 10px;
    }

    /* ── PIE ── */
    .footer {
        margin-top: 28px;
        border-top: 2px solid #1a3a5c;
        padding-top: 10px;
        font-size: 10px;
        color: #777;
        text-align: center;
        line-height: 1.7;
    }

    .footer .ref {
        font-weight: bold;
        color: #1a3a5c;
        font-size: 11px;
        margin-bottom: 4px;
    }

    .spacer {
        width: 16px;
    }
</style>
</head>
<body>

@if($paid)
    <div class="stamp-paid">PAGADO</div>
@endif

<div class="page-frame">

    <div class="header">
        <table>
            <tr>
                <td style="vertical-align: middle; width: 50%;">
                    @if(!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" class="logo" alt="Sefar Universal">
                    @endif
                    <div class="company-tagline">Derecho genealogista</div>
                </td>
                <td class="company-info">
                    <strong>SAVIOR TRADING, S.A.</strong>
                    155736147-2-2023<br>
                    Aquilino De La Guardia, Ocean Business Plaza<br>
                    Piso 12, OF. 1203<br>
                    (507) 3902890<br>
                    info@sefarvzla.com<br>
                    www.sefaruniversal.com
                </td>
            </tr>
        </table>
    </div>

    <div class="invoice-title">FACTURA</div>

    <div class="meta-block">
        <table>
            <tr>
                <td class="client-box">
                    <div class="label">Cliente</div>
                    <div class="name">{{ $customer['name'] }}</div>

                    @if($customer['vat_number'])
                        <div class="vat">{{ $customer['vat_number'] }}</div>
                    @endif

                    @if($customer['email'])
                        <div class="vat">{{ $customer['email'] }}</div>
                    @endif
                </td>
                <td class="spacer"></td>
                <td class="invoice-meta">
                    <table>
                        <tr class="num-factura">
                            <td>Número</td>
                            <td>{{ $invoice->invoice_number }}</td>
                        </tr>
                        <tr>
                            <td>Fecha</td>
                            <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td>Vencimiento</td>
                            <td>{{ \Carbon\Carbon::parse($invoice->expiry_date)->format('d/m/Y') }}</td>
                        </tr>
                        <tr>
                            <td>Condiciones de pago</td>
                            <td>{{ $payment_days }} días después de la fecha de facturación</td>
                        </tr>
                        @if($invoice->paid_date)
                        <tr>
                            <td>Fecha de pago</td>
                            <td>{{ \Carbon\Carbon::parse($invoice->paid_date)->format('d/m/Y') }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <table class="lines-table">
        <thead>
            <tr>
                <th style="width:40%">Descripción</th>
                <th class="right" style="width:8%">Cant.</th>
                <th class="right" style="width:14%">Precio unitario</th>
                <th class="right" style="width:8%">IVA %</th>
                <th class="right" style="width:12%">Importe IVA</th>
                <th class="right" style="width:13%">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lines as $line)
            <tr>
                <td>
                    <div class="desc-main">{{ $line['description'] }}</div>
                    @if($line['extended_description'])
                        <div class="desc-extended">{{ $line['extended_description'] }}</div>
                    @endif
                </td>
                <td class="right">{{ $line['quantity'] }}</td>
                <td class="right">
                    {{ $line['currency'] === 'EUR' ? '€' : '$' }}
                    {{ number_format($line['unit_price'], 2, ',', '.') }}
                </td>
                <td class="right">{{ $line['tax_rate'] }}%</td>
                <td class="right">
                    {{ $line['currency'] === 'EUR' ? '€' : '$' }}
                    {{ number_format($line['tax_amount'], 2, ',', '.') }}
                </td>
                <td class="right">
                    {{ $line['currency'] === 'EUR' ? '€' : '$' }}
                    {{ number_format($line['total'], 2, ',', '.') }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-wrapper">
        <table>
            <tr>
                <td class="totals-spacer"></td>
                <td class="totals-box">
                    <table>
                        <tr class="row-highlight">
                            <td>Total IVA excl.</td>
                            <td>
                                {{ $currency === 'EUR' ? '€' : '$' }}
                                {{ number_format($totals['excl_tax'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @if($has_conversion)
                        <tr>
                            <td>Convertido excl. IVA</td>
                            <td>${{ number_format($totals['excl_tax'] * $exchange_rate['rate'], 2, ',', '.') }}</td>
                        </tr>
                        @endif

                        @foreach($totals['taxes'] as $tax)
                        <tr>
                            <td>IVA {{ $tax['rate'] }}%</td>
                            <td>
                                {{ $currency === 'EUR' ? '€' : '$' }}
                                {{ number_format($tax['tax']['amount'], 2, ',', '.') }}
                            </td>
                        </tr>
                        @endforeach

                        <tr class="row-highlight">
                            <td>Total IVA incl.</td>
                            <td>
                                {{ $currency === 'EUR' ? '€' : '$' }}
                                {{ number_format($totals['incl_tax'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @if($has_conversion)
                        <tr>
                            <td>Convertido IVA incl.</td>
                            <td>${{ number_format($totals['incl_tax'] * $exchange_rate['rate'], 2, ',', '.') }}</td>
                        </tr>
                        @endif

                        <tr class="row-total">
                            <td>Cantidad total</td>
                            <td>
                                {{ $currency === 'EUR' ? '€' : '$' }}
                                {{ number_format($totals['incl_tax'], 2, ',', '.') }}
                            </td>
                        </tr>

                        @if($has_conversion)
                        <tr class="row-total">
                            <td>Total convertido</td>
                            <td>${{ number_format($totals['incl_tax'] * $exchange_rate['rate'], 2, ',', '.') }}</td>
                        </tr>
                        @endif
                    </table>
                </td>
            </tr>
        </table>
    </div>

    <div class="tax-note">
        La operación no está sujeta al IVA por reglas de localización.
    </div>

    <div class="footer">
        <div class="ref">Utilice la referencia siguiente: {{ $invoice->invoice_number }}</div>
        SAVIOR TRADING, S.A. · RUC: 155736147-2-2023 · info@sefarvzla.com · www.sefaruniversal.com
    </div>

</div>
</body>
</html>

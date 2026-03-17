{{-- resources/views/tl/invoices/pdf.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 11px;
        color: #222;
        background: #fff;
    }

    /* ── SELLO PAGADO ── */
    .stamp-paid {
        position: fixed;
        top: 30px;
        right: 30px;
        border: 4px solid #2e7d32;
        color: #2e7d32;
        font-size: 22px;
        font-weight: bold;
        padding: 6px 18px;
        border-radius: 4px;
        opacity: 0.75;
        transform: rotate(-15deg);
        letter-spacing: 2px;
        z-index: 999;
    }

    /* ── CABECERA ── */
    .header {
        width: 100%;
        margin-bottom: 28px;
    }
    .header table {
        width: 100%;
    }
    .logo {
        width: 160px;
    }
    .company-info {
        text-align: right;
        font-size: 10px;
        color: #444;
        line-height: 1.6;
    }
    .company-info strong {
        font-size: 13px;
        color: #111;
        display: block;
        margin-bottom: 2px;
    }
    .company-tagline {
        font-style: italic;
        color: #888;
        font-size: 9px;
        margin-bottom: 6px;
    }

    /* ── TÍTULO FACTURA ── */
    .invoice-title {
        background-color: #1a3a5c;
        color: #fff;
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        letter-spacing: 3px;
        padding: 8px 0;
        margin-bottom: 20px;
    }

    /* ── BLOQUE CLIENTE + DATOS ── */
    .meta-block {
        width: 100%;
        margin-bottom: 24px;
    }
    .meta-block table {
        width: 100%;
    }
    .client-box {
        background: #f4f7fb;
        border-left: 4px solid #1a3a5c;
        padding: 10px 14px;
        width: 55%;
        vertical-align: top;
    }
    .client-box .label {
        font-size: 9px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 4px;
    }
    .client-box .name {
        font-size: 13px;
        font-weight: bold;
        color: #1a3a5c;
    }
    .client-box .vat {
        font-size: 10px;
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
    }
    .invoice-meta td {
        padding: 4px 6px;
        font-size: 10px;
    }
    .invoice-meta td:first-child {
        color: #888;
        text-align: left;
    }
    .invoice-meta td:last-child {
        font-weight: bold;
        color: #222;
        text-align: right;
    }
    .invoice-meta .num-factura td:last-child {
        font-size: 14px;
        color: #1a3a5c;
    }

    /* ── TABLA DE LÍNEAS ── */
    .lines-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 0;
    }
    .lines-table thead tr {
        background-color: #1a3a5c;
        color: #fff;
    }
    .lines-table thead th {
        padding: 7px 8px;
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        text-align: left;
    }
    .lines-table thead th.right { text-align: right; }

    .lines-table tbody tr {
        border-bottom: 1px solid #e8eef5;
    }
    .lines-table tbody tr:nth-child(even) {
        background: #f9fbfd;
    }
    .lines-table tbody td {
        padding: 8px 8px;
        vertical-align: top;
        font-size: 10px;
    }
    .lines-table tbody td.right { text-align: right; }

    .desc-main {
        font-weight: bold;
        color: #1a3a5c;
        margin-bottom: 3px;
    }
    .desc-extended {
        font-size: 9px;
        color: #666;
        line-height: 1.5;
        font-style: italic;
    }

    /* ── TOTALES ── */
    .totals-wrapper {
        width: 100%;
        margin-top: 0;
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
    }
    .totals-box tr {
        border-bottom: 1px solid #e8eef5;
    }
    .totals-box td {
        padding: 5px 8px;
        font-size: 10px;
    }
    .totals-box td:first-child { color: #666; }
    .totals-box td:last-child  { text-align: right; font-weight: bold; }

    .totals-box .row-highlight {
        background: #f4f7fb;
    }
    .totals-box .row-total {
        background: #1a3a5c;
        color: #fff !important;
    }
    .totals-box .row-total td {
        color: #fff !important;
        font-size: 12px;
        padding: 7px 8px;
    }

    /* ── NOTA IVA ── */
    .tax-note {
        margin-top: 20px;
        font-size: 9px;
        color: #888;
        font-style: italic;
        border-top: 1px solid #e0e0e0;
        padding-top: 8px;
    }

    /* ── PIE ── */
    .footer {
        margin-top: 30px;
        border-top: 2px solid #1a3a5c;
        padding-top: 10px;
        font-size: 9px;
        color: #888;
        text-align: center;
        line-height: 1.7;
    }
    .footer .ref {
        font-weight: bold;
        color: #1a3a5c;
        font-size: 10px;
    }

    /* ── HELPERS ── */
    .spacer { padding-right: 20px; }
</style>
</head>
<body>

{{-- SELLO PAGADO --}}
@if($paid)
    <div class="stamp-paid">PAGADO</div>
@endif

{{-- CABECERA: Logo + Empresa --}}
<div class="header">
    <table>
        <tr>
            <td style="vertical-align:middle; width:50%">
                <img src="{{ public_path('img/logo2.png') }}" class="logo" alt="Sefar Universal">
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

{{-- TÍTULO --}}
<div class="invoice-title">FACTURA</div>

{{-- CLIENTE + META --}}
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
                        <td>{{ $payment_days }} días después de<br>la fecha de facturación</td>
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

{{-- TABLA DE LÍNEAS --}}
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

{{-- TOTALES --}}
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
                        <td>
                            ${{ number_format($totals['excl_tax'] * $exchange_rate['rate'], 2, ',', '.') }}
                        </td>
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
                        <td>
                            ${{ number_format($totals['incl_tax'] * $exchange_rate['rate'], 2, ',', '.') }}
                        </td>
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
                        <td>
                            ${{ number_format($totals['incl_tax'] * $exchange_rate['rate'], 2, ',', '.') }}
                        </td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>
</div>

{{-- NOTA IVA --}}
<div class="tax-note">
    La operación no está sujeta al IVA por reglas de localización.
</div>

{{-- PIE --}}
<div class="footer">
    <div class="ref">Utilice la referencia siguiente: {{ $invoice->invoice_number }}</div>
    SAVIOR TRADING, S.A. &nbsp;·&nbsp; RUC: 155736147-2-2023 &nbsp;·&nbsp;
    info@sefarvzla.com &nbsp;·&nbsp; www.sefaruniversal.com
</div>

</body>
</html>

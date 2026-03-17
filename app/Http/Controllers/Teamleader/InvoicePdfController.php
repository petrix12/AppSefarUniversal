<?php

namespace App\Http\Controllers\Teamleader;

use App\Http\Controllers\Controller;
use App\Models\TlInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(string $id): Response
    {
        $invoice = TlInvoice::findOrFail($id);

        // Solo facturas pagadas/completadas
        abort_if($invoice->status !== 'matched', 403, 'Factura no disponible para PDF.');

        $data = $this->prepareData($invoice);

        $pdf = Pdf::loadView('tl.invoices.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'    => 'DejaVu Sans',
                'isRemoteEnabled'=> true,
                'isHtml5ParserEnabled' => true,
                'dpi'            => 150,
            ]);

        $filename = 'Factura_' . str_replace(' ', '_', $invoice->invoice_number) . '.pdf';

        return $pdf->download($filename);
    }

    private function prepareData(TlInvoice $invoice): array
    {
        $raw = $invoice->raw_data;

        // Datos del cliente
        $invoicee  = $raw['invoicee'] ?? [];
        $customer  = [
            'name'       => $invoicee['name']       ?? $invoice->customer_name,
            'vat_number' => $invoicee['vat_number'] ?? null,
            'email'      => $invoicee['email']       ?? null,
        ];

        // Líneas de factura
        $lines = collect($invoice->invoice_lines)->map(function (array $line) use ($invoice) {
            $currency   = $invoice->currency;
            $unitAmount = $line['unit_price']['amount'] ?? 0;
            $total      = $line['total']['tax_exclusive']['amount'] ?? 0;
            $qty        = $line['quantity'] ?? 1;

            // Calcular IVA
            $taxRate    = 0; // siempre 0% según los datos
            $taxAmount  = round($total * $taxRate / 100, 2);

            return [
                'description'          => $line['description'] ?? '',
                'extended_description' => $line['extended_description'] ?? null,
                'quantity'             => $qty,
                'unit_price'           => $unitAmount,
                'tax_rate'             => $taxRate,
                'tax_amount'           => $taxAmount,
                'total'                => $total,
                'currency'             => $currency,
            ];
        });

        // Totales desde raw_data
        $totals = $raw['total'] ?? [];
        $exclTax = $totals['tax_exclusive']['amount']    ?? $invoice->total_price_excl_tax;
        $inclTax = $totals['tax_inclusive']['amount']    ?? $invoice->total_price_incl_tax;
        $taxList = $totals['taxes']                      ?? [];

        // Tipo de cambio (si aplica conversión EUR→USD)
        $exchangeRate = $raw['currency_exchange_rate'] ?? null;
        $hasConversion = $exchangeRate
            && isset($exchangeRate['rate'])
            && $exchangeRate['from'] !== $exchangeRate['to']
            && $exchangeRate['rate'] != 1;

        // Condiciones de pago
        $paymentTerm = $raw['payment_term'] ?? [];
        $paymentDays = $paymentTerm['days'] ?? 14;

        return [
            'invoice'       => $invoice,
            'customer'      => $customer,
            'lines'         => $lines,
            'totals'        => [
                'excl_tax'      => $exclTax,
                'incl_tax'      => $inclTax,
                'taxes'         => $taxList,
            ],
            'currency'      => $invoice->currency,
            'exchange_rate' => $exchangeRate,
            'has_conversion'=> $hasConversion,
            'payment_days'  => $paymentDays,
            'paid'          => $invoice->status === 'matched',
        ];
    }
}

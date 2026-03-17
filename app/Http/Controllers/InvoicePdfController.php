<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        $data = $this->prepareData($invoice);

        $logoPath = public_path('img/logo2.png');
        $data['logoBase64'] = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;

        $pdf = Pdf::loadView('invoices.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'          => 'DejaVu Sans',
                'isRemoteEnabled'      => true,
                'isHtml5ParserEnabled' => true,
                'dpi'                  => 150,
            ]);

        $safeNumber = preg_replace('/[\\\\\\/:"*?<>|]+/', '-', $invoice->invoice_number ?? 'factura');
        $safeNumber = preg_replace('/\s+/', '_', $safeNumber);
        $safeNumber = trim($safeNumber, '-_');

        return $pdf->download('Factura_' . ($safeNumber ?: 'factura') . '.pdf');
    }

    private function prepareData(Invoice $invoice): array
    {
        $invoice->load('lines');

        $customer = [
            'name'       => $invoice->customer_name,
            'vat_number' => $invoice->customer_vat,
            'email'      => $invoice->customer_email,
        ];

        $lines = $invoice->lines->map(function ($line) use ($invoice) {
            $taxAmount = round($line->total * ($line->tax_rate / 100), 2);

            return [
                'description'          => $line->description,
                'extended_description' => null,
                'quantity'             => $line->quantity,
                'unit_price'           => $line->unit_price,
                'tax_rate'             => $line->tax_rate,
                'tax_amount'           => $taxAmount,
                'total'                => $line->total,
                'currency'             => $invoice->currency,
            ];
        });

        // Agrupar impuestos por tasa
        $taxes = $invoice->lines
            ->groupBy('tax_rate')
            ->map(fn($group, $rate) => [
                'rate' => $rate,
                'tax'  => [
                    'amount' => round(
                        $group->sum(fn($l) => $l->total * ($l->tax_rate / 100)),
                        2
                    ),
                ],
            ])
            ->values();

        // Días de pago desde fecha factura a fecha vencimiento
        $paymentDays = $invoice->expiry_date && $invoice->invoice_date
            ? $invoice->invoice_date->diffInDays($invoice->expiry_date)
            : 14;

        return [
            'invoice'        => $invoice,
            'customer'       => $customer,
            'lines'          => $lines,
            'totals'         => [
                'excl_tax' => $invoice->total_excl_tax,
                'incl_tax' => $invoice->total_incl_tax,
                'taxes'    => $taxes,
            ],
            'currency'       => $invoice->currency,
            'exchange_rate'  => null,
            'has_conversion' => false,
            'payment_days'   => $paymentDays,
            'paid'           => $invoice->status === 'paid',
        ];
    }
}

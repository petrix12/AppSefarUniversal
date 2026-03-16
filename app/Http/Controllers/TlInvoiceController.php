<?php
// app/Http/Controllers/TlInvoiceController.php

namespace App\Http\Controllers;

use App\Models\TlInvoice;
use App\Models\TlSyncLog;
use Illuminate\Http\Request;

class TlInvoiceController extends Controller
{
    public function table(Request $request)
    {
        $query = TlInvoice::query();

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->get('from')) {
            $query->whereDate('invoice_date', '>=', $from);
        }

        if ($to = $request->get('to')) {
            $query->whereDate('invoice_date', '<=', $to);
        }

        $invoices = $query->orderByDesc('invoice_date')->paginate(25)->withQueryString();

        $lastSync = TlSyncLog::where('entity', 'invoices')->latest()->first();

        // Totales para tarjetas resumen
        $totals = [
            'draft'       => TlInvoice::where('status', 'draft')->count(),
            'outstanding' => TlInvoice::where('status', 'outstanding')->count(),
            'matched'     => TlInvoice::where('status', 'matched')->count(),
            'late'        => TlInvoice::where('status', 'late')->count(),
        ];

        $totalAmount = TlInvoice::sum('total_price_incl_tax');

        return view('tl.invoices.index', compact('invoices', 'lastSync', 'totals', 'totalAmount'));
    }

    public function show(string $id)
    {
        $invoice = TlInvoice::findOrFail($id);

        $contact = $invoice->customer_type === 'contact'
            ? $invoice->contact
            : null;

        $company = $invoice->customer_type === 'company'
            ? $invoice->company
            : null;

        $creditNotes = $invoice->creditNotes()->get();
        $project     = $invoice->project;

        return view('tl.invoices.show', compact(
            'invoice', 'contact', 'company', 'creditNotes', 'project'
        ));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with('user')
            ->orderByDesc('invoice_date')
            ->paginate(20);

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        $nextNumber = Invoice::nextNumber();
        return view('invoices.create', compact('nextNumber'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'    => 'required|string|max:255',
            'customer_email'   => 'nullable|email',
            'customer_vat'     => 'nullable|string|max:100',
            'customer_address' => 'nullable|string|max:500',
            'customer_country' => 'nullable|string|max:100',
            'invoice_date'     => 'required|date',
            'expiry_date'      => 'nullable|date',
            'currency'         => 'required|in:EUR,USD',
            'status'           => 'required|in:draft,sent,paid',
            'notes'            => 'nullable|string',
            'lines'            => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity'    => 'required|numeric|min:0',
            'lines.*.unit_price'  => 'required|numeric|min:0',
            'lines.*.tax_rate'    => 'required|numeric|min:0|max:100',
        ]);

        $invoice = Invoice::create([
            'invoice_number'   => Invoice::nextNumber(),
            'user_id'          => auth()->id(),
            'customer_name'    => $validated['customer_name'],
            'customer_email'   => $validated['customer_email'],
            'customer_vat'     => $validated['customer_vat'],
            'customer_address' => $validated['customer_address'],
            'customer_country' => $validated['customer_country'],
            'invoice_date'     => $validated['invoice_date'],
            'expiry_date'      => $validated['expiry_date'],
            'currency'         => $validated['currency'],
            'status'           => $validated['status'],
            'notes'            => $validated['notes'],
        ]);

        foreach ($validated['lines'] as $i => $line) {
            $total = round($line['quantity'] * $line['unit_price'], 2);
            $invoice->lines()->create([
                'description' => $line['description'],
                'quantity'    => $line['quantity'],
                'unit_price'  => $line['unit_price'],
                'tax_rate'    => $line['tax_rate'],
                'total'       => $total,
                'sort_order'  => $i,
            ]);
        }

        $invoice->recalculate();

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Factura {$invoice->invoice_number} creada.");
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('lines', 'user');
        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load('lines');
        return view('invoices.edit', compact('invoice'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $validated = $request->validate([
            'customer_name'    => 'required|string|max:255',
            'customer_email'   => 'nullable|email',
            'customer_vat'     => 'nullable|string|max:100',
            'customer_address' => 'nullable|string|max:500',
            'customer_country' => 'nullable|string|max:100',
            'invoice_date'     => 'required|date',
            'expiry_date'      => 'nullable|date',
            'currency'         => 'required|in:EUR,USD',
            'status'           => 'required|in:draft,sent,paid',
            'notes'            => 'nullable|string',
            'lines'            => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity'    => 'required|numeric|min:0',
            'lines.*.unit_price'  => 'required|numeric|min:0',
            'lines.*.tax_rate'    => 'required|numeric|min:0|max:100',
        ]);

        $invoice->update([
            'customer_name'    => $validated['customer_name'],
            'customer_email'   => $validated['customer_email'],
            'customer_vat'     => $validated['customer_vat'],
            'customer_address' => $validated['customer_address'],
            'customer_country' => $validated['customer_country'],
            'invoice_date'     => $validated['invoice_date'],
            'expiry_date'      => $validated['expiry_date'],
            'currency'         => $validated['currency'],
            'status'           => $validated['status'],
            'notes'            => $validated['notes'],
        ]);

        $invoice->lines()->delete();

        foreach ($validated['lines'] as $i => $line) {
            $total = round($line['quantity'] * $line['unit_price'], 2);
            $invoice->lines()->create([
                'description' => $line['description'],
                'quantity'    => $line['quantity'],
                'unit_price'  => $line['unit_price'],
                'tax_rate'    => $line['tax_rate'],
                'total'       => $total,
                'sort_order'  => $i,
            ]);
        }

        $invoice->recalculate();

        return redirect()->route('invoices.show', $invoice)
            ->with('success', "Factura {$invoice->invoice_number} actualizada.");
    }

    public function destroy(Invoice $invoice)
    {
        $number = $invoice->invoice_number;
        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', "Factura $number eliminada.");
    }
}

<?php

namespace App\Http\Controllers\Teamleader;

use App\Http\Controllers\Controller;
use App\Models\TlCustomFieldDefinition;
use App\Models\TlDeal;

class TlDealController extends Controller
{
    public function show(string $id)
    {
        $deal = TlDeal::findOrFail($id);

        $contact = $deal->customer_type === 'contact' ? $deal->contact : null;
        $company = $deal->customer_type === 'company' ? $deal->company : null;
        $documents = $deal->documents()->orderByDesc('tl_created_at')->get();
        $invoices = $deal->invoices()->orderByDesc('invoice_date')->get();
        $definitions = TlCustomFieldDefinition::all()->keyBy('id');

        return view('teamleader.deals.show', compact(
            'deal',
            'contact',
            'company',
            'documents',
            'invoices',
            'definitions'
        ));
    }
}
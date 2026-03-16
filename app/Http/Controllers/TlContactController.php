<?php
// app/Http/Controllers/TlContactController.php

namespace App\Http\Controllers;

use App\Models\TlContact;
use App\Models\TlSyncLog;
use Illuminate\Http\Request;

class TlContactController extends Controller
{
    public function table(Request $request)
    {
        $query = TlContact::query();

        // Búsqueda
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('email',      'like', "%{$search}%")
                  ->orWhere('passport',   'like', "%{$search}%")
                  ->orWhere('phone',      'like', "%{$search}%");
            });
        }

        // Filtro por status
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        // Filtro por tag
        if ($tag = $request->get('tag')) {
            $query->whereJsonContains('tags', $tag);
        }

        $contacts = $query->orderBy('first_name')->paginate(25)->withQueryString();

        // Último sync log
        $lastSync = TlSyncLog::where('entity', 'contacts')
            ->latest()
            ->first();

        // Tags únicos para el filtro
        $allTags = TlContact::whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        return view('teamleader.contacts.index', compact('contacts', 'lastSync', 'allTags'));
    }

    public function show(string $id)
    {
        $contact = TlContact::findOrFail($id);

        $documents = $contact->documents()->orderBy('tl_created_at', 'desc')->get();
        $deals     = $contact->deals()->orderBy('tl_created_at', 'desc')->get();
        $projects  = $contact->projects()->orderBy('tl_created_at', 'desc')->get();
        $invoices  = $contact->invoices()->orderBy('invoice_date', 'desc')->get();

        return view('teamleader.contacts.show', compact('contact', 'documents', 'deals', 'projects', 'invoices'));
    }
}

<?php
// app/Http/Controllers/TlContactController.php

namespace App\Http\Controllers;

use App\Jobs\Teamleader\SyncDocumentsJob;
use App\Models\TlContact;
use App\Models\TlDocument;
use App\Models\TlSyncLog;
use App\Services\TeamleaderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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

    public function importDocuments(string $id, TeamleaderService $teamleader): RedirectResponse
    {
        $contact = TlContact::findOrFail($id);
        $before = $this->documentStats($contact->id);

        $log = TlSyncLog::start('documents');
        $log->update(['total' => 1]);

        try {
            (new SyncDocumentsJob(
                'contact',
                $contact->id,
                1,
                $log->id,
                100,
                true,
                true
            ))->handle($teamleader);
        } catch (\Throwable $e) {
            $log->fail($e->getMessage());

            Log::channel('teamleader')->error('[TL Docs] Error importando archivos desde vista de contacto', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'No se pudieron importar los archivos de Teamleader: ' . e($e->getMessage()));
        }

        $log->refresh();
        $after = $this->documentStats($contact->id);

        if ($log->status === 'failed') {
            return back()->with('error', 'Teamleader detuvo la importacion: ' . e($log->error_message ?: 'revisa la autenticacion o el log de Teamleader.'));
        }

        $newDocuments = max(0, $after['total'] - $before['total']);
        $newDownloaded = max(0, $after['downloaded'] - $before['downloaded']);

        return back()->with(
            'status',
            "Importacion ejecutada. Documentos nuevos: {$newDocuments}. Descargados nuevos: {$newDownloaded}. Total descargados: {$after['downloaded']}."
        );
    }

    private function documentStats(string $contactId): array
    {
        $query = TlDocument::query()
            ->where('entity_type', 'contact')
            ->where('entity_id', $contactId);

        return [
            'total' => (clone $query)->count(),
            'downloaded' => (clone $query)->where('downloaded', true)->count(),
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\TlCreditNote;
use App\Models\TlInvoice;
use App\Services\ApplicationFileResponse;
use App\Services\ClientRestrictionService;
use App\Services\ClientRestrictionTargets;
use App\Services\ClientTreeAccess;
use Illuminate\Http\Request;

class ClientDocumentController extends Controller
{
    public function file(Request $request, File $file, ApplicationFileResponse $files)
    {
        $actor = $request->user();
        abort_unless($actor, 401);
        if (app(ClientTreeAccess::class)->isClientScoped($actor)) {
            if ((int) $file->user_id !== (int) $actor->id) {
                app(ClientTreeAccess::class)->authorize($actor, $file->IDCliente);
            }
        } else {
            abort_unless($actor->can('tree-files.view') || $actor->can('crud.files.index') || $actor->can('administrar.documentos'), 403);
        }
        app(ClientRestrictionTargets::class)->assertFileDeliverable($file);
        $path = preg_replace('~/+~', '/', trim($file->location, '/').'/'.$file->file);

        return $files->stream('s3', $path, $file->file, $request->boolean('download'));
    }

    public function requested(Request $request, DocumentRequest $documentRequest, ApplicationFileResponse $files)
    {
        $actor = $request->user();
        abort_unless($actor, 401);
        if (app(ClientTreeAccess::class)->isClientScoped($actor)) {
            abort_unless((int) $documentRequest->user_id === (int) $actor->id, 403);
        } else {
            abort_unless($actor->can('administrar.documentos') || $actor->can('crud.users.index'), 403);
        }
        app(ClientRestrictionService::class)->assertDeliverable($documentRequest->client()->firstOrFail());
        abort_if(blank($documentRequest->file_path), 404);

        return $files->stream('s3', $documentRequest->file_path, basename($documentRequest->file_path), $request->boolean('download'));
    }

    public function invoice(Request $request, string $id, ApplicationFileResponse $files)
    {
        $invoice = TlInvoice::findOrFail($id);
        foreach (app(ClientRestrictionTargets::class)->teamleader('invoice', $id) as $client) {
            app(ClientRestrictionService::class)->assertDeliverable($client);
        }
        abort_if(blank($invoice->pdf_s3_path), 404);

        return $files->stream($invoice->pdf_s3_disk ?: 's3', $invoice->pdf_s3_path, 'factura-'.$invoice->id.'.pdf', $request->boolean('download'));
    }

    public function creditNote(Request $request, string $id, ApplicationFileResponse $files)
    {
        $note = TlCreditNote::findOrFail($id);
        if ($note->customer_type === 'contact') {
            foreach (app(ClientRestrictionTargets::class)->teamleader('contact', $note->customer_id) as $client) {
                app(ClientRestrictionService::class)->assertDeliverable($client);
            }
        }
        abort_if(blank($note->pdf_s3_path), 404);

        return $files->stream($note->pdf_s3_disk ?: 's3', $note->pdf_s3_path, 'nota-credito-'.$note->id.'.pdf', $request->boolean('download'));
    }
}

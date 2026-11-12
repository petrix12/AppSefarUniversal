<?php

namespace App\Http\Controllers\Teamleader;

use App\Http\Controllers\Controller;
use App\Models\TlDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(string $id): StreamedResponse
    {
        $document = TlDocument::findOrFail($id);
        $document->assertDeliverable();

        abort_if(! $document->downloaded || blank($document->s3_path), 404, 'Documento no descargado.');

        return app(\App\Services\ApplicationFileResponse::class)->stream(
            $document->s3_disk ?: 's3', $document->s3_path, $document->name ?: basename($document->s3_path), true
        );
    }
}

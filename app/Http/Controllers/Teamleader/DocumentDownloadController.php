<?php

namespace App\Http\Controllers\Teamleader;

use App\Http\Controllers\Controller;
use App\Models\TlDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class DocumentDownloadController extends Controller
{
    public function __invoke(string $id): RedirectResponse
    {
        $document = TlDocument::findOrFail($id);

        abort_if(! $document->downloaded || blank($document->s3_path), 404, 'Documento no descargado.');

        $disk = Storage::disk($document->s3_disk ?: 's3');

        abort_unless($disk->exists($document->s3_path), 404, 'El archivo no existe en S3.');

        try {
            $url = $disk->temporaryUrl($document->s3_path, now()->addMinutes(15));
        } catch (\Throwable) {
            $url = $disk->url($document->s3_path);
        }

        return redirect()->away($url);
    }
}

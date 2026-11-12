<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplicationFileResponse
{
    /** Read-only access to storage. The browser receives bytes, never an S3 URL. */
    public function stream(string $disk, string $path, string $filename, bool $download = false): StreamedResponse
    {
        $storage = Storage::disk($disk);
        try {
            $stream = $storage->readStream($path);
            abort_unless(is_resource($stream), 404, 'Archivo no disponible.');
            $mime = $storage->mimeType($path) ?: 'application/octet-stream';
        } catch (\League\Flysystem\FilesystemException $exception) {
            if (isset($stream) && is_resource($stream)) {
                fclose($stream);
            }
            abort(404, 'Archivo no disponible.');
        }
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = str_replace(["\r", "\n", "\0"], '', $filename) ?: 'documento';
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', Str::ascii($filename));
        $fallback = str_replace(['%', '/', '\\'], '_', $fallback);
        $inline = ! $download && in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'text/plain'], true);

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($inline ? 'inline' : 'attachment', $filename, $fallback),
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class DocumentController extends Controller
{
    // LISTADO para usuarios (Drive)
    public function library(Request $request)
    {
        $user = auth()->user();

        $q = Document::query();

        // Proveedor ve proveedores/todos
        if ($user->hasRole('Coord. Ventas')) {
            $q->whereIn('visibility', ['coordventas', 'todos']);
        }

        if ($search = $request->get('q')) {
            $q->where('title', 'like', "%{$search}%");
        }

        if ($cat = $request->get('category')) {
            $q->where('category', $cat);
        }

        $docs = $q->orderByDesc('id')->paginate(20);

        $categories = Document::query()
            ->select('category')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderBy('category')
            ->pluck('category');

        return view('docs.index', compact('docs', 'categories'));
    }

    // PANEL ADMIN (opcional)
    public function admin(Request $request)
    {
        $q = Document::query();

        if ($search = $request->get('q')) {
            $q->where('title', 'like', "%{$search}%");
        }

        if ($cat = $request->get('category')) {
            $q->where('category', $cat);
        }

        $docs = $q->orderByDesc('id')->paginate(20);

        $categories = Document::query()
            ->select('category')
            ->whereNotNull('category')
            ->groupBy('category')
            ->orderBy('category')
            ->pluck('category');

        return view('docs.admin', compact('docs', 'categories'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => ['required','string','max:255'],
                'description' => ['nullable','string'],
                'file' => ['required','file'], // 50MB (en KB)
                'category' => ['nullable','string','max:80'],
                'visibility' => ['required','in:proveedores,todos,admins'],
            ]);
        } catch (ValidationException $e) {
            Log::warning('Document upload validation failed', [
                'errors' => $e->errors(),
                'has_file' => $request->hasFile('file'),
                'file_error' => $request->file('file')?->getError(),
                'file_error_message' => $request->file('file')?->getErrorMessage(),
                'size' => $request->file('file')?->getSize(),
                'mime' => $request->file('file')?->getMimeType(),
                'client_mime' => $request->file('file')?->getClientMimeType(),
                'original' => $request->file('file')?->getClientOriginalName(),
                'content_length' => $request->server('CONTENT_LENGTH'),
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
            ]);

            // Para verlo “en pantalla” rápido (temporal):
            dd([
                'message' => 'VALIDATION_FAILED',
                'errors' => $e->errors(),
                'file_error' => $request->file('file')?->getError(),
                'file_error_message' => $request->file('file')?->getErrorMessage(),
            ], 422);
        }

        $file = $request->file('file');

        $category = $request->input('category', 'general');
        $categorySlug = Str::slug($category);

        $path = "documents/{$categorySlug}/".date('Y')."/".date('m');

        $storedPath = $file->store($path, 's3');

        Document::create([
            'title' => $request->title,
            'description' => $request->description,
            'category' => $categorySlug,

            'disk' => 's3',
            'path' => $storedPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),

            'visibility' => $request->visibility,
            'uploaded_by' => auth()->id(),
        ]);

        return back()->with('status', 'Documento subido correctamente.');
    }

    public function download($id)
    {
        $doc = Document::findOrFail($id);
        $user = auth()->user();

        // Proveedor: solo proveedores/todos
        if ($user->hasRole('Proveedor') && !in_array($doc->visibility, ['proveedores','todos'])) {
            abort(403);
        }

        // Admin: ok (además ya pasó can:docs.view)
        $url = Storage::disk($doc->disk)->temporaryUrl(
            $doc->path,
            now()->addMinutes(10),
            [
                'ResponseContentDisposition' => 'inline; filename="'.$doc->original_name.'"'
            ]
        );

        return redirect($url);
    }

    public function destroy($id)
    {
        $doc = Document::findOrFail($id);

        // borra archivo en S3 (si existe)
        try {
            Storage::disk($doc->disk)->delete($doc->path);
        } catch (\Throwable $e) {
            // si quieres log: \Log::warning($e->getMessage());
        }

        $doc->delete();

        return back()->with('status', 'Documento eliminado.');
    }
}

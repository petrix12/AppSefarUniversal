<?php

namespace App\Http\Controllers;

use App\Models\Agcliente;
use App\Models\DocumentRequest;
use App\Models\File;
use App\Models\User;
use App\Services\GenealogyDocumentService;
use App\Services\GenealogyDocumentUploadNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentRequestController extends Controller
{
    public function __construct(
        private GenealogyDocumentService $documents,
        private GenealogyDocumentUploadNotifier $uploadNotifier,
    )
    {
    }

    /** Crear una solicitud documental genealógica para una persona o unión. */
    public function store(Request $request, User $user)
    {
        $this->requireInternalUser();

        $validated = $request->validate([
            'document_kind' => 'required|in:' . implode(',', array_keys(GenealogyDocumentService::kinds())),
            'person_id' => 'required|integer',
            'spouse_id' => 'nullable|integer|different:person_id',
        ]);

        $person = $this->personForClient($user, (int) $validated['person_id']);
        abort_unless(
            array_key_exists($validated['document_kind'], GenealogyDocumentService::allowedKindsForPerson($person)),
            422,
            'El acta de defunción solo se solicita a antepasados.'
        );
        $union = null;

        if ($validated['document_kind'] === GenealogyDocumentService::KIND_MARRIAGE) {
            if (empty($validated['spouse_id'])) {
                return response()->json(['message' => 'Selecciona el otro cónyuge para solicitar un acta de matrimonio.'], 422);
            }

            $spouse = $this->personForClient($user, (int) $validated['spouse_id']);
            $union = $this->documents->findOrCreateUnion($person, $spouse);
        }

        $documentRequest = DocumentRequest::create([
            'user_id' => $user->id,
            'requested_by' => auth()->id(),
            'document_name' => GenealogyDocumentService::label($validated['document_kind']),
            'document_type' => 'genealogico',
            'document_kind' => $validated['document_kind'],
            'person_id' => $person->id,
            'genealogy_union_id' => $union?->id,
            'status' => 'en_espera_cliente',
            'no_document_button_at' => now()->addMonth(),
        ]);

        return response()->json($documentRequest->load(['person', 'genealogyUnion']));
    }

    public function update(Request $request, DocumentRequest $documentRequest)
    {
        $this->requireInternalUser();

        if (! in_array($documentRequest->status, ['en_espera_cliente', 'resuelto', 'rechazada'], true)) {
            return response()->json(['message' => 'No se puede editar una solicitud en este estado.'], 422);
        }

        $validated = $request->validate([
            'document_kind' => 'required|in:' . implode(',', array_keys(GenealogyDocumentService::kinds())),
        ]);

        if ($documentRequest->person) {
            abort_unless(
                array_key_exists($validated['document_kind'], GenealogyDocumentService::allowedKindsForPerson($documentRequest->person)),
                422,
                'El acta de defunción solo se solicita a antepasados.'
            );
        }

        $documentRequest->update([
            'document_kind' => $validated['document_kind'],
            'document_name' => GenealogyDocumentService::label($validated['document_kind']),
            'document_type' => 'genealogico',
        ]);

        return response()->json(['success' => true, 'data' => $documentRequest->fresh()]);
    }

    public function destroy(DocumentRequest $documentRequest)
    {
        $this->requireInternalUser();
        $this->deleteSubmittedFile($documentRequest);
        $documentRequest->delete();

        return response()->json(['success' => true, 'message' => 'Solicitud eliminada correctamente']);
    }

    public function approve(DocumentRequest $documentRequest)
    {
        $this->requireInternalUser();

        if (! in_array($documentRequest->status, ['en_espera_cliente', 'resuelto'], true)) {
            return response()->json(['message' => 'No se puede aprobar una solicitud en este estado.'], 422);
        }

        $file = $this->fileForRequest($documentRequest);
        if ($file) {
            $file->update([
                'document_kind' => $documentRequest->document_kind,
                'client_visible' => true,
            ]);
            $this->associateRequestFile($documentRequest, $file);
        }

        $documentRequest->update(['status' => 'aprobada', 'status_changed_at' => now()]);

        return response()->json($documentRequest->fresh());
    }

    public function reject(DocumentRequest $documentRequest)
    {
        $this->requireInternalUser();

        if (! in_array($documentRequest->status, ['en_espera_cliente', 'resuelto'], true)) {
            return response()->json(['message' => 'No se puede rechazar una solicitud en este estado.'], 422);
        }

        $this->deleteSubmittedFile($documentRequest);
        $documentRequest->update([
            'status' => 'rechazada',
            'file_path' => null,
            'status_changed_at' => now(),
        ]);

        return response()->json($documentRequest->fresh());
    }

    /** El cliente sube un PDF o imagen a una solicitud que le pertenece. */
    public function upload(Request $request, DocumentRequest $documentRequest)
    {
        $this->requireRequestOwner($documentRequest);

        if (! in_array($documentRequest->status, ['en_espera_cliente', 'rechazada'], true)) {
            return response()->json(['message' => 'No se puede subir un archivo en el estado actual.'], 422);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
        ]);

        $this->deleteSubmittedFile($documentRequest);

        $uploaded = $request->file('file');
        $passport = (string) auth()->user()->passport;
        $extension = strtolower($uploaded->getClientOriginalExtension());
        $name = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($uploaded->getClientOriginalName(), PATHINFO_FILENAME));
        $fileName = $name . '_' . now()->format('Ymd_His') . '.' . $extension;
        $location = 'public/doc/P' . $passport . '/solicitudes';
        $path = $uploaded->storeAs($location, $fileName, 's3');

        $file = File::create([
            'file' => $fileName,
            'location' => $location,
            'tipo' => GenealogyDocumentService::label($documentRequest->document_kind),
            'IDCliente' => $passport,
            // The document is visible under its intended person immediately;
            // its request status still keeps the internal review pending.
            'IDPersona' => 0,
            'IDPersonaNew' => null,
            'user_id' => auth()->id(),
            'source' => 'solicitud_cliente',
            'client_visible' => true,
            'document_kind' => $documentRequest->document_kind,
            'mime_type' => $uploaded->getMimeType(),
            'size_bytes' => $uploaded->getSize(),
            'document_request_id' => $documentRequest->id,
        ]);
        $this->associateRequestFile($documentRequest, $file);

        $documentRequest->update([
            'file_path' => $path,
            'status' => 'resuelto',
            'status_changed_at' => now(),
        ]);
        $this->uploadNotifier->notify(auth()->user(), $file, $documentRequest->person);

        return response()->json([
            'request' => $documentRequest->fresh(),
            'file' => $this->documents->present($file),
        ]);
    }

    /**
     * Lets a customer submit a required tree document without waiting for an
     * internal request. It still enters the normal internal-review workflow.
     */
    public function selfSubmit(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && $user->hasRole('Cliente'), 403);

        $validated = $request->validate([
            'person_id' => 'required|integer',
            'document_kind' => 'required|in:' . implode(',', array_keys(GenealogyDocumentService::kinds())),
            'spouse_id' => 'nullable|integer|different:person_id',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,gif|max:10240',
            'file_id' => 'nullable|integer',
        ]);
        abort_unless($request->hasFile('file') || ! empty($validated['file_id']), 422, 'Selecciona un archivo para continuar.');

        $person = $this->personForClient($user, (int) $validated['person_id']);
        abort_unless(
            array_key_exists($validated['document_kind'], GenealogyDocumentService::allowedKindsForPerson($person)),
            422,
            'El acta de defunción solo aplica a antepasados.'
        );

        $union = null;
        if ($validated['document_kind'] === GenealogyDocumentService::KIND_MARRIAGE) {
            abort_unless(! empty($validated['spouse_id']), 422, 'Selecciona el otro cónyuge para asociar el acta de matrimonio.');
            $union = $this->documents->findOrCreateUnion(
                $person,
                $this->personForClient($user, (int) $validated['spouse_id'])
            );
        }

        $documentRequest = DocumentRequest::create([
            'user_id' => $user->id,
            // There is no internal requester for a self-submission. The client
            // is stored here only to satisfy the legacy non-null audit column.
            'requested_by' => $user->id,
            'document_name' => GenealogyDocumentService::label($validated['document_kind']),
            'document_type' => 'genealogico',
            'document_kind' => $validated['document_kind'],
            'person_id' => $person->id,
            'genealogy_union_id' => $union?->id,
            'status' => 'en_espera_cliente',
        ]);

        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $passport = (string) $user->passport;
            $extension = strtolower($uploaded->getClientOriginalExtension());
            $name = preg_replace('/[^A-Za-z0-9_-]/', '_', pathinfo($uploaded->getClientOriginalName(), PATHINFO_FILENAME));
            $fileName = $name . '_' . now()->format('Ymd_His') . '.' . $extension;
            $location = 'public/doc/P' . $passport . '/solicitudes';
            $path = $uploaded->storeAs($location, $fileName, 's3');

            $file = File::create([
                'file' => $fileName,
                'location' => $location,
                'tipo' => GenealogyDocumentService::label($documentRequest->document_kind),
                'IDCliente' => $passport,
                'IDPersona' => 0,
                'IDPersonaNew' => null,
                'user_id' => $user->id,
                'source' => 'solicitud_cliente',
                'client_visible' => true,
                'document_kind' => $documentRequest->document_kind,
                'mime_type' => $uploaded->getMimeType(),
                'size_bytes' => $uploaded->getSize(),
                'document_request_id' => $documentRequest->id,
            ]);
        } else {
            $file = File::findOrFail($validated['file_id']);
            abort_unless($this->documents->canReuseForRequest($user, $file), 403, 'Ese archivo no puede asociarse a este documento.');
            $file->update([
                'document_kind' => $documentRequest->document_kind,
                'tipo' => GenealogyDocumentService::label($documentRequest->document_kind),
                'client_visible' => true,
                'document_request_id' => $documentRequest->id,
            ]);
            $path = trim((string) $file->location, '/') . '/' . ltrim((string) $file->file, '/');
        }

        $this->associateRequestFile($documentRequest, $file);

        $documentRequest->update([
            'file_path' => $path,
            'status' => 'resuelto',
            'status_changed_at' => now(),
        ]);
        if ($request->hasFile('file')) {
            $this->uploadNotifier->notify($user, $file, $person);
        }

        return response()->json([
            'request' => $documentRequest->fresh(),
            'file' => $this->documents->present($file),
        ]);
    }

    /** Reutiliza un documento que el cliente ya había cargado en la aplicación. */
    public function associateExisting(Request $request, DocumentRequest $documentRequest)
    {
        $this->requireRequestOwner($documentRequest);

        if (! in_array($documentRequest->status, ['en_espera_cliente', 'rechazada'], true)) {
            return response()->json(['message' => 'No se puede asociar un archivo en el estado actual.'], 422);
        }

        $validated = $request->validate(['file_id' => 'required|integer']);
        $file = File::findOrFail($validated['file_id']);

        abort_unless($this->documents->canReuseForRequest(auth()->user(), $file), 403, 'Ese archivo no puede asociarse a esta solicitud.');

        $file->update([
            'document_kind' => $documentRequest->document_kind,
            'tipo' => GenealogyDocumentService::label($documentRequest->document_kind),
            'client_visible' => true,
            'document_request_id' => $documentRequest->id,
        ]);
        $this->associateRequestFile($documentRequest, $file);
        $documentRequest->update([
            'file_path' => trim((string) $file->location, '/') . '/' . ltrim((string) $file->file, '/'),
            'status' => 'resuelto',
            'status_changed_at' => now(),
        ]);

        return response()->json(['request' => $documentRequest->fresh(), 'file' => $this->documents->present($file)]);
    }

    public function noDocument(DocumentRequest $documentRequest)
    {
        $this->requireRequestOwner($documentRequest);

        if (! $documentRequest->no_document_button_at || now()->lt($documentRequest->no_document_button_at)) {
            return response()->json(['message' => 'Esta acción no está disponible aún.'], 422);
        }

        if ($documentRequest->status !== 'en_espera_cliente') {
            return response()->json(['message' => 'No se puede realizar esta acción en el estado actual.'], 422);
        }

        $documentRequest->update(['status' => 'no_documento', 'status_changed_at' => now()]);

        return response()->json($documentRequest->fresh());
    }

    private function associateRequestFile(DocumentRequest $request, File $file): void
    {
        if ($request->person_id && $request->person) {
            $this->documents->associatePerson($file, $request->person);
        }

        if ($request->genealogy_union_id && $request->genealogyUnion) {
            $this->documents->associateUnion($file, $request->genealogyUnion);
        }
    }

    private function fileForRequest(DocumentRequest $request): ?File
    {
        return File::where('document_request_id', $request->id)->latest('id')->first();
    }

    private function deleteSubmittedFile(DocumentRequest $request): void
    {
        $file = $this->fileForRequest($request);

        // A file selected from the client's library belongs to that library.
        // Rejecting or deleting a request must never erase the original upload.
        if ($file && $file->source !== 'solicitud_cliente') {
            $file->update(['document_request_id' => null]);

            return;
        }

        $path = $request->file_path ?: ($file ? trim((string) $file->location, '/') . '/' . ltrim((string) $file->file, '/') : null);

        if ($path) {
            Storage::disk('s3')->delete($path);
        }

        $file?->delete();
    }

    private function personForClient(User $user, int $personId): Agcliente
    {
        return Agcliente::where('IDCliente', $user->passport)->findOrFail($personId);
    }

    private function requireInternalUser(): void
    {
        abort_unless(auth()->check() && $this->documents->isInternalUser(auth()->user()), 403);
    }

    private function requireRequestOwner(DocumentRequest $documentRequest): void
    {
        abort_unless(auth()->check() && $documentRequest->user_id === auth()->id(), 403, 'No autorizado.');
    }
}

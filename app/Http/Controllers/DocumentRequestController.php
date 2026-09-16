<?php

namespace App\Http\Controllers;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\File;
use App\Models\Agcliente;

class DocumentRequestController extends Controller
{
    /**
     * Crear una nueva solicitud de documento (Admin)
     */
    public function store(Request $request, User $user)
    {
        $validated = $request->validate([
            'document_name' => 'required|string|max:255',
            'document_type' => 'required|in:juridico,genealogico',
        ]);

        $documentRequest = DocumentRequest::create([
            'user_id' => $user->id,
            'requested_by' => auth()->id(),
            'document_name' => $validated['document_name'],
            'document_type' => $validated['document_type'],
            'status' => 'en_espera_cliente',
            'no_document_button_at' => now()->addMonth(),
        ]);

        return response()->json($documentRequest);
    }

    /**
     * Actualizar una solicitud de documento (Admin)
     */
    public function update(Request $request, $id)
    {
        // Primero encuentra el documento
        $documentRequest = DocumentRequest::findOrFail($id);

        // Validar que el usuario autenticado es quien creó la solicitud
        if ($documentRequest->requested_by !== auth()->id()) {
            return response()->json([
                'message' => 'No tienes permiso para editar esta solicitud'
            ], 403);
        }

        $validated = $request->validate([
            'document_name' => 'required|string|max:255',
            'document_type' => 'required|in:juridico,genealogico',
        ]);

        // Estados que permiten edición
        $editableStatuses = ['en_espera_cliente', 'resuelto', 'rechazada'];

        if (!in_array($documentRequest->status, $editableStatuses)) {
            return response()->json([
                'message' => 'No se puede editar una solicitud en estado: '.$documentRequest->status,
                'current_status' => $documentRequest->status
            ], 422);
        }

        try {
            $documentRequest->update([
                'document_name' => $validated['document_name'],
                'document_type' => $documentRequest->document_type, // Mantener el tipo original
            ]);

            return response()->json([
                'success' => true,
                'data' => $documentRequest->fresh(),
                'message' => 'Solicitud actualizada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una solicitud de documento (Admin)
     */
    public function destroy($id)
    {
        try {
            $documentRequest = DocumentRequest::findOrFail($id);

            // Opcional: Eliminar archivo asociado si existe
            if ($documentRequest->file_path) {
                Storage::disk('s3')->delete($documentRequest->file_path);
            }

            $documentRequest->delete();

            return response()->json([
                'success' => true,
                'message' => 'Solicitud eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la solicitud',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Aprobar una solicitud de documento (Admin)
     */
    public function approve($id)
    {
        $requestModel = DocumentRequest::findOrFail($id);

        // Estados desde los cuales se puede aprobar
        $approvableStatuses = ['en_espera_cliente', 'resuelto'];

        if (!in_array($requestModel->status, $approvableStatuses)) {
            return response()->json([
                'message' => 'No se puede aprobar una solicitud en estado: '.$requestModel->status,
                'current_status' => $requestModel->status
            ], 422);
        }

        $user = User::findOrFail($requestModel->user_id);

        // Buscar IDPersona en la tabla agclientes
        $idPersona = 0;

        // Actualizar la solicitud
        $requestModel->update([
            'status' => 'aprobada',
            'status_changed_at' => now(),
        ]);

        // Crear el registro en la tabla files
        if ($requestModel->file_path) {
            $fileName = basename($requestModel->file_path);
            $location = dirname($requestModel->file_path);

            File::create([
                'file' => $fileName,
                'location' => $location,
                'tipo' => null,
                'IDCliente' => $user->passport,
                'IDPersona' => $idPersona,
                'migrado' => 0,
                'user_id' => $user->id,
                'document_request_id' => $requestModel->id // Opcional: relacionar con la solicitud
            ]);
        }

        return response()->json($requestModel);
    }

    /**
     * Rechazar una solicitud de documento (Admin)
     */
    public function reject($id)
    {
        $requestModel = DocumentRequest::findOrFail($id);

        // Estados desde los cuales se puede rechazar
        $rejectableStatuses = ['en_espera_cliente', 'resuelto'];

        if (!in_array($requestModel->status, $rejectableStatuses)) {
            return response()->json([
                'message' => 'No se puede rechazar una solicitud en estado: '.$requestModel->status,
                'current_status' => $requestModel->status
            ], 422);
        }

        if ($requestModel->file_path) {
            Storage::disk('s3')->delete($requestModel->file_path);
        }

        $requestModel->update([
            'status' => 'rechazada',
            'file_path' => '',
            'status_changed_at' => now(),
        ]);

        return response()->json($requestModel);
    }

    /**
     * Subir archivo para una solicitud (Cliente)
     */
    public function upload(Request $request, $id)
    {
        $requestModel = DocumentRequest::findOrFail($id);

        // Solo permitir subir archivo si está en espera o rechazada
        if (!in_array($requestModel->status, ['en_espera_cliente', 'rechazada'])) {
            return response()->json(['message' => 'No se puede subir archivo en el estado actual'], 422);
        }

        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        $passport = auth()->user()->passport;

        // Eliminar archivo anterior si existe
        if ($requestModel->file_path) {
            Storage::disk('s3')->delete($requestModel->file_path);
        }

        // Generar nombre del archivo
        $originalName = pathinfo($request->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $request->file('file')->getClientOriginalExtension();
        $currentDate = now()->format('Ymd_His');

        // Limpiar el nombre del documento para usarlo en el nombre del archivo
        $cleanDocName = preg_replace('/[^a-zA-Z0-9]/', '_', $requestModel->document_name);
        $fileName = "{$cleanDocName}_{$currentDate}.{$extension}";

        $location = 'public/doc/P'.$passport.'/solicitudes';

        // Subir el archivo con el nuevo nombre
        $path = $request->file('file')->storeAs(
            $location,
            $fileName,
            's3'
        );

        $requestModel->update([
            'file_path' => $path,
            'status' => 'resuelto',
            'status_changed_at' => now(),
        ]);

        return response()->json([
            'file_url' => Storage::disk('s3')->url($path),
            'request' => $requestModel
        ]);
    }

    /**
     * Marcar como "no tengo documento" (Cliente)
     */
    public function noDocument(DocumentRequest $requestModel)
    {
        // Verificar que el usuario autenticado es el dueño de la solicitud
        if ($requestModel->user_id !== auth()->id()) {
            abort(403, 'No autorizado');
        }

        // Verificar que el botón está disponible
        if (!$requestModel->no_document_button_at || now()->lt($requestModel->no_document_button_at)) {
            return response()->json(['message' => 'Esta acción no está disponible aún'], 422);
        }

        // Solo permitir si está en espera
        if ($requestModel->status !== 'en_espera_cliente') {
            return response()->json(['message' => 'No se puede realizar esta acción en el estado actual'], 422);
        }

        $requestModel->update([
            'status' => 'no_documento',
            'status_changed_at' => now(),
        ]);

        return response()->json($requestModel);
    }
}

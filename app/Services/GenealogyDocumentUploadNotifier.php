<?php

namespace App\Services;

use App\Mail\GenealogyDocumentUploaded;
use App\Models\Agcliente;
use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class GenealogyDocumentUploadNotifier
{
    /** Notify the configured internal mailbox after a customer document is stored. */
    public function notify(User $user, File $file, ?Agcliente $person = null): void
    {
        $recipients = array_values(array_filter((array) config('services.genealogy_documents.upload_notification_to', [])));

        // The recipient will be configured later. Do not attempt a send until it is.
        if (empty($recipients)) {
            return;
        }

        try {
            Mail::to($recipients)->send(new GenealogyDocumentUploaded($user, $file, $person));
        } catch (\Throwable $exception) {
            // A notification problem must never turn a completed upload into a failed one.
            Log::warning('No se pudo notificar la carga de un documento genealógico.', [
                'user_id' => $user->id,
                'file_id' => $file->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

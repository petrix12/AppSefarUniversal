<?php

namespace App\Mail;

use App\Models\Agcliente;
use App\Models\File;
use App\Models\User;
use App\Services\GenealogyDocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenealogyDocumentUploaded extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly File $file,
        public readonly ?Agcliente $person = null,
    ) {
    }

    public function build(): self
    {
        $label = GenealogyDocumentService::label($this->file->document_kind);

        return $this
            ->subject("Nuevo documento cargado: {$label} · {$this->user->passport}")
            ->view('mail.genealogy-document-uploaded');
    }
}

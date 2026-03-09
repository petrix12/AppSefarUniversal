<?php

namespace App\Http\Controllers;

use App\Models\StrategicSuggestionAttachment;
use Illuminate\Support\Facades\Storage;

class StrategicSuggestionAttachmentController extends Controller
{
    private function isCoordinatorUser(): bool
    {
        $u = auth()->user();
        return $u && $u->hasRole('Coord. Ventas');
    }

    private function isAdminUser(): bool
    {
        $u = auth()->user();
        return $u && $u->hasAnyRole(['Administrador']);
    }

    private function authorizeAttachment(StrategicSuggestionAttachment $attachment): void
    {
        $suggestion = $attachment->suggestion;

        abort_unless($suggestion, 404);

        if ($this->isAdminUser()) {
            return;
        }

        if ($this->isCoordinatorUser() && (int) $suggestion->user_id === (int) auth()->id()) {
            return;
        }

        abort(403);
    }

    public function download(StrategicSuggestionAttachment $attachment)
    {
        $this->authorizeAttachment($attachment);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name
        );
    }
}

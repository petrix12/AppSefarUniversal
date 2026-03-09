<?php

namespace App\Http\Controllers;

use App\Models\StrategicSuggestion;
use App\Models\StrategicSuggestionAttachment;
use App\Models\StrategicSuggestionReply;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StrategicSuggestionController extends Controller
{
    private function isCoordVentasUser(): bool
    {
        $u = auth()->user();
        return $u && $u->hasRole('Coord. Ventas');
    }

    private function isAdminUser(): bool
    {
        $u = auth()->user();
        return $u && $u->hasAnyRole(['Administrador', 'Admin']);
    }

    private function canAccessModule(): bool
    {
        return $this->isCoordVentasUser() || $this->isAdminUser();
    }

    private function authorizeSuggestion(StrategicSuggestion $suggestion): void
    {
        if ($this->isAdminUser()) {
            return;
        }

        if ($this->isCoordVentasUser() && (int) $suggestion->user_id === (int) auth()->id()) {
            return;
        }

        abort(403);
    }

    public function main(Request $request)
    {
        abort_unless($this->canAccessModule(), 403);

        $q = trim((string) $request->get('q'));
        $status = trim((string) $request->get('status'));

        $suggestions = StrategicSuggestion::query()
            ->with(['user'])
            ->withCount('replies')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($sub) use ($q) {
                    $sub->where('subject', 'like', "%{$q}%")
                        ->orWhere('message', 'like', "%{$q}%");
                });
            })
            ->when($status !== '', fn($qq) => $qq->where('status', $status))
            ->when($this->isCoordVentasUser(), function ($qq) {
                $qq->where('user_id', auth()->id());
            })
            ->latest('id')
            ->paginate(15);

        $myCount = StrategicSuggestion::query()
            ->when($this->isCoordVentasUser(), fn($qq) => $qq->where('user_id', auth()->id()))
            ->count();

        return view('crud.strategic_suggestions.index', compact('suggestions', 'q', 'status', 'myCount'));
    }

    public function create()
    {
        abort_unless($this->canAccessModule(), 403);

        return view('crud.strategic_suggestions.create');
    }

    public function store(Request $request)
    {
        abort_unless($this->canAccessModule(), 403);

        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip,txt|max:10240',
        ]);

        DB::beginTransaction();

        try {
            $suggestion = StrategicSuggestion::create([
                'user_id' => auth()->id(),
                'subject' => $data['subject'],
                'message' => $data['message'],
                'status' => 'recibida',
                'submitted_at' => now(),
                'updated_by' => auth()->id(),
                'change_log' => [],
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store("strategic-suggestions/{$suggestion->id}", 's3');

                    StrategicSuggestionAttachment::create([
                        'suggestion_id' => $suggestion->id,
                        'reply_id' => null,
                        'uploaded_by' => auth()->id(),
                        'disk' => 's3',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }

            $suggestion->addLog('created', auth()->id(), [
                'status' => 'recibida',
            ]);

            DB::commit();

            return redirect()
                ->route('strategic-suggestions.show', $suggestion)
                ->with('success', 'Propuesta enviada correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function show(StrategicSuggestion $strategic_suggestion)
    {
        $this->authorizeSuggestion($strategic_suggestion);

        $strategic_suggestion->load([
            'user',
            'attachments',
            'replies.user',
            'replies.attachments',
        ]);

        return view('crud.strategic_suggestions.show', [
            'suggestion' => $strategic_suggestion,
        ]);
    }

    public function update(Request $request, StrategicSuggestion $strategic_suggestion)
    {
        $this->authorizeSuggestion($strategic_suggestion);
        abort_unless($this->isAdminUser(), 403);

        $data = $request->validate([
            'status' => 'required|in:recibida,en_revision,respondida,cerrada',
        ]);

        $payload = [
            'status' => $data['status'],
            'updated_by' => auth()->id(),
        ];

        $payload['closed_at'] = $data['status'] === 'cerrada' ? now() : null;

        $strategic_suggestion->update($payload);

        $strategic_suggestion->addLog('status_updated', auth()->id(), [
            'status' => $data['status'],
        ]);

        return back()->with('success', 'Estado actualizado.');
    }

    public function destroy(StrategicSuggestion $strategic_suggestion)
    {
        $this->authorizeSuggestion($strategic_suggestion);
        abort_unless($this->isAdminUser(), 403);

        $strategic_suggestion->delete();

        return redirect()
            ->route('strategic-suggestions.index')
            ->with('success', 'Propuesta eliminada.');
    }

    public function reply(Request $request, StrategicSuggestion $suggestion)
    {
        $this->authorizeSuggestion($suggestion);

        $data = $request->validate([
            'message' => 'required|string|max:10000',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip,txt|max:10240',
        ]);

        DB::beginTransaction();

        try {
            $reply = StrategicSuggestionReply::create([
                'suggestion_id' => $suggestion->id,
                'user_id' => auth()->id(),
                'message' => $data['message'],
                'is_admin_reply' => $this->isAdminUser(),
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store("strategic-suggestions/{$suggestion->id}/replies/{$reply->id}", 's3');

                    StrategicSuggestionAttachment::create([
                        'suggestion_id' => $suggestion->id,
                        'reply_id' => $reply->id,
                        'uploaded_by' => auth()->id(),
                        'disk' => 's3',
                        'path' => $path,
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            }

            $newStatus = $this->isAdminUser() ? 'respondida' : 'recibida';

            $suggestion->update([
                'status' => $newStatus,
                'last_reply_at' => now(),
                'updated_by' => auth()->id(),
            ]);

            $suggestion->addLog('reply_created', auth()->id(), [
                'reply_id' => $reply->id,
                'status' => $newStatus,
            ]);

            DB::commit();

            return back()->with('success', 'Respuesta agregada correctamente.');
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}

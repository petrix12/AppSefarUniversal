<?php
// app/Services/MarkContactedService.php

namespace App\Services;

use App\Mail\ContactRemovedFromReassignmentListsMail;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Collection;

class MarkContactedService
{
    private const REASSIGNMENT_LISTS_RECIPIENT = 'dpm.ladera@sefarvzla.com';

    /**
     * Marca al contacto de la tarea como "contactado"
     * en todas las listas donde esté presente.
     */
    public function markFromTask(Task $task): void
    {
        if (! $task->contact_id) {
            return;
        }

        $contact = $task->contact()->with('listas')->first();

        if (! $contact || $contact->listas->isEmpty()) {
            return;
        }

        $now  = now();
        $updatedLists = collect();
        $note = "Contactado automáticamente desde tarea #{$task->id}";

        foreach ($contact->listas as $lista) {
            // No pisar si ya estaba marcado como contactado
            if ($lista->pivot->contacted) {
                continue;
            }

            $lista->users()->updateExistingPivot($contact->id, [
                'contacted'    => true,
                'contacted_at' => $now,
                'contact_note' => $note,
            ]);

            $updatedLists->push($lista);
        }

        if ($updatedLists->isNotEmpty()) {
            $this->notifyReassignmentListRemoval($task, $contact, $updatedLists);
        }
    }

    private function notifyReassignmentListRemoval(Task $task, User $contact, Collection $updatedLists): void
    {
        try {
            Mail::to(self::REASSIGNMENT_LISTS_RECIPIENT)
                ->send(new ContactRemovedFromReassignmentListsMail($task, $contact, $updatedLists));
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar contacto para sacar de listas de reasignacion', [
                'task_id' => $task->id,
                'contact_id' => $contact->id ?? null,
                'recipient' => self::REASSIGNMENT_LISTS_RECIPIENT,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

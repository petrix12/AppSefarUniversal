<?php
// app/Services/MarkContactedService.php

namespace App\Services;

use App\Models\Task;

class MarkContactedService
{
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
        }
    }
}

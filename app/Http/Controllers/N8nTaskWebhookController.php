<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\HubspotOwnerUser;
use Illuminate\Http\Request;

class N8nTaskWebhookController extends Controller
{
    public function store(Request $request)
    {
        // 🔐 Seguridad básica
        if ($request->header('X-Webhook-Token') !== env('N8N_WEBHOOK_TOKEN')) {
            return response()->json([
                'ok' => false,
                'message' => 'No autorizado',
            ], 401);
        }

        // ✅ Validación
        $data = $request->validate([
            'hubspot_owner_id' => ['required', 'string', 'exists:hubspot_owners,id'],
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'due_date'         => ['required', 'date'],
            'status'           => ['nullable', 'in:pending,in_progress,completed,canceled'],
            'contact_id'       => ['nullable', 'exists:users,id'],
            'follow_up_date'   => ['nullable', 'date'],
        ]);

        // 🔎 Buscar relación owner → user
        $ownerLink = HubspotOwnerUser::with('user')
            ->where('hubspot_owner_id', $data['hubspot_owner_id'])
            ->first();

        if (!$ownerLink || !$ownerLink->user) {
            \Log::warning('Hubspot owner sin usuario asignado', [
                'hubspot_owner_id' => $data['hubspot_owner_id']
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Owner no asignado a ningún usuario',
            ], 404);
        }

        // 🧠 Crear tarea
        $task = Task::create([
            'user_id'            => $ownerLink->user_id,
            'contact_id'         => $data['contact_id'] ?? null,
            'title'              => $data['title'],
            'description'        => $data['description'] ?? null,
            'due_date'           => $data['due_date'],
            'follow_up_date'     => $data['follow_up_date'] ?? null,
            'status'             => $data['status'] ?? Task::STATUS_PENDING,
            'created_by_user_id' => null, // o un user sistema si quieres
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Tarea creada',
            'data' => [
                'task_id' => $task->id,
                'assigned_to' => [
                    'user_id' => $ownerLink->user->id,
                    'name'    => $ownerLink->user->name,
                    'email'   => $ownerLink->user->email,
                ],
                'hubspot_owner' => [
                    'id'   => $ownerLink->hubspot_owner_id,
                    'name' => $ownerLink->hubspot_owner_name,
                ]
            ]
        ], 201);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RoleAiAssistant;
use App\Models\RoleAiKnowledgeEntry;
use App\Models\User;
use App\Services\RoleAiAssistantService;
use Illuminate\Http\Request;

class AdminRoleAiAssistantController extends Controller
{
    public function __construct(private readonly RoleAiAssistantService $assistants)
    {
    }

    public function index()
    {
        $assistantIds = $this->assistants->ensureAssistantsForAllRoles(request()->user())->pluck('id');

        $assistants = RoleAiAssistant::with('role')
            ->withCount([
                'knowledgeEntries',
                'knowledgeEntries as active_knowledge_entries_count' => fn ($query) => $query->where('status', 'active'),
                'chatSessions',
            ])
            ->whereIn('id', $assistantIds)
            ->orderBy('name')
            ->get();

        $assistants->each(function (RoleAiAssistant $assistant) {
            $assistant->access_count = $assistant->role
                ? User::role($assistant->role->name)
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Cliente'))
                    ->count()
                : 0;
        });

        return view('admin.role_ai_assistants.index', compact('assistants'));
    }

    public function show(RoleAiAssistant $assistant)
    {
        $assistant->load('role');

        abort_if($assistant->role?->name === 'Cliente', 404);

        $knowledgeEntries = $assistant->knowledgeEntries()
            ->with('createdBy')
            ->latest()
            ->paginate(20);

        $accessUsers = $assistant->role
            ? User::role($assistant->role->name)
                ->whereDoesntHave('roles', fn ($query) => $query->where('name', 'Cliente'))
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : collect();

        return view('admin.role_ai_assistants.show', compact('assistant', 'knowledgeEntries', 'accessUsers'));
    }

    public function update(Request $request, RoleAiAssistant $assistant)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'model' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'is_active' => ['boolean'],
            'training_enabled' => ['boolean'],
        ]);

        $assistant->update([
            'name' => $data['name'],
            'model' => $data['model'] ?: null,
            'instructions' => $data['instructions'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'training_enabled' => (bool) ($data['training_enabled'] ?? false),
        ]);

        return back()->with('success', 'Asistente actualizado correctamente.');
    }

    public function archiveKnowledge(RoleAiAssistant $assistant, RoleAiKnowledgeEntry $knowledgeEntry)
    {
        abort_unless($knowledgeEntry->assistant_id === $assistant->id, 404);

        $knowledgeEntry->update(['status' => 'archived']);

        return back()->with('success', 'Contexto archivado.');
    }

    public function restoreKnowledge(RoleAiAssistant $assistant, RoleAiKnowledgeEntry $knowledgeEntry)
    {
        abort_unless($knowledgeEntry->assistant_id === $assistant->id, 404);

        $knowledgeEntry->update(['status' => 'active']);

        return back()->with('success', 'Contexto restaurado.');
    }
}

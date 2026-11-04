<?php

namespace App\Http\Controllers;

use App\Models\BrightbeanMessage;
use App\Models\BrightbeanSetting;
use Illuminate\Http\Request;

class BrightbeanInboxController extends Controller
{
    public function inbox(Request $request)
    {
        $query = $request->validate(['conversation' => ['nullable', 'uuid']]);

        return view('admin.integrations.brightbean', [
            'settings' => BrightbeanSetting::current(),
            'conversation' => $query['conversation'] ?? null,
            'messages' => BrightbeanMessage::query()
                ->when($query['conversation'] ?? null, fn ($q, $id) => $q->where('conversation_id', $id))
                ->orderByDesc('source_created_at')->orderByDesc('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'ai_enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:10000'],
        ]);
        BrightbeanSetting::current()->update($data);

        return back()->with('success', 'Configuración de BrightBean actualizada.');
    }
}

<?php

namespace App\Http\Controllers\Teamleader;

use App\Http\Controllers\Controller;
use App\Models\TlCustomFieldDefinition;
use App\Models\TlProject;
use Illuminate\Http\Request;

class TlProjectController extends Controller
{
    public function table(Request $request)
    {
        $query = TlProject::query();

        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('customer_type')) {
            $query->where('customer_type', $request->customer_type);
        }

        $projects = $query->orderBy('starts_on', 'desc')
                          ->paginate(20)
                          ->withQueryString();

        $statuses = TlProject::select('status')
                              ->distinct()
                              ->pluck('status');

        return view('teamleader.projects.index', compact('projects', 'statuses'));
    }

    public function show(string $id)
    {
        $project = TlProject::findOrFail($id);

        // Definiciones indexadas por ID para lookup O(1)
        $definitions = TlCustomFieldDefinition::all()->keyBy('id');

        return view('teamleader.projects.show', compact('project', 'definitions'));
    }
}

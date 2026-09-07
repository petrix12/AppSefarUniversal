<?php

namespace App\Http\Controllers\Teamleader;

use App\Http\Controllers\Controller;
use App\Models\TlCustomFieldDefinition;
use App\Models\TlProject;
use App\Services\TeamleaderProjectPaymentAnalyzer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class TlProjectController extends Controller
{
    public function table(Request $request, TeamleaderProjectPaymentAnalyzer $paymentAnalyzer)
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

        $statuses = TlProject::select('status')
                              ->distinct()
                              ->pluck('status');

        $projectModels = $query->orderByDesc('starts_on')
            ->orderBy('title')
            ->get();

        $projectsById = $projectModels->keyBy('id');
        $paymentAnalysis = $paymentAnalyzer->analyzeProjects($projectModels);

        $paymentRows = collect($paymentAnalysis['projects'])
            ->flatMap(function (array $project) use ($projectsById) {
                return collect($project['phases'])->map(function (array $phase) use ($project, $projectsById) {
                    return [
                        'project' => $projectsById->get($project['project_id']),
                        'analysis' => $project,
                        'phase' => $phase,
                    ];
                });
            })
            ->filter(fn (array $row) =>
                $row['phase']['effective_preestab_amount'] > 0
            );

        if ($request->input('payment_audit') === 'overpaid') {
            $paymentRows = $paymentRows->filter(
                fn (array $row) => $row['phase']['overpaid_amount'] > 0.01
            );
        }

        $paymentRows = $paymentRows
            ->sortByDesc(fn (array $row) => $row['phase']['overpaid_amount'] > 0.01)
            ->values();
        $paymentTotals = [
            'rows' => $paymentRows->count(),
            'projects' => $paymentRows->pluck('analysis.project_id')->unique()->count(),
            'preestab_amount' => round($paymentRows->sum('phase.effective_preestab_amount'), 2),
            'paid_amount' => round($paymentRows->sum('phase.effective_paid_amount'), 2),
            'balance_amount' => round($paymentRows->sum('phase.balance_amount'), 2),
            'rows_to_review' => $paymentRows->where('phase.overpaid_amount', '>', 0.01)->count(),
            'overpaid_amount' => round($paymentRows->sum('phase.overpaid_amount'), 2),
        ];

        $perPage = 50;
        $page = max(1, $request->integer('page', 1));
        $projects = new LengthAwarePaginator(
            $paymentRows->forPage($page, $perPage)->values(),
            $paymentRows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('teamleader.projects.index', compact(
            'projects',
            'statuses',
            'paymentTotals'
        ));
    }

    public function show(string $id)
    {
        $project = TlProject::findOrFail($id);

        // Definiciones indexadas por ID para lookup O(1)
        $definitions = TlCustomFieldDefinition::all()->keyBy('id');

        return view('teamleader.projects.show', compact('project', 'definitions'));
    }
}

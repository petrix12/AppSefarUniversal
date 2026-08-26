<?php

namespace App\Http\Controllers;

use App\Models\DeploymentHistory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeploymentHistoryController extends Controller
{
    public function history(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));

        $deployments = DeploymentHistory::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('version', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhere('after_commit', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['success', 'warning', 'failed'], true), function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('deployed_at')
            ->paginate(20)
            ->withQueryString();

        return view('deployment-histories.index', compact('deployments', 'search', 'status'));
    }

    public function show(DeploymentHistory $deploymentHistory): View
    {
        return view('deployment-histories.show', compact('deploymentHistory'));
    }
}

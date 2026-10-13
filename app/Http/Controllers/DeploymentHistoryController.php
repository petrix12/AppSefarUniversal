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
                        ->orWhere('changes', 'like', "%{$search}%")
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
        if (! $deploymentHistory->changes && $this->hasCommitRange($deploymentHistory)) {
            $changes = $this->recoverChanges($deploymentHistory);
            if ($changes !== '') {
                $deploymentHistory->changes = $changes;
                $deploymentHistory->save();
            }
        }

        return view('deployment-histories.show', compact('deploymentHistory'));
    }

    private function hasCommitRange(DeploymentHistory $history): bool
    {
        return preg_match('/^[a-f0-9]{7,40}$/i', (string) $history->before_commit)
            && preg_match('/^[a-f0-9]{7,40}$/i', (string) $history->after_commit);
    }

    private function recoverChanges(DeploymentHistory $history): string
    {
        $projectPath = base_path();
        $range = escapeshellarg($history->before_commit.'..'.$history->after_commit);
        $directory = 'cd '.escapeshellarg($projectPath).' && ';
        $files = trim((string) shell_exec($directory.'git diff --name-status '.$range.' 2>&1'));
        $commits = trim((string) shell_exec($directory.'git log --format=%s '.$range.' 2>&1'));

        if ($files === '' && $commits === '') {
            return '';
        }

        return "CAMBIOS RECUPERADOS DEL HISTORIAL GIT\n\nCOMMITS:\n"
            .($commits !== '' ? $commits : 'No se encontraron mensajes de commit.')
            ."\n\nARCHIVOS CAMBIADOS:\n"
            .($files !== '' ? $files : 'No se encontraron archivos en el rango de commits.');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeployController extends Controller
{
    public function deploy(Request $request)
    {
        $projectPath = base_path();

        // HEAD antes
        $beforeCmd = "cd " . escapeshellarg($projectPath) . " && git rev-parse HEAD 2>&1";
        $beforeHead = trim(shell_exec($beforeCmd));

        // git pull
        $gitCmd = "cd " . escapeshellarg($projectPath) . " && git pull 2>&1";
        $gitOut = trim(shell_exec($gitCmd));

        // HEAD después
        $afterCmd = "cd " . escapeshellarg($projectPath) . " && git rev-parse HEAD 2>&1";
        $afterHead = trim(shell_exec($afterCmd));

        // Si cambió el commit, hubo actualización real
        $pulledNewChanges = ($beforeHead && $afterHead && $beforeHead !== $afterHead);

        $artisanOut = null;

        if ($pulledNewChanges) {
            $artisanCmd = "cd " . escapeshellarg($projectPath) . " && php artisan optimize:clear 2>&1";
            $artisanOut = trim(shell_exec($artisanCmd));
        }

        return response()->json([
            'ok' => true,
            'before_head' => $beforeHead,
            'after_head' => $afterHead,
            'pulled_new_changes' => $pulledNewChanges,
            'git_pull_output' => $gitOut,
            'optimize_clear_ran' => $pulledNewChanges,
            'optimize_clear_output' => $artisanOut,
        ]);
    }
}

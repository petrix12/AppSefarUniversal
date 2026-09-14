<?php

namespace App\Services;

use App\Jobs\RefreshClientHubspotFilesJob;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ClientFileReviewService
{
    /**
     * Schedule a HubSpot file review only for portal clients and only when the
     * prior review is stale. `revision_archivos` also acts as a short-lived
     * reservation while an asynchronous job is waiting for a worker.
     */
    public function queueIfDue(User $user, string $trigger): bool
    {
        if (! $this->canReview($user)) {
            return false;
        }

        $interval = max(15, (int) config('documents.hubspot_review_interval_minutes', 360));
        $cutoff = now()->subMinutes($interval);

        if ($user->revision_archivos && Carbon::parse($user->revision_archivos)->greaterThan($cutoff)) {
            return false;
        }

        $lock = Cache::lock('client-file-review:' . $user->id, 15);
        $acquired = false;

        try {
            $acquired = $lock->get();
            if (! $acquired) {
                return false;
            }

            $reserved = User::query()
                ->whereKey($user->id)
                ->where(function ($query) use ($cutoff) {
                    $query->whereNull('revision_archivos')
                        ->orWhere('revision_archivos', '<=', $cutoff);
                })
                ->update(['revision_archivos' => now()]);

            if ($reserved !== 1) {
                return false;
            }

            try {
                RefreshClientHubspotFilesJob::dispatch($user->id, $trigger);
            } catch (\Throwable $exception) {
                User::whereKey($user->id)->update(['revision_archivos' => null]);
                throw $exception;
            }

            return true;
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }
    }

    public function canReview(User $user): bool
    {
        return $user->hasRole('Cliente')
            && filled($user->hs_id)
            && filled($user->passport);
    }
}

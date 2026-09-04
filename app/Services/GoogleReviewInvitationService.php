<?php

namespace App\Services;

use App\Models\User;

class GoogleReviewInvitationService
{
    /**
     * The direct Google authoring URL is only exposed after the user has
     * satisfied every campaign rule.
     */
    public function canInvite(?User $user): bool
    {
        if (! $user || ! $user->hasRole('Cliente') || ! $this->reviewUrl()) {
            return false;
        }

        if ($user->google_review_completed_at || ! $user->created_at) {
            return false;
        }

        if ($user->created_at->lt(now()->subMonth())) {
            return false;
        }

        if (trim((string) $user->passport) === '') {
            return false;
        }

        return $user->treePeople()->count() >= $this->minimumPeople();
    }

    public function reviewUrlFor(?User $user): ?string
    {
        return $this->canInvite($user) ? $this->reviewUrl() : null;
    }

    private function reviewUrl(): ?string
    {
        $url = trim((string) config('reviews.google_write_review_url'));

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function minimumPeople(): int
    {
        return max(1, (int) config('reviews.minimum_people', 5));
    }
}

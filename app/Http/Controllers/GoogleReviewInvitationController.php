<?php

namespace App\Http\Controllers;

use App\Services\GoogleReviewInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GoogleReviewInvitationController extends Controller
{
    /**
     * Completes the in-app campaign once the client chooses to write a review.
     * Google does not make the publication outcome available to this app.
     */
    public function write(Request $request, GoogleReviewInvitationService $reviewInvitation): RedirectResponse
    {
        $reviewUrl = $reviewInvitation->reviewUrlFor($request->user());

        abort_unless($reviewUrl, 404);

        $request->user()->forceFill([
            'google_review_completed_at' => now(),
        ])->save();

        return redirect()->away($reviewUrl);
    }
}

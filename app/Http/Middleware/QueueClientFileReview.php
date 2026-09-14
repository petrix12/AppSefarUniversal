<?php

namespace App\Http\Middleware;

use App\Services\ClientFileReviewService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QueueClientFileReview
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $user = $request->user();

        if ($user) {
            app(ClientFileReviewService::class)->queueIfDue($user, 'client_navigation');
        }

        return $response;
    }
}

<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\GoogleAuthenticationService;
use Illuminate\Auth\Events\Login;

class CompleteGoogleAccountLink
{
    public function handle(Login $event): void
    {
        if ($event->guard === config('fortify.guard', 'web') && $event->user instanceof User) {
            app(GoogleAuthenticationService::class)->completePendingLink($event->user, request());
        }
    }
}

<?php

namespace App\Providers;

use App\Mail\Transports\AppMailTransport;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

class AppMailServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->afterResolving('mail.manager', function (MailManager $manager): void {
            $manager->extend('app-mail', fn (array $config) => new AppMailTransport(
                $config['provider_override'] ?? null, (bool) ($config['is_test'] ?? false)
            ));
        });
    }

    public function boot(): void
    {
        \App\Models\Task::updated(function ($task): void {
            if ($task->wasChanged('customer_responded') && $task->customer_responded === true && $task->contact_id
                && \Illuminate\Support\Facades\Schema::hasTable('registration_followup_states')) {
                $client = \App\Models\User::find($task->contact_id);
                if ($client?->hasRole('Cliente')) app(\App\Services\RegistrationFollowupService::class)->stopForResponse($client, auth()->id());
            }
        });
        $original = config('mail.default', 'smtp');
        if ($original === 'app-mail') $original = config('app_mail.original_mailer') ?: 'smtp';
        config([
            'app_mail.original_mailer' => $original,
            'mail.mailers.app-mail' => ['transport' => 'app-mail'],
            'mail.default' => 'app-mail',
        ]);
    }
}

<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('ver.mi.estatus', function ($user) {
            return $user->cosready == 1;
        });

        Gate::define('docs.view', function ($user) {
            return $user->hasAnyRole(['Coord. Ventas']);
        });

        Gate::define('docs.upload', function ($user) {
            return $user->hasRole('Administrador');
        });

        Gate::define('docs.delete', function ($user) {
            return $user->hasRole('Administrador');
        });

        //
    }
}

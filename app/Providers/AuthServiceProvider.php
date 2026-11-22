<?php

namespace App\Providers;

use App\Services\ClientTreeAccess;
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
        Gate::define('superadmin', fn ($user) => $user->isSuperadmin());
        Gate::define('client-notes.delete', fn ($user) => $user->isSuperadmin());
        Gate::define('registration.meeting', fn ($user) => $user->hasRole('Cliente') && (int) $user->pay === 2 && (int) $user->contrato === 1
            && \App\Services\RegistrationMeetingService::supportsUser($user)
            && ! \App\Services\RegistrationMeetingService::hasBookedInitialMeeting($user));
        Gate::define('kalendia.manage', fn ($user) => $user->hasRole('Administrador') && ! $user->hasRole('Cliente'));
        Gate::define('platforms.manage', fn ($user) => $user->hasRole('Administrador') && ! $user->hasRole('Cliente'));
        Gate::define('stripe.manage', fn ($user) => $user->hasRole('Administrador') && ! $user->hasRole('Cliente'));
        Gate::define('paypal.history', fn ($user) => $user->hasRole('Administrador') && ! $user->hasRole('Cliente'));
        Gate::define('stripe.transactions', fn ($user) => $user->isSuperadmin());
        Gate::define('menu.stripe-legacy', fn ($user) => $user->can('admin.payments') && $user->can('crud.stripeverify.index'));
        // Combine the existing navigation paths without granting route permissions.
        Gate::define('menu.clients', fn ($user) => $user->can('crud.users.index') || $user->can('users.viewlist')
            || (($user->can('genealogista') || $user->can('produccion')) && $user->can('crud.clients.index')));
        Gate::define('menu.payment-receipts', fn ($user) => $user->can('admin.payments') && $user->can('crud.comprobantes.index'));
        Gate::define('clients.blocks.review', fn ($user) => app(\App\Services\ClientRestrictionService::class)->canReview($user));

        Gate::define('ver.mi.estatus', function ($user) {
            return $user->cosready == 1;
        });

        Gate::define('docs.view', function ($user) {
            return $user->hasAnyRole(['Coord. de Nacionalidad y Genealogía']);
        });

        Gate::define('docs.upload', function ($user) {
            return $user->hasRole('Administrador');
        });

        Gate::define('docs.delete', function ($user) {
            return $user->hasRole('Administrador');
        });

        Gate::define('notCliente', function ($user) {
            return !$user->hasRole('Cliente');
        });

        Gate::define('integrations.manage', function ($user) {
            return $user->hasRole('Administrador');
        });

        Gate::define('clients.export', function ($user) {
            return $user->hasRole('Administrador') && ! $user->hasRole('Cliente');
        });

        // Shared tree requests: clients are scoped to their assigned tree in the controller.
        // Internal users keep the same genealogy and file permission requirements.
        foreach (['view' => 'index', 'create' => 'create', 'edit' => 'edit'] as $action => $permission) {
            Gate::define('tree-files.'.$action, function ($user) use ($permission) {
                return app(ClientTreeAccess::class)->isClientScoped($user) ||
                    ($user->can('genealogista') && $user->can('crud.files.'.$permission));
            });
        }
        //
    }
}

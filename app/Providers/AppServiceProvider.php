<?php

namespace App\Providers;

use App\Http\Middleware\RestrictClientRoutes;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Livewire\Features\SupportFileUploads\FilePreviewController;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        Paginator::useBootstrapFour();

        // Reject internal client requests before resolving model IDs or running actions.
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            SubstituteBindings::class, RestrictClientRoutes::class
        );
        Livewire::addPersistentMiddleware([RestrictClientRoutes::class]);

        // Los componentes Livewire de esta aplicación pertenecen a pantallas privadas.
        Livewire::setUpdateRoute(fn ($handler) => Route::post('/livewire/update', $handler)
            ->middleware(['web', 'auth']));
        FilePreviewController::$middleware = ['web', 'auth'];
        $uploadMiddleware = (array) (config('livewire.temporary_file_upload.middleware') ?: 'throttle:60,1');
        config(['livewire.temporary_file_upload.middleware' => array_values(array_unique([
            'auth', ...$uploadMiddleware,
        ]))]);
    }
}

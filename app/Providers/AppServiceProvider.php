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
        \Illuminate\Support\Facades\DB::listen(static function (\Illuminate\Database\Events\QueryExecuted $query): void {
            \App\Support\CosPageTiming::current()?->query($query->time);
        });
        foreach ([\App\Models\File::class, \App\Models\Agcliente::class, \App\Models\Negocio::class,
            \App\Models\MondayData::class, \App\Models\MondayItem::class, \App\Models\TlContact::class,
            \App\Models\TlDeal::class, \App\Models\TlProject::class, \App\Models\TlInvoice::class, \App\Models\TlCreditNote::class,
            \App\Models\TlDocument::class, \App\Models\ClientChatMessage::class] as $model) {
            $model::addGlobalScope(new \App\Models\Scopes\BlockedClientRecordScope());
        }

        // Reject internal client requests before resolving model IDs or running actions.
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            SubstituteBindings::class, RestrictClientRoutes::class
        );
        $this->app->make(Kernel::class)->addToMiddlewarePriorityBefore(
            SubstituteBindings::class, \App\Http\Middleware\BlockClientAppAccess::class
        );
        Livewire::addPersistentMiddleware([RestrictClientRoutes::class]);
        Livewire::addPersistentMiddleware([\App\Http\Middleware\RestrictBlockedClientRecords::class]);

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

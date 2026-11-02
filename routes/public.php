<?php

use App\Http\Controllers\Auth\GoogleAuthenticationController;
use App\Http\Controllers\BancaOnlineController;
use App\Http\Controllers\MarketingTrackingController;
use App\Http\Controllers\ProveedorRegisterController;
use App\Http\Controllers\RegisterV2Controller;
use Illuminate\Support\Facades\Route;

// Explicit public entry points. Internal application routes belong in web.php.
Route::get('/registerv2', [RegisterV2Controller::class, 'index'])->name('register.v2.form');
Route::post('/registerv2', [RegisterV2Controller::class, 'store'])->name('register.v2');
Route::get('/registerv2/google', [GoogleAuthenticationController::class, 'redirect'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('register.v2.google.redirect');
Route::get('/registerv2/google/callback', [GoogleAuthenticationController::class, 'callback'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('register.v2.google.callback');

Route::get('/registro-coordinador', [ProveedorRegisterController::class, 'create'])->name('proveedor.register');
Route::post('/registro-coordinador', [ProveedorRegisterController::class, 'store'])->name('proveedor.register.store');

Route::get('/email/t/{recipient}/open', [MarketingTrackingController::class, 'open'])->name('marketing.track.open');
Route::get('/email/t/{recipient}/click/{url}', [MarketingTrackingController::class, 'click'])->name('marketing.track.click');
Route::get('/email/unsubscribe/{recipient}', [MarketingTrackingController::class, 'unsubscribeForm'])->name('marketing.unsubscribe.show');
Route::post('/email/unsubscribe/{recipient}', [MarketingTrackingController::class, 'unsubscribe'])->name('marketing.unsubscribe');

Route::prefix('banca-online-2026')
    ->name('banca-online.')
    ->group(function () {
        Route::get('/', [BancaOnlineController::class, 'landing'])->name('index');
        Route::get('/cliente', [BancaOnlineController::class, 'lookupClient'])->name('client.lookup');
        Route::get('/pago/{token}', [BancaOnlineController::class, 'payment'])->name('payment');
        Route::post('/pago/{token}/stripe', [BancaOnlineController::class, 'processPayment'])->name('payment.process');
        Route::get('/gracias/{token}', [BancaOnlineController::class, 'thankYou'])->name('thank-you');
        Route::get('/{country}', [BancaOnlineController::class, 'landingForCountry'])
            ->whereIn('country', ['espana', 'portugal', 'italia'])
            ->name('country');
        Route::get('/{country}/{plan}', [BancaOnlineController::class, 'configureForCountry'])->name('configure.country');
        Route::post('/{country}/{plan}', [BancaOnlineController::class, 'checkoutForCountry'])->name('checkout.country');
        Route::get('/{plan}', [BancaOnlineController::class, 'configure'])->name('configure');
        Route::post('/{plan}', [BancaOnlineController::class, 'checkout'])->name('checkout');
    });

Route::get('/gracias', function () {
    return view('clientes.gracias');
})->name('gracias');

Route::get('/noservices', function () {
    return view('clientes.noservices');
})->name('noservices');

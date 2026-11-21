<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Authenticated by Stripe's signature; the handler rejects missing or invalid signatures.
Route::post('webhooks/kalendia/stripe', \App\Http\Controllers\KalendiaStripeWebhookController::class)
    ->middleware('throttle:120,1')->name('api.kalendia.stripe');

// Excepción pública utilizada por el registro; no devuelve datos de la cuenta.
Route::post('/user/check-email', [UserController::class, 'checkEmail'])
    ->middleware('throttle:5,1,registration-email')
    ->name('api.registration.check-email');

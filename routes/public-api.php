<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('webhooks/stripe/admin/{account}', \App\Http\Controllers\StripeAdminWebhookController::class)
    ->where('account', 'main')->withoutMiddleware('throttle:api')->middleware('throttle:120,1')->name('api.stripe.admin.webhook');

// Authenticated by Stripe's signature; the handler rejects missing or invalid signatures.
Route::post('webhooks/kalendia/stripe', \App\Http\Controllers\KalendiaStripeWebhookController::class)
    ->withoutMiddleware('throttle:api')->middleware('throttle:120,1')->name('api.kalendia.stripe');

// Kalendia signs its own appointment notifications; this is separate from Stripe.
Route::post('webhooks/kalendia', \App\Http\Controllers\KalendiaWebhookController::class)
    ->withoutMiddleware('throttle:api')->middleware('throttle:120,1')->name('api.kalendia.webhook');

// Excepción pública utilizada por el registro; no devuelve datos de la cuenta.
Route::post('/user/check-email', [UserController::class, 'checkEmail'])
    ->middleware('throttle:5,1,registration-email')
    ->name('api.registration.check-email');

<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Excepción pública utilizada por el registro; no devuelve datos de la cuenta.
Route::post('/user/check-email', [UserController::class, 'checkEmail'])
    ->middleware('throttle:5,1,registration-email')
    ->name('api.registration.check-email');

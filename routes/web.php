<?php

use Illuminate\Support\Facades\Route;

// Home
Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');


// Para probar vistas
Route::get('/pruebas', function () {
    return view('layouts.demoAdminLTE');
});
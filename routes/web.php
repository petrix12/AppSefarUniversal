<?php

use Illuminate\Support\Facades\Route;

// Vista inicio
Route::get('/', function () {
    return view('inicio');
})->name('inicio')->middleware('auth');

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');


// Para probar vistas
Route::get('/pruebas', function () {
    return view('layouts.demoAdminLTE');
});
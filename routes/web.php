<?php

use Illuminate\Support\Facades\Route;

// Vista inicio
Route::get('/', function () {
    return view('inicio');
})->name('inicio')->middleware('auth');

// Grupo de rutas CRUD
Route::group(['middleware' => ['auth'], 'as' => 'crud.'], function(){
    //
});



Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');


// Para probar vistas
Route::get('/pruebas', function () {
    return view('resources\markdown\terms.md');
});
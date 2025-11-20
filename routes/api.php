<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\WhatsappController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/getservicio', [ServicioController::class, 'getservicio']);

Route::get('/getactivealerts', [AlertController::class, 'getactivealerts']);

Route::post('/assistant/chat', [AssistantController::class, 'chat']);

Route::post('/chat/iniciar', [ChatController::class, 'iniciarChat']);
Route::post('/chat/enviar', [ChatController::class, 'enviarMensaje']);

Route::post('/user/check-email', [UserController::class, 'checkEmail']);

Route::get('/whatsapp-requests/pending', [WhatsappController::class, 'pending']);
Route::post('/whatsapp-requests/{id}/success', [WhatsappController::class, 'success']);
Route::post('/whatsapp-requests/{id}/fail', [WhatsappController::class, 'fail']);

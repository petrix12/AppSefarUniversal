<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\N8nTaskWebhookController;
use App\Http\Controllers\BancaOnlineStripeWebhookController;
use App\Http\Controllers\Api\Mcp\ClientController as McpClientController;
use App\Http\Middleware\AuditMcpRequests;
use App\Http\Middleware\EnsureMcpReadToken;

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

Route::middleware(['auth:sanctum', AuditMcpRequests::class, EnsureMcpReadToken::class])
    ->prefix('mcp/v1')
    ->name('api.mcp.')
    ->group(function () {
        Route::get('/clientes', [McpClientController::class, 'search'])->name('clientes.index');
        Route::get('/clientes/{cliente}', [McpClientController::class, 'show'])->name('clientes.show');
        Route::post('/clientes/{cliente}/cos', [McpClientController::class, 'cos'])->name('clientes.cos');
    });

Route::get('/getservicio', [ServicioController::class, 'getservicio']);

Route::get('/getactivealerts', [AlertController::class, 'getactivealerts']);

Route::post('/assistant/chat', [AssistantController::class, 'chat']);

Route::post('/chat/iniciar', [ChatController::class, 'iniciarChat']);
Route::post('/chat/enviar', [ChatController::class, 'enviarMensaje']);

Route::post('/user/check-email', [UserController::class, 'checkEmail']);

Route::post('/n8n/tasks', [N8nTaskWebhookController::class, 'store']);

Route::post('/stripe/banca-online/webhook', BancaOnlineStripeWebhookController::class);

Route::get('/whatsapp-requests/pending', [WhatsappController::class, 'pending'])->withoutMiddleware('auth:sanctum');
Route::post('/whatsapp-requests/{id}/success', [WhatsappController::class, 'success'])->withoutMiddleware('auth:sanctum');
Route::post('/whatsapp-requests/{id}/fail', [WhatsappController::class, 'fail'])->withoutMiddleware('auth:sanctum');

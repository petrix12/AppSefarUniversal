<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServicioController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Api\Mcp\ClientController as McpClientController;
use App\Http\Controllers\Api\ClientCosRefreshController;
use App\Http\Controllers\Api\ClientNoteController;
use App\Http\Middleware\AuditMcpRequests;
use App\Http\Middleware\EnsureCosRefreshToken;
use App\Http\Middleware\EnsureInternalApiToken;
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

// Todas las rutas de esta API requieren un usuario autenticado.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/v1/clientes/delivery-permission', [\App\Http\Controllers\Api\ClientRestrictionApiController::class, 'delivery'])
        ->middleware(EnsureInternalApiToken::class.':client-blocks:read')->name('api.client-blocks.delivery');
    Route::post('/v1/clientes/reconcile-retirement', [\App\Http\Controllers\Api\ClientRestrictionApiController::class, 'refresh'])
        ->middleware(EnsureInternalApiToken::class.':client-blocks:sync')->name('api.client-blocks.refresh');
    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/v1/clientes/{cliente}/cos/refresh', ClientCosRefreshController::class)
        ->middleware(['auth:sanctum', EnsureCosRefreshToken::class])
        ->whereNumber('cliente')
        ->name('api.clientes.cos.refresh');

    Route::post('/v1/clientes/cos/refresh', [ClientCosRefreshController::class, 'lookup'])
        ->middleware(['auth:sanctum', EnsureCosRefreshToken::class])
        ->name('api.clientes.cos.refresh.lookup');

    Route::post('/v1/clientes/{cliente}/notas', [ClientNoteController::class, 'store'])
        ->middleware(['auth:sanctum', EnsureInternalApiToken::class . ':client-notes:create'])
        ->whereNumber('cliente')
        ->name('api.clientes.notes.store');

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

    Route::post('/assistant/chat', [AssistantController::class, 'chat'])
        ->middleware(['can:notCliente', 'throttle:10,1,legacy-chat']);

    Route::post('/chat/iniciar', [ChatController::class, 'iniciarChat'])
        ->middleware(['can:notCliente', 'throttle:10,1,legacy-chat']);
    Route::post('/chat/enviar', [ChatController::class, 'enviarMensaje'])
        ->middleware(['can:notCliente', 'throttle:10,1,legacy-chat']);
});

<?php

use App\Http\Controllers\BancaOnlineStripeWebhookController;
use App\Http\Controllers\Api\BrightbeanController;
use App\Http\Controllers\DeployController;
use App\Http\Controllers\MarketingSesWebhookController;
use App\Http\Controllers\MondayWebhookController;
use App\Http\Controllers\N8nTaskWebhookController;
use App\Http\Controllers\WhatsappController;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\AuditIntegrationTokenRequests;
use App\Http\Middleware\EnsureInternalApiToken;
use App\Http\Middleware\EnsureDeploymentToken;

// Tokens creados y revocados desde Integraciones → Tokens API.
$tokenMiddleware = fn (string $ability): array => [
    'auth:sanctum',
    AuditIntegrationTokenRequests::class . ':' . $ability,
    EnsureInternalApiToken::class . ':' . $ability,
];

Route::post('/deploy', [DeployController::class, 'deploy'])
    ->name('api.deploy.run')
    ->middleware([...$tokenMiddleware('deploy:run'), EnsureDeploymentToken::class, 'throttle:1,1,deploy-api']);

Route::post('/n8n/tasks', [N8nTaskWebhookController::class, 'store'])
    ->middleware($tokenMiddleware('tasks:create'));
Route::post('/brightbean/messages', [BrightbeanController::class, 'sync'])
    ->name('api.brightbean.messages.sync')->middleware($tokenMiddleware('brightbean:sync'));
Route::post('/brightbean/messages/{message}/reply', [BrightbeanController::class, 'reply'])
    ->whereNumber('message')->name('api.brightbean.messages.reply')->middleware($tokenMiddleware('brightbean:ai'));
Route::get('/whatsapp-requests/pending', [WhatsappController::class, 'pending'])
    ->middleware($tokenMiddleware('whatsapp:read'));
Route::post('/whatsapp-requests/{id}/success', [WhatsappController::class, 'success'])
    ->middleware($tokenMiddleware('whatsapp:update'))->whereNumber('id');
Route::post('/whatsapp-requests/{id}/fail', [WhatsappController::class, 'fail'])
    ->middleware($tokenMiddleware('whatsapp:update'))->whereNumber('id');

// Cada controlador verifica la firma/token del proveedor antes de procesar datos.
Route::post('/marketing/ses-events', MarketingSesWebhookController::class)
    ->name('marketing.ses.webhook')
    ->withoutMiddleware('throttle:api');

Route::post('/stripe/banca-online/webhook', BancaOnlineStripeWebhookController::class);

Route::post('/webhooks/monday', MondayWebhookController::class)
    ->name('api.monday.webhook')
    ->withoutMiddleware('throttle:api');

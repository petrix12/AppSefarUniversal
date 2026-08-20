<?php

use App\Http\Controllers\Api\Mcp\StreamableHttpController;
use App\Http\Middleware\AuditMcpRequests;
use App\Http\Middleware\EnsureMcpReadToken;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'], '/mcp', StreamableHttpController::class)
    ->middleware(['auth:sanctum', AuditMcpRequests::class, EnsureMcpReadToken::class])
    ->name('mcp.streamable-http.unsupported');

Route::post('/mcp', StreamableHttpController::class)
    ->middleware(['auth:sanctum', AuditMcpRequests::class, EnsureMcpReadToken::class])
    ->name('mcp.streamable-http');

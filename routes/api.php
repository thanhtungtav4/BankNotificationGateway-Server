<?php

use App\Http\Controllers\Dashboard\DashboardSummaryController;
use App\Http\Controllers\Dashboard\EventReplayController;
use App\Http\Controllers\Dashboard\PairingTokenController;
use App\Http\Controllers\Dashboard\TenantController;
use App\Http\Controllers\Dashboard\WebhookTestController;
use App\Http\Controllers\Mobile\ConfigController;
use App\Http\Controllers\Mobile\HeartbeatController;
use App\Http\Controllers\Mobile\NotificationIngestController;
use App\Http\Controllers\Mobile\PairingController;
use Illuminate\Support\Facades\Route;

Route::prefix('dashboard')->group(function (): void {
    Route::get('summary', DashboardSummaryController::class);
});

Route::get('tenants', [TenantController::class, 'index']);
Route::post('tenants', [TenantController::class, 'store']);
Route::post('pairing-token', PairingTokenController::class);
Route::post('webhooks/test', WebhookTestController::class);

Route::prefix('v1')->group(function (): void {
    Route::prefix('mobile')->group(function (): void {
        Route::post('pair', PairingController::class);
        Route::get('config', ConfigController::class);
        Route::post('heartbeat', HeartbeatController::class);
        Route::post('notifications', NotificationIngestController::class);
    });

    Route::prefix('dashboard')->group(function (): void {
        Route::post('events/{event_id}/replay', EventReplayController::class);
    });
});

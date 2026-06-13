<?php

use App\Http\Controllers\Dashboard\AIController;
use App\Http\Controllers\Dashboard\AuditLogController;
use App\Http\Controllers\Dashboard\AuthController;
use App\Http\Controllers\Dashboard\DashboardSummaryController;
use App\Http\Controllers\Dashboard\DeviceController;
use App\Http\Controllers\Dashboard\EventReplayController;
use App\Http\Controllers\Dashboard\PairingTokenController;
use App\Http\Controllers\Dashboard\QuotaController;
use App\Http\Controllers\Dashboard\TenantController;
use App\Http\Controllers\Dashboard\TenantUserController;
use App\Http\Controllers\Dashboard\TenantWebhookController;
use App\Http\Controllers\Dashboard\WebhookTestController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Mobile\ConfigController;
use App\Http\Controllers\Mobile\HeartbeatController;
use App\Http\Controllers\Mobile\NotificationIngestController;
use App\Http\Controllers\Mobile\PairingController;
use App\Http\Controllers\Mobile\UnpairController;
use App\Http\Controllers\Dashboard\TenantAuthController;
use Illuminate\Support\Facades\Route;

// Public auth routes (rate limited)
Route::prefix('admin')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/register', [AuthController::class, 'register']);
});

// Protected admin routes
Route::prefix('admin')->middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::get('auth/me', [AuthController::class, 'me']);
});

// Tenant auth routes (public)
Route::prefix('tenant')->group(function (): void {
    Route::post('auth/login', [TenantAuthController::class, 'login']);
});

// Protected tenant routes
Route::prefix('tenant')->middleware('auth:sanctum')->group(function (): void {
    Route::post('auth/logout', [TenantAuthController::class, 'logout']);
    Route::get('auth/me', [TenantAuthController::class, 'me']);
});

// Protected dashboard routes
Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('dashboard')->group(function (): void {
        Route::get('summary', DashboardSummaryController::class);
        Route::prefix('ai')->group(function (): void {
            Route::post('generate-regex', [AIController::class, 'generateRegex']);
            Route::post('parse', [AIController::class, 'parseNotification']);
        });
    });

    Route::get('tenants', [TenantController::class, 'index']);
    Route::post('tenants', [TenantController::class, 'store']);
    Route::get('tenants/{id}', [TenantController::class, 'show']);
    Route::patch('tenants/{id}', [TenantController::class, 'update']);
    Route::delete('tenants/{id}', [TenantController::class, 'destroy']);
    Route::get('tenants/{id}/quota', [QuotaController::class, 'show']);
    Route::post('tenants/{id}/parser-config', [TenantController::class, 'saveParserConfig']);

    Route::post('pairing-token', PairingTokenController::class);

    Route::get('devices', [DeviceController::class, 'index']);
    Route::get('devices/{id}', [DeviceController::class, 'show']);
    Route::delete('devices/{id}', [DeviceController::class, 'destroy']);

    Route::get('tenant-webhooks', [TenantWebhookController::class, 'index']);
    Route::post('tenant-webhooks', [TenantWebhookController::class, 'store'])->middleware('quota:webhook');
    Route::patch('tenant-webhooks/{webhook}', [TenantWebhookController::class, 'update']);
    Route::delete('tenant-webhooks/{webhook}', [TenantWebhookController::class, 'destroy']);
    Route::post('tenant-webhooks/{webhook}/parser-config', [TenantWebhookController::class, 'saveParserConfig']);
    Route::delete('tenant-webhooks/{webhook}/parser-config', [TenantWebhookController::class, 'clearParserConfig']);

    Route::post('webhooks/test', WebhookTestController::class);

    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/{id}', [AuditLogController::class, 'show']);
    Route::get('audit-logs/actions', [AuditLogController::class, 'actions']);

    Route::get('tenant-users', [TenantUserController::class, 'index']);
    Route::post('tenant-users', [TenantUserController::class, 'store']);
    Route::get('tenant-users/{id}', [TenantUserController::class, 'show']);
    Route::patch('tenant-users/{id}', [TenantUserController::class, 'update']);
    Route::delete('tenant-users/{id}', [TenantUserController::class, 'destroy']);

    Route::prefix('v1')->group(function (): void {
        Route::prefix('dashboard')->group(function (): void {
            Route::post('events/{event_id}/replay', EventReplayController::class);
        });
        Route::prefix('integration')->group(function (): void {
            Route::get('transactions/lookup', [\App\Http\Controllers\Integration\TransactionLookupController::class, 'lookup']);
        });
    });
});

// Public mobile routes (device authentication via HMAC)
Route::prefix('v1')->group(function (): void {
    Route::prefix('mobile')->group(function (): void {
        Route::post('pair', PairingController::class);
        Route::post('unpair', UnpairController::class);
        Route::get('config', ConfigController::class);
        Route::post('heartbeat', HeartbeatController::class);
        Route::post('notifications', NotificationIngestController::class);
    });
});

// Health check (public)
Route::get('health', HealthController::class);

// API info (public)
Route::get('/', function () {
    return response()->json([
        'name' => 'Bank Notification Gateway API',
        'version' => '1.0.0',
        'documentation' => '/api/docs',
    ]);
});
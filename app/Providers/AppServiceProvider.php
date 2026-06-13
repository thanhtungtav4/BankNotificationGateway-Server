<?php

namespace App\Providers;

use App\Models\Device;
use App\Models\Tenant;
use App\Models\TenantWebhook;
use App\Observers\DeviceObserver;
use App\Observers\TenantObserver;
use App\Observers\TenantWebhookObserver;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditLogger::class, function () {
            return new AuditLogger();
        });
    }

    public function boot(): void
    {
        // Register observers after app is fully booted
        if ($this->app->runningInConsole()) {
            return;
        }

        Tenant::observe(TenantObserver::class);
        Device::observe(DeviceObserver::class);
        TenantWebhook::observe(TenantWebhookObserver::class);
    }
}
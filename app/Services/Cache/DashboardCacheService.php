<?php

namespace App\Services\Cache;

use Illuminate\Support\Facades\Cache;

final class DashboardCacheService
{
    private const CACHE_TTL_MINUTES = [
        'dashboard_summary' => 5,
        'tenant_list' => 10,
        'webhook_list' => 5,
        'device_list' => 2,
    ];

    public function getDashboardSummary(?int $tenantId = null): ?array
    {
        $key = $this->getKey('dashboard_summary', $tenantId);
        return Cache::get($key);
    }

    public function setDashboardSummary(?int $tenantId, array $data): void
    {
        $key = $this->getKey('dashboard_summary', $tenantId);
        Cache::put($key, $data, now()->addMinutes(self::CACHE_TTL_MINUTES['dashboard_summary']));
    }

    public function getTenantList(): ?array
    {
        return Cache::get($this->getKey('tenant_list'));
    }

    public function setTenantList(array $data): void
    {
        Cache::put($this->getKey('tenant_list'), $data, now()->addMinutes(self::CACHE_TTL_MINUTES['tenant_list']));
    }

    public function getWebhookList(int $tenantId): ?array
    {
        return Cache::get($this->getKey('webhook_list', $tenantId));
    }

    public function setWebhookList(int $tenantId, array $data): void
    {
        Cache::put($this->getKey('webhook_list', $tenantId), $data, now()->addMinutes(self::CACHE_TTL_MINUTES['webhook_list']));
    }

    public function getDeviceList(int $tenantId): ?array
    {
        return Cache::get($this->getKey('device_list', $tenantId));
    }

    public function setDeviceList(int $tenantId, array $data): void
    {
        Cache::put($this->getKey('device_list', $tenantId), $data, now()->addMinutes(self::CACHE_TTL_MINUTES['device_list']));
    }

    public function invalidateTenant(int $tenantId): void
    {
        Cache::forget($this->getKey('dashboard_summary', $tenantId));
        Cache::forget($this->getKey('webhook_list', $tenantId));
        Cache::forget($this->getKey('device_list', $tenantId));
    }

    public function invalidateAll(): void
    {
        Cache::forget($this->getKey('dashboard_summary'));
        Cache::forget($this->getKey('tenant_list'));
    }

    public function warmDashboardCache(): void
    {
        // Warm cache for frequently accessed data
        $this->setDashboardSummary(null, $this->buildDashboardSummary());
    }

    private function buildDashboardSummary(): array
    {
        $tenants = \App\Models\Tenant::query()->count();
        $devices = \App\Models\Device::query()->count();
        $devicesOnline = \App\Models\Device::query()
            ->where('last_seen_at', '>', now()->subMinutes(15))
            ->count();
        $notificationsToday = \App\Models\NotificationEvent::query()
            ->whereDate('created_at', today())
            ->count();
        $webhookFailed = \App\Models\WebhookDelivery::query()
            ->whereIn('status', ['failed', 'dead'])
            ->count();

        return [
            'metrics' => [
                ['label' => 'Tenants', 'value' => (string) $tenants],
                ['label' => 'Devices online', 'value' => "{$devicesOnline} / {$devices}"],
                ['label' => 'Notifications today', 'value' => (string) $notificationsToday],
                ['label' => 'Webhook failed', 'value' => (string) $webhookFailed],
            ],
        ];
    }

    private function getKey(string $type, ?int $id = null): string
    {
        if ($id !== null) {
            return "dashboard:{$type}:{$id}";
        }
        return "dashboard:{$type}";
    }
}
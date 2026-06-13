<?php

namespace App\Services\Quota;

use App\Models\Device;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantWebhook;
use Illuminate\Support\Facades\DB;

final class QuotaService
{
    public function checkDeviceLimit(Tenant $tenant): QuotaCheckResult
    {
        $plan = $tenant->plan;
        if (!$plan) {
            return new QuotaCheckResult(true, null, 0, 0);
        }

        $limit = $plan->max_devices;
        $current = $tenant->devices()->count();

        if ($current >= $limit) {
            return new QuotaCheckResult(
                false,
                "Đã đạt giới hạn thiết bị ({$limit}) cho gói {$plan->name}",
                $limit,
                $current
            );
        }

        return new QuotaCheckResult(true, null, $limit, $current);
    }

    public function checkWebhookLimit(Tenant $tenant): QuotaCheckResult
    {
        $plan = $tenant->plan;
        if (!$plan) {
            return new QuotaCheckResult(true, null, 0, 0);
        }

        $limit = $plan->max_webhooks;
        $current = $tenant->webhooks()->count();

        if ($current >= $limit) {
            return new QuotaCheckResult(
                false,
                "Đã đạt giới hạn webhook ({$limit}) cho gói {$plan->name}",
                $limit,
                $current
            );
        }

        return new QuotaCheckResult(true, null, $limit, $current);
    }

    public function checkNotificationLimit(Tenant $tenant): QuotaCheckResult
    {
        $plan = $tenant->plan;
        if (!$plan || !$plan->fair_use_notifications) {
            return new QuotaCheckResult(true, null, null, 0);
        }

        $limit = $plan->fair_use_notifications;
        $current = $tenant->notificationEvents()
            ->whereDate('created_at', today())
            ->count();

        $percentUsed = ($current / $limit) * 100;

        if ($percentUsed >= 100) {
            return new QuotaCheckResult(
                false,
                "Đã vượt giới hạn notification hôm nay ({$current}/{$limit})",
                $limit,
                $current
            );
        }

        return new QuotaCheckResult(true, null, $limit, $current, $percentUsed);
    }

    public function getTenantQuotaInfo(Tenant $tenant): array
    {
        $plan = $tenant->plan;

        return [
            'plan' => $plan?->name ?? 'No Plan',
            'devices' => [
                'limit' => $plan?->max_devices ?? null,
                'current' => $tenant->devices()->count(),
                'available' => $plan ? max(0, $plan->max_devices - $tenant->devices()->count()) : null,
            ],
            'webhooks' => [
                'limit' => $plan?->max_webhooks ?? null,
                'current' => $tenant->webhooks()->count(),
                'available' => $plan ? max(0, $plan->max_webhooks - $tenant->webhooks()->count()) : null,
            ],
            'notifications' => [
                'limit' => $plan?->fair_use_notifications ?? null,
                'current_today' => $tenant->notificationEvents()
                    ->whereDate('created_at', today())
                    ->count(),
            ],
            'log_retention_days' => $plan?->log_retention_days ?? 7,
        ];
    }
}

final class QuotaCheckResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $message,
        public readonly ?int $limit,
        public readonly int $current,
        public readonly ?float $percentUsed = null,
    ) {}

    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'message' => $this->message,
            'limit' => $this->limit,
            'current' => $this->current,
            'percent_used' => $this->percentUsed,
        ];
    }
}
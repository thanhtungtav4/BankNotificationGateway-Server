<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Device;
use App\Models\NotificationEvent;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardSummaryController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $isTenantUser = $user instanceof \App\Models\TenantUser;
        $tenantId = $isTenantUser ? $user->tenant_id : null;

        $tenantQuery = Tenant::query();
        $deviceQuery = Device::query();
        $eventQuery = NotificationEvent::query();
        $webhookQuery = WebhookDelivery::query();

        if ($isTenantUser) {
            $tenantQuery->where('id', $tenantId);
            $deviceQuery->where('tenant_id', $tenantId);
            $eventQuery->where('tenant_id', $tenantId);
            $webhookQuery->where('tenant_id', $tenantId);
        }

        // Metrics calculations
        $tenantsCount = $isTenantUser ? 1 : $tenantQuery->count();
        $devicesOnline = (clone $deviceQuery)->where('status', 'active')->count();
        $devicesTotal = (clone $deviceQuery)->count();
        $eventsToday = (clone $eventQuery)->whereDate('created_at', today())->count();
        $webhooksFailed = (clone $webhookQuery)->whereIn('status', ['failed', 'dead'])->count();

        return response()->json([
            'metrics' => [
                ['label' => 'Tenants', 'value' => (string) $tenantsCount],
                ['label' => 'Devices online', 'value' => $devicesOnline . ' / ' . $devicesTotal],
                ['label' => 'Notifications today', 'value' => (string) $eventsToday],
                ['label' => 'Webhook failed', 'value' => (string) $webhooksFailed],
            ],
            'tenants' => $tenantQuery->latest()->limit(20)->get()->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'plan' => $tenant->plan?->name ?? 'Manual',
                'status' => $tenant->status,
                'devices' => $tenant->devices()->count(),
                'webhooks' => $tenant->webhooks()->count(),
            ]),
            'devices' => $deviceQuery->with('tenant')->latest()->limit(20)->get()->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->device_name,
                'tenant' => $device->tenant?->name,
                'bank' => $device->allowedPackages()->where('is_active', true)->value('bank_name') ?? 'Not selected',
                'status' => $device->last_seen_at && $device->last_seen_at->gt(now()->subMinutes(15)) ? 'online' : 'offline',
                'queue' => $device->queue_pending,
                'seen' => $device->last_seen_at?->diffForHumans() ?? 'never',
            ]),
            'events' => $eventQuery->latest()->limit(30)->get()->map(fn (NotificationEvent $event) => [
                'bank' => $event->app_name ?? $event->package_name,
                'content' => trim(($event->title ?? '') . ' ' . ($event->text ?? '')),
                'amount' => $event->parsedTransaction?->amount ? number_format($event->parsedTransaction->amount) : '-',
                'order' => $event->parsedTransaction?->order_code ?? '-',
                'status' => $event->status,
            ]),
            'webhooks' => $webhookQuery->with('tenant', 'webhook')->latest()->limit(30)->get()->map(fn (WebhookDelivery $delivery) => [
                'tenant' => $delivery->tenant?->name,
                'webhook_name' => $delivery->webhook?->name,
                'url' => $delivery->webhook?->url,
                'status' => $delivery->status,
                'attempt' => $delivery->attempt_count,
                'http' => $delivery->last_http_status ?? '-',
            ]),
        ]);
    }
}

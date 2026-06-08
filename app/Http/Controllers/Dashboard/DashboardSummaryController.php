<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Device;
use App\Models\NotificationEvent;
use App\Models\Tenant;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;

final class DashboardSummaryController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'metrics' => [
                ['label' => 'Tenants', 'value' => (string) Tenant::query()->count()],
                ['label' => 'Devices online', 'value' => Device::query()->where('status', 'active')->count() . ' / ' . Device::query()->count()],
                ['label' => 'Notifications today', 'value' => (string) NotificationEvent::query()->whereDate('created_at', today())->count()],
                ['label' => 'Webhook failed', 'value' => (string) WebhookDelivery::query()->whereIn('status', ['failed', 'dead'])->count()],
            ],
            'tenants' => Tenant::query()->latest()->limit(20)->get()->map(fn (Tenant $tenant) => [
                'name' => $tenant->name,
                'plan' => $tenant->plan?->name ?? 'Manual',
                'status' => $tenant->status,
                'devices' => $tenant->devices()->count(),
                'webhooks' => $tenant->webhooks()->count(),
            ]),
            'devices' => Device::query()->with('tenant')->latest()->limit(20)->get()->map(fn (Device $device) => [
                'name' => $device->device_name,
                'tenant' => $device->tenant?->name,
                'bank' => $device->allowedPackages()->where('is_active', true)->value('bank_name') ?? 'Not selected',
                'status' => $device->last_seen_at && $device->last_seen_at->gt(now()->subMinutes(15)) ? 'online' : 'offline',
                'queue' => $device->queue_pending,
                'seen' => $device->last_seen_at?->diffForHumans() ?? 'never',
            ]),
            'events' => NotificationEvent::query()->latest()->limit(30)->get()->map(fn (NotificationEvent $event) => [
                'bank' => $event->app_name ?? $event->package_name,
                'content' => trim(($event->title ?? '') . ' ' . ($event->text ?? '')),
                'amount' => $event->parsedTransaction?->amount ? number_format($event->parsedTransaction->amount) : '-',
                'order' => $event->parsedTransaction?->order_code ?? '-',
                'status' => $event->status,
            ]),
            'webhooks' => WebhookDelivery::query()->with('tenant', 'webhook')->latest()->limit(30)->get()->map(fn (WebhookDelivery $delivery) => [
                'tenant' => $delivery->tenant?->name,
                'url' => $delivery->webhook?->url,
                'status' => $delivery->status,
                'attempt' => $delivery->attempt_count,
                'http' => $delivery->last_http_status ?? '-',
            ]),
        ]);
    }
}

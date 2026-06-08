<?php

namespace App\Http\Controllers\Dashboard;

use App\Jobs\DispatchWebhookJob;
use App\Models\Tenant;
use App\Models\TenantWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class WebhookTestController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
        ]);

        $tenant = Tenant::query()->findOrFail($payload['tenant_id']);

        $webhooks = TenantWebhook::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->get();

        if ($webhooks->isEmpty()) {
            return response()->json([
                'message' => 'Tenant chưa có webhook active nào',
                'dispatched' => 0,
                'tenant' => $tenant->name,
            ], 422);
        }

        $samplePayload = $this->sampleTestPayload($tenant);
        $deliveries = [];

        foreach ($webhooks as $webhook) {
            $delivery = WebhookDelivery::query()->create([
                'tenant_id' => $tenant->id,
                'webhook_id' => $webhook->id,
                'notification_event_id' => null,
                'parsed_transaction_id' => null,
                'payload' => $samplePayload,
                'status' => 'pending',
            ]);

            DispatchWebhookJob::dispatch($delivery->id);
            $deliveries[] = [
                'id' => $delivery->id,
                'webhook_id' => $webhook->id,
                'name' => $webhook->name,
                'url' => $webhook->url,
            ];
        }

        return response()->json([
            'status' => 'queued',
            'dispatched' => count($deliveries),
            'tenant' => $tenant->name,
            'data' => $deliveries,
        ], 202);
    }

    /** @return array<string,mixed> */
    private function sampleTestPayload(Tenant $tenant): array
    {
        return [
            'event_id' => 'evt_test_' . Str::lower((string) Str::ulid()),
            'type' => 'bank.credit_alert.test',
            'livemode' => false,
            'created_at' => now()->toIso8601String(),
            'data' => [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'bank_app' => 'Test Bank',
                'amount' => 500000,
                'currency' => 'VND',
                'direction' => 'in',
                'order_code' => 'TEST' . strtoupper(Str::random(6)),
                'transfer_content' => 'Test webhook delivery from dashboard',
                'confidence' => 1.0,
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Jobs\DispatchWebhookJob;
use App\Models\NotificationEvent;
use App\Models\TenantWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EventReplayController
{
    public function __invoke(Request $request, int $event_id): JsonResponse
    {
        $event = NotificationEvent::query()->with(['tenant', 'parsedTransaction'])->findOrFail($event_id);

        $user = $request->user();
        if ($user instanceof \App\Models\TenantUser && ($event->tenant_id !== $user->tenant_id || !$user->isAdmin())) {
            abort(403, 'Unauthorized action');
        }

        // Get all active webhooks for this tenant
        $webhooks = TenantWebhook::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('is_active', true)
            ->get();

        if ($webhooks->isEmpty()) {
            return response()->json([
                'error' => 'no_webhooks',
                'message' => 'Tenant không có webhook active nào.',
            ], 400);
        }

        $dispatched = 0;
        $errors = [];

        foreach ($webhooks as $webhook) {
            try {
                // Create new delivery record
                $delivery = \App\Models\WebhookDelivery::query()->create([
                    'tenant_id' => $event->tenant_id,
                    'webhook_id' => $webhook->id,
                    'notification_event_id' => $event->id,
                    'parsed_transaction_id' => $event->parsedTransaction?->id,
                    'payload' => $this->buildPayload($event, $webhook),
                    'status' => 'pending',
                    'attempt_count' => 0,
                    'next_retry_at' => now(),
                ]);

                // Dispatch job
                DispatchWebhookJob::dispatch($delivery);

                $dispatched++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'webhook_id' => $webhook->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'message' => 'Đã queue replay',
            'event_id' => $event_id,
            'dispatched_count' => $dispatched,
            'errors' => $errors,
        ]);
    }

    private function buildPayload(NotificationEvent $event, TenantWebhook $webhook): array
    {
        $payload = [
            'event' => 'notification.replay',
            'timestamp' => now()->toIso8601String(),
            'notification' => [
                'id' => $event->id,
                'package_name' => $event->package_name,
                'app_name' => $event->app_name,
                'title' => $event->title,
                'text' => $event->text,
                'posted_at' => $event->posted_at?->toIso8601String(),
                'received_at' => $event->received_at->toIso8601String(),
            ],
        ];

        if ($event->parsedTransaction) {
            $payload['transaction'] = [
                'bank_name' => $event->parsedTransaction->bank_name,
                'account_number' => $event->parsedTransaction->account_number,
                'amount' => $event->parsedTransaction->amount,
                'currency' => $event->parsedTransaction->currency,
                'direction' => $event->parsedTransaction->direction,
                'order_code' => $event->parsedTransaction->order_code,
                'transfer_content' => $event->parsedTransaction->transfer_content,
                'confidence' => $event->parsedTransaction->confidence,
            ];
        }

        return $payload;
    }
}
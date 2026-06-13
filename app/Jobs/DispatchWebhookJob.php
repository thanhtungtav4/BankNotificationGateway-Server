<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookSignatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class DispatchWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $webhookDeliveryId) {}

    public function handle(WebhookSignatureService $signatureService): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->findOrFail($this->webhookDeliveryId);
        $delivery->update(['status' => 'processing']);

        $webhook = $delivery->webhook;

        if (!$webhook->is_active) {
            $delivery->update([
                'status' => 'failed',
                'last_error' => 'Webhook is inactive (circuit breaker opened or manually disabled)',
            ]);
            return;
        }

        $rawBody = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $timestamp = (string) time();
        $signature = $signatureService->sign($timestamp, $rawBody, $webhook->secret);

        try {
            $response = Http::timeout((int) config('bank_gateway.webhook_timeout_seconds', 3))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Event-Id' => (string) ($delivery->payload['event_id'] ?? $delivery->id),
                    'X-Timestamp' => $timestamp,
                    'X-Signature' => $signature,
                ])
                ->withBody($rawBody, 'application/json')
                ->post($webhook->url);

            $isSuccess = $response->successful();

            $delivery->update([
                'attempt_count' => $delivery->attempt_count + 1,
                'last_http_status' => $response->status(),
                'last_error' => $isSuccess ? null : $response->body(),
                'status' => $isSuccess ? 'sent' : $this->failureStatus($delivery->attempt_count + 1, $response->status()),
                'sent_at' => $isSuccess ? now() : null,
                'next_retry_at' => $isSuccess ? null : $this->nextRetryAt($delivery->attempt_count + 1, $response->status()),
            ]);

            if ($isSuccess) {
                Cache::forget("webhook:failures:{$webhook->id}");
            } else {
                $this->handleFailure($webhook);
            }
        } catch (\Throwable $exception) {
            $attempt = $delivery->attempt_count + 1;
            $delivery->update([
                'attempt_count' => $attempt,
                'last_error' => $exception->getMessage(),
                'status' => $attempt >= 6 ? 'dead' : 'failed',
                'next_retry_at' => $attempt >= 6 ? null : now()->addSeconds($this->retryDelay($attempt)),
            ]);

            $this->handleFailure($webhook);
        }
    }

    private function handleFailure(\App\Models\TenantWebhook $webhook): void
    {
        $failures = Cache::increment("webhook:failures:{$webhook->id}");
        if ($failures >= config('bank_gateway.webhook_max_consecutive_failures', 5)) {
            $webhook->update(['is_active' => false]);
            Cache::forget("webhook:failures:{$webhook->id}");
        }
    }

    private function failureStatus(int $attempt, int $status): string
    {
        if (in_array($status, [400, 401, 403, 404], true)) {
            return 'failed';
        }

        return $attempt >= 6 ? 'dead' : 'failed';
    }

    private function nextRetryAt(int $attempt, int $status): mixed
    {
        if (in_array($status, [400, 401, 403, 404], true) || $attempt >= 6) {
            return null;
        }

        return now()->addSeconds($this->retryDelay($attempt));
    }

    private function retryDelay(int $attempt): int
    {
        return config('bank_gateway.webhook_retry_delays_seconds')[$attempt] ?? 21600;
    }
}

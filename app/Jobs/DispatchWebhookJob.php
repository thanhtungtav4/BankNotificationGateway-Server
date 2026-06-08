<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookSignatureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

final class DispatchWebhookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $webhookDeliveryId) {}

    public function handle(WebhookSignatureService $signatureService): void
    {
        $delivery = WebhookDelivery::query()->with('webhook')->findOrFail($this->webhookDeliveryId);
        $webhook = $delivery->webhook;
        $rawBody = json_encode($delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $timestamp = (string) time();
        $signature = $signatureService->sign($timestamp, $rawBody, $webhook->secret);

        try {
            $response = Http::timeout((int) config('bank_gateway.webhook_timeout_seconds', 10))
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Event-Id' => (string) ($delivery->payload['event_id'] ?? $delivery->id),
                    'X-Timestamp' => $timestamp,
                    'X-Signature' => $signature,
                ])
                ->withBody($rawBody, 'application/json')
                ->post($webhook->url);

            $delivery->update([
                'attempt_count' => $delivery->attempt_count + 1,
                'last_http_status' => $response->status(),
                'last_error' => $response->successful() ? null : $response->body(),
                'status' => $response->successful() ? 'sent' : $this->failureStatus($delivery->attempt_count + 1, $response->status()),
                'sent_at' => $response->successful() ? now() : null,
                'next_retry_at' => $response->successful() ? null : $this->nextRetryAt($delivery->attempt_count + 1, $response->status()),
            ]);
        } catch (\Throwable $exception) {
            $attempt = $delivery->attempt_count + 1;
            $delivery->update([
                'attempt_count' => $attempt,
                'last_error' => $exception->getMessage(),
                'status' => $attempt >= 6 ? 'dead' : 'failed',
                'next_retry_at' => $attempt >= 6 ? null : now()->addSeconds($this->retryDelay($attempt)),
            ]);
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

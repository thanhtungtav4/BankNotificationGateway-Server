<?php

namespace App\Services\Webhooks;

use App\Models\NotificationEvent;
use App\Models\ParsedTransaction;
use Illuminate\Support\Str;

final class WebhookPayloadBuilder
{
    /** @return array<string,mixed> */
    public function build(NotificationEvent $event, ParsedTransaction $transaction): array
    {
        return [
            'event_id' => 'evt_' . Str::ulid()->lower(),
            'type' => 'bank.credit_alert',
            'livemode' => app()->environment('production'),
            'created_at' => now()->toIso8601String(),
            'data' => [
                'device_id' => $event->device?->device_id,
                'bank_app' => $event->app_name,
                'package_name' => $event->package_name,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'direction' => $transaction->direction,
                'order_code' => $transaction->order_code,
                'transfer_content' => $transaction->transfer_content,
                'confidence' => $transaction->confidence,
                'posted_at' => $event->posted_at?->toIso8601String(),
                'raw' => [
                    'title' => $event->title,
                    'text' => $event->text,
                    'big_text' => $event->big_text,
                ],
            ],
        ];
    }
}

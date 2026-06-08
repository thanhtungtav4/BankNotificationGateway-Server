<?php

namespace App\Jobs;

use App\Models\NotificationEvent;
use App\Models\ParsedTransaction;
use App\Models\TenantWebhook;
use App\Models\WebhookDelivery;
use App\Services\Parsing\GenericBankParser;
use App\Services\Webhooks\WebhookPayloadBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class ParseNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $notificationEventId) {}

    public function handle(GenericBankParser $parser, WebhookPayloadBuilder $payloadBuilder): void
    {
        $event = NotificationEvent::query()->with('device')->findOrFail($this->notificationEventId);
        $parsed = $parser->parse([
            'package_name' => $event->package_name,
            'app_name' => $event->app_name,
            'title' => $event->title,
            'text' => $event->text,
            'big_text' => $event->big_text,
        ]);

        $status = match (true) {
            $parsed['amount'] === null => 'missing_amount',
            $parsed['direction'] !== 'in' => 'not_money_in',
            $parsed['order_code'] === null => 'missing_order_code',
            default => 'parsed',
        };

        $transaction = ParsedTransaction::query()->create([
            'tenant_id' => $event->tenant_id,
            'notification_event_id' => $event->id,
            'device_id' => $event->device_id,
            'bank_name' => $event->app_name,
            'account_number' => $parsed['account_number'],
            'amount' => $parsed['amount'],
            'currency' => $parsed['currency'],
            'direction' => $parsed['direction'],
            'order_code' => $parsed['order_code'],
            'transfer_content' => $parsed['transfer_content'],
            'confidence' => $parsed['confidence'],
            'parser_name' => $parsed['parser_name'],
            'status' => $status,
        ]);

        $event->update(['status' => $status === 'parsed' ? 'parsed' : 'parse_failed']);

        if ($status !== 'parsed') {
            return;
        }

        TenantWebhook::query()
            ->where('tenant_id', $event->tenant_id)
            ->where('is_active', true)
            ->each(function (TenantWebhook $webhook) use ($event, $transaction, $payloadBuilder): void {
                $delivery = WebhookDelivery::query()->create([
                    'tenant_id' => $event->tenant_id,
                    'webhook_id' => $webhook->id,
                    'notification_event_id' => $event->id,
                    'parsed_transaction_id' => $transaction->id,
                    'payload' => $payloadBuilder->build($event, $transaction),
                    'status' => 'pending',
                ]);

                DispatchWebhookJob::dispatch($delivery->id);
            });
    }
}

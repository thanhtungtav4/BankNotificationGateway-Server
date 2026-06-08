<?php

namespace App\Console\Commands;

use App\Jobs\DispatchWebhookJob;
use App\Models\WebhookDelivery;
use Illuminate\Console\Command;

final class RetryDueWebhooksCommand extends Command
{
    protected $signature = 'gateway:webhooks:retry-due {--limit=100}';

    protected $description = 'Queue retry jobs for failed webhook deliveries whose next_retry_at is due.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $deliveries = WebhookDelivery::query()
            ->where('status', 'failed')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', now())
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            DispatchWebhookJob::dispatch($delivery->id);
        }

        $this->info("Queued {$deliveries->count()} webhook retry jobs.");

        return self::SUCCESS;
    }
}

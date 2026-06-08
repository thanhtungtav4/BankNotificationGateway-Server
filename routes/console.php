<?php

use App\Console\Commands\RetryDueWebhooksCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('gateway:status', function (): void {
    $this->info('Bank Notification Gateway Laravel skeleton is installed.');
});

Schedule::command(RetryDueWebhooksCommand::class)->everyMinute()->withoutOverlapping();

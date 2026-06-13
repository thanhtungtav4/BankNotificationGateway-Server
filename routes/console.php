<?php

use App\Console\Commands\BackupDatabase;
use App\Console\Commands\CleanupBackups;
use App\Console\Commands\ListBackups;
use App\Console\Commands\RetryDueWebhooksCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Webhook retry - every minute
Schedule::command(RetryDueWebhooksCommand::class)->everyMinute()->withoutOverlapping();

// Database backup - daily at 2 AM
Schedule::command(BackupDatabase::class)->dailyAt('02:00')->withoutOverlapping();

// Cleanup old backups - daily at 3 AM
Schedule::command(CleanupBackups::class)->dailyAt('03:00')->withoutOverlapping();
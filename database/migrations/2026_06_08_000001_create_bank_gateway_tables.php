<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('price_monthly')->default(0);
            $table->unsignedInteger('max_devices')->default(1);
            $table->unsignedInteger('max_webhooks')->default(1);
            $table->unsignedInteger('log_retention_days')->default(7);
            $table->unsignedInteger('fair_use_notifications')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('status')->default('trial');
            $table->unsignedBigInteger('monthly_fee')->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('device_id')->unique();
            $table->string('device_name');
            $table->string('secret_hash');
            $table->string('status')->default('inactive');
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_ip')->nullable();
            $table->string('app_version')->nullable();
            $table->string('android_version')->nullable();
            $table->unsignedTinyInteger('battery_level')->nullable();
            $table->boolean('is_charging')->nullable();
            $table->boolean('listener_enabled')->default(false);
            $table->unsignedInteger('queue_pending')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('device_allowed_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('package_name');
            $table->string('app_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'device_id', 'package_name'], 'device_allowed_package_unique');
        });

        Schema::create('notification_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('package_name');
            $table->string('app_name')->nullable();
            $table->text('title')->nullable();
            $table->text('text')->nullable();
            $table->text('big_text')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('received_at');
            $table->string('notification_key')->nullable();
            $table->string('event_hash');
            $table->json('raw_payload')->nullable();
            $table->string('status')->default('received');
            $table->timestamps();
            $table->unique(['tenant_id', 'device_id', 'notification_key'], 'notification_dedupe_key');
            $table->index(['tenant_id', 'created_at']);
            $table->index(['status']);
            $table->index(['event_hash']);
        });

        Schema::create('parsed_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_event_id')->constrained('notification_events')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('currency', 8)->default('VND');
            $table->string('direction')->default('unknown');
            $table->string('order_code')->nullable();
            $table->text('transfer_content')->nullable();
            $table->decimal('confidence', 3, 2)->default(0);
            $table->string('parser_name')->nullable();
            $table->string('status')->default('parsed');
            $table->timestamps();
            $table->index(['tenant_id', 'order_code']);
        });

        Schema::create('tenant_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url');
            $table->string('secret');
            $table->boolean('is_active')->default(true);
            $table->json('event_types')->nullable();
            $table->timestamps();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_id')->constrained('tenant_webhooks')->cascadeOnDelete();
            $table->foreignId('notification_event_id')->constrained('notification_events')->cascadeOnDelete();
            $table->foreignId('parsed_transaction_id')->nullable()->constrained('parsed_transactions')->nullOnDelete();
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('tenant_webhooks');
        Schema::dropIfExists('parsed_transactions');
        Schema::dropIfExists('notification_events');
        Schema::dropIfExists('device_allowed_packages');
        Schema::dropIfExists('devices');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('plans');
    }
};

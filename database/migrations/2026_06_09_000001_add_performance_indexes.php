<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // notification_events - package_name index for filtering by bank app
        if (!Schema::hasIndex('notification_events', 'idx_notification_events_package')) {
            Schema::table('notification_events', function (Blueprint $table) {
                $table->index(['tenant_id', 'package_name'], 'idx_notification_events_package');
            });
        }

        // webhook_deliveries - webhook_id index
        if (!Schema::hasIndex('webhook_deliveries', 'idx_webhook_deliveries_webhook')) {
            Schema::table('webhook_deliveries', function (Blueprint $table) {
                $table->index(['webhook_id', 'status'], 'idx_webhook_deliveries_webhook');
            });
        }

        // parsed_transactions - status index
        if (!Schema::hasIndex('parsed_transactions', 'idx_parsed_transactions_status')) {
            Schema::table('parsed_transactions', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'idx_parsed_transactions_status');
            });
        }

        // audit_logs - actor index
        if (!Schema::hasIndex('audit_logs', 'idx_audit_logs_actor')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->index(['tenant_id', 'actor_type', 'actor_id'], 'idx_audit_logs_actor');
            });
        }

        // devices - status index for quick filtering
        if (!Schema::hasIndex('devices', 'idx_devices_status')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->index(['tenant_id', 'status', 'last_seen_at'], 'idx_devices_status');
            });
        }

        // tenant_users - tenant and email index
        if (!Schema::hasIndex('tenant_users', 'idx_tenant_users_tenant')) {
            Schema::table('tenant_users', function (Blueprint $table) {
                $table->index('tenant_id', 'idx_tenant_users_tenant');
                $table->index('email', 'idx_tenant_users_email');
            });
        }
    }

    public function down(): void
    {
        Schema::table('notification_events', function (Blueprint $table) {
            $table->dropIndex('idx_notification_events_package');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table) {
            $table->dropIndex('idx_webhook_deliveries_webhook');
        });

        Schema::table('parsed_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_parsed_transactions_status');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_audit_logs_actor');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->dropIndex('idx_devices_status');
        });

        Schema::table('tenant_users', function (Blueprint $table) {
            $table->dropIndex('idx_tenant_users_tenant');
            $table->dropIndex('idx_tenant_users_email');
        });
    }
};
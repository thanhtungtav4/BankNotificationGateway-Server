<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenant_webhooks', function (Blueprint $table): void {
            $table->json('bank_rules')->nullable()->after('event_types');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_webhooks', function (Blueprint $table): void {
            $table->dropColumn('bank_rules');
        });
    }
};

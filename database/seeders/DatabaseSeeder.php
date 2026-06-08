<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\DeviceAllowedPackage;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantWebhook;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $basic = Plan::query()->firstOrCreate(['name' => 'Basic'], [
            'price_monthly' => 299000,
            'max_devices' => 1,
            'max_webhooks' => 1,
            'log_retention_days' => 7,
            'fair_use_notifications' => 10000,
            'is_active' => true,
        ]);

        $pro = Plan::query()->firstOrCreate(['name' => 'Pro'], [
            'price_monthly' => 799000,
            'max_devices' => 3,
            'max_webhooks' => 3,
            'log_retention_days' => 30,
            'fair_use_notifications' => 50000,
            'is_active' => true,
        ]);

        $tenant = Tenant::query()->firstOrCreate(['slug' => 'demo-shop'], [
            'plan_id' => $pro->id,
            'name' => 'Demo Shop',
            'status' => 'active',
            'monthly_fee' => 799000,
        ]);

        $secret = 'demo-device-secret-change-me';
        $device = Device::query()->firstOrCreate(['device_id' => 'dev_demo_mb_01'], [
            'tenant_id' => $tenant->id,
            'device_name' => 'MB Phone 01',
            'secret_hash' => Hash::make($secret),
            'secret_encrypted' => Crypt::encryptString($secret),
            'status' => 'active',
            'last_seen_at' => now(),
            'app_version' => '1.0.0',
            'android_version' => '14',
            'listener_enabled' => true,
        ]);

        DeviceAllowedPackage::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'device_id' => $device->id,
            'package_name' => 'com.mbmobile',
        ], [
            'app_name' => 'MB Bank',
            'bank_name' => 'MB Bank',
            'is_active' => true,
        ]);

        TenantWebhook::query()->firstOrCreate([
            'tenant_id' => $tenant->id,
            'url' => 'https://example.com/api/bank-credit-alert',
        ], [
            'name' => 'Demo webhook',
            'secret' => Str::random(48),
            'is_active' => true,
            'event_types' => ['bank.credit_alert'],
        ]);
    }
}

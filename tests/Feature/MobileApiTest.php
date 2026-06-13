<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\DevicePairingToken;
use App\Models\Tenant;
use App\Services\Mobile\DevicePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'components' => [
                    'database',
                    'redis',
                ],
            ]);
    }

    public function test_can_pair_device(): void
    {
        $tenant = Tenant::factory()->create();
        $service = new DevicePairingService();
        $token = $service->createPairingToken($tenant->id, 'Test Phone');

        $response = $this->postJson('/api/v1/mobile/pair', [
            'pairing_token' => $token,
            'device_name' => 'Test Phone',
            'app_version' => '1.0.0',
            'android_version' => '14',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'device_id',
                'device_secret',
                'server_url',
            ]);

        $this->assertDatabaseHas('devices', [
            'tenant_id' => $tenant->id,
            'device_name' => 'Test Phone',
        ]);
    }

    public function test_cannot_pair_with_invalid_token(): void
    {
        $response = $this->postJson('/api/v1/mobile/pair', [
            'pairing_token' => 'invalid_token_xxx',
            'device_name' => 'Test Phone',
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_pair_with_used_token(): void
    {
        $tenant = Tenant::factory()->create();
        $service = new DevicePairingService();
        $token = $service->createPairingToken($tenant->id);
        $service->pair(['pairing_token' => $token]);

        $response = $this->postJson('/api/v1/mobile/pair', [
            'pairing_token' => $token,
            'device_name' => 'Another Phone',
        ]);

        $response->assertStatus(422);
    }

    public function test_unpair_requires_device_secret(): void
    {
        $tenant = Tenant::factory()->create();
        $device = Device::factory()->create([
            'tenant_id' => $tenant->id,
            'secret_encrypted' => Crypt::encryptString('secret123'),
        ]);

        $response = $this->postJson('/api/v1/mobile/unpair', [
            'device_id' => $device->device_id,
            'device_secret' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Đã hủy pair thiết bị thành công.']);

        $this->assertDatabaseMissing('devices', [
            'id' => $device->id,
        ]);
    }

    public function test_unpair_fails_with_wrong_secret(): void
    {
        $tenant = Tenant::factory()->create();
        $device = Device::factory()->create([
            'tenant_id' => $tenant->id,
            'secret_encrypted' => Crypt::encryptString('secret123'),
        ]);

        $response = $this->postJson('/api/v1/mobile/unpair', [
            'device_id' => $device->device_id,
            'device_secret' => 'wrong_secret',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'invalid_credentials',
            ]);
    }

    public function test_can_ingest_notification(): void
    {
        $tenant = Tenant::factory()->create();
        $device = Device::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'active',
            'secret_encrypted' => Crypt::encryptString('secret123'),
        ]);

        \App\Models\DeviceAllowedPackage::query()->create([
            'tenant_id' => $tenant->id,
            'device_id' => $device->id,
            'package_name' => 'com.mbmobile',
            'app_name' => 'MB Bank',
            'is_active' => true,
        ]);

        $payload = [
            'package_name' => 'com.mbmobile',
            'app_name' => 'MB Bank',
            'title' => 'Giao dich',
            'text' => 'TK 123456 +50,000 VND',
            'big_text' => null,
            'posted_at' => now()->toIso8601String(),
            'notification_key' => 'notif_key_123',
            'raw' => ['id' => 123],
        ];

        $rawBody = json_encode($payload);
        $timestamp = (string) time();

        $signer = new \App\Services\Mobile\DeviceSignatureService();
        $signature = $signer->sign($timestamp, $rawBody, 'secret123');

        $response = $this->call(
            'POST',
            '/api/v1/mobile/notifications',
            [],
            [],
            [],
            [
                'HTTP_X-Device-Id' => $device->device_id,
                'HTTP_X-Timestamp' => $timestamp,
                'HTTP_X-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawBody
        );

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'event_id',
            ]);
    }
}

<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\Tenant;
use App\Services\Mobile\DevicePairingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DevicePairingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DevicePairingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DevicePairingService();
    }

    public function test_can_create_pairing_token(): void
    {
        $tenant = Tenant::factory()->create();

        $token = $this->service->createPairingToken($tenant->id, 'Test Device');

        $this->assertNotEmpty($token);
        $this->assertStringStartsWith('pair_', $token);
        $this->assertEquals(53, strlen($token)); // 'pair_' + 48 chars
    }

    public function test_token_is_stored_with_hash(): void
    {
        $tenant = Tenant::factory()->create();

        $token = $this->service->createPairingToken($tenant->id);

        $storedToken = \App\Models\DevicePairingToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        $this->assertNotNull($storedToken);
        $this->assertEquals($tenant->id, $storedToken->tenant_id);
        $this->assertNull($storedToken->consumed_at);
    }

    public function test_can_pair_device_with_valid_token(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->service->createPairingToken($tenant->id, 'My Phone');

        $result = $this->service->pair([
            'pairing_token' => $token,
            'device_name' => 'Test Device',
            'app_version' => '1.0.0',
            'android_version' => '14',
        ]);

        $this->assertArrayHasKey('device_id', $result);
        $this->assertArrayHasKey('device_secret', $result);
        $this->assertArrayHasKey('server_url', $result);
        $this->assertStringStartsWith('dev_', $result['device_id']);
    }

    public function test_token_is_consumed_after_pairing(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->service->createPairingToken($tenant->id);

        $this->service->pair(['pairing_token' => $token]);

        $storedToken = \App\Models\DevicePairingToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        $this->assertNotNull($storedToken->consumed_at);
    }

    public function test_cannot_reuse_consumed_token(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->service->createPairingToken($tenant->id);

        $this->service->pair(['pairing_token' => $token]);

        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);
        $this->service->pair(['pairing_token' => $token]);
    }

    public function test_cannot_pair_with_invalid_token(): void
    {
        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);
        $this->service->pair(['pairing_token' => 'invalid_token_123']);
    }

    public function test_device_is_created_with_correct_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $token = $this->service->createPairingToken($tenant->id);

        $result = $this->service->pair(['pairing_token' => $token]);

        $device = Device::query()->where('device_id', $result['device_id'])->first();

        $this->assertNotNull($device);
        $this->assertEquals($tenant->id, $device->tenant_id);
        $this->assertEquals('active', $device->status);
    }
}
<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Models\Device;
use App\Services\Quota\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QuotaServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuotaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuotaService();
    }

    public function test_tenant_without_plan_has_unlimited_devices(): void
    {
        $tenant = Tenant::factory()->create();

        $result = $this->service->checkDeviceLimit($tenant);

        $this->assertTrue($result->allowed);
        $this->assertNull($result->limit);
    }

    public function test_tenant_with_plan_has_device_limit(): void
    {
        $plan = \App\Models\Plan::factory()->create(['max_devices' => 3]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $result = $this->service->checkDeviceLimit($tenant);

        $this->assertTrue($result->allowed);
        $this->assertEquals(3, $result->limit);
    }

    public function test_cannot_exceed_plan_device_limit(): void
    {
        $plan = \App\Models\Plan::factory()->create(['max_devices' => 2]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);
        
        Device::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $result = $this->service->checkDeviceLimit($tenant);

        $this->assertFalse($result->allowed);
        $this->assertEquals(2, $result->limit);
        $this->assertEquals(2, $result->current);
    }

    public function test_tenant_with_plan_has_webhook_limit(): void
    {
        $plan = \App\Models\Plan::factory()->create(['max_webhooks' => 5]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $result = $this->service->checkWebhookLimit($tenant);

        $this->assertTrue($result->allowed);
        $this->assertEquals(5, $result->limit);
    }

    public function test_get_tenant_quota_info(): void
    {
        $plan = \App\Models\Plan::factory()->create([
            'name' => 'Pro',
            'max_devices' => 10,
            'max_webhooks' => 5,
            'fair_use_notifications' => 1000,
        ]);
        $tenant = Tenant::factory()->create(['plan_id' => $plan->id]);

        $info = $this->service->getTenantQuotaInfo($tenant);

        $this->assertEquals('Pro', $info['plan']);
        $this->assertEquals(10, $info['devices']['limit']);
        $this->assertEquals(5, $info['webhooks']['limit']);
        $this->assertEquals(1000, $info['notifications']['limit']);
    }
}
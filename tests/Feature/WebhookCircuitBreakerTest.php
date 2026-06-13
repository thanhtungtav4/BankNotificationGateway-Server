<?php

namespace Tests\Feature;

use App\Jobs\DispatchWebhookJob;
use App\Models\Tenant;
use App\Models\TenantWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WebhookCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    public function test_circuit_breaker_opens_after_max_failures(): void
    {
        Http::fake([
            '*' => Http::response('Server Error', 500),
        ]);

        $tenant = Tenant::factory()->create();
        $webhook = TenantWebhook::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'secret' => 'supersecret',
            'is_active' => true,
        ]);

        config(['bank_gateway.webhook_max_consecutive_failures' => 3]);

        // First failure
        $delivery1 = WebhookDelivery::query()->create([
            'tenant_id' => $tenant->id,
            'webhook_id' => $webhook->id,
            'payload' => ['event_id' => 'evt_1'],
            'status' => 'pending',
        ]);
        // To bypass foreign key constraints if they are enforced in sqlite (sqlite in-memory usually doesn't enforce unless enabled, but let's be safe and check if it throws).
        // If we don't have constraints enabled in sqlite testing, it is fine.

        $job = new DispatchWebhookJob($delivery1->id);
        $job->handle(new \App\Services\Webhooks\WebhookSignatureService());

        $this->assertTrue($webhook->fresh()->is_active);
        $this->assertEquals(1, Cache::get("webhook:failures:{$webhook->id}"));

        // Second failure
        $delivery2 = WebhookDelivery::query()->create([
            'tenant_id' => $tenant->id,
            'webhook_id' => $webhook->id,
            'payload' => ['event_id' => 'evt_2'],
            'status' => 'pending',
        ]);
        $job = new DispatchWebhookJob($delivery2->id);
        $job->handle(new \App\Services\Webhooks\WebhookSignatureService());

        $this->assertTrue($webhook->fresh()->is_active);
        $this->assertEquals(2, Cache::get("webhook:failures:{$webhook->id}"));

        // Third failure - should trigger circuit breaker (max consecutive failures is 3)
        $delivery3 = WebhookDelivery::query()->create([
            'tenant_id' => $tenant->id,
            'webhook_id' => $webhook->id,
            'payload' => ['event_id' => 'evt_3'],
            'status' => 'pending',
        ]);
        $job = new DispatchWebhookJob($delivery3->id);
        $job->handle(new \App\Services\Webhooks\WebhookSignatureService());

        $this->assertFalse($webhook->fresh()->is_active);
        $this->assertNull(Cache::get("webhook:failures:{$webhook->id}"));
    }

    public function test_circuit_breaker_resets_on_success(): void
    {
        $tenant = Tenant::factory()->create();
        $webhook = TenantWebhook::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'secret' => 'supersecret',
            'is_active' => true,
        ]);

        config(['bank_gateway.webhook_max_consecutive_failures' => 3]);

        Http::fakeSequence()
            ->push('Error', 500)
            ->push('OK', 200);

        // 1. First attempt fails
        $delivery1 = WebhookDelivery::query()->create([
            'tenant_id' => $tenant->id,
            'webhook_id' => $webhook->id,
            'payload' => ['event_id' => 'evt_1'],
            'status' => 'pending',
        ]);
        (new DispatchWebhookJob($delivery1->id))->handle(new \App\Services\Webhooks\WebhookSignatureService());
        $this->assertEquals(1, Cache::get("webhook:failures:{$webhook->id}"));

        // 2. Second attempt succeeds
        $delivery2 = WebhookDelivery::query()->create([
            'tenant_id' => $tenant->id,
            'webhook_id' => $webhook->id,
            'payload' => ['event_id' => 'evt_2'],
            'status' => 'pending',
        ]);
        (new DispatchWebhookJob($delivery2->id))->handle(new \App\Services\Webhooks\WebhookSignatureService());
        
        $this->assertNull(Cache::get("webhook:failures:{$webhook->id}"));
        $this->assertTrue($webhook->fresh()->is_active);
    }

    public function test_does_not_execute_if_webhook_is_inactive(): void
    {
        Http::fake();

        $tenant = Tenant::factory()->create();
        $webhook = TenantWebhook::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Test Webhook',
            'url' => 'https://example.com/webhook',
            'secret' => 'supersecret',
            'is_active' => false,
        ]);

        $delivery = WebhookDelivery::query()->create([
            'tenant_id' => $tenant->id,
            'webhook_id' => $webhook->id,
            'payload' => ['event_id' => 'evt_1'],
            'status' => 'pending',
        ]);

        (new DispatchWebhookJob($delivery->id))->handle(new \App\Services\Webhooks\WebhookSignatureService());

        Http::assertNothingSent();
        $this->assertEquals('failed', $delivery->fresh()->status);
        $this->assertStringContainsString('inactive', $delivery->fresh()->last_error);
    }
}

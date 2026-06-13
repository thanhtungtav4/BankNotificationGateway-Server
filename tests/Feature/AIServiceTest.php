<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use App\Services\AI\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Exception;

final class AIServiceTest extends TestCase
{
    use RefreshDatabase;

    private AIService $aiService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aiService = new AIService();
    }

    public function test_ai_service_default_completions_succeeds(): void
    {
        // Mock successful call to VPS-163
        Http::fake([
            'http://163.61.110.132:3001/v1/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello from VPS-163',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->aiService->complete([
            ['role' => 'user', 'content' => 'Test message'],
        ]);

        $this->assertEquals('Hello from VPS-163', $result['content']);
        $this->assertEquals('vps-163', $result['provider']);
    }

    public function test_ai_service_failover_works(): void
    {
        // Mock VPS-163 failing (500), but VPS-103 succeeding (200)
        Http::fake([
            'http://163.61.110.132:3001/v1/*' => Http::response('Server Error', 500),
            'http://103.157.204.253:3001/v1/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => 'Hello from VPS-103',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = $this->aiService->complete([
            ['role' => 'user', 'content' => 'Test message'],
        ]);

        $this->assertEquals('Hello from VPS-103', $result['content']);
        $this->assertEquals('vps-103', $result['provider']);
    }

    public function test_ai_service_all_fail_throws_exception(): void
    {
        // Mock all providers failing
        Http::fake([
            'http://163.61.110.132:3001/v1/*' => Http::response('Server Error', 500),
            'http://103.157.204.253:3001/v1/*' => Http::response('Server Error', 500),
            'https://opencode.ai/v1/*' => Http::response('Server Error', 500),
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('All AI providers failed');

        $this->aiService->complete([
            ['role' => 'user', 'content' => 'Test message'],
        ]);
    }

    public function test_generate_regex_endpoint_requires_auth(): void
    {
        $response = $this->postJson('/api/dashboard/ai/generate-regex', [
            'sample_text' => 'Vietcombank GD: +50,000 VND. ND: DH123456 thanh toan',
        ]);

        $response->assertStatus(401);
    }

    public function test_generate_regex_endpoint_returns_data(): void
    {
        $admin = AdminUser::factory()->create();

        $mockResponseContent = json_encode([
            'regex' => '/GD: \+([0-9,]+) VND\. ND: ([A-Z0-9]+)/i',
            'amount_group' => 1,
            'direction_group' => null,
            'order_code_group' => 2,
            'transfer_content_group' => null,
            'bank_name' => 'Vietcombank',
            'explanation' => 'Matches the transaction text format.',
        ]);

        Http::fake([
            'http://163.61.110.132:3001/v1/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $mockResponseContent,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/dashboard/ai/generate-regex', [
                'sample_text' => 'Vietcombank GD: +50,000 VND. ND: DH123456 thanh toan',
                'bank_name' => 'Vietcombank',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'regex',
                'amount_group',
                'direction_group',
                'order_code_group',
                'transfer_content_group',
                'bank_name',
                'explanation',
                'provider_used',
                'model_used',
            ])
            ->assertJson([
                'bank_name' => 'Vietcombank',
                'provider_used' => 'vps-163',
            ]);
    }

    public function test_parse_notification_endpoint_returns_data(): void
    {
        $admin = AdminUser::factory()->create();

        $mockResponseContent = json_encode([
            'amount' => 50000,
            'direction' => 'in',
            'transfer_content' => 'DH123456 thanh toan',
            'order_code' => 'DH123456',
            'bank_name' => 'Vietcombank',
            'confidence' => 0.95,
        ]);

        Http::fake([
            'http://163.61.110.132:3001/v1/*' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => $mockResponseContent,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/dashboard/ai/parse', [
                'sample_text' => 'Vietcombank GD: +50,000 VND. ND: DH123456 thanh toan',
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'amount',
                'direction',
                'transfer_content',
                'order_code',
                'bank_name',
                'confidence',
                'provider_used',
                'model_used',
            ])
            ->assertJson([
                'amount' => 50000,
                'direction' => 'in',
                'order_code' => 'DH123456',
                'provider_used' => 'vps-163',
            ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_login_with_valid_credentials(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'admin' => ['id', 'name', 'email', 'role'],
                'token',
            ])
            ->assertJson([
                'admin' => [
                    'email' => 'admin@test.com',
                ],
            ]);
    }

    public function test_cannot_login_with_wrong_password(): void
    {
        AdminUser::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'invalid_credentials',
            ]);
    }

    public function test_cannot_login_with_inactive_account(): void
    {
        AdminUser::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123'),
            'status' => 'inactive',
        ]);

        $response = $this->postJson('/api/admin/auth/login', [
            'email' => 'admin@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'error' => 'account_inactive',
            ]);
    }

    public function test_can_register_new_admin(): void
    {
        $response = $this->postJson('/api/admin/auth/register', [
            'name' => 'New Admin',
            'email' => 'newadmin@test.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'admin' => ['id', 'name', 'email', 'role'],
            ]);

        $this->assertDatabaseHas('admin_users', [
            'email' => 'newadmin@test.com',
            'role' => 'admin',
        ]);
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/admin/auth/logout');

        $response->assertStatus(401);
    }

    public function test_can_get_current_user_info(): void
    {
        $admin = AdminUser::factory()->create([
            'email' => 'admin@test.com',
            'name' => 'Test Admin',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/auth/me');

        $response->assertOk()
            ->assertJsonStructure([
                'admin' => ['id', 'name', 'email', 'role'],
            ]);
    }
}
<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'device_id' => 'dev_' . strtolower(Str::ulid()->toBase32()),
            'device_name' => fake()->word() . ' Phone',
            'secret_hash' => bcrypt(fake()->password()),
            'secret_encrypted' => encrypt(fake()->password()),
            'status' => 'active',
            'app_version' => '1.0.' . fake()->randomNumber(2),
            'android_version' => '14',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'last_seen_at' => now()->subHours(2),
        ]);
    }
}
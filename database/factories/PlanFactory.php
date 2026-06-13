<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Basic', 'Pro', 'Business']),
            'price_monthly' => fake()->randomElement([0, 99000, 199000, 299000]),
            'max_devices' => fake()->randomElement([1, 3, 5, 10]),
            'max_webhooks' => fake()->randomElement([1, 3, 5, 10]),
            'log_retention_days' => fake()->randomElement([7, 14, 30]),
            'fair_use_notifications' => fake()->optional()->randomElement([500, 1000, 5000]),
            'is_active' => true,
        ];
    }

    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Basic',
            'price_monthly' => 99000,
            'max_devices' => 1,
            'max_webhooks' => 1,
            'log_retention_days' => 7,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Pro',
            'price_monthly' => 199000,
            'max_devices' => 5,
            'max_webhooks' => 5,
            'log_retention_days' => 14,
            'fair_use_notifications' => 2000,
        ]);
    }
}
<?php

namespace Database\Factories;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

final class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }
}

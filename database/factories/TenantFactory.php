<?php

namespace Database\Factories;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'status' => TenantStatus::Active,
            'settings' => null,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => ['status' => TenantStatus::Blocked]);
    }
}

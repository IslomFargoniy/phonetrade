<?php

namespace Database\Factories;

use App\Enums\Tracking;
use App\Models\Category;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word().' kategoriyasi',
            'tracking' => Tracking::Serial,
            'is_active' => true,
        ];
    }
}

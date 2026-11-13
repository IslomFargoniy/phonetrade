<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => fn (array $a) => Category::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'name' => fake()->unique()->words(3, true),
            'default_warranty_months' => 12,
            'default_max_discount_pct' => '10.00',
            'is_active' => true,
        ];
    }
}

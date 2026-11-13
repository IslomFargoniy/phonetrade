<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => fn (array $a) => Product::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'barcode' => fake()->unique()->ean13(),
            'attributes' => null,
            'sale_price' => '60000.00',
            'sale_currency' => Currency::UZS,
            'max_discount_pct' => '0.00',
            'warranty_months' => 0,
        ];
    }
}

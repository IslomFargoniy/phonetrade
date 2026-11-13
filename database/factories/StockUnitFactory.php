<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\StockStatus;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockUnit>
 */
class StockUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $a) => Branch::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'product_id' => fn (array $a) => Product::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'imei1' => fake()->unique()->numerify('35###########0'),
            'imei2' => null,
            'barcode' => 'TD-'.fake()->unique()->numerify('######'),
            'attributes' => null,
            'cost' => '320.00',
            'cost_currency' => Currency::USD,
            'cost_rate' => '12750.00',
            'sale_price' => '4850000.00',
            'sale_currency' => Currency::UZS,
            'max_discount_pct' => '15.00',
            'warranty_months' => 12,
            'status' => StockStatus::InStock,
        ];
    }
}

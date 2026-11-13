<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Models\Branch;
use App\Models\ProductVariant;
use App\Models\StockBatch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBatch>
 */
class StockBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $a) => Branch::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'variant_id' => fn (array $a) => ProductVariant::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'qty_in' => 10,
            'qty_left' => 10,
            'unit_cost' => '20000.00',
            'cost_currency' => Currency::UZS,
        ];
    }
}

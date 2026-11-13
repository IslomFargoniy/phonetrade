<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturn>
 */
class SaleReturnFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'sale_id' => fn (array $a) => Sale::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'refund_amount' => '100000.00',
            'profit_reversed' => '0.00',
        ];
    }
}

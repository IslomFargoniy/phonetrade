<?php

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => fn (array $a) => Branch::factory()->create(['tenant_id' => $a['tenant_id']])->id,
            'number' => fake()->unique()->numberBetween(1, 1000000),
            'type' => SaleType::Full,
            'currency' => Currency::UZS,
            'subtotal' => '100000.00',
            'total' => '100000.00',
            'status' => SaleStatus::Completed,
        ];
    }
}
